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

    /**
     * /llms.txt — a plain summary of the store for AI search engines (ChatGPT, Perplexity, Claude, Gemini) that read it
     * before crawling (the llmstxt.org convention). Facts only, with live counts and the pages worth citing. Google does not
     * need it (AI Overviews use the normal index); the others are helped by it. Served on every host, like the sitemap.
     */
    public function llms(\App\Services\CatalogService $catalog)
    {
        $base = rtrim(config('app.url'), '/');
        $txt = \Illuminate\Support\Facades\Cache::remember('seo:llms', 21600, function () use ($catalog, $base) {
            $groups = [];
            foreach ($catalog->allCategorySlugs() as $slug => $group) {
                $t = $catalog->term($slug);
                if (!$t) continue;
                $name = html_entity_decode((string) ($t->name ?? $t['name'] ?? $slug));
                $n = (int) ($t->count ?? $t['count'] ?? 0);
                $groups[$group][] = "- [{$name}]({$base}/shop/{$group}/{$slug})" . ($n ? ": {$n} parts" : '');
            }
            $veh = app(\App\Services\VehiclePages::class);
            $makes = count($veh->makeMap()); $models = count($veh->allModels());
            $shafts = \Illuminate\Support\Facades\DB::table('ds_parts')->where('is_active', 1)->count();
            $free = number_format((float) config('uds.free_shipping_min', 1000));
            $out = "# Universal Driveshafts\n\n"
                . "> Driveshaft parts and complete driveshafts: u-joints, yokes, center support bearings, flanges, CV parts and custom-cut tubing, plus complete driveshafts by vehicle and custom driveshafts built and balanced in our Florida shop. Online store shipping across the U.S.\n\n"
                . "## The business\n"
                . "- Universal Driveshafts LLC (UDS), 11611 Prospect Drive STE 4, Odessa, FL 33556 (Tampa Bay area), United States\n"
                . "- Phone (813) 524-6491 · e-mail inquiry@universaldriveshafts.com · counter Monday to Friday 8 am to 5 pm, Saturday 9 am to 2 pm (Eastern)\n"
                . "- Shop services: driveshaft repair and rebuild, balancing, custom one- and two-piece driveshafts, u-joint and center bearing replacement: {$base}/services\n"
                . "- Industrial driveshafts (pumps, compressors, conveyors, generators, test stands): {$base}/industrial-driveshafts\n"
                . (\App\Support\Settings::get('reviews.badge_enabled', true) ? '- Rated ' . number_format((float) \App\Support\Settings::get('reviews.google_rating', 4.7), 1) . ' out of 5 on Google from ' . number_format((int) \App\Support\Settings::get('reviews.google_count', 200)) . "+ reviews: " . \App\Support\Settings::get('reviews.google_profile_url', \App\Support\ReviewRequests::PROFILE_URL) . "\n" : '')
                . "\n"
                . "## Complete driveshafts by vehicle\n"
                . "- {$shafts} complete driveshafts for {$models} models of {$makes} makes, with year-by-year fitment, position (front, rear, intermediate) and center bearing numbers: {$base}/driveshafts\n"
                . "- Vehicle finder (year, make, model, drive, transmission): {$base}/catalog/driveshafts\n"
                . "- Custom driveshaft built to your measurements, priced live: {$base}/online-builder\n\n";
            foreach (config('catalog.groups') as $g => $cfg) if (!empty($groups[$g])) $out .= '## ' . $cfg['label'] . "\n" . implode("\n", $groups[$g]) . "\n\n";
            $out .= "## Sizing facts\n"
                . "- U-joint series by bearing cap diameter × width across the caps: 1310 = 1.062\" × 3.219\", 1330 = 1.062\" × 3.625\", 1350 = 1.188\" × 3.625\", 1410 = 1.188\" × 4.188\", 1480 = 1.375\" × 4.188\". Series pages: {$base}/series\n"
                . "- Every part page lists its measurements, price and live stock; part numbers like 1680-60 are also written 1680/60.\n\n"
                . "## Buying\n"
                . "- In-stock parts ordered before 2 pm Eastern on a weekday ship the same day from Florida; custom driveshafts are built to order.\n"
                . "- Free U.S. shipping on parts orders over \${$free} (tubing and complete driveshafts excluded). Shipping policy: {$base}/shipping-policy\n"
                . "- Returns: 14 days with an RMA number. Return policy: {$base}/return-policy · start a return: {$base}/rma\n"
                . "- Business accounts (shops, fleets, resellers) get tier discounts: {$base}/wholesale\n"
                . "- Contact: {$base}/contact\n";
            return $out;
        });
        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
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

    /** Absolute URLs of every sitemap entry, or only those modified since $since (for IndexNow). */
    public function urls(?\Carbon\Carbon $since = null): array
    {
        $base = rtrim(config('app.url'), '/');
        $out = [];
        foreach (self::PARTS as $part) foreach ($this->rows($part) as $r) {
            if ($since && (empty($r['lastmod']) || substr((string) $r['lastmod'], 0, 10) < $since->toDateString())) continue;
            $out[] = str_starts_with($r['loc'], 'http') ? $r['loc'] : $base . $r['loc'];
        }
        return array_values(array_unique($out));
    }

    private function latest(array $rows): ?string
    {
        $max = null;
        foreach ($rows as $r) if (!empty($r['lastmod']) && (!$max || $r['lastmod'] > $max)) $max = $r['lastmod'];
        return $max ? substr($max, 0, 10) : null;
    }
}
