<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Read-only catalog queries for the driveshaft builder against the local WooCommerce catalog (connection 'wp').
 * Yokes (flange / transmission) and u-joint hardware (strap and u-bolt kits).
 */
class DsbCatalog
{
    public const FLANGE_CAT_ID = 307;    // product_cat: flange yokes
    public const TRANS_CAT_ID  = 1802;   // product_cat: transmission (slip) yokes
    public const STRAP_CAT_ID  = 4839;   // product_cat: strap kits
    public const UBOLT_CAT_ID  = 4838;   // product_cat: u-bolt kits

    public const FLANGE_FILTERS = ['pilot-dia', 'cj-to-flat', 'bolt-holes', 'bolt-circle-dia'];
    public const TRANS_FILTERS  = ['splines-count-dia', 'center-u-joint-to-end', 'hub-dia'];
    public const HARDWARE_ATTRS = ['series', 'fits-cap-dia', 'thread-dia'];

    /** Builder end type -> the yoke category the customer picks a new part from. */
    public const YOKE_TYPES = ['flange' => self::FLANGE_CAT_ID, 'trans' => self::TRANS_CAT_ID];
    /** Hardware kind -> category. */
    public const HARDWARE_TYPES = ['strap' => self::STRAP_CAT_ID, 'ubolt' => self::UBOLT_CAT_ID];

    private const UPLOADS = '/uploads/wp/';   // local mirror of the old WordPress uploads (App\Support\WpUploads)

    /**
     * Yoke catalog search. $spline (transmission yokes only) keeps the yokes whose spline count attribute
     * ("26/27 X 1.125") names that count; $ids limits the result to given product ids (restoring a saved build).
     */
    public function yokes(string $type, string $series, array $filters, int $page, int $perPage, string $spline = '', array $ids = []): array
    {
        $catId = self::YOKE_TYPES[$type] ?? self::FLANGE_CAT_ID;
        $whitelist = $type === 'flange' ? self::FLANGE_FILTERS : self::TRANS_FILTERS;
        $perPage = max(1, min(48, $perPage));
        $page = max(1, $page);

        $q = $this->productBase($catId);
        if ($ids) $q->whereIn('p.ID', array_map('intval', $ids));
        if ($series !== '') $this->whereSeries($q, $series);
        if ($type === 'trans' && preg_match('/^\d{1,2}$/', $spline)) $this->whereSpline($q, $spline);

        foreach ($filters as $slug => $value) {
            if (!in_array($slug, $whitelist, true) || !is_string($value) || $value === '') continue;
            $q->whereExists(function ($sub) use ($slug, $value) {
                $sub->select(DB::raw(1))
                    ->from('wp_term_relationships as trf')
                    ->join('wp_term_taxonomy as ttf', 'ttf.term_taxonomy_id', '=', 'trf.term_taxonomy_id')
                    ->join('wp_terms as tf', 'tf.term_id', '=', 'ttf.term_id')
                    ->whereColumn('trf.object_id', 'p.ID')
                    ->where('ttf.taxonomy', 'pa_' . $slug)
                    ->where('tf.name', $value);
            });
        }

        $total = (clone $q)->count();
        $rows = $q->orderBy('p.post_title')->forPage($page, $perPage)->get(['p.ID', 'p.post_title']);

        return [
            'products' => $rows->map(fn ($r) => $this->card((int) $r->ID, $r->post_title, $whitelist))->values()->all(),
            'page'     => $page,
            'pages'    => (int) ceil($total / $perPage),
            'total'    => $total,
            'filters'  => $this->availableFilters($catId, $series, $whitelist),
            'spline_counts' => $type === 'trans' ? $this->splineCounts($series) : [],
        ];
    }

