<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads the copied WooCommerce catalog (uds_shop / "wp" connection) and
 * exposes it as PTI-style groups -> subcategories -> product tables.
 * NB: wp_postmeta rows are triplicated in this import, so every meta read
 * is de-duplicated with MAX(...) + GROUP BY.
 */
class CatalogService
{
    private string $uploads;

    public function __construct()
    {
        $this->uploads = rtrim(config('catalog.uploads'), '/') . '/';
    }

    private function db()
    {
        return DB::connection('wp');
    }

    /* ---------- groups & categories ---------- */

    /** Short-lived cache for the catalog reads that dominate page time (schemas, listings, terms).
     *  The version key is bumped by catalog:sync-live so a fresh sync shows up within the hour. */
    private function remember(string $key, int $ttl, \Closure $fn)
    {
        $ver = \Illuminate\Support\Facades\Cache::get('catalog_ver', 1);
        return \Illuminate\Support\Facades\Cache::remember("cat:$ver:$key", $ttl, $fn);
    }

    public function groups(): array
    {
        return $this->remember('groups', 600, fn () => $this->groupsUncached());
    }

    private function groupsUncached(): array
    {
        $out = [];
        foreach (config('catalog.groups') as $slug => $g) {
            $cats = $this->groupCategorySlugs($slug, $g);
            $count = $cats ? (int) $this->db()->table('wp_term_taxonomy as tt')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'product_cat')->whereIn('t.slug', $cats)->sum('tt.count') : 0;
            if ($slug === 'complete-driveshafts') { $count += $this->vehicleDriveshaftsCategory()['count']; $cats[] = 'by-vehicle'; }
            $out[] = [
                'slug' => $slug, 'label' => $g['label'], 'blurb' => $g['blurb'] ?? '',
                'count' => $count, 'cat_count' => count($cats),
                'image' => $this->firstCategoryImage($cats),
            ];
        }
        return $out;
    }

    public function group(string $slug): ?array
    {
        return $this->remember("group:$slug", 600, fn () => $this->groupUncached($slug));
    }

    private function groupUncached(string $slug): ?array
    {
        $g = config("catalog.groups.$slug");
        if (!$g) return null;
        $cats = $this->groupCategorySlugs($slug, $g);
        $subs = [];
        foreach ($cats as $cslug) {
            $term = $this->term($cslug);
            if (!$term) continue;
            $subs[] = [
                'slug' => $term->slug, 'name' => html_entity_decode($term->name),
                'count' => (int) $term->count, 'image' => $this->categoryImage($term),
            ];
        }
        // keep config order for explicit lists; alpha for dynamic children
        if (isset($g['children_of'])) usort($subs, fn ($a, $b) => strcmp($a['name'], $b['name']));
        if ($slug === 'complete-driveshafts') $subs[] = $this->vehicleDriveshaftsCategory();
        return ['slug' => $slug, 'label' => $g['label'], 'blurb' => $g['blurb'] ?? '', 'subcategories' => $subs];
    }

    /** The complete driveshafts sold through the vehicle finder (ds_parts) as one more subcategory
     *  of the Complete Driveshafts group. They are not WooCommerce products, so they get their own table. */
    public function vehicleDriveshaftsCategory(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('catalog_ds_subcat', 600, function () {
            $count = (int) DB::table('ds_parts')->where('is_active', 1)->count();
            $img = DB::table('ds_parts')->where('is_active', 1)->where('main_image', '!=', '')->whereNotNull('main_image')
                ->orderByDesc('picture_count')->orderBy('part_number')->value('main_image');
            return ['slug' => 'by-vehicle', 'name' => 'Complete Driveshafts', 'count' => $count, 'image' => $img ? media_url($img, 640) : null];
        });
    }

    /** Every category slug the catalog groups cover => its group slug. */
    public function allCategorySlugs(): array
    {
        $map = [];
        foreach (config('catalog.groups') as $slug => $g) foreach ($this->groupCategorySlugs($slug, $g) as $c) $map[$c] = $slug;
        return $map;
    }

    /** Resolve the category slugs a group covers (explicit list or children_of). */
    private function groupCategorySlugs(string $slug, array $g): array
    {
        // categories created / moved from the admin (settings: catalog.groups => {group: [slug,…]}) extend the config list
        $extra = (array) (\App\Support\Settings::get('catalog.groups', [])[$slug] ?? []);
        $hidden = (array) \App\Support\Settings::get('catalog.hidden_categories', []);
        if (!empty($g['categories'])) return array_values(array_diff(array_unique(array_merge($g['categories'], $extra)), $hidden));
        if (!empty($g['children_of'])) {
            $parent = $this->term($g['children_of']);
            if (!$parent) return [];
            return $this->db()->table('wp_term_taxonomy as tt')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'product_cat')->where('tt.parent', $parent->term_id)
                ->orderByDesc('tt.count')->pluck('t.slug')->all();
        }
        return [];
    }

    public function term(string $slug)
    {
        static $memo = [];
        if (array_key_exists($slug, $memo)) return $memo[$slug];
        // cached as a plain array: the file cache cannot round-trip the PDO row object
        $row = $this->remember("term:$slug", 3600, fn () => (array) ($this->db()->table('wp_term_taxonomy as tt')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'product_cat')->where('t.slug', $slug)
            ->select('t.term_id', 't.name', 't.slug', 'tt.count', 'tt.term_taxonomy_id')->first() ?: []));
        if (!is_array($row)) { // a stale entry written by an older build: drop it and read fresh
            \Illuminate\Support\Facades\Cache::forget('cat:' . \Illuminate\Support\Facades\Cache::get('catalog_ver', 1) . ":term:$slug");
            $row = (array) ($this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'product_cat')->where('t.slug', $slug)
                ->select('t.term_id', 't.name', 't.slug', 'tt.count', 'tt.term_taxonomy_id')->first() ?: []);
        }
        return $memo[$slug] = $row ? (object) $row : null;
    }

    /** Which group a category slug belongs to (for breadcrumbs). */
    public function groupOf(string $catSlug): ?array
    {
        foreach (config('catalog.groups') as $slug => $g) {
            if (in_array($catSlug, $this->groupCategorySlugs($slug, $g), true)) {
                return ['slug' => $slug, 'label' => $g['label']];
            }
        }
        return null;
    }

    /** product_cat term slugs a product sits in — used to classify it for shipping (tube / complete
     *  driveshaft / everything else), since those are exactly the groups a product belongs to. */
    public function categorySlugsOf(int $productId): array
    {
        return $this->db()->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $productId)->where('tt.taxonomy', 'product_cat')
            ->distinct()->pluck('t.slug')->all();
    }

    /** Which top-level catalog group (Complete Driveshafts / Custom Cut Tubing / ...) a product
     *  belongs to, resolved through its own categories. Null for anything uncategorized. */
    public function groupOfProduct(int $productId): ?array
    {
        foreach ($this->categorySlugsOf($productId) as $slug) {
            if ($g = $this->groupOf($slug)) return $g;
        }
        return null;
    }

    /* ---------- images ---------- */

    private function firstCategoryImage(array $catSlugs): string
    {
        foreach ($catSlugs as $s) {
            if ($t = $this->term($s)) {
                $img = $this->categoryImage($t);
                if ($img) return $img;
            }
        }
        return '';
    }

    private function categoryImage($term): string
    {
        // image uploaded from the admin wins
        $own = $this->db()->table('wp_termmeta')->where('term_id', $term->term_id)->where('meta_key', 'uds_image')->value('meta_value');
        if ($own) return media_url($own, 640);
        // WooCommerce category thumbnail (termmeta) first
        $thumb = $this->db()->table('wp_termmeta')->where('term_id', $term->term_id)
            ->where('meta_key', 'thumbnail_id')->value('meta_value');
        if ($thumb && ($u = $this->attachmentUrl((int) $thumb))) return media_url($u, 640);
        // else: newest product's thumbnail in the category
        $pid = $this->db()->table('wp_term_relationships as tr')
            ->join('wp_posts as p', 'p.ID', '=', 'tr.object_id')
            ->where('tr.term_taxonomy_id', $term->term_taxonomy_id)
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->orderByDesc('p.ID')->value('p.ID');
        if ($pid) return media_url($this->productThumb((int) $pid), 640);
        return '';
    }

    /** Batch: map of wp_product_id => image URL ('' when none) for the given ids. */
    /** Canonical shop URL for a category slug (/shop/{group}/{cat}); falls back to the legacy redirecting route. */
    public function categoryPath(string $catSlug): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            try { foreach ($this->groups() as $g) { $full = $this->group($g['slug']); foreach (($full['subcategories'] ?? []) as $c) $map[$c['slug']] = '/shop/' . $g['slug'] . '/' . $c['slug']; } } catch (\Throwable $e) {}
        }
        return $map[$catSlug] ?? ('/product-category/' . $catSlug);
    }

    /** Live overlay row (from catalog:sync-live) for a product, or null. One cached load per 10 minutes. */
    public static function live(int $id): ?array
    {
        static $map = null;
        if ($map === null) {
            $map = \Illuminate\Support\Facades\Cache::remember('catalog_live_map', 600, function () {
                $m = [];
                try {
                    foreach (DB::table('catalog_live')->get(['wp_product_id', 'image', 'thumb', 'gallery', 'price', 'in_stock', 'pti_in_stock', 'name']) as $r) {
                        // in stock when we have it or our supplier PTI has it on hand (pti:sync --stock)
                        $m[(int) $r->wp_product_id] = ['image' => $r->image, 'thumb' => $r->thumb ?: $r->image, 'gallery' => json_decode($r->gallery ?: '[]', true) ?: [], 'price' => $r->price, 'in_stock' => (bool) $r->in_stock || (bool) $r->pti_in_stock, 'name' => $r->name,
                            'own' => (bool) $r->in_stock, 'supplier_only' => !$r->in_stock && (bool) $r->pti_in_stock];
                    }
                } catch (\Throwable $e) {}
                return $m;
            });
        }
        return $map[$id] ?? null;
    }

    public function thumbsFor(array $ids): array
    {
        return array_map(fn ($u) => media_url($u, 160), $this->thumbSources($ids));   // served from the local WebP cache
    }

    /** Batch: map of wp_product_id => the product's own picture (the source the WebP cache resizes), '' when none. */
    public function thumbSources(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $thumbs = $this->db()->table('wp_postmeta')
            ->select('post_id', DB::raw('MAX(meta_value) as v'))
            ->whereIn('post_id', $ids)->where('meta_key', '_thumbnail_id')
            ->groupBy('post_id')->pluck('v', 'post_id');
        $attachIds = array_values(array_unique(array_filter(array_map('intval', $thumbs->all()))));
        $files = [];
        if ($attachIds) {
            $files = $this->db()->table('wp_postmeta')
                ->select('post_id', DB::raw('MAX(meta_value) as v'))
                ->whereIn('post_id', $attachIds)->where('meta_key', '_wp_attached_file')
                ->groupBy('post_id')->pluck('v', 'post_id')->all();
        }
        $out = [];
        foreach ($ids as $pid) {
            $aid = (int) ($thumbs[$pid] ?? 0);
            $file = $files[$aid] ?? null;
            if (!$aid || $aid === 11868 || !$file
                || str_contains($file, 'placeholder') || str_contains($file, '1312312312132')) {
                $out[$pid] = ''; continue;
            }
            $out[$pid] = $this->uploads . ltrim($file, '/');
        }
        foreach ($ids as $pid) { if ($lv = self::live($pid)) { if (!empty($lv['image'])) $out[$pid] = $lv['image']; } }
        return $out;
    }

    private function productThumb(int $productId): string
    {
        $tid = $this->metaVal($productId, '_thumbnail_id');
        return $tid ? $this->attachmentUrl((int) $tid) : '';
    }

    /**
     * A rendered listing image URL (media_url output) that is really "no picture": either empty, or the
     * WooCommerce placeholder. attachmentUrl() already blanks placeholder thumbs, but catalog_live.image
     * can still carry the placeholder, so we decode the media token and look for it there too.
     */
    public static function imageMissing(?string $url): bool
    {
        $url = (string) $url;
        if ($url === '') return true;
        if (preg_match('#/media/\d+/([A-Za-z0-9_-]+)\.webp$#', $url, $m)) {
            $src = base64_decode(strtr($m[1], '-_', '+/'), true);
            if ($src !== false && stripos($src, 'placeholder') !== false) return true;
        }
        return stripos($url, 'placeholder') !== false;
    }

    private function attachmentUrl(int $attachId): string
    {
        // 11868 = the shared "IMAGE NOT AVAILABLE" placeholder -> treat as no image
        if ($attachId === 11868) return '';
        $file = $this->db()->table('wp_postmeta')->where('post_id', $attachId)
            ->where('meta_key', '_wp_attached_file')->value('meta_value');
        if (!$file) return '';
        if (str_contains($file, 'placeholder') || str_contains($file, '1312312312132')) return '';
        return $this->uploads . ltrim($file, '/');
    }

    private function metaVal(int $postId, string $key): ?string
    {
        return $this->db()->table('wp_postmeta')->where('post_id', $postId)->where('meta_key', $key)
            ->max('meta_value');
    }

    /* ---------- per-category schema (dynamic columns + filters) ---------- */

    /** Human label for a pa_ attribute taxonomy. */
    public function attrLabel(string $tax): string
    {
        static $map = [
            'pa_series' => 'Series', 'pa_brand' => 'Brand', 'pa_style' => 'Style', 'pa_notes' => 'Notes',
            'pa_tube-dia-wall' => 'Tube Dia & Wall', 'pa_tube-diameter' => 'Tube Diameter', 'pa_tube-dia' => 'Tube Dia',
            'pa_tube-length' => 'Tube Length', 'pa_tube-wall' => 'Tube Wall',
            'pa_butt-dia' => 'Butt Dia', 'pa_center-u-j-to-weld' => 'Center U-J to Weld',
            'pa_ball-stud-length-dia' => 'Ball Stud Length & Dia', 'pa_ball-stud-dia' => 'Ball Stud Dia', 'pa_ball-dia' => 'Ball Dia',
            'pa_bolt-circle-dia' => 'Bolt Circle Dia', 'pa_bolt-hole-dia' => 'Bolt Hole Dia', 'pa_bolt-holes' => 'Bolt Holes',
            'pa_bolt-hole-count' => 'Bolt Hole Count', 'pa_bolt-hole-pilot-dia' => 'Bolt Hole & Pilot Dia',
            'pa_pilot-dia' => 'Pilot Dia', 'pa_pilot-type' => 'Pilot Type',
            'pa_splines-count-dia' => 'Splines Count & Dia', 'pa_splines-count' => 'Splines Count',
            'pa_splines-dia' => 'Splines Dia', 'pa_splines-length' => 'Splines Length', 'pa_spline' => 'Spline', 'pa_spline-size' => 'Spline Size',
            'pa_cap-dia-1' => 'Cap Dia', 'pa_cap-dia-2' => 'Cap Dia 2', 'pa_u-joint-cap-size' => 'U-Joint Cap Size', 'pa_fits-cap-dia' => 'Fits Cap Dia',
            'pa_center-to-center' => 'Center to Center', 'pa_center-u-joint-to-end' => 'Center U-Joint to End',
            'pa_center-bearing-to-base' => 'Center Bearing to Base',
            'pa_bearing-dia' => 'Bearing Dia', 'pa_bearing-id' => 'Bearing ID', 'pa_inner-race-width' => 'Inner Race Width',
            'pa_outer-race-width' => 'Outer Race Width', 'pa_nose-bushing-dia' => 'Nose Bushing Dia',
            'pa_hub-dia' => 'Hub Dia', 'pa_disc-width' => 'Disc Width', 'pa_through-hole' => 'Through Hole', 'pa_swing-dia' => 'Swing Dia',
            'pa_c-l-to-end' => 'CL to End', 'pa_c-l-to-face' => 'CL to Face', 'pa_c-l-to-base' => 'CL to Base', 'pa_cl-to-cb' => 'CL to CB', 'pa_cl-to-cl' => 'CL to CL',
            'pa_end-to-weld' => 'End to Weld', 'pa_face-to-weld' => 'Face to Weld', 'pa_face-to-end' => 'Face to End', 'pa_flange-to-flange' => 'Flange to Flange',
            'pa_cj-to-cj' => 'CJ to CJ', 'pa_cj-to-end' => 'CJ to End', 'pa_cj-to-flat' => 'CJ to Flat',
            'pa_overall-length' => 'Overall Length', 'pa_overall-len' => 'Overall Length', 'pa_max-length' => 'Max Length', 'pa_max-length-collapsed' => 'Max Length Collapsed',
            'pa_transmission' => 'Transmission', 'pa_material' => 'Material', 'pa_vehicle' => 'Vehicle', 'pa_model' => 'Model', 'pa_year-compatibility' => 'Year',
            'pa_ctr-to-ctr-of-mount-holes' => 'Ctr-to-Ctr Mount Holes', 'pa_counter-bore-dia' => 'Counter Bore Dia',
            'pa_thickness' => 'Thickness', 'pa_thread-size' => 'Thread Size', 'pa_thread-dia' => 'Thread Dia', 'pa_bolt-length' => 'Bolt Length', 'pa_bolt-torque' => 'Bolt Torque', 'pa_bolt-type' => 'Bolt Type',
            'pa_inside-dia' => 'Inside Dia', 'pa_outside-dia' => 'Outside Dia', 'pa_outer-dia' => 'Outer Dia', 'pa_bore-dia' => 'Bore Dia', 'pa_bore-type' => 'Bore Type',
            'pa_hex' => 'Hex', 'pa_keyway' => 'Keyway', 'pa_width-1' => 'Width', 'pa_width-2' => 'Width 2', 'pa_height' => 'Height', 'pa_length' => 'Length', 'pa_grade' => 'Grade', 'pa_lube' => 'Lube',
            'pa_from-tube' => 'From Tube', 'pa_to-tube' => 'To Tube', 'pa_fits-to-tube' => 'Fits to Tube', 'pa_od' => 'OD', 'pa_disc-width' => 'Disc Width', 'pa_bracket-wid' => 'Bracket Width', 'pa_seal-o-d' => 'Seal OD', 'pa_seal-height' => 'Seal Height',
        ];
        if (isset($map[$tax])) return $map[$tax];
        static $wc = null;
        if ($wc === null) { try { $wc = $this->db()->table('wp_woocommerce_attribute_taxonomies')->pluck('attribute_label', 'attribute_name')->all(); } catch (\Throwable $e) { $wc = []; } }
        if (isset($wc[substr($tax, 3)])) return $wc[substr($tax, 3)];
        $s = ' ' . ucwords(str_replace(['pa_', '-'], ['', ' '], $tax)) . ' ';
        $s = str_replace([' Dia ', ' Id ', ' Od ', ' Cl ', ' Uj ', ' U J '], [' Dia ', ' ID ', ' OD ', ' CL ', ' U-J ', ' U-J '], $s);
        return trim($s);
    }

    /**
     * Derive the display schema for a category: which attribute columns to show
     * and which filters to offer, from what's actually populated on its products.
     */
    public function categorySchema(string $catSlug): array
    {
        return $this->remember("schema:$catSlug", 3600, fn () => $this->categorySchemaUncached($catSlug));
    }

    private function categorySchemaUncached(string $catSlug): array
    {
        $term = $this->term($catSlug);
        if (!$term) return ['columns' => [], 'filters' => [], 'attrKeys' => []];
        $total = max(1, (int) $term->count);

        $rows = $this->db()->table('wp_term_relationships as ptr')
            ->join('wp_posts as p', 'p.ID', '=', 'ptr.object_id')
            ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'p.ID')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->where('ptr.term_taxonomy_id', $term->term_taxonomy_id)
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->where('tt.taxonomy', 'like', 'pa_%')
            ->groupBy('tt.taxonomy')
            ->select('tt.taxonomy', DB::raw('COUNT(DISTINCT tr.object_id) as prods'), DB::raw('COUNT(DISTINCT tr.term_taxonomy_id) as vals'))
            ->get();

        $thr = max(3, (int) ceil($total * 0.25));
        $keep = [];
        foreach ($rows as $r) {
            if ($r->taxonomy === 'pa_partn-number' || in_array($r->taxonomy, self::DETAIL_ATTRS, true)) continue;
            if ((int) $r->prods >= $thr) $keep[$r->taxonomy] = ['prods' => (int) $r->prods, 'vals' => (int) $r->vals];
        }
        $has = fn ($t) => isset($keep[$t]);

        // dimension attrs = kept minus the classified ones, ordered by coverage
        $dims = array_values(array_filter(array_keys($keep), fn ($t) => !in_array($t, ['pa_series', 'pa_brand', 'pa_style', 'pa_notes'], true)));
        usort($dims, fn ($a, $b) => $keep[$b]['prods'] <=> $keep[$a]['prods']);
        // the table's own columns (config/catalog_columns.php) keep their place and order; others only fill free slots
        $pinned = array_values(array_filter((array) config("catalog_columns.$catSlug", []), $has));
        $dims = array_slice(array_values(array_unique(array_merge($pinned, $dims))), 0, 6);

        $cols = [];
        if ($has('pa_series')) $cols[] = ['key' => 'pa_series', 'label' => 'Series'];
        foreach ($dims as $t) $cols[] = ['key' => $t, 'label' => $this->attrLabel($t)];
        if ($has('pa_style')) $cols[] = ['key' => 'pa_style', 'label' => 'Style'];
        if ($has('pa_brand')) $cols[] = ['key' => 'pa_brand', 'label' => 'Brand'];
        if ($has('pa_notes')) $cols[] = ['key' => 'pa_notes', 'label' => 'Notes'];

        // filters: curated whitelist ∩ kept, capped at 4
        $filters = [];
        foreach (['pa_series', 'pa_brand', 'pa_style', 'pa_tube-dia-wall', 'pa_tube-diameter', 'pa_transmission', 'pa_spline', 'pa_material', 'pa_vehicle'] as $t) {
            if (count($filters) >= 4 || !$has($t)) continue;
            $opts = $this->filterValues($term->term_taxonomy_id, $t);
            if ($opts) $filters[] = ['key' => $t, 'param' => substr($t, 3), 'label' => $this->attrLabel($t), 'options' => $opts];
        }

        $attrKeys = array_values(array_unique(array_merge(array_column($cols, 'key'), array_column($filters, 'key'))));
        return ['columns' => $cols, 'filters' => $filters, 'attrKeys' => $attrKeys];
    }

    private function filterValues(int $catTtid, string $tax): array
    {
        return $this->db()->table('wp_term_relationships as ptr')
            ->join('wp_posts as p', 'p.ID', '=', 'ptr.object_id')
            ->join('wp_term_relationships as atr', 'atr.object_id', '=', 'p.ID')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'atr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('ptr.term_taxonomy_id', $catTtid)
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->where('tt.taxonomy', $tax)
            ->distinct()->orderBy('t.name')->pluck('t.name', 't.slug')->all();
    }

    /* ---------- product listing ---------- */

    /**
     * Paginated products with search, sort and per-category attribute columns.
     * $filters keyed by taxonomy => term-slug. $sort: popular|price_asc|price_desc|part_asc|part_desc|name|attr:<tax>:<dir>.
     */
    public function products(string $catSlug, array $filters = [], int $page = 1, int $perPage = 60, string $sort = 'price_asc', string $q = '', array $attrKeys = []): array
    {
        $key = 'list:' . md5(json_encode([$catSlug, $filters, $page, $perPage, $sort, $q, $attrKeys]));
        return $this->remember($key, 600, fn () => $this->productsUncached($catSlug, $filters, $page, $perPage, $sort, $q, $attrKeys));
    }

    private function productsUncached(string $catSlug, array $filters, int $page, int $perPage, string $sort, string $q, array $attrKeys): array
    {
        $term = $this->term($catSlug);
        if (!$term) return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];

        // a search without attribute filters runs on the in-memory index (same matching and order as the SQL below)
        if ($q !== '' && !array_filter($filters, fn ($v) => $v !== '' && $v !== null) && !str_starts_with($sort, 'attr:')) {
            $all = $this->searchIds($q, (int) $term->term_taxonomy_id, $sort);
            $total = count($all);
            $pages = (int) ceil($total / $perPage);
            $page = max(1, min($page, max(1, $pages)));
            return ['items' => $this->listItems($catSlug, array_slice($all, ($page - 1) * $perPage, $perPage), $attrKeys), 'total' => $total, 'page' => $page, 'pages' => $pages];
        }

        $base = $this->db()->table('wp_posts as p')
            ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'p.ID')
            ->where('tr.term_taxonomy_id', $term->term_taxonomy_id)
            ->where('p.post_type', 'product')->where('p.post_status', 'publish');

        foreach ($filters as $tax => $slug) {
            if ($slug === '' || $slug === null) continue;
            $ttid = $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', $tax)->where('t.slug', $slug)->value('tt.term_taxonomy_id');
            if ($ttid) {
                $alias = 'f_' . substr(md5($tax), 0, 8);
                $base->join("wp_term_relationships as $alias", function ($j) use ($alias, $ttid) {
                    $j->on("$alias.object_id", '=', 'p.ID')->where("$alias.term_taxonomy_id", '=', $ttid);
                });
            }
        }

        // price + sku as deduped subquery joins (postmeta is triplicated)
        $priceSub = $this->db()->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value+0) as pv'))->where('meta_key', '_price')->groupBy('post_id');
        $skuSub = $this->db()->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) as sv'))->where('meta_key', '_sku')->groupBy('post_id');
        $base->leftJoinSub($priceSub, 'pr', 'pr.post_id', '=', 'p.ID')->leftJoinSub($skuSub, 'sk', 'sk.post_id', '=', 'p.ID');

        if ($q !== '') {
            // literal match: "%" / "_" typed in the box are characters, not LIKE wildcards
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $base->where(function ($w) use ($like) {
                $w->where('p.post_title', 'like', $like)->orWhere('sk.sv', 'like', $like);
            });
        }

        $total = (clone $base)->distinct()->count('p.ID');
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($page, max(1, $pages)));

        $sel = (clone $base)->select('p.ID', 'p.post_title')->groupBy('p.ID', 'p.post_title', 'pr.pv', 'sk.sv');
        if (str_starts_with($sort, 'attr:')) {
            [, $atax, $dir] = array_pad(explode(':', $sort), 3, 'asc');
            $dir = $dir === 'desc' ? 'desc' : 'asc';
            $avSub = $this->db()->table('wp_term_relationships as tr2')
                ->join('wp_term_taxonomy as tt2', 'tt2.term_taxonomy_id', '=', 'tr2.term_taxonomy_id')
                ->join('wp_terms as t2', 't2.term_id', '=', 'tt2.term_id')
                ->where('tt2.taxonomy', $atax)->select('tr2.object_id', DB::raw('MAX(t2.name) as av'))->groupBy('tr2.object_id');
            $sel->leftJoinSub($avSub, 'av', 'av.object_id', '=', 'p.ID')->groupBy('av.av')->orderByRaw('av.av+0 ' . $dir)->orderBy('av.av', $dir);
        } else {
            // WooCommerce's lifetime sales counter (best sellers first)
            $sales = fn () => $sel->leftJoinSub($this->db()->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value+0) as ts'))->where('meta_key', 'total_sales')->groupBy('post_id'), 'ts', 'ts.post_id', '=', 'p.ID')->groupBy('ts.ts');
            if ($sort === 'relevance' && $q !== '') {
                // search: the part number itself first (exact, then starting a dash-separated part: "1310" → 5-1310XU), then
                // part numbers containing it, then title matches; best sellers first inside each band (audit 2026-11-11)
                $lk = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
                $sales()->orderByRaw('CASE WHEN sk.sv = ? THEN 0 WHEN sk.sv LIKE ? OR sk.sv LIKE ? THEN 1 WHEN sk.sv LIKE ? THEN 2 ELSE 3 END', [$q, $lk . '%', '%-' . $lk . '%', '%' . $lk . '%'])
                    ->orderByRaw('COALESCE(ts.ts, 0) desc')->orderBy('sk.sv');
                $sort = 'done';
            }
            match ($sort) {
                'done' => null,
                'price_desc' => $sel->orderByDesc('pr.pv'),
                'part_asc' => $sel->orderBy('sk.sv'),
                'part_desc' => $sel->orderByDesc('sk.sv'),
                'name' => $sel->orderBy('p.post_title'),
                // best sellers, then cheapest first among parts that never sold
                'popular' => $sales()->orderByRaw('COALESCE(ts.ts, 0) desc')->orderBy('pr.pv'),
                default => $sel->orderBy('pr.pv'),
            };
        }
        $ids = $sel->offset(($page - 1) * $perPage)->limit($perPage)->pluck('p.ID')->all();

        return ['items' => $this->listItems($catSlug, $ids, $attrKeys), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Listing rows for one page of ids; products with no picture (or the placeholder) borrow one from a same-series sibling. */
    private function listItems(string $catSlug, array $ids, array $attrKeys): array
    {
        $items = $this->hydrateList($ids, $attrKeys);
        $seriesImg = null;
        foreach ($items as &$it) {
            if (!self::imageMissing($it['image'] ?? '')) continue;
            $s = trim((string) ($it['series'] ?? ''));
            if ($s === '') continue;
            $seriesImg ??= $this->categoryImageBySeries($catSlug);
            if (!empty($seriesImg[$s])) $it['image'] = $seriesImg[$s];
        }
        unset($it);
        return $items;
    }

    /* ---------- search index ---------- */

    /**
     * Every published product as a few short lowercase strings. A search used to be SQL LIKE over a postmeta derived
     * table, 100–190 ms a query and 17 of them for one results page (2 s; owner, 2026-11-11: "2s no es aceptable");
     * scanning ~3,000 strings in PHP takes about a millisecond.
     * 'p' => [id => [sku lowercase, title + sku haystack, sku, price|null, lifetime sales, [product_cat term_taxonomy_id, …],
     *   title + sku + category names in catalog spelling (canon())]],
     * 'cat' => [term_taxonomy_id => [slug, name]], 'v' => [catalog word => products using it], 'vb' => ' every word '.
     */
    public function searchIndex(): array
    {
        // one search page asks for it a dozen times (each category, the counts): read and unserialize the 1 MB once
        $ver = \Illuminate\Support\Facades\Cache::get('catalog_ver', 1);
        if ($this->searchMemo && $this->searchMemo[0] === $ver) return $this->searchMemo[1];
        return ($this->searchMemo = [$ver, $this->searchIndexCached()])[1];
    }

    private ?array $searchMemo = null;

    private function searchIndexCached(): array
    {
        return $this->remember('search:index:v2', 21600, function () {   // catalog edits bump catalog_ver, so 6 h only ages the sales order
            $titles = $this->db()->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->pluck('post_title', 'ID');
            $meta = [];
            foreach ($this->db()->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')->whereIn('m.meta_key', ['_sku', '_price', 'total_sales'])
                ->groupBy('m.post_id', 'm.meta_key')->select('m.post_id', 'm.meta_key', DB::raw('MAX(m.meta_value) as s'), DB::raw('MAX(m.meta_value+0) as n'))->get() as $r) {
                $meta[(int) $r->post_id][$r->meta_key] = $r->meta_key === '_sku' ? (string) $r->s : ($r->s === null || $r->s === '' ? null : (float) $r->n);
            }
            $cats = []; $catOf = [];
            foreach ($this->db()->table('wp_term_relationships as tr')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'product_cat')->select('tr.object_id', 'tt.term_taxonomy_id', 't.slug', 't.name')->get() as $r) {
                $cats[(int) $r->term_taxonomy_id] = [$r->slug, html_entity_decode($r->name)];
                $catOf[(int) $r->object_id][] = (int) $r->term_taxonomy_id;
            }
            $p = []; $vocab = [];
            foreach ($titles as $id => $title) {
                $m = $meta[$id] ?? []; $sku = (string) ($m['_sku'] ?? ''); $tts = array_values(array_unique($catOf[$id] ?? []));
                $words = self::canon(html_entity_decode((string) $title) . ' ' . $sku . ' ' . implode(' ', array_map(fn ($tt) => $cats[$tt][1] ?? '', $tts)));
                foreach (self::vocabOf($words) as $w) $vocab[$w] = ($vocab[$w] ?? 0) + 1;
                $p[(int) $id] = [mb_strtolower($sku), self::haystack([(string) $title, html_entity_decode((string) $title), $sku]), $sku,
                    $m['_price'] ?? null, (float) ($m['total_sales'] ?? 0), $tts, $words];
            }
            return ['p' => $p, 'cat' => $cats, 'v' => $vocab, 'vb' => ' ' . implode(' ', array_keys($vocab)) . ' '];
        });
    }

    /**
     * Ids of the published products a query finds, optionally only those in one category, in the listing's order: those
     * whose title or SKU contains $q as typed (any case), then those holding every word of it in any order, in catalog
     * spelling (understand(): "yoke spicer 1310", "1310 ujoint", "drivshaft" all found nothing before, owner 2026-11-12).
     * 'relevance': the part number itself first (exact, then starting a dash-separated part: "1310" → 5-1310XU), then part
     * numbers containing it, then title matches, then word matches; best sellers first inside each band.
     */
    public function searchIds(string $q, ?int $ttid = null, string $sort = 'relevance'): array
    {
        $ql = mb_strtolower(trim($q));
        if ($ql === '') return [];
        $idx = $this->searchIndex();
        $words = $this->understood($q)['words'];
        $hits = []; $loose = [];
        foreach ($idx['p'] as $id => $r) {
            if ($ttid !== null && !in_array($ttid, $r[5], true)) continue;
            if (self::found($r[1], $q)) $hits[$id] = $r;
            elseif ($words && self::hasWords($r[6], $words)) { $hits[$id] = $r; $loose[$id] = true; }
        }
        $band = fn ($s) => $s === $ql ? 0 : (str_starts_with($s, $ql) || str_contains($s, '-' . $ql) ? 1 : (str_contains($s, $ql) ? 2 : 3));
        $price = fn ($r) => $r[3] ?? -INF;   // SQL puts a missing price first going up
        uksort($hits, fn ($a, $b) => match ($sort) {
            'popular' => $hits[$b][4] <=> $hits[$a][4] ?: $price($hits[$a]) <=> $price($hits[$b]),
            'price_asc' => $price($hits[$a]) <=> $price($hits[$b]),
            'price_desc' => $price($hits[$b]) <=> $price($hits[$a]),
            'part_asc' => strcmp($hits[$a][0], $hits[$b][0]),
            'part_desc' => strcmp($hits[$b][0], $hits[$a][0]),
            'name' => strcmp($hits[$a][1], $hits[$b][1]),
            default => (isset($loose[$a]) ? 4 : $band($hits[$a][0])) <=> (isset($loose[$b]) ? 4 : $band($hits[$b][0])) ?: $hits[$b][4] <=> $hits[$a][4] ?: strcmp($hits[$a][0], $hits[$b][0]),
        } ?: $a <=> $b);
        return array_keys($hits);
    }

    /**
     * Lowercase search text of a record: each value as written and folded to ASCII, so "1310 series" finds a title with
     * non-breaking spaces and "cardán" finds "cardan", as the database's LIKE did. Fields are joined with \x1f, which
     * nobody types, so a phrase never matches across two fields.
     */
    public static function haystack(array $values): string
    {
        $out = [];
        foreach ($values as $v) { $out[mb_strtolower((string) $v)] = true; $out[mb_strtolower(\Illuminate\Support\Str::ascii((string) $v))] = true; }
        return implode("\x1f", array_keys($out));
    }

    /** Does a haystack() contain $q, as typed or folded to ASCII (a query that folds to nothing, "日本", is matched as typed)? */
    public static function found(string $hay, string $q): bool
    {
        static $memo = [];
        if (count($memo) > 200) $memo = [];
        $n = $memo[$q] ??= array_values(array_unique(array_filter([mb_strtolower(trim($q)), trim(mb_strtolower(\Illuminate\Support\Str::ascii(trim($q))))], fn ($x) => $x !== '')));
        foreach ($n as $x) if (str_contains($hay, $x)) return true;
        return false;
    }

    /**
     * What people type → how the catalog writes it, applied to product text and queries alike (canon()). Trade synonyms
     * and the Spanish names fold to one spelling; F-250 / F 250 → f250. Order matters: the longer phrases go first.
     */
    private const SAY = [
        '/\bcardan joints?\b|\bu[ -]?joints?\b|\buniversal joints?\b|\bcrucetas?\b/' => 'ujoint',
        '/\bdrive[ -]?shafts?\b|\bprop(?:eller)?[ -]?shafts?\b|\bdrivelines?\b|\bcardan(?:es)?\b/' => 'driveshaft',
        '/\bslip[ -]?yokes?\b|\btrans(?:mission)?[ -]yokes?\b/' => 'slip yoke',
        '/\bweld[ -]yokes?\b/' => 'tube yoke',
        '/\bpinion[ -]yokes?\b/' => 'end yoke',
        '/\byolks?\b|\bhorquillas?\b|\byugos?\b/' => 'yoke',
        '/\b(?:carrier|hanger|centre|center)[ -](?:support[ -])?bearings?\b|\bsoportes? central(?:es)?\b/' => 'center support bearing',
        '/\brodamientos?\b|\baleros?\b/' => 'bearing',
        '/\bu[ -]?bolts?\b/' => 'ubolt',
        '/\bbridas?\b/' => 'flange',
        '/\btubing\b|\btubos?\b/' => 'tube',
        '/\bchevy\b/' => 'chevrolet',
        '/\bvw\b/' => 'volkswagen',
        '/\bmercedes[ -]benz\b|\bbenz\b/' => 'mercedes',
        '/\b([a-z]{1,2})-(\d{2,4})\b/' => '$1$2',
        '/\b([cdefkr]) (\d{2,4})\b/' => '$1$2',
    ];

    /** Vehicle makes in catalog spelling: on a universal part ("chevy 1310 u-joint") they are the first word to give up. */
    private const MAKES = ['acura' => 1, 'audi' => 1, 'bmw' => 1, 'buick' => 1, 'cadillac' => 1, 'chevrolet' => 1, 'chrysler' => 1, 'dodge' => 1,
        'ford' => 1, 'gmc' => 1, 'honda' => 1, 'hummer' => 1, 'hyundai' => 1, 'infiniti' => 1, 'international' => 1, 'isuzu' => 1, 'jeep' => 1,
        'kia' => 1, 'lexus' => 1, 'lincoln' => 1, 'mazda' => 1, 'mercedes' => 1, 'mercury' => 1, 'mitsubishi' => 1, 'nissan' => 1, 'pontiac' => 1,
        'porsche' => 1, 'ram' => 1, 'subaru' => 1, 'suzuki' => 1, 'toyota' => 1, 'volkswagen' => 1, 'volvo' => 1, 'freightliner' => 1,
        'kenworth' => 1, 'peterbilt' => 1, 'mack' => 1, 'hino' => 1];

    /** Words a query may carry that say nothing about the part. */
    private const FILLER = ['for' => 1, 'and' => 1, 'the' => 1, 'with' => 1, 'new' => 1, 'oem' => 1, 'part' => 1, 'parts' => 1, 'piece' => 1,
        'replacement' => 1, 'fits' => 1, 'fit' => 1, 'del' => 1, 'las' => 1, 'los' => 1, 'para' => 1, 'con' => 1, 'una' => 1,
        'pieza' => 1, 'piezas' => 1, 'repuesto' => 1, 'repuestos' => 1];

    /** Lowercase ASCII text in catalog spelling (SAY), punctuation as spaces: "U-Joint, 1310 (Spicer)" → "ujoint 1310 spicer". */
    public static function canon(string $s): string
    {
        $s = ' ' . mb_strtolower(\Illuminate\Support\Str::ascii($s)) . ' ';
        $s = preg_replace(array_keys(self::SAY), array_values(self::SAY), $s);
        return trim(preg_replace('/[\s,;:()\[\]"\/|+*!?]+/', ' ', $s));
    }

    /** The plain words of a canon() text that a typo can be corrected to: letters only, 3 or more. */
    public static function vocabOf(string $words): array
    {
        return array_values(array_unique(array_filter(explode(' ', $words), fn ($w) => strlen($w) >= 3 && ctype_alpha($w))));
    }

    /**
     * A query as the words to match, in catalog spelling: plurals as singulars, filler dropped. A word no product uses
     * becomes the closest word the index does use (one typo in a word of up to 5 letters, two in a longer one:
     * "drivshaft" → driveshaft); with nothing that close it is dropped when $drop (the parts catalog: "chevy 1310 ujoint"
     * still finds the u-joints) or kept, so nothing matches (the finder). $idx = ['v' => word counts, 'vb' => ' words '].
     * ['words' => [...], 'fixed' => the query as corrected, null when nothing was, 'dropped' => [words left out]].
     */
    public static function understand(string $q, array $idx, bool $drop = true): array
    {
        static $memo = [];
        $key = $q . "\0" . strlen($idx['vb'] ?? '') . ($drop ? 'd' : 'k');
        if (isset($memo[$key])) return $memo[$key];
        if (count($memo) > 200) $memo = [];
        $words = []; $dropped = []; $fixed = false;
        foreach (explode(' ', self::canon($q)) as $w) {
            $w = trim($w, '-.\'');
            if ($w === '' || !preg_match('/[a-z0-9]/', $w) || isset(self::FILLER[$w])) continue;
            if (ctype_alpha($w)) {
                if (strlen($w) < 3) continue;   // "a", "u", "de": in a substring match they would hit everything
                if (strlen($w) >= 4 && str_ends_with($w, 's') && !str_ends_with($w, 'ss')) $w = substr($w, 0, -1);
                if (!str_contains($idx['vb'] ?? '', $w)) {
                    $near = self::nearest($w, $idx['v'] ?? []);
                    if ($near !== null) { $w = $near; $fixed = true; }
                    elseif ($drop) { $dropped[] = $w; continue; }
                }
            }
            $words[] = $w;
        }
        $words = array_values(array_unique($words));
        $say = fn ($w) => ['ujoint' => 'u-joint', 'ubolt' => 'u-bolt'][$w] ?? $w;
        return $memo[$key] = ['words' => $words, 'fixed' => $fixed && $words ? implode(' ', array_map($say, $words)) : null, 'dropped' => $dropped];
    }

    /** The index word closest to a misspelt one (fewest edits, then the most used), null when none is close enough. */
    private static function nearest(string $w, array $vocab): ?string
    {
        $max = strlen($w) <= 5 ? 1 : 2; $best = null; $bestD = $max + 1; $bestN = 0;
        foreach ($vocab as $v => $n) {
            $v = (string) $v;
            if (abs(strlen($v) - strlen($w)) > $max) continue;
            $d = levenshtein($w, $v);
            if ($d < $bestD || ($d === $bestD && $n > $bestN)) { $best = $v; $bestD = $d; $bestN = $n; }
        }
        return $bestD <= $max ? $best : null;
    }

    /** Does a canon() text hold every word? Numbers must stand alone ("60" is not in "1600"); other words may be part of one. */
    public static function hasWords(string $text, array $words): bool
    {
        foreach ($words as $w) if (ctype_digit($w) ? !preg_match('/(?<!\d)' . $w . '(?!\d)/', $text) : !str_contains($text, $w)) return false;
        return (bool) $words;
    }

    /**
     * How a query is read for the parts catalog (understand()), for searchIds() and the "showing results for" line. When
     * no product holds all its words together, the rarest word is left out while two or more remain: a vehicle make on
     * a universal part ("chevy 1310 u-joint" → the 1310 u-joints) or a word only a few titles use.
     */
    public function understood(string $q): array
    {
        $idx = $this->searchIndex();
        $key = $q . "\0" . $idx['vb'];
        if (isset($this->readMemo[$key])) return $this->readMemo[$key];
        $u = self::understand($q, $idx);
        if (count($u['words']) >= 2) {
            $phrase = false;
            foreach ($idx['p'] as $r) if (self::found($r[1], $q)) { $phrase = true; break; }
            $any = function (array $words) use ($idx) { foreach ($idx['p'] as $r) if (self::hasWords($r[6], $words)) return true; return false; };
            while (!$phrase && count($u['words']) >= 2 && !$any($u['words'])) {
                // which word to give up: a year, then a vehicle make (parts fit by series, not by vehicle), then the rarest
                // other word, a number last; the first whose absence lets the rest match, else the first of them
                $n = array_fill_keys($u['words'], 0);
                foreach ($idx['p'] as $r) foreach ($u['words'] as $w) if (self::hasWords($r[6], [$w])) $n[$w]++;
                $tier = fn ($w) => preg_match('/^(19|20)\d\d$/', $w) ? 0 : (isset(self::MAKES[$w]) ? 1 : (ctype_alpha($w) ? 2 : 3));
                $order = $u['words'];
                usort($order, fn ($a, $b) => $tier($a) <=> $tier($b) ?: $n[$a] <=> $n[$b]);
                $give = $order[0];
                foreach ($order as $w) if ($any(array_diff($u['words'], [$w]))) { $give = $w; break; }
                $u['dropped'][] = $give;
                $u['words'] = array_values(array_diff($u['words'], [$give]));
            }
        }
        return $this->readMemo[$key] = $u;
    }

    private array $readMemo = [];

    /** Categories holding products whose title or SKU contains $q: [['slug', 'name', 'n'], …], most hits first. */
    public function searchCategories(string $q): array
    {
        $idx = $this->searchIndex(); $n = [];
        foreach ($this->searchIds($q, null, 'part_asc') as $id) foreach ($idx['p'][$id][5] as $tt) $n[$tt] = ($n[$tt] ?? 0) + 1;
        arsort($n);
        $out = [];
        foreach ($n as $tt => $c) if (isset($idx['cat'][$tt])) $out[] = ['slug' => $idx['cat'][$tt][0], 'name' => $idx['cat'][$tt][1], 'n' => $c];
        return $out;
    }

    /** Build display rows for product IDs (order preserved), incl. dynamic attribute columns. */
    private function hydrateList(array $ids, array $attrKeys = []): array
    {
        if (!$ids) return [];
        $meta = $this->db()->table('wp_posts as p')
            ->leftJoin('wp_postmeta as m', 'm.post_id', '=', 'p.ID')
            ->whereIn('p.ID', $ids)
            ->whereIn('m.meta_key', ['_sku', '_price', '_regular_price', '_sale_price', '_weight', '_stock', '_stock_status', '_thumbnail_id'])
            ->groupBy('p.ID', 'p.post_title')
            ->select('p.ID', 'p.post_title', DB::raw(
                "MAX(CASE WHEN m.meta_key='_sku' THEN m.meta_value END) sku," .
                "MAX(CASE WHEN m.meta_key='_price' THEN m.meta_value END) price," .
                "MAX(CASE WHEN m.meta_key='_regular_price' THEN m.meta_value END) regular," .
                "MAX(CASE WHEN m.meta_key='_sale_price' THEN m.meta_value END) sale," .
                "MAX(CASE WHEN m.meta_key='_weight' THEN m.meta_value END) weight," .
                "MAX(CASE WHEN m.meta_key='_stock' THEN m.meta_value END) stock," .
                "MAX(CASE WHEN m.meta_key='_stock_status' THEN m.meta_value END) stock_status," .
                "MAX(CASE WHEN m.meta_key='_thumbnail_id' THEN m.meta_value END) thumb"
            ))->get()->keyBy('ID');

        $fetchKeys = array_values(array_unique(array_merge(['pa_brand', 'pa_series', 'pa_style', 'pa_notes'], $attrKeys)));
        $attrs = $this->attrsFor($ids, $fetchKeys);

        $out = [];
        foreach ($ids as $id) {
            $m = $meta[$id] ?? null;
            if (!$m) continue;
            $a = $attrs[$id] ?? [];
            $price = (float) ($m->price ?: $m->regular ?: 0);
            $regular = (float) ($m->regular ?: $m->price ?: 0);
            $out[] = [
                'id' => (int) $id,
                'name' => html_entity_decode($m->post_title),
                'sku' => $m->sku ?: '',
                'price' => $price,
                'regular' => $regular,
                'on_sale' => $regular > $price + 0.001,
                'weight' => $m->weight !== null ? rtrim(rtrim(number_format((float) $m->weight, 3), '0'), '.') : '',
                'style' => $a['pa_style'] ?? '',
                'brand' => $a['pa_brand'] ?? '',
                'series' => $a['pa_series'] ?? '',
                'notes' => $a['pa_notes'] ?? '',
                'attrs' => $a,
                // status comes from the live overlay (ours OR supplier PTI); the _stock count is the stale catalog copy, so a
                // count is shown only when it is > 0 and the live data says WE hold the part (never "In stock · 0", never a
                // Florida count for a supplier-only part)
                'stock_status' => ($lv = self::live((int) $m->ID)) ? ($lv['in_stock'] ? 'instock' : 'outofstock') : ($m->stock_status ?: 'instock'),
                'stock_source' => $lv ? (($lv['own'] ?? $lv['in_stock']) ? 'ours' : (!empty($lv['supplier_only']) ? 'supplier' : null)) : null,
                'stock' => (is_numeric($m->stock) && (int) $m->stock > 0 && (!$lv || ($lv['own'] ?? $lv['in_stock']))) ? (int) $m->stock : null,
                'image' => media_url(($lv['image'] ?? null) ?: ($m->thumb ? $this->attachmentUrl((int) $m->thumb) : ''), 320),
            ];
        }
        // batch: does an alternate SKU (base<->U, -01) exist for each row? -> ⚠ indicator
        $cands = [];
        foreach ($out as $it) {
            $sk = $it['sku']; if ($sk === '') continue;
            $cands[] = str_ends_with($sk, 'U') ? substr($sk, 0, -1) : $sk . 'U';
            $cands[] = str_ends_with($sk, '-01') ? substr($sk, 0, -3) : $sk . '-01';
        }
        $existing = [];
        if ($cands) {
            $existing = array_flip($this->db()->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
                ->where('m.meta_key', '_sku')->whereIn('m.meta_value', array_values(array_unique($cands)))
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')->pluck('m.meta_value')->all());
        }
        foreach ($out as &$it) {
            $sk = $it['sku'];
            $c1 = str_ends_with($sk, 'U') ? substr($sk, 0, -1) : $sk . 'U';
            $c2 = str_ends_with($sk, '-01') ? substr($sk, 0, -3) : $sk . '-01';
            $it['has_alt'] = $sk !== '' && (isset($existing[$c1]) || isset($existing[$c2]));
        }
        unset($it);
        return $out;
    }

    /** map of productId => [taxonomy => comma-joined term names]. */
    private function attrsFor(array $ids, array $taxonomies): array
    {
        $rows = $this->db()->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->whereIn('tr.object_id', $ids)->whereIn('tt.taxonomy', $taxonomies)
            ->select('tr.object_id', 'tt.taxonomy', 't.name')->get();
        $map = [];
        foreach ($rows as $r) {
            $map[$r->object_id][$r->taxonomy] = isset($map[$r->object_id][$r->taxonomy])
                ? $map[$r->object_id][$r->taxonomy] . ', ' . $r->name : $r->name;
        }
        return $map;
    }


    /**
     * Alternate SKUs for a part: our-brand "U" version, base (other-brand), or "-01" supplier alt.
     */
    public function alternates(int $id): array
    {
        $sku = $this->metaVal($id, '_sku');
        if (!$sku) return [];
        $cands = [];
        if (str_ends_with($sku, 'U')) $cands[] = substr($sku, 0, -1); else $cands[] = $sku . 'U';
        if (str_ends_with($sku, '-01')) $cands[] = substr($sku, 0, -3); else $cands[] = $sku . '-01';
        $cands = array_values(array_filter(array_unique($cands), fn ($c) => $c !== '' && $c !== $sku));
        if (!$cands) return [];
        $ids = $this->db()->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
            ->where('m.meta_key', '_sku')->whereIn('m.meta_value', $cands)
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->distinct()->pluck('p.ID')->all();
        $out = [];
        foreach ($this->hydrateList($ids, ['pa_brand']) as $p) {
            if ((int) $p['id'] === $id) continue;
            $a = $p['sku'];
            // Business rule: SKU ending in "U" = Universal Driveshafts (our brand); otherwise Spicer.
            if (str_ends_with($a, '-01')) { $note = 'New part — alternate supplier'; $type = 'Alt Part'; }
            elseif (str_ends_with(strtoupper($a), 'U')) { $note = 'UDS — our brand'; $type = 'Our Brand'; }
            else { $note = 'Spicer — same part'; $type = 'Spicer'; }
            $out[] = ['id' => $p['id'], 'sku' => $a, 'name' => $p['name'], 'brand' => $p['brand'],
                      'price' => $p['price'], 'regular' => $p['regular'], 'note' => $note, 'type' => $type];
        }
        return $out;
    }

    /* ---------- single product (detail / quick view) ---------- */

    public function productById(int $id): ?array
    {
        $p = $this->db()->table('wp_posts')->where('ID', $id)
            ->where('post_type', 'product')->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_content', 'post_excerpt')->first();
        if (!$p) return null;
        return $this->detail($p);
    }

    public function productBySku(string $sku): ?array
    {
        $id = $this->db()->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', $sku)->max('post_id');
        return $id ? $this->productById((int) $id) : null;
    }

    private function detail($p): array
    {
        $id = (int) $p->ID;
        $base = $this->hydrateList([$id])[0] ?? [];
        // gallery, at full size through the local WebP cache (the list thumb above is only 320px)
        $lv = self::live($id);
        $gallery = [];
        $main = ($lv['image'] ?? null) ?: $this->productThumb($id);
        if ($main) $gallery[] = $main;
        if (!empty($lv['gallery'])) { foreach ($lv['gallery'] as $u) $gallery[] = $u; }
        $galIds = $this->metaVal($id, '_product_image_gallery');
        if ($galIds) {
            foreach (array_filter(array_map('trim', explode(',', $galIds))) as $gid) {
                if ($u = $this->attachmentUrl((int) $gid)) $gallery[] = $u;
            }
        }
        $gallery = array_map(fn ($u) => media_url($u, 1200), $gallery);
        if ($gallery) $base['image'] = $gallery[0];
        // full spec attributes (all pa_ except the display ones we already have)
        $specs = $this->allSpecs($id);
        return array_merge($base, [
            'description' => trim(strip_tags((string) $p->post_content)) ?: trim(strip_tags((string) $p->post_excerpt)),
            'gallery' => array_values(array_unique($gallery)),
            'specs' => $specs,
        ]);
    }

    /** All attribute values as label=>value for the spec table. */
    private function allSpecs(int $id): array
    {
        $rows = $this->db()->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $id)->where('tt.taxonomy', 'like', 'pa_%')
            ->select('tt.taxonomy', 't.name')->get();
        $skip = ['pa_partn-number', 'pa_notes'];
        $out = [];
        foreach ($rows as $r) {
            if (in_array($r->taxonomy, $skip, true)) continue;
            $label = ucwords(str_replace(['pa_', '-'], ['', ' '], $r->taxonomy));
            $out[$label] = isset($out[$label]) ? $out[$label] . ', ' . $r->name : $r->name;
        }
        return $out;
    }

    /* ---------- on-page SEO helpers (product titles, copy, cross-reference, related) ---------- */

    /** Singular part-type name for a category slug (config/seo-copy.php), or '' when unknown. */
    public function typeName(?string $catSlug): string
    {
        return $catSlug ? (string) (config("seo-copy.categories.$catSlug.type") ?? '') : '';
    }

    /**
     * Clean display name: drops the SKU / brand prefix the import repeats, turns en-dash separators
     * into commas and cuts the uppercase note dumps ("HEAVY DUTY COMES WITH 2 SNAP RINGS...").
     */
    /** Tube wall is stored as either 0.083 or 83 (thousandths of an inch); show inches. */
    public function wallInches(string $v): string
    {
        $n = (float) preg_replace('/[^\d.]/', '', $v);
        if ($n <= 0) return $v;
        if ($n >= 1) $n /= 1000;
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    /** cleanName() in English, for text that gets stored (cart lines, orders): the display version is translated. */
    public function cleanNameStored(array $p): string
    {
        $loc = app()->getLocale();
        if ($loc === 'en') return $this->cleanName($p);
        app()->setLocale('en');
        try { return $this->cleanName($p); } finally { app()->setLocale($loc); }
    }

    public function cleanName(array $p): string
    {
        $name = trim((string) $p['name']);
        $sku = (string) $p['sku'];
        $skuVariants = array_filter(array_unique([$sku, str_ends_with($sku, 'U') ? substr($sku, 0, -1) : $sku . 'U', str_ends_with($sku, '-01') ? substr($sku, 0, -3) : $sku]));
        foreach ($skuVariants as $v) {
            if ($v === '') continue;
            $name = preg_replace('/(^|[\s,(])' . preg_quote($v, '/') . '(?=$|[\s,)\x{2013}\x{2014}|:-])/u', '$1', $name);
        }
        // brand / supplier words that lead the title
        $name = preg_replace('/^[\s\x{2013}\x{2014}|:-]*((UDS|Spicer|OEM\s+SPICER|Neapco|Dana)\b[\s\x{2013}\x{2014}|:-]*)+/iu', '', $name);
        // "SOLD BY THE FOOT" and other shouted trailers
        $name = preg_replace('/\s*[-\x{2013}\x{2014},]*\s*SOLD BY (THE )?FOOT.*$/iu', '', $name);
        if (preg_match('/\s((?:[A-Z][A-Z0-9]{2,}[\s,]+){2,}[A-Z0-9 ,]*)$/u', $name, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 8) {
            $name = substr($name, 0, $m[0][1]);
        }
        $name = preg_replace('/\s*[\x{2013}\x{2014}|]\s*/u', ', ', $name);      // en/em dashes used as separators
        $name = preg_replace('/\s+,/', ',', $name);
        $name = preg_replace('/(\d)([A-Z][a-z]{2,})/', '$1 $2', $name);          // "0.441Holes"
        $name = preg_replace('/\s{2,}/', ' ', $name);
        $name = trim(preg_replace('/^[\s,\-\x{2013}\x{2014}]+|[\s,\-\x{2013}\x{2014}]+$/u', '', $name));
        $name = preg_replace('/\bWeigths\b/', 'Weights', $name);
        // a bare type ("Universal Joint" on the 106 "UDS Spicer - 5-153X Universal Joint" titles) tells a buyer nothing:
        // add the series, style and lube from the part's own data, the way the other u-joints are named
        if ($name !== '' && count(preg_split('/\s+/', $name)) <= 3 && !preg_match('/\d/', $name)) {
            if (!preg_match('/[a-z]/', $name)) $name = ucwords(strtolower($name));   // "ALUMINUM WELD YOKE"
            $series = trim((string) ($p['series'] ?? ''));
            if (preg_match('/^[A-Z]+$/', $series)) $series = ucfirst(strtolower($series));   // "TOYOTA", "KIA"
            $style = trim((string) ($p['style'] ?? ''));
            if (preg_match('/^[A-Z]{4,}$/', $style)) $style = ucfirst(strtolower($style));    // "COMBO"; OSR, ISR, WB, HR stay
            $lube = strtoupper(trim((string) ($p['specs']['Lube'] ?? '')));
            $extra = array_filter([$style, str_starts_with($lube, 'Y') ? 'Greaseable' : (str_starts_with($lube, 'N') ? 'Sealed' : '')]);
            if ($series !== '' && stripos($name, $series) === false) $name .= ' ' . $series . ' Series';
            if ($extra) $name .= ', ' . implode(', ', $extra);
        }
        // display text: Spanish visitors get the driveline vocabulary in Spanish (never stored)
        return \App\Support\PartGlossary::t($name !== '' ? $name : (string) $p['name']);
    }

    /** Title (≤60), meta description (140–160) and the short descriptive name used in both. */
    public function productSeo(array $p, ?string $catSlug): array
    {
        $sku = (string) $p['sku'];
        $type = $this->typeName($catSlug);
        $specs = array_map(fn ($v) => html_entity_decode((string) $v), $p['specs'] ?? []);
        $spec = fn (string ...$labels) => (function () use ($specs, $labels) { foreach ($labels as $l) if (!empty($specs[$l])) return trim((string) $specs[$l]); return ''; })();
        $series = trim((string) ($p['series'] ?? ''));
        $brand = trim((string) ($p['brand'] ?? ''));
        $in = fn (string $v) => $v === '' ? '' : (str_contains($v, '"') ? $v : $v . '"');

        // qualifier: the one dimension a buyer searches with for this part type
        $qual = '';
        $lube = strtoupper($spec('Lube'));
        $greaseable = null;
        if ($lube !== '') $greaseable = str_starts_with($lube, 'Y') || str_contains($lube, 'CAP') ? true : (str_starts_with($lube, 'N') ? false : null);
        if ($greaseable === null) { $n = strtolower($p['name']); if (str_contains($n, 'non-greas') || str_contains($n, 'sealed') || str_contains($n, 'lube for life')) $greaseable = false; elseif (str_contains($n, 'greas')) $greaseable = true; }
        switch ($catSlug) {
            case 'u-joints':
                $qual = $greaseable === true ? 'Greaseable' : ($greaseable === false ? 'Sealed' : ''); break;
            case 'flange-yokes': case 'cv-flange-yokes': case 'companion-flanges': case 'rubber-flex-disc':
                $h = $spec('Bolt Holes', 'Bolt Hole Count'); $bc = $spec('Bolt Circle Dia');
                $qual = trim(($h !== '' && is_numeric($h) ? $h . '-Bolt' : '') . ($bc !== '' ? ' ' . $in($bc) . ' BC' : '')); break;
            case 'weld-yoke':
                $t = $spec('Tube Dia Wall', 'Tube Dia'); $qual = $t !== '' ? $t . ' Tube' : ''; break;
            case 'slip-yoke': case 'transmission-yokes': case 'end-yokes': case 'yoke-shafts': case 'splined-sleeves': case 'stub-midship-shaft':
                $sc = $spec('Splines Count'); $sd = $spec('Splines Dia'); $scd = $spec('Splines Count Dia');
                $qual = $scd !== '' ? $scd . ' Spline' : trim(($sc !== '' ? $sc . ' Spline' : '') . ($sd !== '' ? ' ' . $in($sd) : '')); break;
            case 'center-support-bearing':
                // buyers search a center bearing by vehicle ("hyundai genesis center bearing"); the bore when there is no short application
                $app = ucwords(strtolower(trim(preg_replace('/\s*,\s*$/', '', $spec('Application', 'Vehicle')))));
                $b = $spec('Bearing Id', 'Bearing Dia');
                $qual = $app !== '' && mb_strlen($app) <= 24 ? $app : ($b !== '' ? $in($b) . ' ID' : ''); break;
            case 'tubing':
                $od = $spec('Tube Dia'); $w = $spec('Tube Wall'); $mat = ucfirst(strtolower($spec('Material') ?: 'steel'));
                $type = trim(($od !== '' ? $in($od) . ' × ' : '') . ($w !== '' ? $in($w) . ' Wall ' : '') . 'DOM ' . $mat . ' Tubing'); break;
            case 'premade-driveshafts': case 'short-couple-driveshafts': case 'unwelded-assemblies':
                $qual = trim($spec('Vehicle') . ' ' . $spec('Model')); break;
            default:
                foreach ($specs as $l => $v) { if (!in_array($l, ['Series', 'Brand', 'Style', 'Notes', 'Material'], true) && trim((string) $v) !== '') { $qual = $l . ' ' . trim((string) $v); break; } }
        }
        $short = trim(($series !== '' && $catSlug !== 'tubing' ? $series . ' Series ' : '') . ($type !== '' ? $type : ''));
        if ($short === '') $short = rtrim(\Illuminate\Support\Str::limit($this->cleanName($p), 40, '', true), ' ,-');

        $title = $sku . ' ' . $short . ($qual !== '' ? ', ' . $qual : '') . ' | UDS';
        if (mb_strlen($title) > 60) $title = $sku . ' ' . $short . ' | UDS';
        if (mb_strlen($title) > 60) $title = rtrim(\Illuminate\Support\Str::limit($sku . ' ' . $short, 54, '', true), ' ,-') . ' | UDS';

        // description: name + series + brand + key specs + stock line, 140–160 chars
        $lead = $sku . ' ' . ($type !== '' ? $this->lowerType($type) : 'driveshaft part');
        if ($catSlug === 'tubing') $lead = $sku . ' ' . $type;
        if ($series !== '' && $catSlug !== 'tubing') $lead .= ', ' . $series . ' series';
        if ($brand !== '') $lead .= ' by ' . $brand;
        $lead .= '.';
        $prefer = ['Cap Dia 1', 'Width 1', 'Lube', 'Bolt Holes', 'Bolt Circle Dia', 'Bolt Hole Dia', 'Pilot Dia', 'Splines Count', 'Splines Dia', 'Splines Count Dia', 'Tube Dia Wall', 'Butt Dia', 'Bearing Id', 'Ctr To Ctr Of Mount Holes', 'Center Bearing To Base', 'Tube Dia', 'Tube Wall', 'Material', 'Hub Dia', 'Center U Joint To End', 'Style'];
        $pairs = [];
        foreach ($prefer as $l) if (!empty($specs[$l]) && count($pairs) < 4) $pairs[] = $this->specPhrase($l, (string) $specs[$l]);
        foreach ($specs as $l => $v) if (count($pairs) < 3 && !in_array($l, array_merge($prefer, ['Series', 'Brand']), true) && $v !== '') $pairs[] = $this->specPhrase((string) $l, (string) $v);
        if ($catSlug === 'u-joints' && $greaseable !== null && !in_array('Lube', array_keys($specs))) $pairs[] = $greaseable ? 'greaseable' : 'sealed, lube for life';
        $st = $p['stock_status'] ?? 'instock';
        $stockLine = $st === 'outofstock' ? 'Call for availability; ships from Florida.' : ($st === 'onbackorder' ? 'Ships from Florida; call for lead time.'
            : (($p['stock_source'] ?? null) === 'supplier' ? 'Available from our supplier; call for a ship date.' : 'In stock, ships same day from Florida.'));
        // returns: 14 days with an RMA (return-policy page); the prepaid label is only for our own errors
        $tails = ['Free shipping on parts orders over $' . number_format((float) config('uds.free_shipping_min', 150)) . '.', 'Fitment help: (813) 524-6491.', '14-day returns with an RMA.'];
        $build = function (int $n) use ($lead, $pairs, $stockLine) {
            $s = $lead; $use = array_slice($pairs, 0, $n);
            if ($use) $s .= ' ' . ucfirst(implode(', ', $use)) . '.';
            return $s . ' ' . $stockLine;
        };
        $n = count($pairs); $desc = $build($n);
        while (mb_strlen($desc) > 160 && $n > 0) $desc = $build(--$n);
        foreach ($tails as $t) if (mb_strlen($desc) < 140 && mb_strlen($desc . ' ' . $t) <= 160) $desc .= ' ' . $t;
        if (mb_strlen($desc) > 160) $desc = rtrim(mb_substr($desc, 0, 157), " ,.;") . '...';

        return ['type' => $type, 'short' => $short, 'qual' => $qual, 'title' => $title, 'desc' => $desc, 'greaseable' => $greaseable];
    }

    /** "Flange Yoke" -> "flange yoke", keeping the acronyms (U-Joint, CV, PTO, DOM, H Yoke). */
    public function lowerType(string $type): string
    {
        $t = mb_strtolower($type);
        return trim(preg_replace_callback('/\b(u-joint|cv|pto|dom|h)\b/', fn ($m) => $m[1] === 'u-joint' ? 'u-joint' : strtoupper($m[1]), $t));
    }

    private function specPhrase(string $label, string $val): string
    {
        $label = str_replace([' Dia 1', ' 1', 'Ctr To Ctr Of Mount Holes', 'Center U Joint To End', 'Splines Count Dia', 'Bearing Id'], [' dia', '', 'mount holes', 'joint center to end', 'splines', 'bearing ID'], $label);
        $label = str_replace([' id', ' od', ' cl ', 'u j '], [' ID', ' OD', ' CL ', 'U-J '], ' ' . mb_strtolower($label) . ' ');
        $label = trim($label);
        if (preg_match('/^[A-Z]{5,}$/', $val)) $val = ucfirst(strtolower($val));
        if ($label === 'lube') return str_starts_with(strtoupper($val), 'Y') ? 'greaseable' : (str_starts_with(strtoupper($val), 'N') ? 'sealed (lube for life)' : 'lube ' . $val);
        if ($label === 'bolt holes') return $val . ' bolt holes';
        if ($label === 'pilot dia') return 'pilot ' . $val;
        return $label . ' ' . $val;
    }

    /** Product long description (WooCommerce post_content) as sanitized HTML: structural tags only, headings demoted. */
    public function descriptionHtml(int $id): string
    {
        $html = (string) $this->db()->table('wp_posts')->where('ID', $id)->value('post_content');
        if (trim($html) === '') return '';
        $html = preg_replace('/<(script|style|iframe|form)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = strip_tags($html, '<p><h2><h3><h4><ul><ol><li><strong><b><em><i><br><table><thead><tbody><tr><th><td>');
        $html = preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $html);                       // drop every attribute (inline styles, ids…)
        $html = preg_replace(['/<(\/?)h2>/', '/<(\/?)h3>/'], ['<$1h3>', '<$1h4>'], $html);
        $html = preg_replace('/<p>\s*(&nbsp;|\s)*<\/p>/', '', $html);
        return trim($html);
    }

    /** OEM / competitor cross-reference numbers (pa_partn-number) and applications (pa_application) for a product. */
    public function interchange(int $id, string $sku): array
    {
        return $this->remember("xref:$id", 3600, function () use ($id, $sku) {
            $rows = $this->db()->table('wp_term_relationships as tr')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tr.object_id', $id)->whereIn('tt.taxonomy', ['pa_partn-number', 'pa_application'])
                ->select('tt.taxonomy', 't.name')->distinct()->get();
            $own = array_map('strtoupper', array_filter([$sku, str_ends_with($sku, 'U') ? substr($sku, 0, -1) : $sku . 'U']));
            $xref = []; $apps = [];
            foreach ($rows as $r) {
                $v = trim(html_entity_decode($r->name));
                if ($v === '' || strtoupper($v) === 'N/A') continue;
                if ($r->taxonomy === 'pa_application') { $apps[] = $v; continue; }
                foreach (preg_split('/\s*[,;]\s*/', $v) as $num) {   // "A, B; C"
                    $num = trim($num);
                    if (mb_strlen($num) < 3 || in_array(strtoupper($num), $own, true)) continue;
                    $xref[$num] = true;
                }
            }
            // and the Spicer / Neapco / OEM numbers PTI lists for this same part (App\Support\PtiXref)
            $seen = array_map(fn ($n) => self::normKey($n), array_keys($xref));
            foreach (\App\Support\PtiXref::forProduct($id) as $num) {
                if (in_array(strtoupper($num), $own, true) || in_array(self::normKey($num), $seen, true)) continue;
                $xref[$num] = true; $seen[] = self::normKey($num);
            }
            $xref = array_keys($xref);
            // which cross numbers are themselves in the catalog -> link them
            $links = [];
            if ($xref) {
                $links = $this->db()->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
                    ->where('m.meta_key', '_sku')->whereIn('m.meta_value', $xref)
                    ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('p.ID', '!=', $id)
                    ->distinct()->pluck('m.meta_value')->all();
                $links = array_flip($links);
            }
            return [
                'xref' => array_map(fn ($n) => ['num' => $n, 'sku' => isset($links[$n]) ? $n : null], $xref),
                'application' => array_values(array_unique($apps)),
            ];
        });
    }

    /* ---------- cross-reference / interchange index ---------- */

    /** Comparison key for part numbers: uppercase, no spaces/dashes/dots/slashes ("5-153X" == "5153X" == "5 153 x"). */
    public static function normKey(string $s): string
    {
        return strtoupper((string) preg_replace('~[\s\-./]+~u', '', trim($s)));
    }

    /**
     * Every number a product can be found by, normalised (1 h cache, bumped with catalog_ver):
     *   ['num' => [normKey => [['id' => productId, 'raw' => 'as stored', 'own' => bool], ...]], 'sku' => [productId => sku]]
     * 'own' is true for the product's own SKU (and a pa_partn-number that merely repeats it); false for a genuine
     * competitor/OEM interchange number from pa_partn-number. Values may hold several numbers ("A, B; C").
     */
    public function xrefIndex(): array
    {
        return $this->remember('xref:index', 3600, function () {
            $num = []; $skus = [];
            $add = function (string $raw, int $id, bool $own) use (&$num) {
                $k = self::normKey($raw);
                if ($k === '' || mb_strlen($k) < 2) return;
                foreach ($num[$k] ?? [] as &$e) {           // same product already there -> keep the strongest flag
                    if ($e['id'] === $id) { $e['own'] = $e['own'] || $own; return; }
                }
                unset($e);
                $num[$k][] = ['id' => $id, 'raw' => $raw, 'own' => $own];
            };
            $rows = $this->db()->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
                ->where('m.meta_key', '_sku')->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->where('m.meta_value', '!=', '')->select('p.ID', 'm.meta_value')->distinct()->get();
            foreach ($rows as $r) { $skus[(int) $r->ID] = trim($r->meta_value); $add(trim($r->meta_value), (int) $r->ID, true); }

            $rows = $this->db()->table('wp_term_relationships as tr')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->join('wp_posts as p', 'p.ID', '=', 'tr.object_id')
                ->where('tt.taxonomy', 'pa_partn-number')->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->select('tr.object_id', 't.name')->distinct()->get();
            foreach ($rows as $r) {
                $v = trim(html_entity_decode($r->name));
                if ($v === '' || strtoupper($v) === 'N/A') continue;
                $id = (int) $r->object_id;
                $ownKey = self::normKey($skus[$id] ?? '');
                foreach (preg_split('/\s*[,;]\s*|\s+\/\s+/', $v) as $n) {   // "A, B; C", "A / B"
                    $n = trim($n);
                    if (mb_strlen($n) < 3) continue;
                    $add($n, $id, $ownKey !== '' && self::normKey($n) === $ownKey);
                }
            }
            // interchange numbers PTI lists for the same part (published products only)
            foreach (\App\Support\PtiXref::map() as $id => $nums) {
                if (!isset($skus[$id])) continue;
                $ownKey = self::normKey($skus[$id]);
                foreach ($nums as $n) $add($n, $id, $ownKey !== '' && self::normKey($n) === $ownKey);
            }
            return ['num' => $num, 'sku' => $skus];
        });
    }

    /**
     * Products whose SKU or interchange number matches $q: exact normalised matches first, then (query >= 4 chars)
     * prefix and contains matches. One row per product: ['id', 'sku', 'num' => matched raw number, 'own', 'exact'].
     */
    public function xrefMatches(string $q, int $limit = 24): array
    {
        $k = self::normKey($q);
        if ($k === '' || mb_strlen($k) < 2) return [];
        $idx = $this->xrefIndex();
        $out = [];
        $take = function (string $key, bool $exact) use (&$out, $idx, $limit) {
            foreach ($idx['num'][$key] ?? [] as $e) {
                if (count($out) >= $limit) return;
                $id = $e['id'];
                if (isset($out[$id])) continue;                       // first (strongest) match for a product wins
                $out[$id] = ['id' => $id, 'sku' => $idx['sku'][$id] ?? '', 'num' => $e['raw'], 'own' => $e['own'], 'exact' => $exact];
            }
        };
        $take($k, true);
        if (count($out) < $limit && mb_strlen($k) >= 4) {
            $keys = array_keys($idx['num']);
            $pre = array_filter($keys, fn ($x) => $x !== $k && str_starts_with($x, $k));
            sort($pre, SORT_STRING);
            foreach ($pre as $x) { if (count($out) >= $limit) break; $take($x, false); }
            if (count($out) < $limit) {
                $in = array_filter($keys, fn ($x) => $x !== $k && !str_starts_with($x, $k) && str_contains($x, $k));
                usort($in, fn ($a, $b) => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
                foreach ($in as $x) { if (count($out) >= $limit) break; $take($x, false); }
            }
        }
        return array_values($out);
    }

    /** Display rows for product IDs (order preserved) — public wrapper around the listing hydrator. */
    public function rows(array $ids, array $attrKeys = []): array
    {
        return $this->hydrateList(array_values(array_unique(array_map('intval', $ids))), $attrKeys);
    }

    /** Most specific product_cat for a product (lowest term count), as ['slug','name'] or null. */
    public function primaryCategory(int $id): ?array
    {
        $row = $this->db()->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tr.object_id', $id)->where('tt.taxonomy', 'product_cat')
            ->orderBy('tt.count')->get(['t.slug', 't.name']);
        // the most specific category (fewest products) that has a public /shop URL; "Small Parts"-style catch-alls
        // and hidden categories lose to "U-Joints". No public one → the biggest, as before (breadcrumb falls back).
        $public = $this->allCategorySlugs();
        $row = $row->first(fn ($r) => isset($public[$r->slug])) ?? $row->last();
        return $row ? ['slug' => $row->slug, 'name' => html_entity_decode($row->name)] : null;
    }

    /** Spec-sheet details (most copied from PTI, 2026-11-11): shown on the product page, never a table column, never part of
     *  the fitment signature, so filling them on some parts neither reshapes a category table nor splits identical parts. */
    public const DETAIL_ATTRS = ['pa_class', 'pa_vented', 'pa_cl-to-washer', 'pa_counter-bore-depth', 'pa_hub-length', 'pa_joint-angle', 'pa_hole-to-hole',
        'pa_dust-cap', 'pa_midship', 'pa_end-to-radius', 'pa_movement', 'pa_included-parts', 'pa_end-cap', 'pa_set-screw', 'pa_flinger', 'pa_u-bolt-no',
        'pa_lock-washer-no', 'pa_nut-no', 'pa_bolt-no', 'pa_strap-no', 'pa_oem-manufacturer', 'pa_oem-series', 'pa_differential'];

    /** Attributes that describe a part but not its fitment: two parts may differ in these and still interchange. */
    public const NON_FITMENT_ATTRS = ['pa_brand', 'pa_notes', 'pa_partn-number', 'pa_application', 'pa_weight', 'pa_lube', 'pa_material',
        'pa_class', 'pa_grade', 'pa_bolt-torque', 'pa_gasket', 'pa_dust-cover', 'pa_self-locking', 'pa_use-with', 'pa_year-compatibility',
        'pa_d-year', 'pa_model', 'pa_transmission', 'pa_vehicle', 'pa_type-sp', 'pa_etp', 'pa_boot-cap-part'];

    /** Alternative parts: other products in the same category whose every measurement attribute is identical
     *  (series, cap/pilot/bolt-circle/spline/tube dimensions, style…). Brand, notes, lube or supplier number may differ. */
    public function sameFitment(int $id, int $limit = 12): array
    {
        $cat = $this->primaryCategory($id);
        $term = $cat ? $this->term($cat['slug']) : null;
        if (!$term) return [];
        $sigs = $this->remember('fitsig:' . $cat['slug'], 1800, function () use ($term) {
            $rows = $this->db()->table('wp_term_relationships as pr')
                ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'pr.object_id')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_posts as p', 'p.ID', '=', 'pr.object_id')
                ->where('pr.term_taxonomy_id', $term->term_taxonomy_id)->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->where('tt.taxonomy', 'like', 'pa\_%')->whereNotIn('tt.taxonomy', array_merge(self::NON_FITMENT_ATTRS, self::DETAIL_ATTRS))
                ->distinct()->get(['pr.object_id as pid', 'tt.taxonomy as tax', 'tt.term_id as tid']);
            $sig = [];
            foreach ($rows as $r) $sig[(int) $r->pid][] = $r->tax . '=' . $r->tid;
            $out = ['bypid' => [], 'groups' => []];
            foreach ($sig as $pid => $a) {
                if (count($a) < 2) continue;                       // one lone attribute is not a fitment
                sort($a); $k = md5(implode('|', $a));
                $out['bypid'][$pid] = $k; $out['groups'][$k][] = $pid;
            }
            return $out;
        });
        $k = $sigs['bypid'][$id] ?? null;
        if (!$k) return [];
        $ids = array_values(array_filter($sigs['groups'][$k], fn ($p) => $p !== $id));
        if (!$ids) return [];
        return $this->hydrateList(array_slice($ids, 0, $limit), ['pa_brand', 'pa_notes', 'pa_lube', 'pa_series']);
    }

    /** Rows for the product page's "Alternative parts" table in the category-table format: exact-fitment
     *  matches plus the same number under another brand, hydrated with the category's own attribute columns. */
    public function alternativeRows(int $id, array $attrKeys): array
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $this->sameFitment($id, 24));
        foreach ($this->alternates($id) as $a) $ids[] = (int) $a['id'];
        $ids = array_values(array_unique(array_filter($ids, fn ($i) => $i && $i !== $id)));
        return $ids ? $this->hydrateList($ids, $attrKeys) : [];
    }

    /** Related parts: same category and series first, then same category. Cached list rows. */
    public function related(int $id, string $series, ?string $catSlug, int $limit = 8): array
    {
        if (!$catSlug) return [];
        return $this->remember("related:$id:$limit", 1800, function () use ($id, $series, $catSlug, $limit) {
            $term = $this->term($catSlug);
            if (!$term) return [];
            $pick = function (?string $seriesName) use ($term, $id, $limit) {
                $q = $this->db()->table('wp_posts as p')
                    ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'p.ID')
                    ->where('tr.term_taxonomy_id', $term->term_taxonomy_id)
                    ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('p.ID', '!=', $id);
                if ($seriesName !== null) {
                    $ttid = $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                        ->where('tt.taxonomy', 'pa_series')->where('t.name', $seriesName)->value('tt.term_taxonomy_id');
                    if (!$ttid) return [];
                    $q->join('wp_term_relationships as sr', fn ($j) => $j->on('sr.object_id', '=', 'p.ID')->where('sr.term_taxonomy_id', '=', $ttid));
                }
                return $q->distinct()->orderBy('p.ID')->limit($limit)->pluck('p.ID')->all();
            };
            $ids = $series !== '' ? $pick($series) : [];
            if (count($ids) < $limit) {
                foreach ($pick(null) as $x) if (!in_array($x, $ids, true) && count($ids) < $limit) $ids[] = $x;
            }
            return $this->hydrateList($ids, ['pa_series']);
        });
    }

    /** Distinct pa_series values inside a category: slug => ['name','count'], most common first. */
    public function categorySeries(string $catSlug): array
    {
        return $this->remember("catseries:$catSlug", 3600, function () use ($catSlug) {
            $term = $this->term($catSlug);
            if (!$term) return [];
            $rows = $this->db()->table('wp_term_relationships as ptr')
                ->join('wp_posts as p', 'p.ID', '=', 'ptr.object_id')
                ->join('wp_term_relationships as atr', 'atr.object_id', '=', 'p.ID')
                ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'atr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('ptr.term_taxonomy_id', $term->term_taxonomy_id)
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->where('tt.taxonomy', 'pa_series')
                ->groupBy('t.slug', 't.name')->select('t.slug', 't.name', DB::raw('COUNT(DISTINCT p.ID) as n'))
                ->orderByDesc('n')->get();
            $out = [];
            foreach ($rows as $r) $out[$r->slug] = ['name' => html_entity_decode($r->name), 'count' => (int) $r->n];
            return $out;
        });
    }

    /**
     * One real image per series, found among the products of this category only. Used to fill in products
     * whose own picture is missing or the placeholder: a part shares its series' look, so a sibling of the
     * same series in the same category is a safe stand-in (owner request 2026-10-26). First real image wins.
     *
     * @return array<string,string> series name => rendered image URL
     */
    public function categoryImageBySeries(string $catSlug): array
    {
        return $this->remember("catimg:$catSlug", 600, function () use ($catSlug) {
            $term = $this->term($catSlug);
            if (!$term) return [];
            $ids = $this->db()->table('wp_posts as p')
                ->join('wp_term_relationships as tr', 'tr.object_id', '=', 'p.ID')
                ->where('tr.term_taxonomy_id', $term->term_taxonomy_id)
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->distinct()->pluck('p.ID')->all();
            $map = [];
            foreach ($this->hydrateList($ids, ['pa_series']) as $it) {
                $s = trim((string) ($it['series'] ?? ''));
                if ($s === '' || isset($map[$s]) || self::imageMissing($it['image'])) continue;
                $map[$s] = $it['image'];
            }
            return $map;
        });
    }

    /* ---------- series hubs (/series/{series}) ---------- */

    /** U-joint series that get a hub page: real series designations with 2+ products, most stocked first. */
    public function seriesList(): array
    {
        return $this->remember('series:list', 3600, function () {
            $rows = $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'pa_series')->where('tt.count', '>=', 2)
                ->orderByDesc('tt.count')->get(['t.name', 't.slug', 'tt.count']);
            $out = [];
            foreach ($rows as $r) {
                $name = trim(html_entity_decode($r->name));
                if (!preg_match('/^(\d{3,4}[A-Z]{0,2}|\d{4}-\d{2}|SPL\s?\d{2,3}|\dR)$/i', $name)) continue;
                $key = strtoupper(str_replace(' ', '', $name));
                $info = config("seo-copy.series.$key") ?? [];
                $out[$r->slug] = ['slug' => $r->slug, 'name' => $name, 'count' => (int) $r->count,
                    'cap' => $info['cap'] ?? null, 'width' => $info['width'] ?? null, 'desc' => $info['desc'] ?? ''];
            }
            return $out;
        });
    }

    public function seriesTerm(string $slug): ?array
    {
        return $this->seriesList()[$slug] ?? null;
    }

    /**
     * Parts of a series grouped by category: [[cat => [slug,name,path], total, items(≤$perCat)], …],
     * biggest categories first. Categories outside the shop groups (no public URL) are skipped.
     */
    public function seriesByCategory(string $seriesSlug, int $perCat = 24): array
    {
        return $this->remember("series:cats:$seriesSlug:$perCat", 1800, function () use ($seriesSlug, $perCat) {
            $ttid = $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tt.taxonomy', 'pa_series')->where('t.slug', $seriesSlug)->value('tt.term_taxonomy_id');
            if (!$ttid) return [];
            $priceSub = $this->db()->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value+0) as pv'))->where('meta_key', '_price')->groupBy('post_id');
            $rows = $this->db()->table('wp_term_relationships as sr')
                ->join('wp_posts as p', 'p.ID', '=', 'sr.object_id')
                ->join('wp_term_relationships as cr', 'cr.object_id', '=', 'p.ID')
                ->join('wp_term_taxonomy as ct', 'ct.term_taxonomy_id', '=', 'cr.term_taxonomy_id')
                ->join('wp_terms as c', 'c.term_id', '=', 'ct.term_id')
                ->leftJoinSub($priceSub, 'pr', 'pr.post_id', '=', 'p.ID')
                ->where('sr.term_taxonomy_id', $ttid)->where('ct.taxonomy', 'product_cat')
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->select('c.slug', 'c.name', 'p.ID', 'pr.pv')->distinct()->orderBy('pr.pv')->get();
            $cats = [];
            foreach ($rows as $r) {
                $cats[$r->slug]['name'] = html_entity_decode($r->name);
                $cats[$r->slug]['ids'][$r->ID] = true;
            }
            uasort($cats, fn ($a, $b) => count($b['ids']) <=> count($a['ids']));
            $out = [];
            foreach ($cats as $slug => $c) {
                $g = $this->groupOf($slug);
                if (!$g) continue;
                $ids = array_slice(array_keys($c['ids']), 0, $perCat);
                $out[] = [
                    'cat' => ['slug' => $slug, 'name' => $c['name'], 'path' => '/shop/' . $g['slug'] . '/' . $slug, 'group' => $g['label']],
                    'total' => count($c['ids']),
                    'items' => $this->hydrateList($ids, ['pa_series']),
                ];
            }
            return $out;
        });
    }

    /** Data-driven FAQ for a product page: only questions whose answer comes from real attribute data. */
    public function productFaq(array $p, array $seo, array $xref, ?string $catSlug): array
    {
        $sku = $p['sku']; $specs = $p['specs'] ?? [];
        $spec = fn (string ...$labels) => (function () use ($specs, $labels) { foreach ($labels as $l) if (!empty($specs[$l])) return trim((string) $specs[$l]); return ''; })();
        $in = fn (string $v) => $v === '' ? '' : (str_contains($v, '"') ? $v : $v . '"');
        $type = $seo['type'] !== '' ? \App\Support\PartGlossary::t($this->lowerType($seo['type'])) : __('part');
        $faq = [];
        $series = trim((string) ($p['series'] ?? ''));
        if ($series !== '' && $catSlug !== 'tubing') {
            $a = __(':sku is a :series series :type.', ['sku' => $sku, 'series' => $series, 'type' => $type]);
            $info = config('seo-copy.series.' . strtoupper(str_replace(' ', '', $series)));
            if (!empty($info['cap'])) $a .= __(' Nominal :series dimensions are :cap bearing caps', ['series' => $series, 'cap' => $info['cap']]) . (!empty($info['width']) ? __(' on a :width snap-ring-to-snap-ring width', ['width' => $info['width']]) : '') . __('. Measure the cap diameter on your old joint to confirm the series before ordering.');
            $faq[] = [__('What series is :sku?', ['sku' => $sku]), $a];
        }
        if ($catSlug === 'u-joints' && $seo['greaseable'] !== null) {
            $faq[] = [__('Is :sku greaseable?', ['sku' => $sku]), $seo['greaseable']
                ? __('Yes. :sku is a greaseable universal joint with a lube fitting; grease it at each chassis service.', ['sku' => $sku])
                : __('No. :sku is a sealed, lube-for-life universal joint. It has no grease fitting and needs no field lubrication; replace it when any cap shows radial play.', ['sku' => $sku])];
        }
        $holes = $spec('Bolt Holes', 'Bolt Hole Count'); $bc = $spec('Bolt Circle Dia');
        if ($holes !== '' && $bc !== '' && is_numeric($holes)) {
            $a = __(':sku has :holes bolt holes on a :bc bolt circle', ['sku' => $sku, 'holes' => $holes, 'bc' => $in($bc)]);
            if ($hd = $spec('Bolt Hole Dia')) $a .= __(', :hd hole diameter', ['hd' => $in($hd)]);
            if ($pd = $spec('Pilot Dia')) $a .= __(', :pd pilot', ['pd' => $pd]);
            $faq[] = [__('What bolt pattern does :sku have?', ['sku' => $sku]), $a . __('. Match all three to the companion flange before ordering.')];
        }
        $sc = $spec('Splines Count'); $sd = $spec('Splines Dia'); $scd = $spec('Splines Count Dia');
        if ($scd !== '' || ($sc !== '' && $sd !== '')) {
            $a = $scd !== '' ? __(':sku is cut for :scd splines (count × major diameter).', ['sku' => $sku, 'scd' => $scd]) : __(':sku has :sc splines on a :sd spline diameter.', ['sku' => $sku, 'sc' => $sc, 'sd' => $in($sd)]);
            $faq[] = [__('What spline count does :sku have?', ['sku' => $sku]), $a . __(' Count the splines on the output shaft and measure across the major diameter to confirm.')];
        }
        if ($catSlug === 'weld-yoke' && ($t = $spec('Tube Dia Wall'))) {
            $faq[] = [__('What tube size does :sku fit?', ['sku' => $sku]), __(':sku is machined for :t driveshaft tube (outside diameter × wall thickness)', ['sku' => $sku, 't' => $t]) . (($bd = $spec('Butt Dia')) ? __(', with a :bd butt diameter', ['bd' => $in($bd)]) : '') . __('. The tube must match both numbers for a correct weld fit.')];
        }
        if ($catSlug === 'tubing' && ($od = $spec('Tube Dia'))) {
            // Only the 1"-length listings are sold by the foot; a fixed-length tube (ATU483 is
            // 72") is one piece, and telling the buyer to count feet on it is simply wrong.
            $byFoot = app(ProductService::class)->soldByFoot((int) $p['id']);
            $len = $spec('Tube Length');
            $how = $byFoot
                ? __('sold by the foot: the quantity you enter is the number of feet. Tell us the exact cut length in the order notes.')
                : ($len !== '' ? __('supplied as a single :len length.', ['len' => $in($len)]) : __('supplied as a single cut length.'));
            $material = ucfirst(strtolower($spec('Material') ?: 'steel'));
            $faq[] = [__('What size is :sku tubing?', ['sku' => $sku]), __(':sku is :od OD', ['sku' => $sku, 'od' => $in($od)]) . (($w = $spec('Tube Wall')) ? __(' × :w wall', ['w' => $in($this->wallInches($w))]) : '') . ' ' . __(':material tubing, ', ['material' => __($material)]) . $how];
        }
        if ($catSlug === 'center-support-bearing' && ($bid = $spec('Bearing Id'))) {
            $a = __(':sku has a :bid bearing inside diameter', ['sku' => $sku, 'bid' => $in($bid)]);
            if ($cc = $spec('Ctr To Ctr Of Mount Holes')) $a .= __(', mounting holes :cc center to center', ['cc' => $in($cc)]);
            if ($h = $spec('Center Bearing To Base')) $a .= __(', :h from bearing center to base', ['h' => $in($h)]);
            $faq[] = [__('What are the dimensions of :sku?', ['sku' => $sku]), $a . '.'];
        }
        if (!empty($xref['xref'])) {
            $nums = array_slice(array_column($xref['xref'], 'num'), 0, 8);
            $faq[] = [__('What part numbers does :sku replace?', ['sku' => $sku]), __(':sku cross-references to :nums', ['sku' => $sku, 'nums' => implode(', ', $nums)]) . (count($xref['xref']) > 8 ? __(' and others') : '') . __('. Interchange data is for reference; compare the dimensions before substituting.')];
        }
        if (($p['stock_status'] ?? '') === 'instock' && ($p['stock'] ?? 0) > 0) {
            $faq[] = [__('Is :sku in stock?', ['sku' => $sku]), $p['stock'] == 1
                ? __('Yes. :n is in stock in Florida. Orders placed before 2pm EST on a weekday ship the same day.', ['n' => stock_label($p['stock'])])
                : __('Yes. :n are in stock in Florida. Orders placed before 2pm EST on a weekday ship the same day.', ['n' => stock_label($p['stock'])])];
        }
        return array_slice($faq, 0, 4);
    }

    /* ---------- purchase history (commerce: buy again / customers also bought) ---------- */

    /** WooCommerce order statuses that count as a real purchase in the historical wp_wc_* tables. */
    public const WC_PAID = ['wc-completed', 'wc-processing', 'wc-partial-shipped'];

    /** Published products for a list of ids, in the order given, with the list-level fields (image, price, stock…). */
    public function productsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $pub = array_flip($this->db()->table('wp_posts')->whereIn('ID', $ids)
            ->where('post_type', 'product')->where('post_status', 'publish')->pluck('ID')->map(fn ($v) => (int) $v)->all());
        $keep = array_values(array_filter($ids, fn ($id) => isset($pub[$id])));
        return $keep ? $this->hydrateList($keep) : [];
    }

    /**
     * "Customers also bought": products that sit in the same orders as $id (this shop's orders +
     * the WooCommerce history), ranked by how many orders they share. Never includes $id itself.
     */
    public function alsoBought(int $id, int $limit = 8): array
    {
        $map = $this->alsoBoughtMap();
        $ids = array_keys($map[$id] ?? []);
        if (!$ids) return [];
        $rows = array_values(array_filter($this->productsByIds($ids), fn ($r) => $r['id'] !== $id));
        return array_slice($rows, 0, $limit);
    }

    /** What was ordered together with any of these products (a kind of part, say): [product id => orders shared], most first. */
    public function alsoBoughtCounts(array $ids): array
    {
        $map = $this->alsoBoughtMap();
        $sum = [];
        foreach ($ids as $id) foreach ($map[$id] ?? [] as $other => $n) $sum[$other] = ($sum[$other] ?? 0) + $n;
        foreach ($ids as $id) unset($sum[$id]);
        arsort($sum);
        return $sum;
    }

    /** product_id => [co-purchased product_id => shared-order count] (top 12 each), built in one pass over both order sources. Cached 6 h. */
    private function alsoBoughtMap(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('also_bought_map', 21600, function () {
            $orders = [];   // order key => set of product ids
            try {
                $rows = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
                    ->whereNotNull('i.wp_product_id')->where('i.wp_product_id', '>', 0)
                    ->whereNotIn('o.status', ['cancelled', 'failed', 'refunded'])
                    ->get(['i.order_id', 'i.wp_product_id']);
                foreach ($rows as $r) $orders['l' . $r->order_id][(int) $r->wp_product_id] = true;
            } catch (\Throwable $e) {}
            try {
                $rows = $this->db()->table('wp_wc_order_product_lookup as l')
                    ->join('wp_wc_order_stats as s', 's.order_id', '=', 'l.order_id')
                    ->whereIn('s.status', self::WC_PAID)->get(['l.order_id', 'l.product_id']);
                foreach ($rows as $r) if ((int) $r->product_id > 0) $orders['w' . $r->order_id][(int) $r->product_id] = true;
            } catch (\Throwable $e) {}
            $counts = [];
            foreach ($orders as $set) {
                $ids = array_keys($set);
                if (count($ids) < 2) continue;
                foreach ($ids as $a) foreach ($ids as $b) {
                    if ($a === $b) continue;
                    $counts[$a][$b] = ($counts[$a][$b] ?? 0) + 1;
                }
            }
            foreach ($counts as &$c) { arsort($c); $c = array_slice($c, 0, 12, true); }
            unset($c);
            return $counts;
        });
    }
}
