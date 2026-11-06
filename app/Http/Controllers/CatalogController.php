<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatalogController extends Controller
{
    use Concerns\ScalarInput;

    public function __construct(private CatalogService $catalog, private CartService $cart)
    {
    }

    public function index(Request $r)
    {
        $q = trim($this->qs($r, 'q'));
        if ($q !== '') return redirect('/search?q=' . rawurlencode(mb_substr($q, 0, 80)), 301);
        return view('shop.catalog', ['groups' => $this->catalog->groups()]);
    }

    public function group(string $group)
    {
        $g = $this->catalog->group($group);
        abort_unless($g, 404);
        // single-category group -> jump straight to the table
        if (count($g['subcategories']) === 1) {
            return redirect("/shop/{$group}/{$g['subcategories'][0]['slug']}", 301); // permanent: /shop/custom-cut-tubing
        }
        return view('shop.group', ['group' => $g]);
    }

    public function category(Request $r, string $group, string $cat)
    {
        $g = config("catalog.groups.$group");
        abort_unless($g, 404);
        if ($group === 'complete-driveshafts' && $cat === 'by-vehicle') return app(DriveshaftFinderController::class)->all($r);
        $term = $this->catalog->term($cat);
        abort_unless($term, 404);
        // a category lives under exactly one group: hidden / unlisted → 404, wrong group → 301 to its real URL
        $real = $this->catalog->groupOf($term->slug);
        abort_unless($real, 404);
        if ($real['slug'] !== $group || $term->slug !== $cat) {
            $qs = $r->getQueryString();
            return redirect('/shop/' . $real['slug'] . '/' . $term->slug . ($qs ? '?' . $qs : ''), 301);
        }

        // only known option values filter (and reach the cache key); anything else is dropped and the URL cleaned
        $schema = $this->catalog->categorySchema($cat);
        $filters = []; $drop = [];
        foreach ($schema['filters'] as $f) {
            $v = $r->query($f['param']);
            if ($v === null || $v === '') continue;
            if (is_string($v) && isset($f['options'][$v])) $filters[$f['key']] = $v; else $drop[] = $f['param'];
        }
        // series hubs link here with ?series={slug} even for categories whose table has no series filter
        if (!isset($filters['pa_series']) && ($sv = $r->query('series')) !== null && $sv !== '') {
            if (is_string($sv) && isset($this->catalog->categorySeries($cat)[$sv])) $filters['pa_series'] = $sv; else $drop[] = 'series';
        }
        $sortIn = $r->query('sort');
        $sorts = array_merge(['price_asc', 'price_desc', 'part_asc', 'part_desc', 'name'],
            ...array_map(fn ($c) => ["attr:{$c['key']}:asc", "attr:{$c['key']}:desc"], $schema['columns']));
        $sort = is_string($sortIn) && in_array($sortIn, $sorts, true) ? $sortIn : 'price_asc';
        if ($sortIn !== null && $sortIn !== $sort) $drop[] = 'sort';
        $qIn = $r->query('q');
        if ($qIn !== null && !is_string($qIn)) $drop[] = 'q';
        $q = mb_substr(trim(is_string($qIn) ? $qIn : ''), 0, 80);
        if ($drop && !$r->boolean('_ajax')) {
            $keep = array_diff_key($r->query(), array_flip(array_unique($drop)));
            return redirect('/shop/' . $group . '/' . $cat . ($keep ? '?' . http_build_query($keep) : ''), 301);
        }

        $data = $this->catalog->products($cat, $filters, max(1, (int) $this->qs($r, 'page', '1')), 60, $sort, $q, $schema['attrKeys']);
        foreach ($data['items'] as &$it) {
            $it['price'] = $this->price($it['price']);
            $it['regular'] = $this->price($it['regular']);
        }
        unset($it);

        $grp = $this->catalog->group($group);
        $viewData = [
            'group' => ['slug' => $group, 'label' => $g['label']],
            'cat' => ['slug' => $term->slug, 'name' => html_entity_decode($term->name)],
            'data' => $data,
            'schema' => $schema,
            'filters' => $filters,
            'sort' => $sort,
            'q' => $q,
            'siblings' => $grp['subcategories'] ?? [],
            'seoCopy' => config("seo-copy.categories.{$term->slug}", []),
            'seoOwn' => \Illuminate\Support\Facades\DB::connection('wp')->table('wp_termmeta')->where('term_id', $term->term_id)->whereIn('meta_key', ['uds_seo_title', 'uds_seo_desc'])->pluck('meta_value', 'meta_key')->map(fn ($v) => trim((string) $v))->filter()->all(),
            'catSeries' => (function () use ($cat) {   // hub-worthy series only, ordered by overall popularity (1310, 1350, 1330…)
                $in = $this->catalog->categorySeries($cat); $out = [];
                foreach ($this->catalog->seriesList() as $slug => $s) if (isset($in[$slug])) $out[$slug] = $in[$slug];
                return $out;
            })(),
        ];
        if ($r->boolean('_ajax')) return view('shop.partials.table', $viewData);
        return view('shop.category', $viewData);
    }

    public function detail(string $sku)
    {
        $p = $this->catalog->productBySku($sku);
        if (!$p) { // complete driveshafts from the vehicle finder live on their own page (U-prefixed SKU)
            $bare = \App\Services\DriveshaftFinder::pn($sku);
            if (\Illuminate\Support\Facades\DB::table('ds_parts')->where('part_number', $bare)->exists()) {
                return redirect('/catalog/driveshafts/part/' . rawurlencode(\App\Services\DriveshaftFinder::sku($bare)), 301);
            }
            // a SKU renamed on the live store keeps its old links working (Settings catalog.sku_redirects: old => new)
            $moved = (array) \App\Support\Settings::get('catalog.sku_redirects', []);
            foreach ($moved as $old => $new) if (strcasecmp((string) $old, $sku) === 0 && $new) return redirect('/parts/' . rawurlencode((string) $new), 301);
            // cross-reference: a competitor/OEM number (or a SKU typed without dashes) that maps to exactly one part -> its page
            $hits = array_values(array_filter($this->catalog->xrefMatches($sku, 10), fn ($h) => $h['exact'] && $h['sku'] !== ''));
            if (count($hits) === 1) return redirect('/parts/' . rawurlencode($hits[0]['sku']), 301);
            if (count($hits) > 1) return redirect('/search?q=' . rawurlencode($sku), 302);
            abort(404);
        }
        $p['price'] = $this->price($p['price']);
        $p['regular'] = $this->price($p['regular']);
        $cat = $this->catalog->primaryCategory($p['id']);
        $catSlug = $cat['slug'] ?? null;
        $grp = ($catSlug ? $this->catalog->groupOf($catSlug) : null) ?? $this->catalog->groupOfProduct($p['id']);
        if ($grp && $catSlug && !$this->catalog->groupOf($catSlug)) $cat = null;   // category without a public URL

        $seo = $this->catalog->productSeo($p, $catSlug);
        // SEO title / meta description typed in the admin win over the generated ones
        $own = \Illuminate\Support\Facades\DB::connection('wp')->table('wp_postmeta')->where('post_id', $p['id'])->whereIn('meta_key', ['_uds_seo_title', '_uds_seo_desc'])->pluck('meta_value', 'meta_key');
        if (trim((string) ($own['_uds_seo_title'] ?? '')) !== '') $seo['title'] = trim($own['_uds_seo_title']);
        if (trim((string) ($own['_uds_seo_desc'] ?? '')) !== '') $seo['desc'] = trim($own['_uds_seo_desc']);
        $xref = $this->catalog->interchange($p['id'], $p['sku']);
        $related = $this->catalog->related($p['id'], (string) $p['series'], $catSlug, 8);
        foreach ($related as &$it) { $it['price'] = $this->price($it['price']); $it['regular'] = $this->price($it['regular']); }
        unset($it);
        $alts = [];
        if (!empty($p['has_alt'])) {
            $alts = $this->catalog->alternates($p['id']);
            foreach ($alts as &$a) { $a['price'] = $this->price($a['price']); $a['regular'] = $this->price($a['regular']); }
            unset($a);
        }
        // alternative parts, rendered with the same table (and columns) as the product's category page
        $altSchema = $cat ? $this->catalog->categorySchema($cat['slug']) : ['columns' => [], 'filters' => [], 'attrKeys' => []];
        $sameFit = $this->catalog->alternativeRows($p['id'], $altSchema['attrKeys']);
        foreach ($sameFit as &$sf) { $sf['price'] = $this->price((float) $sf['price']); $sf['regular'] = $this->price((float) $sf['regular']); }
        unset($sf);
        // a yoke or flange is bought with the u-joint that goes in it: offer the cheapest in-stock joints of the same series
        $joints = [];
        $yokeCats = ['weld-yoke', 'slip-yoke', 'end-yokes', 'transmission-yokes', 'flange-yokes', 'pto-end-yokes', 'companion-flanges', 'tri-quad-prongs', 'h-yokes', 'yoke-shafts', 'stub-midship-shaft'];
        if ($p['series'] && in_array($catSlug, $yokeCats, true)) {
            $series = trim(explode(',', (string) $p['series'])[0]);
            $rows = $this->catalog->products('u-joints', ['pa_series' => \Illuminate\Support\Str::slug($series)], 1, 12, 'price_asc')['items'] ?? [];
            foreach ($rows as $r) {
                if (count($joints) >= 3) break;
                if ($r['stock_status'] !== 'instock' || $r['price'] <= 0 || $r['sku'] === '') continue;
                $r['price'] = $this->price($r['price']); $r['regular'] = $this->price($r['regular']);
                $joints[] = $r;
            }
        }
        return view('shop.product', [
            'p' => $p, 'group' => $grp, 'cat' => $cat, 'seo' => $seo, 'xref' => $xref, 'related' => $related, 'alts' => $alts, 'sameFit' => $sameFit, 'altSchema' => $altSchema,
            'joints' => $joints,
            'faq' => $this->catalog->productFaq($p, $seo, $xref, $catSlug),
            'descriptionHtml' => $this->catalog->descriptionHtml($p['id']),
            'seriesHub' => $p['series'] ? collect($this->catalog->seriesList())->first(fn ($s) => strcasecmp($s['name'], $p['series']) === 0) : null,
        ]);
    }

    /** Resolve a WooCommerce product slug (post_name) to its /parts/{sku} page. */
    public function productBySlug(string $slug)
    {
        $id = \Illuminate\Support\Facades\DB::connection('wp')->table('wp_posts')
            ->where('post_type', 'product')->where('post_status', 'publish')
            ->where('post_name', $slug)->value('ID');
        if ($id) {
            $sku = \Illuminate\Support\Facades\DB::connection('wp')->table('wp_postmeta')
                ->where('post_id', $id)->where('meta_key', '_sku')->max('meta_value');
            if ($sku) return redirect('/parts/' . urlencode($sku), 301);
        }
        abort(404);
    }

    /** Alternate SKU suggestions for the + hover popover. */
    public function alternates(int $id)
    {
        $alts = $this->catalog->alternates($id);
        foreach ($alts as &$a) { $a['price'] = $this->price($a['price']); $a['regular'] = $this->price($a['regular']); }
        unset($a);
        $current = null;
        if ($alts && ($cur = $this->catalog->productById($id))) {
            $current = ['id' => $cur['id'], 'sku' => $cur['sku'], 'name' => $cur['name'], 'brand' => $cur['brand'],
                        'price' => $this->price($cur['price']), 'regular' => $this->price($cur['regular'])];
        }
        return response()->json(['current' => $current, 'alternates' => $alts]);
    }

    /** JSON for the quick-view modal. */
    public function quickView(int $id)
    {
        $p = $this->catalog->productById($id);
        if (!$p) return response()->json(['found' => false], 404);
        $p['price'] = $this->price($p['price']);
        $p['regular'] = $this->price($p['regular']);
        return response()->json(['found' => true, 'product' => $p]);
    }

    /** Add a catalog product to the cart (AJAX). */
    public function add(Request $r)
    {
        $id = (int) $r->input('id');
        $qty = min(999, max(1, (int) $r->input('qty', 1)));
        $p = $this->catalog->productById($id);
        if (!$p) return response()->json(['success' => false, 'error' => __('Product not found')], 404);
        // a $0.00 catalog price is a data gap ("call for price"), never a free part
        if ((float) $p['price'] <= 0) return response()->json(['success' => false, 'error' => __('This part is priced on request. Call (813) 524-6491 for a quote.')], 422);
        $this->cart->add([
            'type' => 'product', 'wp_product_id' => $p['id'], 'name' => $p['name'],
            'sku' => $p['sku'], 'image' => $p['image'], 'price' => $this->price($p['price']), 'qty' => $qty,
        ]);
        return response()->json(['success' => true, 'count' => $this->cart->count(), 'name' => $p['name']]);
    }

    /* helpers */

    private function price(float $base): float
    {
        $u = Auth::user();
        if ($u && ($u->wholesale_status ?? '') === 'approved' && ($u->wholesale_discount ?? 0) > 0) {
            return round($base * (1 - ((float) $u->wholesale_discount) / 100), 2);
        }
        return round($base, 2);
    }

    private function firstCatSlug(int $id): string
    {
        $slug = \Illuminate\Support\Facades\DB::connection('wp')->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $id)->where('tt.taxonomy', 'product_cat')
            ->orderByDesc('tt.count')->value('t.slug');
        return (string) $slug;
    }
}