    /**
     * Strap or u-bolt kits for a series. Kits are tagged with the series they fit ("1350", "1480, 1550");
     * when nothing in the category is tagged for the series every kit is listed so the customer can still pick.
     */
    public function hardware(string $kind, string $series): array
    {
        $catId = self::HARDWARE_TYPES[$kind] ?? null;
        if (!$catId) return ['products' => [], 'total' => 0, 'matched_series' => false];
        $q = $this->productBase($catId);
        $matched = true;
        if ($series !== '') {
            $this->whereSeries($q, $series);
            if (!(clone $q)->exists()) { $q = $this->productBase($catId); $matched = false; }
        }
        $rows = $q->orderBy('p.post_title')->limit(24)->get(['p.ID', 'p.post_title']);
        $products = $rows->map(fn ($r) => $this->card((int) $r->ID, $r->post_title, self::HARDWARE_ATTRS))
            ->filter(fn ($c) => $c['price'] > 0)->values()->all();
        return ['products' => $products, 'total' => count($products), 'matched_series' => $matched];
    }

    /** Product price straight from WP meta — server-side source of truth. */
    public function price(int $productId): float
    {
        return (float) $this->meta($productId, '_price');
    }

    /** A published product in one of the given categories: the only ids the builder prices as a yoke or a kit. */
    public function inCategory(int $productId, array $catIds): bool
    {
        if ($productId <= 0) return false;
        return DB::connection('wp')->table('wp_posts as p')
            ->where('p.ID', $productId)->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->whereExists(function ($sub) use ($catIds) {
                $sub->select(DB::raw(1))
                    ->from('wp_term_relationships as tr')
                    ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                    ->whereColumn('tr.object_id', 'p.ID')
                    ->where('tt.taxonomy', 'product_cat')
                    ->whereIn('tt.term_id', $catIds);
            })->exists();
    }

    public function productName(int $productId): ?string
    {
        $r = DB::connection('wp')->table('wp_posts')->where('ID', $productId)->where('post_type', 'product')->first(['post_title']);
        return $r?->post_title;
    }

    public function productSku(int $productId): string
    {
        return (string) $this->meta($productId, '_sku');
    }

    /** Name, SKU, price and photo of one product, for the order line and the review page. */
    public function part(int $productId): ?array
    {
        $name = $this->productName($productId);
        if ($name === null) return null;
        return ['id' => $productId, 'name' => $name, 'sku' => $this->productSku($productId), 'price' => $this->price($productId), 'image' => $this->thumb($productId)];
    }

    // ---- internals -------------------------------------------------------

    private function card(int $id, string $title, array $attrs): array
    {
        return [
            'id'         => $id,
            'name'       => $title,
            'price'      => $this->price($id),
            'image'      => $this->thumb($id),
            'sku'        => $this->productSku($id),
            'attributes' => $this->attributeValues($id, $attrs),
        ];
    }

    private function whereSeries($q, string $series): void
    {
        $slug = \Str::slug($series);
        $name = strtoupper(trim($series));
        $q->whereExists(function ($sub) use ($slug, $name) {
            $sub->select(DB::raw(1))
                ->from('wp_term_relationships as tr2')
                ->join('wp_term_taxonomy as tt2', 'tt2.term_taxonomy_id', '=', 'tr2.term_taxonomy_id')
                ->join('wp_terms as t2', 't2.term_id', '=', 'tt2.term_id')
                ->whereColumn('tr2.object_id', 'p.ID')
                ->where('tt2.taxonomy', 'pa_series')
                // exact slug, or a comma list that names the series ("1480, 1550")
                ->where(fn ($w) => $w->where('t2.slug', $slug)
                    ->orWhere('t2.name', 'like', $name . ',%')->orWhere('t2.name', 'like', '%, ' . $name)->orWhere('t2.name', 'like', '%, ' . $name . ',%'));
        });
    }

    /** Spline count attribute values look like "27 X 1.125", "26/27 X 1.125" or "32X34": match the count as a whole number. */
    private function whereSpline($q, string $spline): void
    {
        $q->whereExists(function ($sub) use ($spline) {
            $sub->select(DB::raw(1))
                ->from('wp_term_relationships as trs')
                ->join('wp_term_taxonomy as tts', 'tts.term_taxonomy_id', '=', 'trs.term_taxonomy_id')
                ->join('wp_terms as ts', 'ts.term_id', '=', 'tts.term_id')
                ->whereColumn('trs.object_id', 'p.ID')
                ->where('tts.taxonomy', 'pa_splines-count-dia')
                ->whereRaw('ts.name REGEXP ?', ['^([0-9]+/)?' . (int) $spline . '([ /Xx]|$)']);
        });
    }

