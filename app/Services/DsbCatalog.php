<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Read-only catalog queries against the copied WordPress/WooCommerce DB (connection 'wp').
 * Ports DSB_Yoke_Ajax + dsb_premade_products from ubs-driveshaft-builder v2.9.1.
 */
class DsbCatalog
{
    public const FLANGE_CAT_ID = 307;    // product_cat: flange yokes
    public const TRANS_CAT_ID  = 1802;   // product_cat: transmission yokes
    public const PREMADE_CAT_ID = 5377;  // product_cat: pre-made driveshafts

    public const FLANGE_FILTERS = ['pilot-dia', 'cj-to-flat', 'bolt-holes', 'bolt-circle-dia'];
    public const TRANS_FILTERS  = ['splines-count-dia', 'center-u-joint-to-end', 'hub-dia'];

    private const UPLOADS = '/uploads/wp/';   // local mirror of the old WordPress uploads (App\Support\WpUploads)

    /** Yoke catalog search — mirrors AJAX dsb_yoke_catalog. */
    public function yokes(string $type, string $series, array $filters, int $page, int $perPage): array
    {
        $catId = $type === 'flange' ? self::FLANGE_CAT_ID : self::TRANS_CAT_ID;
        $whitelist = $type === 'flange' ? self::FLANGE_FILTERS : self::TRANS_FILTERS;
        $perPage = max(1, min(48, $perPage));
        $page = max(1, $page);

        $q = $this->productBase($catId);

        if ($series !== '') {
            $slug = \Str::slug($series);
            $q->whereExists(function ($sub) use ($slug) {
                $sub->select(DB::raw(1))
                    ->from('wp_term_relationships as tr2')
                    ->join('wp_term_taxonomy as tt2', 'tt2.term_taxonomy_id', '=', 'tr2.term_taxonomy_id')
                    ->join('wp_terms as t2', 't2.term_id', '=', 'tt2.term_id')
                    ->whereColumn('tr2.object_id', 'p.ID')
                    ->where('tt2.taxonomy', 'pa_series')
                    ->where('t2.slug', $slug);
            });
        }

        foreach ($filters as $slug => $value) {
            if (!in_array($slug, $whitelist, true) || $value === '') continue;
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
        $rows = $q->orderBy('p.post_title')
            ->forPage($page, $perPage)
            ->get(['p.ID', 'p.post_title']);

        $products = $rows->map(function ($r) use ($whitelist) {
            return [
                'id'         => (int) $r->ID,
                'name'       => $r->post_title,
                'price'      => $this->price($r->ID),
                'image'      => $this->thumb($r->ID),
                'sku'        => $this->meta($r->ID, '_sku'),
                'attributes' => $this->attributeValues($r->ID, $whitelist),
            ];
        })->values()->all();

        return [
            'products' => $products,
            'page'     => $page,
            'pages'    => (int) ceil($total / $perPage),
            'total'    => $total,
            'filters'  => $this->availableFilters($catId, $series, $whitelist),
        ];
    }

    /** Premade driveshaft search — mirrors AJAX dsb_premade_products (keyword search on series label). */
    public function premades(string $seriesLabel, int $page, string $lengthFilter): array
    {
        $perPage = 6;
        $page = max(1, $page);

        $q = $this->productBase(self::PREMADE_CAT_ID);

        if ($seriesLabel !== '') {
            // WP 's' search: title OR excerpt OR content
            $q->where(function ($w) use ($seriesLabel) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $seriesLabel) . '%';
                $w->where('p.post_title', 'like', $like)
                  ->orWhere('p.post_content', 'like', $like)
                  ->orWhere('p.post_excerpt', 'like', $like);
            });
        }
        if ($lengthFilter !== '') {
            $q->whereExists(function ($sub) use ($lengthFilter) {
                $sub->select(DB::raw(1))
                    ->from('wp_term_relationships as trl')
                    ->join('wp_term_taxonomy as ttl', 'ttl.term_taxonomy_id', '=', 'trl.term_taxonomy_id')
                    ->join('wp_terms as tl', 'tl.term_id', '=', 'ttl.term_id')
                    ->whereColumn('trl.object_id', 'p.ID')
                    ->where('ttl.taxonomy', 'pa_overall-len')
                    ->where('tl.name', $lengthFilter);
            });
        }

