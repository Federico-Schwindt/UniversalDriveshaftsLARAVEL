<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Local SEO store: redirects, the 404 log and per-URL title/description/robots overrides
 * (tables seo_redirects, seo_404s, seo_pages; managed in /admin/seo).
 * Lookups read a cached map, flushed whenever the admin or an import changes a row.
 */
class Seo
{
    private const MAP_REDIRECTS = 'seo_redirects_map';
    private const MAP_PAGES = 'seo_pages_map';

    /** "/Product/BW4/?x=1" → "product/bw4": how sources and override paths are stored and matched. */
    public static function norm(string $path): string
    {
        $path = trim($path);
        $path = preg_match('#^https?://#i', $path) ? (string) parse_url($path, PHP_URL_PATH) : preg_replace('/[?#].*$/s', '', $path);
        return mb_strtolower(trim(rawurldecode($path), "/ \t"));
    }

    /** ['target' => ?string, 'code' => int, 'id' => int] for a path, or null. */
    public static function redirectFor(string $path): ?array
    {
        $map = Cache::rememberForever(self::MAP_REDIRECTS, function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('seo_redirects')) return [];
            $out = [];
            foreach (DB::table('seo_redirects')->get(['id', 'source', 'target', 'code']) as $r) $out[$r->source] = [$r->id, $r->target, (int) $r->code];
            return $out;
        });
        $hit = $map[self::norm($path)] ?? null;
        return $hit ? ['id' => $hit[0], 'target' => $hit[1], 'code' => $hit[2]] : null;
    }

    /** Admin override for a URL: ['title' => ?, 'description' => ?, 'robots' => ?] or null. */
    public static function page(string $path): ?array
    {
        $map = Cache::rememberForever(self::MAP_PAGES, function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('seo_pages')) return [];
            $out = [];
            foreach (DB::table('seo_pages')->get(['path', 'title', 'description', 'robots']) as $r) $out[$r->path] = array_filter(['title' => $r->title, 'description' => $r->description, 'robots' => $r->robots], fn ($v) => trim((string) $v) !== '');
            return $out;
        });
        return $map[self::norm($path)] ?? null;
    }

    public static function flush(): void
    {
        Cache::forget(self::MAP_REDIRECTS);
        Cache::forget(self::MAP_PAGES);
    }

    public static function countHit(int $id): void
    {
        try { DB::table('seo_redirects')->where('id', $id)->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]); } catch (\Throwable $e) {}
    }

    /** Remember a URL that answered 404 so the admin can point it somewhere (assets and bots' probes are skipped). */
    public static function log404(string $path, ?string $referer): void
    {
        $norm = self::norm($path);
        if ($norm === '' || mb_strlen($norm) > 400) return;
        if (preg_match('#\.(js|css|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|txt|xml|json|php\d?|aspx?|env|ini|bak|sql|zip|gz)$#i', $norm)) return;
        if (preg_match('#^(wp-admin|wp-includes|wp-json|xmlrpc|cgi-bin|\.well-known|vendor|media/|api/)#', $norm) || str_contains($norm, '/.')) return;
        try {
            $now = now();
            DB::table('seo_404s')->upsert(
                [['path' => $norm, 'hits' => 1, 'referer' => $referer ? mb_substr($referer, 0, 500) : null, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
                ['path'],
                ['hits' => DB::raw('hits + 1'), 'last_seen_at' => $now, 'updated_at' => $now, 'referer' => DB::raw('COALESCE(VALUES(referer), referer)')]
            );
        } catch (\Throwable $e) {}
    }
}
