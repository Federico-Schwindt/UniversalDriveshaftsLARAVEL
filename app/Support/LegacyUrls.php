<?php

namespace App\Support;

use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Where the URLs of the old WordPress / WooCommerce store live now (owner, 2026-11-12: "urls viejas: arreglala no
 * quiero perder nada de seo"). Search Console, the old sitemap and the old database know 8,734 of them; each one
 * answers with a single 301 to its page here. The legacy routes in web.php ask this class for categories and product
 * slugs, and SeoRedirects asks it for whatever would otherwise be a 404 (after the redirects kept in admin → SEO).
 */
class LegacyUrls
{
    /** Query parameters that follow the visitor through a redirect: ad clicks and campaigns. WooCommerce's own
     *  (add-to-cart, per_page, filter_*…) mean nothing here and would only make duplicate URLs. */
    private const CARRY = '/^(utm_[a-z0-9_]+|gclid|gclsrc|gbraid|wbraid|gad_[a-z0-9_]+|dclid|fbclid|msclkid|ttclid|srsltid|mc_[ce]id|_gl)$/';

    /** WooCommerce account pages → ours (links in the old store's order and password e-mails keep working). */
    private const ACCOUNT = ['orders' => '/account/orders', 'view-order' => '/account/orders', 'edit-address' => '/account/addresses',
        'edit-account' => '/account/settings', 'payment-methods' => '/account/settings', 'lost-password' => '/forgot-password'];

    /** The old store's "product table" pages Google knows whose address was not their category's (each listed one). */
    private const TABLES = ['u-joint-2' => 'u-joints', 'splined-stub-shafts' => 'stub-midship-shaft'];

    /**
     * [target, code] for an old URL with no page and no redirect row of its own; a null target is 410 Gone.
     * Null when nothing is known about it: a real 404.
     */
    public static function resolve(string $path): ?array
    {
        $p = Seo::norm($path);
        // WordPress archive pages, feeds and embeds (/product-category/tubing/page/3, /product/x/feed) are their page
        $rest = preg_replace('#(?:^|/)(?:page/\d+|feed(?:/[a-z0-9]+)?|embed|amp)$#', '', $p, 1, $cut);
        $hit = $cut ? (($to = self::follow($rest)) ? [$to, 301] : null) : self::known($p);
        // never back to the address that was just not found
        return $hit && $hit[0] !== null && Seo::norm($hit[0]) === $p ? null : $hit;
    }

    /** The redirect target with the visitor's campaign parameters, and only those, carried over. */
    public static function carry(Request $request, string $target): string
    {
        $keep = array_filter($request->query(), fn ($v, $k) => is_string($v) && $v !== '' && preg_match(self::CARRY, (string) $k), ARRAY_FILTER_USE_BOTH);
        parse_str((string) parse_url($target, PHP_URL_QUERY), $have);
        if (!($keep = array_diff_key($keep, $have))) return $target;
        [$url, $hash] = array_pad(explode('#', $target, 2), 2, null);
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($keep, '', '&', PHP_QUERY_RFC3986) . ($hash === null ? '' : '#' . $hash);
    }

    /** The shop page of an old /product-category/ URL (nested ones resolve by their last segment), or null. */
    public static function category(string $path): ?string
    {
        // /product-category/tubing/page/3 and /product-category/tubing/feed are the category
        $path = preg_replace('#(?:^|/)(?:page/\d+|feed(?:/[a-z0-9]+)?)$#', '', trim($path, '/'));
        $slug = basename($path);
        if ($slug === '') return '/shop';
        // a top-level group slug (driveshaft-components, small-parts…) goes straight to its group page
        if (array_key_exists($slug, config('catalog.groups', []))) return '/shop/' . $slug;
        $catalog = app(CatalogService::class);
        $gs = null;
        try { $gs = $catalog->groupOf($slug)['slug'] ?? null; } catch (\Throwable $e) {}
        if (!$gs) {
            foreach (config('catalog.groups', []) as $key => $def) {
                if (in_array($slug, $def['categories'] ?? [], true)) { $gs = $key; break; }
            }
        }
        if ($gs && $gs !== $slug) return "/shop/{$gs}/{$slug}";
        // a redirect kept from the old site (admin → SEO) knows a better home than the generic catalog
        if (($hit = Seo::redirectFor('/product-category/' . $path)) && $hit['target']) return $hit['target'];
        // a real WooCommerce category that has no shop page of its own goes to the catalog; anything else is unknown
        try { if ($catalog->term($slug)) return '/shop'; } catch (\Throwable $e) {}
        return null;
    }

    /**
     * /parts/{sku} of the published product behind a WooCommerce slug: the one it has now or, with $former, one it
     * had before (WordPress keeps every earlier slug of a post in _wp_old_slug, which is how it redirected them).
     */
    public static function part(string $slug, bool $former = false): ?string
    {
        // WordPress stores a slug's non-ASCII characters percent-encoded in lower case (1-125″ is 1-125%e2%80%b3)
        $slug = preg_replace_callback('/[^\x00-\x7f]+/', fn ($m) => strtolower(rawurlencode($m[0])), mb_strtolower(trim($slug, '/')));
        if ($slug === '' || strlen($slug) > 200) return null;
        $wp = DB::connection('wp');
        $id = $former
            ? $wp->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')->where('m.meta_key', '_wp_old_slug')->where('m.meta_value', $slug)
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')->max('p.ID')
            : $wp->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->where('post_name', $slug)->value('ID');
        return $id ? self::partOf((int) $id) : null;
    }

    private static function partOf(int $id): ?string
    {
        $sku = DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_sku')->max('meta_value');
        return $sku ? self::page($sku) : null;
    }

    private static function page(string $sku): string
    {
        return part_path($sku);
    }

    /**
     * A product slug nobody recorded: old slugs were "<part number>-<title>", so the longest leading run that is a
     * part number of ours names the product — when the rest of the slug also reads like that product (one word of
     * its name at least). Without that second proof a short number such as 1480 would send a carrier bearing's page
     * to a u-joint.
     */
    private static function partByNumber(string $slug): ?string
    {
        $t = explode('-', trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(rawurldecode($slug))), '-'));
        $index = self::numbers();
        for ($k = min(7, count($t) - 1); $k >= 1; $k--) {
            $pre = implode('-', array_slice($t, 0, $k));
            if (strlen(str_replace('-', '', $pre)) < 3) break;
            // the old store listed UDS-brand parts without the U our part numbers end in
            foreach ([$pre, $pre . 'u'] as $n) {
                if (!($hit = $index[$n] ?? null)) continue;
                if (array_intersect(array_slice($t, $k), explode('-', $hit[1]))) return self::page($hit[0]);
            }
        }
        return null;
    }

    /** "5-793x" → ['5-793X', 'universal-joint-1330x3r'] for every published product: part number, and its name's words. */
    private static function numbers(): array
    {
        return Cache::remember('legacy_part_numbers:' . Cache::get('catalog_ver', 1), 3600, function () {
            $out = [];
            $rows = DB::connection('wp')->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')->where('m.meta_key', '_sku')->where('m.meta_value', '!=', '')
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')->get(['m.meta_value as sku', 'p.post_title as title']);
            foreach ($rows as $r) {
                $n = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($r->sku)), '-');
                // a name's words without the ones every part shares
                $words = array_diff(explode('-', trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($r->title)), '-')), explode('-', $n), ['', 'series', 'uds', 'for', 'the', 'and', 'with', 'x', 'to', 'of', 'inch']);
                if ($n !== '') $out[$n] = [$r->sku, implode('-', $words)];
            }
            return $out;
        });
    }

    /** [target, code] by the shape of the old URL, or null. */
    private static function known(string $p): ?array
    {
        [$head, $tail] = array_pad(explode('/', $p, 2), 2, '');
        $to = match ($head) {
            'product-category' => self::category($tail),
            'product' => $tail === '' ? '/shop' : (self::part($tail, true) ?? self::partByNumber($tail)),
            // the old shop with WooCommerce's sorting and filters in the path
            'driveshaft-shop', 'product-tag' => '/shop',
            // the old "product table" pages listed one category each
            'product-table' => self::category(self::TABLES[$tail] ?? $tail) ?? '/shop',
            // a category asked for without its group or under another one (/shop/tubing, /shop/yokes/cv-flange-yokes)
            'shop' => $tail === '' ? null : self::category(basename($tail)),
            'my-account' => self::ACCOUNT[explode('/', $tail)[0]] ?? '/account',
            'checkout' => preg_match('#^order-(received|pay)(/|$)#', $tail) ? '/account/orders' : null,
            'blog', 'category', 'tag', 'author' => '/guides',
            // customers' quote requests the old theme published as pages: never content
            'form-sumbission-post' => false,
            default => null,
        };
        return $to === null ? null : [$to ?: null, $to ? 301 : 410];
    }

    /** Where a GET of this path ends, in one step: a legacy shape, a redirect route, a page of ours or a kept redirect. */
    private static function follow(string $rest): ?string
    {
        if ($rest === '') return '/';
        [$head, $tail] = array_pad(explode('/', $rest, 2), 2, '');
        if ($head === 'product-category') return self::category($tail);
        if ($head === 'product') {
            if ($to = self::part($tail)) return $to;
        } else {
            try {
                // asked on this site's own host: a made-up one is refused once the trusted hosts are set
                $route = Route::getRoutes()->match(Request::create(url('/' . $rest)));
                // Route::redirect('/driveshaft-shop', '/shop') carries its target; any other route is a page of ours
                return (string) ($route->defaults['destination'] ?? '/' . $rest);
            } catch (\Throwable $e) {}
        }
        if (($hit = Seo::redirectFor($rest)) && $hit['target']) return $hit['target'];
        return self::known($rest)[0] ?? null;
    }
}