        $total = (clone $q)->count();
        $rows = $q->orderBy('p.post_title')->forPage($page, $perPage)->get(['p.ID', 'p.post_title', 'p.post_content', 'p.post_excerpt']);

        $products = $rows->map(function ($r) {
            $sku = (string) $this->meta($r->ID, '_sku');
            $hasAb = (bool) preg_match('/^(5A|4A)/i', $sku)
                || stripos($r->post_title, 'Duramax') !== false
                || stripos($r->post_content ?? '', 'Duramax') !== false
                || stripos($r->post_excerpt ?? '', 'Duramax') !== false;
            return [
                'id'        => (int) $r->ID,
                'name'      => $r->post_title,
                'price'     => $this->price($r->ID),
                'image'     => $this->thumb($r->ID),
                'sku'       => $sku,
                'has_ab'    => $hasAb,
            ];
        })->values()->all();

        return [
            'products'       => $products,
            'total'          => $total,
            'pages'          => (int) ceil($total / $perPage),
            'per_page'       => $perPage,
            'length_options' => $this->lengthOptions(),
        ];
    }

    /** Product price straight from WP meta — server-side source of truth. */
    public function price(int $productId): float
    {
        return (float) $this->meta($productId, '_price');
    }

    /** A published product in the pre-made driveshaft category — the only IDs the builder may price as a premade. */
    public function isPremade(int $productId): bool
    {
        return $productId > 0 && $this->productBase(self::PREMADE_CAT_ID)->where('p.ID', $productId)->exists();
    }

    public function productName(int $productId): ?string
    {
        $r = DB::connection('wp')->table('wp_posts')->where('ID', $productId)->where('post_type', 'product')->first(['post_title']);
        return $r?->post_title;
    }

    // ---- internals -------------------------------------------------------

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
        return \Cache::remember("dsb_yoke_filters_{$catId}_{$series}", 300, function () use ($catId, $series, $slugs) {
            $q = DB::connection('wp')->table('wp_posts as p')
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->whereExists(function ($sub) use ($catId) {
                    $sub->select(DB::raw(1))->from('wp_term_relationships as tr')
                        ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                        ->whereColumn('tr.object_id', 'p.ID')
                        ->where('tt.taxonomy', 'product_cat')->where('tt.term_id', $catId);
                });
            if ($series !== '') {
                $slug = \Str::slug($series);
                $q->whereExists(function ($sub) use ($slug) {
                    $sub->select(DB::raw(1))->from('wp_term_relationships as tr2')
                        ->join('wp_term_taxonomy as tt2', 'tt2.term_taxonomy_id', '=', 'tr2.term_taxonomy_id')
                        ->join('wp_terms as t2', 't2.term_id', '=', 'tt2.term_id')
                        ->whereColumn('tr2.object_id', 'p.ID')
                        ->where('tt2.taxonomy', 'pa_series')->where('t2.slug', $slug);
                });
            }
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

    private function lengthOptions(): array
    {
        $rows = DB::connection('wp')->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->join('wp_posts as p', 'p.ID', '=', 'tr.object_id')
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->where('tt.taxonomy', 'pa_overall-len')
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))->from('wp_term_relationships as trc')
                    ->join('wp_term_taxonomy as ttc', 'ttc.term_taxonomy_id', '=', 'trc.term_taxonomy_id')
                    ->whereColumn('trc.object_id', 'p.ID')
                    ->where('ttc.taxonomy', 'product_cat')->where('ttc.term_id', self::PREMADE_CAT_ID);
            })
            ->distinct()->pluck('t.name')->all();
        natsort($rows);
        return array_values($rows);
    }
}
