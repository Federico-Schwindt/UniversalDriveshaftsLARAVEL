<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * robots.txt and the sitemap index. Both react to the environment: only production is
 * crawlable, every other host answers "Disallow: /" (the layout adds noindex as well).
 *
 * Sitemap layout (each file stays far below the 50,000-URL / 50 MB limits):
 *   /sitemap.xml             index -> the files below
 *   /sitemap-pages.xml       static pages
 *   /sitemap-categories.xml  shop groups + categories
 *   /sitemap-products.xml    /parts/{sku} with <image:image> and <lastmod>
 *   /sitemap-driveshafts.xml /catalog/driveshafts/part/{pn} (finder parts)
 *   /sitemap-extra.xml       pluggable sources in app/Seo/Sources (vehicle hubs, series, guides…)
 */
class SeoController extends Controller
{
    private const TTL = 21600; // 6 h, same as before
    private const MAX = 49000; // hard cap per file (spec: 50,000)

    /** Static, high-value URLs. Order roughly mirrors crawl priority. */
    private const PAGES = [
        ['/', '1.0', 'daily'],
        ['/shop', '0.9', 'daily'],
        ['/catalog/driveshafts', '0.9', 'weekly'],
        ['/online-builder', '0.9', 'weekly'],
        ['/online-builder/two-piece', '0.8', 'weekly'],
        ['/premade-driveshafts', '0.8', 'weekly'],
        ['/shop/complete-driveshafts/by-vehicle', '0.7', 'weekly'],
        ['/driveshaft-designer', '0.7', 'monthly'],
        ['/heavy-duty-driveshafts', '0.7', 'monthly'],
        ['/industrial-driveshafts', '0.7', 'monthly'],
        ['/custom-off-road-driveshafts', '0.7', 'monthly'],
        ['/pto-agriculture', '0.7', 'monthly'],
        ['/services', '0.7', 'monthly'],
        ['/about-us', '0.6', 'monthly'],
        ['/contact', '0.6', 'monthly'],
        ['/wholesale', '0.6', 'monthly'],
        ['/return-policy', '0.3', 'yearly'],
        ['/terms', '0.2', 'yearly'],
        ['/privacy-policy', '0.2', 'yearly'],
        ['/copyright', '0.2', 'yearly'],
    ];

    private const PARTS = ['pages', 'categories', 'products', 'driveshafts', 'extra'];

