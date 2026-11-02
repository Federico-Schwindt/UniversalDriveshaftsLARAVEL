<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Catalog-wide search: the header widget (JSON) and the full results page, both grouped by category. */
class SearchController extends Controller
{
    use Concerns\ScalarInput;

    public function __construct(private CatalogService $catalog, private DriveshaftFinder $finder) {}

    /** Header widget: a few hits per category plus complete driveshafts from the vehicle finder. */
    public function api(Request $r)
    {
        $q = $this->q($r);
        if (mb_strlen($q) < 2) return response()->json(['q' => $q, 'groups' => [], 'total' => 0]);
        $u = Auth::user(); $tier = ($u && ($u->wholesale_status ?? '') === 'approved') ? (string) ($u->wholesale_discount ?? 0) : '0';
        $ver = \Illuminate\Support\Facades\Cache::get('catalog_ver', 1);   // a catalog sync busts these too
        return response()->json($this->cached("search_api:$ver:" . app()->getLocale() . ":$tier:" . mb_strtolower($q), $q, fn () => $this->grouped($q)));
    }

    /**
     * Cache a result set for 5 minutes — but only useful ones: misses (0 results) and long queries (> 40 chars,
     * pasted junk / bots) are computed fresh, so random input cannot fill the cache with unbounded keys.
     */
    private function cached(string $key, string $q, \Closure $fn): array
    {
        if (mb_strlen($q) > 40) return $fn();
        $hit = \Illuminate\Support\Facades\Cache::get($key);
        if (is_array($hit)) return $hit;
        $v = $fn();
        if (($v['total'] ?? 0) > 0) \Illuminate\Support\Facades\Cache::put($key, $v, 300);
        return $v;
    }

    private function grouped(string $q): array
    {
        $groups = []; $total = 0;
        foreach ($this->matchingCategories($q, 5) as $c) {
            $data = $this->catalog->products($c['slug'], [], 1, 5, 'part_asc', $q, []);
            if (!$data['items']) continue;
            $total += $data['total'];
            $groups[] = ['key' => $c['slug'], 'label' => $c['name'], 'total' => $data['total'],
                'url' => '/shop/' . $c['group'] . '/' . $c['slug'] . '?q=' . rawurlencode($q),
                'items' => array_map(fn ($p) => ['sku' => $p['sku'], 'name' => $this->title($p['name'], $p['sku']), 'price' => $this->price((float) $p['price']),
                    'image' => $p['image'] ?: null, 'url' => '/parts/' . rawurlencode($p['sku']), 'add' => (float) $p['price'] > 0 ? $p['sku'] : null], $data['items'])];
        }
        $ds = $this->finder->search($q);
        if ($ds) {
            $total += count($ds);
            $groups[] = ['key' => 'driveshafts', 'label' => __('Complete driveshafts'), 'total' => count($ds),
                'url' => '/shop/complete-driveshafts/by-vehicle?q=' . rawurlencode($q),
                'items' => array_map(fn ($p) => ['sku' => $p['sku'], 'name' => trim(($p['description'] ?: __('Complete driveshaft')) . (!empty($p['fits']) ? ' · ' . __('fits :vehicle', ['vehicle' => $p['fits'][0]]) : '')),
                    'price' => $p['price'], 'image' => $p['image'] ?: null, 'url' => $p['url'], 'add' => null], array_slice($ds, 0, 5))];
        }
        // part-number index: an exact normalised SKU goes first ("51310XU" -> 5-1310XU), interchange numbers get their own group
        $x = $this->xref($q, 8);
        if ($x['exact']) {
            $placed = false;
            foreach ($groups as $gi => $g) {
                foreach ($g['items'] as $ii => $it) {
                    if (in_array($it['sku'], $x['exact'], true)) { unset($groups[$gi]['items'][$ii]); array_unshift($groups[$gi]['items'], $it); $groups[$gi]['items'] = array_values($groups[$gi]['items']); if (!$placed) { $top = $groups[$gi]; unset($groups[$gi]); array_unshift($groups, $top); $groups = array_values($groups); } $placed = true; break; }
                }
            }
            if (!$placed) {
                $items = $this->items($this->catalog->rows($x['exactIds']));
                $total += count($items);
                array_unshift($groups, ['key' => 'exact', 'label' => __('Part number'), 'total' => count($items), 'url' => '/search?q=' . rawurlencode($q), 'items' => $items]);
            }
        }
        if ($x['xref']) {
            $items = $this->items($this->catalog->rows(array_keys($x['xref'])), $x['xref']);
            $group = ['key' => 'xref', 'label' => __('Interchange matches'), 'total' => count($items), 'url' => '/search?q=' . rawurlencode($q) . '#cat-xref', 'items' => $items];
            if ($x['xrefExact'] && $total < 4) array_unshift($groups, $group); else $groups[] = $group;
            $total += count($items);
        }
        return ['q' => $q, 'groups' => $groups, 'total' => $total];
    }

