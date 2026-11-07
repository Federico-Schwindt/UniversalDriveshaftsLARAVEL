<?php

namespace App\Console\Commands;

use App\Support\LiveOrigin;
use App\Support\WpUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Copies every WordPress upload the store still references (product photos, galleries, thumbnails,
 * category images, inline description images, the About video…) into public/uploads/wp/, so the new
 * site serves them itself and keeps working when the old WordPress site goes away.
 * Only missing files are downloaded; safe to run hourly (after catalog:sync-live brings new pictures).
 */
class LocalizeImages extends Command
{
    protected $signature = 'catalog:localize-images {--dry-run : only count what is missing} {--limit=0 : stop after N downloads}';
    protected $description = 'Mirror the WordPress uploads the catalog references into public/uploads/wp';

    private const MAX_BYTES = 60 * 1024 * 1024;   // the About video is the largest file

    public function handle(): int
    {
        if (\App\Support\LiveOrigin::retired()) {
            $this->warn('The catalog is local since ' . \App\Support\Settings::get('catalog.detached_at') . ' (catalog:detach): every picture is already here. Skipped.');
            return self::SUCCESS;
        }
        if (\App\Support\LiveOrigin::isSelf()) {
            $this->warn('universaldriveshafts.com is this site now: there is no old WordPress store to read from. Skipped.');
            return self::SUCCESS;
        }
        $urls = $this->collect();
        $missing = [];
        foreach ($urls as $u) {
            $rel = WpUploads::rel($u);
            if ($rel !== null && !is_file(WpUploads::file($rel))) $missing[$rel] = $u;
        }
        $this->info(count($urls) . ' referenced uploads, ' . count($missing) . ' not mirrored yet.');
        if ($this->option('dry-run') || !$missing) return self::SUCCESS;
        if ($limit = (int) $this->option('limit')) $missing = array_slice($missing, 0, $limit, true);

        $free = @disk_free_space(public_path());
        if ($free !== false && $free < 1024 * 1024 * 1024) {
            $this->error('Less than 1 GB free on the disk; not downloading.');
            return self::FAILURE;
        }

        $ok = 0; $fail = [];
        $opts = LiveOrigin::httpOptions();
        foreach (array_chunk($missing, 8, true) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $opts) {
                foreach ($chunk as $rel => $url) {
                    $pool->as($rel)->withOptions($opts)->timeout(60)->withHeaders(['User-Agent' => 'UDS media mirror'])->get($this->encode($url));
                }
            });
            foreach ($chunk as $rel => $url) {
                $r = $responses[$rel] ?? null;
                if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) { $fail[$rel] = $r instanceof \Illuminate\Http\Client\Response ? $r->status() : 'error'; continue; }
                $body = $r->body();
                $isVideo = (bool) preg_match('/\.(mp4|webm|mov)$/i', $rel);
                $isSvg = str_ends_with(strtolower($rel), '.svg') && str_contains(substr($body, 0, 2048), '<svg');
                if (strlen($body) < 100 || strlen($body) > self::MAX_BYTES || (!$isVideo && !$isSvg && !@getimagesizefromstring($body) && !$this->isAvif($body))) { $fail[$rel] = 'not an image'; continue; }
                $file = WpUploads::file($rel);
                if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
                $tmp = $file . '.' . getmypid() . '.tmp';
                if (@file_put_contents($tmp, $body) === false || !@rename($tmp, $file)) { @unlink($tmp); $fail[$rel] = 'write failed'; continue; }
                $ok++;
            }
            $this->output->write('.');
            usleep(300000);   // the live host answers 429 to bursts
        }
        $this->newLine();
        $this->info("Downloaded $ok, failed " . count($fail) . '.');
        foreach (array_slice($fail, 0, 25, true) as $rel => $why) $this->line("  $rel: $why");
        return self::SUCCESS;
    }

    /** Every old-uploads URL the store can render (catalog:detach checks they are all mirrored). */
    public function collect(): array
    {
        $urls = [];
        $add = function ($u) use (&$urls) { if (is_string($u) && WpUploads::rel($u) !== null) $urls[trim($u)] = true; };

        // live catalog overlay: main photo, thumbnail, gallery
        foreach (DB::table('catalog_live')->get(['image', 'thumb', 'gallery']) as $r) {
            $add($r->image); $add($r->thumb);
            foreach ((json_decode((string) $r->gallery, true) ?: []) as $g) $add(is_array($g) ? ($g['src'] ?? $g['url'] ?? null) : $g);
        }

        // local catalog copy: attachments of products (featured + gallery) and of categories
        $wp = DB::connection('wp');
        $base = 'https://' . LiveOrigin::HOST . '/wp-content/uploads/';   // where the attachment files came from (catalog.uploads is the local copy now)
        $ids = [];
        foreach ($wp->table('wp_postmeta')->whereIn('meta_key', ['_thumbnail_id', '_product_image_gallery'])->pluck('meta_value') as $v) {
            foreach (explode(',', (string) $v) as $id) if (ctype_digit(trim($id))) $ids[(int) $id] = true;
        }
        foreach ($wp->table('wp_termmeta')->where('meta_key', 'thumbnail_id')->pluck('meta_value') as $id) if (ctype_digit((string) $id)) $ids[(int) $id] = true;
        foreach (array_chunk(array_keys($ids), 1000) as $chunk) {
            foreach ($wp->table('wp_postmeta')->whereIn('post_id', $chunk)->where('meta_key', '_wp_attached_file')->pluck('meta_value') as $f) $add($base . ltrim((string) $f, '/'));
        }
        foreach ($wp->table('wp_termmeta')->where('meta_key', 'uds_image')->pluck('meta_value') as $u) $add($u);

        // pictures embedded in product descriptions
        $wp->table('wp_posts')->where('post_type', 'product')->whereIn('post_status', ['publish', 'draft', 'private'])
            ->where(fn ($q) => $q->where('post_content', 'like', '%wp-content/uploads%')->orWhere('post_excerpt', 'like', '%wp-content/uploads%'))
            ->select(['ID', 'post_content', 'post_excerpt'])->orderBy('ID')
            ->chunk(500, function ($rows) use ($add) {
                foreach ($rows as $r) {
                    preg_match_all('#https?://(?:www\.)?universaldriveshafts\.com/wp-content/uploads/[^\s"\'<>)]+#i', $r->post_content . ' ' . $r->post_excerpt, $m);
                    foreach ($m[0] as $u) { $add($u); $add(preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $u)); }   // the size used + the original
                }
            });

        // URLs written into config and views (category tiles, the About video)
        $cfg = [config('uds'), config('catalog')];
        array_walk_recursive($cfg, fn ($v) => $add($v));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (!str_ends_with($f->getFilename(), '.php')) continue;
            preg_match_all('#https?://(?:www\.)?universaldriveshafts\.com/wp-content/uploads/[^\s"\'<>)]+#i', (string) file_get_contents($f->getPathname()), $m);
            foreach ($m[0] as $u) $add($u);
        }
        return array_keys($urls);
    }

    /** Spaces and other unsafe characters in WordPress file names must be percent-encoded on the wire. */
    private function encode(string $url): string
    {
        $p = parse_url($url);
        $path = implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $p['path'] ?? '')));
        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . $path;
    }

    private function isAvif(string $b): bool
    {
        return substr($b, 4, 8) === 'ftypavif' || substr($b, 4, 8) === 'ftypavis';
    }
}
