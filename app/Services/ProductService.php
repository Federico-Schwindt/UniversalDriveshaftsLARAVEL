<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** General product lookup against the copied WooCommerce catalog (connection 'wp'). */
class ProductService
{
    private const UPLOADS = 'https://universaldriveshafts.com/wp-content/uploads/';

    /** Autocomplete search by name or SKU. */
    /**
     * Words people use for a kind of part => the category it lives in. "1310 u-joint" has to mean parts in
     * Universal Joints whose name has 1310, not every title that happens to say "U-Joint" (a flange yoke
     * "to replace the U-Joint style", a U-bolt "fits 1310 U-joints"). Longest phrases first.
     */
    private const TYPE_WORDS = [
        'universal joints' => 'u-joints', 'universal joint' => 'u-joints', 'u-joints' => 'u-joints', 'u-joint' => 'u-joints',
        'u joints' => 'u-joints', 'u joint' => 'u-joints', 'ujoints' => 'u-joints', 'ujoint' => 'u-joints', 'crucetas' => 'u-joints', 'cruceta' => 'u-joints',
        'flange yokes' => 'flange-yokes', 'flange yoke' => 'flange-yokes', 'slip yokes' => 'slip-yoke', 'slip yoke' => 'slip-yoke',
        'weld yokes' => 'weld-yoke', 'weld yoke' => 'weld-yoke', 'tube yoke' => 'weld-yoke', 'end yokes' => 'end-yokes', 'end yoke' => 'end-yokes',
        'transmission yoke' => 'transmission-yokes', 'pinion yoke' => 'end-yokes', 'pto yoke' => 'pto-end-yokes',
        'center support bearing' => 'center-support-bearing', 'center bearing' => 'center-support-bearing', 'carrier bearing' => 'center-support-bearing',
        'strap kits' => 'strap-kits', 'strap kit' => 'strap-kits', 'straps' => 'strap-kits', 'u-bolts' => 'u-bolt-kits', 'u-bolt' => 'u-bolt-kits', 'u bolt' => 'u-bolt-kits',
        'flex disc' => 'rubber-flex-disc', 'guibo' => 'rubber-flex-disc', 'stub shaft' => 'stub-midship-shaft', 'midship shaft' => 'stub-midship-shaft',
        'companion flange' => 'companion-flanges', 'cv joint kit' => 'cv-joint-kit', 'cv kit' => 'cv-joint-kit', 'tubing' => 'tubing',
    ];

    public function search(string $q, int $limit = 8): array
    {
        // part numbers are often typed with a slash ("1680/60", "2880/16"): ours use a dash
        $q = trim(preg_replace('#(?<=\d)\s*/\s*(?=\d)#', '-', $q));
        if (strlen($q) < 2) return [];
        // a kind of part in the query becomes a category filter; the rest must be in the name
        $lower = ' ' . mb_strtolower($q) . ' ';
        foreach (self::TYPE_WORDS as $phrase => $cat) {
            if (!preg_match('/(?<![a-z0-9])' . preg_quote($phrase, '/') . '(?![a-z0-9])/', $lower)) continue;
            $rest = trim(preg_replace('/\s+/', ' ', preg_replace('/(?<![a-z0-9])' . preg_quote($phrase, '/') . '(?![a-z0-9])/', ' ', $lower)));
            $rest = trim(preg_replace('/\b(series|for|a|an|the|de|para)\b/', ' ', $rest));
            $hits = $this->searchIn($rest, $cat, $limit);
            if ($hits) return $hits;
            break;
        }
        return $this->dedupe($this->searchTitle($q, $limit * 2), $limit);
    }