    /**
     * Part-number index hits for a query, split for ranking: 'exact' = SKUs equal to the normalised query (and their ids),
     * 'xref' = productId => matched competitor/OEM number, 'xrefExact' = at least one interchange number matched exactly.
     */
    private function xref(string $q, int $limit): array
    {
        $exact = []; $exactIds = []; $xref = []; $xrefExact = false;
        foreach ($this->catalog->xrefMatches($q, $limit) as $m) {
            if ($m['own']) { if ($m['exact'] && $m['sku'] !== '') { $exact[] = $m['sku']; $exactIds[] = $m['id']; } continue; }
            $xref[$m['id']] = $m['num'];
            if ($m['exact']) $xrefExact = true;
        }
        return ['exact' => $exact, 'exactIds' => $exactIds, 'xref' => $xref, 'xrefExact' => $xrefExact];
    }

    /** Widget items from hydrated catalog rows; $xref (id => number) adds the "Replaces …" line. */
    private function items(array $rows, array $xref = []): array
    {
        return array_map(fn ($p) => ['sku' => $p['sku'], 'name' => $this->title($p['name'], $p['sku']), 'price' => $this->price((float) $p['price']),
            'image' => $p['image'] ?: null, 'url' => '/parts/' . rawurlencode($p['sku']), 'add' => (float) $p['price'] > 0 ? $p['sku'] : null, 'xref' => $xref[$p['id']] ?? null], $rows);
    }

    /** Results page: one table per category with that category's own attribute columns. */
    public function page(Request $r)
    {
        $q = $this->q($r);
        // The assembled results (prices already baked in) are cached per pricing tier for 5 minutes, like the
        // header widget API — popular repeat searches (1310, 1350, u-joint) skip the ~140ms of catalog queries.
        $u = Auth::user();
        $tier = ($u && ($u->wholesale_status ?? '') === 'approved') ? (string) ($u->wholesale_discount ?? 0) : '0';
        $ver = \Illuminate\Support\Facades\Cache::get('catalog_ver', 1);   // bumped by catalog:sync-live, so a sync busts these
        $vm = mb_strlen($q) >= 2
            ? $this->cached("search_page:$ver:" . app()->getLocale() . ":$tier:" . mb_strtolower($q), $q, fn () => $this->buildPage($q))
            : ['sections' => [], 'driveshafts' => [], 'total' => 0];
        return view('search', ['q' => $q, 'sections' => $vm['sections'], 'driveshafts' => $vm['driveshafts'], 'total' => $vm['total']]);
    }

    /** Build the full results view-model for a query (cached by page()). */
    private function buildPage(string $q): array
    {
        $sections = []; $ds = []; $total = 0;
        {
            foreach ($this->matchingCategories($q, 8) as $c) {
                $schema = $this->catalog->categorySchema($c['slug']);
                $data = $this->catalog->products($c['slug'], [], 1, 24, 'part_asc', $q, $schema['attrKeys']);
                if (!$data['items']) continue;
                foreach ($data['items'] as &$it) { $it['price'] = $this->price((float) $it['price']); $it['regular'] = $this->price((float) $it['regular']); }
                unset($it);
                $total += $data['total'];
                $sections[] = ['cat' => $c, 'schema' => $schema, 'data' => $data];
            }
            $ds = $this->finder->search($q);
            $total += count($ds);
            // part-number index: exact SKU the LIKE search missed ("5153X"), plus interchange numbers as their own section
            $x = $this->xref($q, 24);
            $schema = ['columns' => [['key' => 'xref_num', 'label' => __('Replaces')], ['key' => 'pa_series', 'label' => __('Series')], ['key' => 'pa_brand', 'label' => __('Brand')], ['key' => 'pa_notes', 'label' => __('Notes')]], 'filters' => [], 'attrKeys' => []];
            $listed = []; foreach ($sections as $s) foreach ($s['data']['items'] as $it) $listed[$it['sku']] = true;
            $section = function (string $slug, string $name, array $ids, array $nums, array $cols) use ($schema) {
                $items = $this->catalog->rows($ids);
                foreach ($items as &$it) { $it['price'] = $this->price((float) $it['price']); $it['regular'] = $this->price((float) $it['regular']); $it['attrs']['xref_num'] = $nums[$it['id']] ?? ''; }
                unset($it);
                return ['cat' => ['slug' => $slug, 'name' => $name, 'group' => null], 'schema' => ['columns' => $cols] + $schema,
                    'data' => ['items' => $items, 'total' => count($items)], 'xref' => array_values(array_unique($nums))];
            };
            if ($x['exact'] && !array_intersect_key($listed, array_flip($x['exact']))) {
                $sec = $section('exact', __('Part number'), $x['exactIds'], [], array_slice($schema['columns'], 1));
                if ($sec['data']['items']) { $total += $sec['data']['total']; array_unshift($sections, $sec); }
            }
            if ($x['xref']) {
                $sec = $section('xref', __('Interchange matches'), array_keys($x['xref']), $x['xref'], $schema['columns']);
                if ($sec['data']['items']) {
                    if ($x['xrefExact'] && $total < 4) array_unshift($sections, $sec); else $sections[] = $sec;
                    $total += $sec['data']['total'];
                }
            }
        }
        return ['sections' => $sections, 'driveshafts' => $ds, 'total' => $total];
    }

