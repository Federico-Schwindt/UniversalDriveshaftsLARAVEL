<?php

namespace App\Console\Commands;

use App\Http\Controllers\MediaController;
use App\Services\CatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Make the catalog's WebP pictures ahead of time. A picture nobody had opened yet was resized on the visitor's request,
 * about 2 s each and one PHP worker per picture, so a category page full of them loaded slowly (owner, 2026-11-11:
 * "images not loading, bad speed"). Every published product at the sizes the pages ask for (160 cart and suggestions,
 * 320 lists, 1200 the product page) and every finder shaft (320 lists, 1200 its page). Pictures already made are skipped,
 * so a nightly run only makes the new ones.
 */
class MediaWarm extends Command
{
    protected $signature = 'media:warm {--limit=0 : stop after this many new pictures} {--sleep=0 : milliseconds between new pictures}';
    protected $description = 'Make the catalog WebP pictures ahead of time (skips the ones already made)';

    public function handle(CatalogService $catalog): int
    {
        $jobs = [];
        $ids = DB::table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->pluck('ID')->map(fn ($v) => (int) $v)->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($catalog->thumbSources($chunk) as $src) if ($src) foreach ([160, 320, 1200] as $w) $jobs[] = [$w, $src];
        }
        foreach (DB::table('ds_parts')->where('is_active', 1)->whereNotNull('main_image')->where('main_image', '!=', '')->pluck('main_image') as $src) {
            foreach ([320, 1200] as $w) $jobs[] = [$w, (string) $src];
        }

        $made = $have = $skip = $fail = 0; $limit = (int) $this->option('limit'); $sleep = (int) $this->option('sleep');
        $seen = [];
        foreach ($jobs as [$w, $src]) {
            $url = MediaController::url($src, $w);
            if (!preg_match('#^/media/(\d+)/([A-Za-z0-9_-]+)\.webp#', $url, $m)) { $skip++; continue; }   // not a picture the cache serves
            if (isset($seen[$m[1] . $m[2]])) continue;
            $seen[$m[1] . $m[2]] = true;
            if (is_file(public_path("media/{$m[1]}/{$m[2]}.webp"))) { $have++; continue; }
            [$file] = MediaController::ensure((int) $m[1], $m[2]);
            $file ? $made++ : $fail++;
            if (($made + $fail) % 100 === 0) $this->line(($made + $fail) . " new so far ($fail failed)");
            if ($limit && $made + $fail >= $limit) break;
            if ($sleep) usleep($sleep * 1000);
        }
        $this->info("media:warm — $made made, $have already there, $fail failed, $skip not cached by size");
        return self::SUCCESS;
    }
}