    public function robots()
    {
        $base = rtrim(config('app.url'), '/');
        if (!app()->environment('production')) {
            // every non-production host stays out of the index entirely
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $disallow = [
            // private / transactional (cart, wishlist, login, rma… are NOT listed: they carry a noindex meta,
            // which Google can only honour if it is allowed to fetch the page)
            '/checkout', '/account', '/admin', '/order-received', '/forgot-password', '/reset-password/',
            // JSON, fragments and tooling endpoints (HTML pages under /driveshafts/ and /catalog/driveshafts stay crawlable)
            '/search', '/api/', '/shop/quick-view/', '/shop/alternates/', '/dsb/', '/geo', '/vehicles/models', '/health', '/up',
            '/driveshaft-designer/svg/', '/index.php', '/home-classic',
            // parameter variants of catalog pages
            '/*?sort=', '/*&sort=', '/*?*q=', '/*?*_ajax=', '/*&_ajax=',
        ];
        $body = "User-agent: *\nAllow: /\n";
        foreach ($disallow as $d) $body .= "Disallow: {$d}\n";
        // images served through the local media cache stay crawlable for image search
        $body .= "Allow: /media/\n\nSitemap: {$base}/sitemap.xml\n";
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Sitemap index pointing at the per-section files. */
    public function sitemap()
    {
        $base = rtrim(config('app.url'), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::PARTS as $part) {
            $rows = $this->rows($part);
            if (!$rows) continue; // an empty section (no finder parts, no extra sources yet) is simply left out
            $last = $this->latest($rows);
            $xml .= "  <sitemap><loc>{$base}/sitemap-{$part}.xml</loc>" . ($last ? "<lastmod>{$last}</lastmod>" : '') . "</sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** One section of the index: /sitemap-{pages|categories|products|driveshafts|extra}.xml */
    public function sitemapPart(string $part)
    {
        abort_unless(in_array($part, self::PARTS, true), 404);
        $base = rtrim(config('app.url'), '/');
        $rows = $this->rows($part);
        $hasImages = (bool) array_filter($rows, fn ($r) => !empty($r['image']));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
             . ($hasImages ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . ">\n";
        foreach ($rows as $u) {
            $loc = preg_match('#^https?://#', $u['loc']) ? $u['loc'] : $base . $u['loc'];
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) $xml .= '<lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1) . '</lastmod>';
            if (!empty($u['freq'])) $xml .= '<changefreq>' . $u['freq'] . '</changefreq>';
            if (!empty($u['pri'])) $xml .= '<priority>' . $u['pri'] . '</priority>';
            if (!empty($u['image'])) {
                $img = preg_match('#^https?://#', $u['image']) ? $u['image'] : $base . $u['image'];
                $xml .= '<image:image><image:loc>' . htmlspecialchars($img, ENT_XML1) . '</image:loc></image:image>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>';
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /* ---------------- sources (each cached 6 h under its own key) ---------------- */

    private function rows(string $part): array
    {
        return Cache::remember('uds_sitemap_' . $part, self::TTL, function () use ($part) {
            try {
                $rows = match ($part) {
                    'pages'       => $this->pages(),
                    'categories'  => $this->categories(),
                    'products'    => $this->products(),
                    'driveshafts' => $this->driveshafts(),
                    'extra'       => $this->extra(),
                };
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("sitemap-{$part} failed: " . $e->getMessage());
                $rows = [];
            }
            // de-duplicate on loc, keep order, cap the file
            $seen = []; $out = [];
            foreach ($rows as $r) {
                if (empty($r['loc']) || isset($seen[$r['loc']])) continue;
                $seen[$r['loc']] = true; $out[] = $r;
                if (count($out) >= self::MAX) break;
            }
            return $out;
        });
    }

    private function pages(): array
    {
        $out = [];
        foreach (self::PAGES as [$loc, $pri, $freq]) {
            $file = $this->viewFileFor($loc);
            $out[] = ['loc' => $loc, 'pri' => $pri, 'freq' => $freq, 'lastmod' => $file && is_file($file) ? date('Y-m-d', filemtime($file)) : null];
        }
        return $out;
    }

    /** Static pages are plain Blade views: their file mtime is an honest lastmod. */
    private function viewFileFor(string $loc): ?string
    {
        $map = [
            '/' => 'home', '/shop' => 'shop/catalog', '/catalog/driveshafts' => 'finder', '/online-builder' => 'designer',
            '/online-builder/two-piece' => 'designer', '/premade-driveshafts' => 'premade', '/shop/complete-driveshafts/by-vehicle' => 'finder-all',
            '/driveshaft-designer' => 'designer-tool', '/heavy-duty-driveshafts' => 'heavy-duty', '/industrial-driveshafts' => 'industrial',
            '/custom-off-road-driveshafts' => 'offroad', '/pto-agriculture' => 'pto', '/services' => 'services', '/about-us' => 'about',
            '/contact' => 'contact', '/wholesale' => 'wholesale', '/return-policy' => 'return-policy', '/terms' => 'terms',
            '/privacy-policy' => 'privacy-policy', '/copyright' => 'copyright',
        ];
        return isset($map[$loc]) ? resource_path('views/' . $map[$loc] . '.blade.php') : null;
    }

    private function categories(): array
    {
        $out = [];
        $catalog = app(CatalogService::class);
        foreach ($catalog->groups() as $g) {
            $full = $catalog->group($g['slug']) ?: [];
            // a one-category group only redirects to its category page: list the category, not the redirect
            if (count($full['subcategories'] ?? []) !== 1) $out[] = ['loc' => '/shop/' . $g['slug'], 'pri' => '0.8', 'freq' => 'weekly'];
            foreach (($full['subcategories'] ?? []) as $c) {
                if (empty($c['slug']) || $c['slug'] === 'by-vehicle') continue; // by-vehicle is listed as a static page
                $out[] = ['loc' => '/shop/' . $g['slug'] . '/' . $c['slug'], 'pri' => '0.7', 'freq' => 'weekly'];
            }
        }
        return $out;
    }

    /** Every published WooCommerce product with a SKU, one row per SKU, newest modification wins, with its thumbnail. */
    private function products(): array
    {
        $db = DB::connection('wp');
        $rows = $db->table('wp_posts as p')
            ->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
            ->leftJoin('wp_postmeta as t', fn ($j) => $j->on('t.post_id', '=', 'p.ID')->where('t.meta_key', '_thumbnail_id'))
            ->leftJoin('wp_postmeta as f', fn ($j) => $j->on('f.post_id', '=', 't.meta_value')->where('f.meta_key', '_wp_attached_file'))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', '!=', '')
            ->orderBy('p.post_modified', 'desc')
            ->limit(self::MAX * 3)
            ->get(['s.meta_value as sku', 'p.post_modified', 't.meta_value as thumb', 'f.meta_value as file']);
        $uploads = rtrim(config('catalog.uploads'), '/') . '/';
        $out = [];
        foreach ($rows as $r) {
            $sku = trim((string) $r->sku);
            if ($sku === '' || isset($out[$sku])) continue;
            $image = null;
            $file = (string) ($r->file ?? '');
            if ($file !== '' && (int) $r->thumb !== 11868 && !str_contains($file, 'placeholder') && !str_contains($file, '1312312312132')) {
                $image = media_url($uploads . ltrim($file, '/'), 640); // local WebP cache, same URL the product page uses
            }
            $out[$sku] = ['loc' => '/parts/' . rawurlencode($sku), 'pri' => '0.6', 'freq' => 'weekly',
                          'lastmod' => $r->post_modified ? date('Y-m-d', strtotime($r->post_modified)) : null, 'image' => $image];
        }
        return array_values($out);
    }

    /** Vehicle-finder complete driveshafts (ds_parts), with the main photo when there is one. */
    private function driveshafts(): array
    {
        $out = [];
        $rows = DB::table('ds_parts')->where('is_active', 1)->whereNotNull('part_number')
            ->orderBy('part_number')->get(['part_number', 'updated_at', 'main_image']);
        foreach ($rows as $r) {
            $pn = trim((string) $r->part_number);
            if ($pn === '') continue;
            $image = $r->main_image ? media_url($r->main_image, 640) : null;
            $out[] = ['loc' => '/catalog/driveshafts/part/' . rawurlencode(\App\Services\DriveshaftFinder::sku($pn)), 'pri' => '0.6', 'freq' => 'monthly',
                      'lastmod' => $r->updated_at ? date('Y-m-d', strtotime($r->updated_at)) : null, 'image' => $image ?: null];
        }
        return $out;
    }

    /** Pluggable sources: every class in app/Seo/Sources with urls(): array (vehicle pages, series hubs, guides…). */
    private function extra(): array
    {
        $out = [];
        foreach (glob(app_path('Seo/Sources/*.php')) ?: [] as $file) {
            $class = 'App\\Seo\\Sources\\' . basename($file, '.php');
            try {
                if (class_exists($class) && method_exists($class, 'urls')) {
                    foreach ((new $class)->urls() as $row) {
                        if (is_string($row)) $row = ['loc' => $row];
                        if (!empty($row['loc'])) $out[] = $row + ['pri' => '0.7', 'freq' => 'weekly'];
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("sitemap source $class failed: " . $e->getMessage());
            }
        }
        return $out;
    }

    private function latest(array $rows): ?string
    {
        $max = null;
        foreach ($rows as $r) if (!empty($r['lastmod']) && (!$max || $r['lastmod'] > $max)) $max = $r['lastmod'];
        return $max ? substr($max, 0, 10) : null;
    }
}