    /** The spline counts our transmission yokes exist in for a series (one number per count, ascending). */
    public function splineCounts(string $series): array
    {
        return \Cache::remember('dsb_splines_' . \Str::slug($series), 600, function () use ($series) {
            $q = $this->productBase(self::TRANS_CAT_ID);
            if ($series !== '') $this->whereSeries($q, $series);
            $ids = $q->pluck('p.ID');
            if ($ids->isEmpty()) return [];
            $names = DB::connection('wp')->table('wp_term_relationships as tr')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->whereIn('tr.object_id', $ids)->where('tt.taxonomy', 'pa_splines-count-dia')
                ->distinct()->pluck('t.name');
            $counts = [];
            foreach ($names as $n) {
                if (preg_match('/^(\d{1,2})(?:\/(\d{1,2}))?/', trim($n), $m)) {
                    $counts[(int) $m[1]] = true;
                    if (!empty($m[2])) $counts[(int) $m[2]] = true;
                }
            }
            $out = array_keys($counts);
            sort($out);
            return $out;
        });
    }

    private function productBase(int $catId)
    {
        return DB::connection('wp')->table('wp_posts as p')
            ->where('p.post_type', 'product')
            ->where('p.post_status', 'publish')
            ->whereExists(function ($sub) use ($catId) {
                $sub->select(DB::raw(1))
                    ->from('wp_term_relationships as tr')
                    ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                    ->whereColumn('tr.object_id', 'p.ID')
                    ->where('tt.taxonomy', 'product_cat')
                    ->where('tt.term_id', $catId);
            });
    }

    private function meta(int $postId, string $key)
    {
        return DB::connection('wp')->table('wp_postmeta')
            ->where('post_id', $postId)->where('meta_key', $key)->value('meta_value');
    }

    /**
     * The product photo the storefront shows (live-store overlay first, then the catalog's own attachment),
     * served through the site's WebP media cache: the raw live-store URL does not load from this server.
     * Empty when the part has no photo, so the builder can draw a placeholder.
     */
    private function thumb(int $productId): string
    {
        $live = \App\Services\CatalogService::live($productId);
        $src = ($live['thumb'] ?? null) ?: ($live['image'] ?? null);
        if (!$src && ($thumbId = $this->meta($productId, '_thumbnail_id')) && ($file = $this->meta((int) $thumbId, '_wp_attached_file'))) $src = self::UPLOADS . $file;
        return $src ? media_url($src, 320) : '';
    }

    private function attributeValues(int $productId, array $slugs): array
    {
        $rows = DB::connection('wp')->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $productId)
            ->whereIn('tt.taxonomy', array_map(fn ($s) => 'pa_' . $s, $slugs))
            ->get(['tt.taxonomy', 't.name']);
        $out = [];
        foreach ($rows as $r) {
            $slug = substr($r->taxonomy, 3);
            $out[$slug] = isset($out[$slug]) ? $out[$slug] . ', ' . $r->name : $r->name;
        }
        return $out;
    }

    private function availableFilters(int $catId, string $series, array $slugs): array
    {
        return \Cache::remember("dsb_yoke_filters_{$catId}_" . \Str::slug($series), 300, function () use ($catId, $series, $slugs) {
            $q = $this->productBase($catId);
            if ($series !== '') $this->whereSeries($q, $series);
            $ids = $q->pluck('p.ID');
            if ($ids->isEmpty()) return (object) [];

            $rows = DB::connection('wp')->table('wp_term_relationships as tr')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->whereIn('tr.object_id', $ids)
                ->whereIn('tt.taxonomy', array_map(fn ($s) => 'pa_' . $s, $slugs))
                ->distinct()->get(['tt.taxonomy', 't.name']);

            $out = [];
            foreach ($rows as $r) $out[substr($r->taxonomy, 3)][] = $r->name;
            foreach ($out as &$vals) natsort($vals) && $vals = array_values($vals);
            return $out ?: (object) [];
        });
    }
}