    private function q(Request $r): string
    {
        return mb_substr(trim(str_replace(['%', '_'], ' ', $this->qs($r, 'q'))), 0, 80);
    }

    /** Catalog categories holding products whose title or SKU contains the phrase, most hits first. */
    private function matchingCategories(string $q, int $limit): array
    {
        $cats = $this->matchingCategoriesCached($q, $limit);
        // a bare series number ("1310", "1350 series") is a u-joint search first: that group leads instead of whichever
        // category happens to hold the most titles containing the number (Flange Yokes, 70)
        if (preg_match('/^\s*(?:spl\s*)?\d{4}[a-z]{0,3}(?:\s*series)?\s*$/i', $q)) {
            usort($cats, fn ($a, $b) => (int) ($b['slug'] === 'u-joints') <=> (int) ($a['slug'] === 'u-joints'));
        }
        return $cats;
    }

    private function matchingCategoriesCached(string $q, int $limit): array
    {
        if (mb_strlen($q) > 40) return $this->matchingCategoriesUncached($q, $limit);
        $key = 'search_cats:' . md5(mb_strtolower($q)) . ":$limit";
        if (is_array($hit = \Illuminate\Support\Facades\Cache::get($key))) return $hit;
        $out = $this->matchingCategoriesUncached($q, $limit);
        if ($out) \Illuminate\Support\Facades\Cache::put($key, $out, 600); // misses are not cached (bounded keys)
        return $out;
    }

    private function matchingCategoriesUncached(string $q, int $limit): array
    {
        $map = $this->catalog->allCategorySlugs();
        $db = DB::connection('wp');
        $skuSub = $db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) as sv'))->where('meta_key', '_sku')->groupBy('post_id');
        $rows = $db->table('wp_posts as p')->leftJoinSub($skuSub, 'sk', 'sk.post_id', '=', 'p.ID')
            ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'p.ID')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('tt.taxonomy', 'product_cat')
            ->where(fn ($w) => $w->where('p.post_title', 'like', '%' . $q . '%')->orWhere('sk.sv', 'like', '%' . $q . '%'))
            ->groupBy('t.slug', 't.name')->select('t.slug', 't.name', DB::raw('COUNT(DISTINCT p.ID) as n'))
            ->orderByDesc('n')->get();
        $out = [];
        foreach ($rows as $row) {
            if (!isset($map[$row->slug])) continue;
            $out[] = ['slug' => $row->slug, 'name' => html_entity_decode($row->name), 'group' => $map[$row->slug], 'n' => (int) $row->n];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Product titles repeat the SKU up front ("2-2-329 – Flange Yokes – …"); the widget shows the SKU on its own line. */
    private function title(string $name, string $sku): string
    {
        $t = $sku !== '' ? preg_replace('/^\s*' . preg_quote($sku, '/') . '\s*[\x{2013}\x{2014}|:-]*\s*/u', '', $name) : $name;
        return trim((string) $t) !== '' ? trim($t) : $name;
    }

    private function price(float $base): float
    {
        $u = Auth::user();
        if ($u && ($u->wholesale_status ?? '') === 'approved' && ($u->wholesale_discount ?? 0) > 0) {
            return round($base * (1 - ((float) $u->wholesale_discount) / 100), 2);
        }
        return round($base, 2);
    }
}