    /** Published parts in a category (and its children) whose name has every remaining word. */
    private function searchIn(string $rest, string $catSlug, int $limit): array
    {
        $esc = fn ($v) => str_replace(['%', '_'], ['\\%', '\\_'], $v);
        $cat = DB::connection('wp')->table('wp_terms as w')->join('wp_term_taxonomy as t', 't.term_id', '=', 'w.term_id')
            ->where('t.taxonomy', 'product_cat')->where('w.slug', $catSlug)->first(['t.term_taxonomy_id', 'w.term_id']);
        if (!$cat) return [];
        $tt = DB::connection('wp')->table('wp_term_taxonomy')->where('taxonomy', 'product_cat')->where('parent', $cat->term_id)->pluck('term_taxonomy_id')->push($cat->term_taxonomy_id)->all();
        $query = DB::connection('wp')->table('wp_posts as p')->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('wp_term_relationships as r')->whereColumn('r.object_id', 'p.ID')->whereIn('r.term_taxonomy_id', $tt));
        foreach (array_filter(preg_split('/\s+/', $rest)) as $word) {
            $query->where(fn ($w) => $w->where('p.post_title', 'like', '%' . $esc($word) . '%')
                ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('wp_postmeta as pm')->whereColumn('pm.post_id', 'p.ID')->where('pm.meta_key', '_sku')->where('pm.meta_value', 'like', '%' . $esc($word) . '%')));
        }
        $rows = $query->orderBy('p.menu_order')->orderBy('p.post_title')->limit(max(40, $limit * 6))->get(['p.ID', 'p.post_title']);
        // the plain part first: combination / conversion / offset versions after it
        $odd = fn ($t) => (int) (bool) preg_match('/\b(combo|combination|conversion|convertible|offset)\b|\d{4}\s*[x×]\s*\d{4}|\bto\s+\d{4}\b/i', $t);
        $rows = $rows->sortBy(fn ($r) => [$odd($r->post_title), mb_strlen($r->post_title)])->values();
        return $this->dedupe($rows->map(fn ($r) => $this->hydrate($r->ID, $r->post_title))->all(), $limit);
    }

    /**
     * One row per part: the same part listed twice (ours and the PTI copy with a trailing U, or the same
     * name under two numbers) shows once, keeping the one in stock. In-stock parts come first.
     */
    private function dedupe(array $rows, int $limit): array
    {
        $seen = []; $out = [];
        foreach ($rows as $p) {
            $k1 = 'sku:' . preg_replace('/U$/i', '', strtoupper((string) $p['sku']));
            $k2 = 'name:' . strtolower(preg_replace('/[^a-z0-9]+/i', '', preg_replace('/^\S*' . preg_quote((string) $p['sku'], '/') . '\S*/i', '', $p['name'])));
            $dup = $seen[$k1] ?? (strlen($k2) > 12 ? ($seen[$k2] ?? null) : null);
            if ($dup !== null) {
                if (empty($out[$dup]['in_stock']) && !empty($p['in_stock'])) $out[$dup] = $p;
                continue;
            }
            $out[] = $p; $seen[$k1] = $seen[$k2] = count($out) - 1;
        }
        usort($out, fn ($a, $b) => (int) !empty($b['in_stock']) <=> (int) !empty($a['in_stock']));
        return array_slice($out, 0, $limit);
    }

    private function searchTitle(string $q, int $limit): array
    {
        $esc = fn ($v) => str_replace(['%', '_'], ['\\%', '\\_'], $v);
        $words = array_filter(preg_split('/\s+/', $q));

        $query = DB::connection('wp')->table('wp_posts as p')
            ->where('p.post_type', 'product')->where('p.post_status', 'publish');

        // whole phrase can also be an exact SKU fragment
        $query->where(function ($outer) use ($words, $esc, $q) {
            // every word must appear in the title
            $outer->where(function ($w) use ($words, $esc) {
                foreach ($words as $word) $w->where('p.post_title', 'like', '%' . $esc($word) . '%');
            });
            // or the SKU matches the phrase
            $outer->orWhereExists(function ($sub) use ($q, $esc) {
                $sub->select(DB::raw(1))->from('wp_postmeta as pm')
                    ->whereColumn('pm.post_id', 'p.ID')
                    ->where('pm.meta_key', '_sku')->where('pm.meta_value', 'like', '%' . $esc($q) . '%');
            });
        });

        $rows = $query
            ->orderByRaw('CASE WHEN p.post_title LIKE ? THEN 0 ELSE 1 END', [$esc($q) . '%'])
            ->limit($limit)
            ->get(['p.ID', 'p.post_title']);

        return $rows->map(fn ($r) => $this->hydrate($r->ID, $r->post_title))->all();
    }

    /** Exact SKU lookup (for quick-order). */
    public function bySku(string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '') return null;
        // plain equality uses the (meta_key, meta_value) index; the column collation (utf8mb4_unicode_520_ci) is already
        // case-insensitive, so LOWER() only forced a full scan. Joined to published posts so a draft twin cannot win.
        $post = DB::connection('wp')->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')
            ->where('m.meta_key', '_sku')->where('m.meta_value', mb_substr($sku, 0, 64))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->orderByDesc('p.ID')->first(['p.ID', 'p.post_title']);
        return $post ? $this->hydrate($post->ID, $post->post_title) : null;
    }

    public function byId(int $id): ?array
    {
        $post = DB::connection('wp')->table('wp_posts')->where('ID', $id)
            ->where('post_type', 'product')->where('post_status', 'publish')->first(['ID', 'post_title']);
        return $post ? $this->hydrate($post->ID, $post->post_title) : null;
    }

    private function hydrate(int $id, string $title): array
    {
        return [
            'id' => (int) $id,
            'name' => $title,
            'sku' => (string) $this->meta($id, '_sku'),
            'price' => (float) ($this->meta($id, '_price') ?: $this->meta($id, '_regular_price') ?: 0), // like CatalogService::hydrateList
            'image' => $this->thumb($id),
            'in_stock' => (bool) (\App\Services\CatalogService::live((int) $id)['in_stock'] ?? false),
        ];
    }

    private function meta(int $postId, string $key)
    {
        return DB::connection('wp')->table('wp_postmeta')
            ->where('post_id', $postId)->where('meta_key', $key)->value('meta_value');
    }

    /** Product weight in pounds (WooCommerce stores it in whatever unit the store uses; this
     *  catalog is US/lb throughout). 0 when the product carries no weight, e.g. custom builds
     *  that are not a real WP product row. */
    public function weight(int $id): float
    {
        return (float) ($this->meta($id, '_weight') ?: 0);
    }

    /**
     * The product's real box, longest side first — carriers take the longest side as the
     * length and compute girth from the other two, and this catalog stores a driveshaft as
     * 7.5 x 7.5 x 83, so an unsorted triple makes an ordinary shaft look oversize.
     *
     * @return array{0:float,1:float,2:float}|null  null when the product carries no box
     */
    public function dims(int $id): ?array
    {
        $d = [(float) ($this->meta($id, '_length') ?: 0), (float) ($this->meta($id, '_width') ?: 0), (float) ($this->meta($id, '_height') ?: 0)];
        if (min($d) <= 0) return null;
        rsort($d);
        return $d;
    }

    /**
     * Tubing listed at a 1" length is the shop's sold-by-the-foot listing: the price and the
     * stored weight are per foot, and the cart's quantity counts feet rather than pieces.
     */
    public function soldByFoot(int $id): bool
    {
        if (DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_uds_by_foot')->where('meta_value', 'yes')->exists()) return true;
        $title = (string) DB::connection('wp')->table('wp_posts')->where('ID', $id)->value('post_title');
        if (preg_match('/sold by (the )?(1 )?foot/i', $title)) return true;
        return (bool) preg_match('/Tube Length\s*1(\.0+)?\s*"?\s*(?:–|-|$)/i', $title);
    }

    private function thumb(int $productId): string
    {
        if (($lv = \App\Services\CatalogService::live($productId)) && !empty($lv['image'])) return media_url($lv['image'], 320);
        $thumbId = $this->meta($productId, '_thumbnail_id');
        if ($thumbId && (int) $thumbId !== 11868 && ($file = $this->meta((int) $thumbId, '_wp_attached_file'))) {
            if (str_contains($file, 'placeholder') || str_contains($file, '1312312312132')) return '';
            return media_url(self::UPLOADS . $file, 320);
        }
        return '';
    }
}
