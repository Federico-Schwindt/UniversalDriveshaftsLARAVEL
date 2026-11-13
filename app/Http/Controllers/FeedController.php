<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Google Merchant Center product feeds (RSS 2.0 + g: namespace) so the catalog can appear in
 * Shopping / free product listings once the site is live. Prices and stock come from the same
 * services the pages use; rebuilt every 6 hours.
 *   /feeds/google-merchant.xml              — WooCommerce catalog (components, tubing, premade)
 *   /feeds/google-merchant-driveshafts.xml  — vehicle-specific complete driveshafts (finder parts)
 */
class FeedController extends Controller
{
    private const GCAT = 'Vehicles & Parts > Vehicle Parts & Accessories > Motor Vehicle Parts > Motor Vehicle Power & Drive Train Parts';

    public function products(CatalogService $catalog)
    {
        $xml = Cache::remember('feed:google:products', 21600, function () use ($catalog) {
            $base = rtrim(config('app.url'), '/');
            $items = [];
            foreach ($catalog->allCategorySlugs() as $slug => $group) {
                $label = html_entity_decode(optional($catalog->term($slug))->name ?? $slug);
                $gLabel = config("catalog.groups.$group.label", $group);
                for ($page = 1; $page <= 20; $page++) {
                    $data = $catalog->products($slug, [], $page, 200, 'part_asc', '', ['pa_series', 'pa_brand']);
                    if (!$data['items']) break;
                    $ids = array_column($data['items'], 'id');
                    $texts = DB::connection('wp')->table('wp_posts')->whereIn('ID', $ids)->pluck('post_excerpt', 'ID');
                    $bodies = DB::connection('wp')->table('wp_posts')->whereIn('ID', $ids)->pluck('post_content', 'ID');
                    foreach ($data['items'] as $p) {
                        if (isset($items[$p['sku']]) || $p['sku'] === '' || (float) $p['price'] <= 0) continue;
                        $desc = trim(strip_tags((string) ($texts[$p['id']] ?? ''))) ?: trim(strip_tags((string) ($bodies[$p['id']] ?? '')));
                        $desc = mb_substr(preg_replace('/\s+/', ' ', $desc ?: $p['name']), 0, 4900);
                        $live = CatalogService::live($p['id']);
                        $img = !empty($live['image']) ? media_url($live['image'], 1200) : ($p['image'] ?: '');
                        if ($img === '') continue;   // Merchant Center rejects items without an image
                        $items[$p['sku']] = [
                            'id' => self::offerId((int) $p['id']), 'title' => mb_substr($p['name'], 0, 150), 'description' => $desc,
                            'link' => $base . part_path($p['sku']),
                            'image_link' => $img ? ($img[0] === '/' ? $base . $img : $img) : '',
                            'availability' => ($p['stock_status'] ?? '') === 'instock' ? 'in_stock' : (($p['stock_status'] ?? '') === 'onbackorder' ? 'backorder' : 'out_of_stock'),
                            'price' => number_format((float) $p['price'], 2, '.', '') . ' USD',
                            'brand' => ($p['attrs']['pa_brand'] ?? ($p['brand'] ?? '')) ?: 'UDS',
                            'mpn' => $p['sku'], 'condition' => 'new', 'identifier_exists' => 'no',
                            'product_type' => $gLabel . ' > ' . $label, 'google_product_category' => self::GCAT,
                            'shipping_weight' => !empty($p['weight']) ? $p['weight'] . ' lb' : '',
                            'custom_label_0' => $p['attrs']['pa_series'] ?? '',
                        ];
                    }
                    if ($data['page'] >= $data['pages']) break;
                }
            }
            return self::rss('Universal Driveshafts — driveshaft parts', $base, array_values($items));
        });
        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function driveshafts(DriveshaftFinder $finder)
    {
        $xml = Cache::remember('feed:google:driveshafts', 21600, function () use ($finder) {
            $base = rtrim(config('app.url'), '/');
            $items = [];
            // a shaft that is also a catalog product is already in the parts feed (under its Merchant Center id): never twice
            $catalogSkus = DB::connection('wp')->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
                ->where('m.meta_key', '_sku')->where('p.post_type', 'product')->where('p.post_status', 'publish')->pluck('m.meta_value')->map(fn ($v) => strtoupper(trim((string) $v)))->flip();
            foreach (DB::table('ds_parts')->where('is_active', 1)->orderBy('part_number')->get() as $p) {
                $price = DriveshaftFinder::customerPrice($p);
                if ($price === null) continue;
                $fits = \App\Services\DriveshaftFinder::fitments()->where('part_number', $p->part_number)->selectRaw('make, model, min(year) y1, max(year) y2')->groupBy('make', 'model')->limit(6)->get();
                $fitText = $fits->map(fn ($f) => ($f->y1 == $f->y2 ? $f->y1 : "$f->y1-$f->y2") . " $f->make $f->model")->implode(', ');
                $sku = DriveshaftFinder::sku($p->part_number);
                if (isset($catalogSkus[strtoupper($sku)])) continue;
                $title = trim(($p->description ?: 'Complete driveshaft') . ' ' . $sku . ($fitText ? ' — fits ' . mb_substr($fitText, 0, 90) : ''));
                if (!$p->main_image) continue;   // Merchant Center rejects items without an image
                $items[] = [
                    'id' => $sku, 'title' => mb_substr($title, 0, 150),
                    'description' => mb_substr('Complete driveshaft ' . $sku . ($fitText ? ', fits ' . $fitText : '') . '. Assembled and balanced, ships from Florida. ' . ($p->notes ?: ''), 0, 4900),
                    'link' => $base . '/catalog/driveshafts/part/' . rawurlencode($sku),
                    'image_link' => $p->main_image ? $base . media_url($p->main_image, 1200) : '',
                    'availability' => 'in_stock', 'price' => number_format($price, 2, '.', '') . ' USD',
                    'brand' => 'UDS', 'mpn' => $p->part_number, 'condition' => ($p->condition ?: 'new') === 'new' ? 'new' : 'refurbished',
                    'identifier_exists' => $p->upc ? 'yes' : 'no', 'gtin' => $p->upc ?: '',
                    'product_type' => 'Complete Driveshafts > By vehicle', 'google_product_category' => self::GCAT,
                    'shipping_weight' => $p->weight ? round((float) $p->weight, 1) . ' lb' : '',
                ];
            }
            return self::rss('Universal Driveshafts — complete driveshafts by vehicle', $base, $items);
        });
        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    /**
     * The id Merchant Center already knows each catalog product by: Google Listings & Ads (the old WordPress plugin)
     * sent them as "gla_{product id}" (1,527 products, e.g. online:en:US:gla_9113), and the catalog keeps the same ids.
     * Same id = same product in Merchant Center: approvals, history and Shopping campaigns carry over at launch.
     */
    public static function offerId(int $wpProductId): string
    {
        return 'gla_' . $wpProductId;
    }

    private static function rss(string $title, string $base, array $items): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n"
             . '<title>' . $e($title) . '</title><link>' . $e($base) . '</link><description>Driveshaft components and complete driveshafts from Universal Driveshafts, Florida.</description>' . "\n";
        foreach ($items as $it) {
            $out .= "<item>\n";
            foreach ($it as $k => $v) {
                if ($v === '' || $v === null) continue;
                $out .= "  <g:$k>" . $e($v) . "</g:$k>\n";
            }
            $out .= "</item>\n";
        }
        return $out . "</channel>\n</rss>\n";
    }
}
