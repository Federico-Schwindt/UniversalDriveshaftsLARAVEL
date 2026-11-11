<?php

namespace App\Services;

use App\Http\Controllers\ShopController;

/**
 * "Complete the job" (cart) and "Fits this part" (product page): parts that fit what the customer has, suggested ONLY
 * when the catalog data proves the fit (owner, 2026-11-11: "el fitment debe ser perfecto, debes asegurarme de que son
 * compatibles las partes"). Two kinds of proof:
 *
 *  1. Tube size. A weld yoke, CV head, midship stub or splined sleeve is made for one tube: "3.000X0.083" = 3.000" OD ×
 *     .083" wall. Tube ↔ tube end match when OD and wall are equal (to the thousandth) and both materials are known and
 *     the same (a steel yoke does not weld to aluminium tube). A value that cannot be read for certain (a wall typed "4")
 *     counts as unknown, and unknown never matches.
 *  2. The manufacturer's own "use with" list (pa_use-with), both ways.
 *
 * Series alone is not proof (snap-ring style, cap size and strap bolt spacing differ inside a series), so u-joints,
 * strap / U-bolt kits and CV ball kits are never suggested from a series; flange yoke ↔ companion flange is not either
 * (the pilot's male / female side is not in the data). Best sellers first; nothing already in the cart; a tube size the
 * cart already covers is not offered again.
 */
class CompleteJob
{
    private const KEYS = ['pa_series', 'pa_tube-dia-wall', 'pa_fits-to-tube', 'pa_tube-dia', 'pa_tube-wall', 'pa_material', 'pa_notes', 'pa_tube-length', 'pa_use-with'];
    /** Parts welded to a tube; their tube size is the tube they fit. */
    private const TUBE_ENDS = ['weld-yoke', 'cv-double-joints-heads', 'stub-midship-shaft', 'splined-sleeves'];

    public function __construct(private CatalogService $catalog) {}

    /** @return array{items: array<int,array>, build: bool} */
    public function forCart(array $cartItems, int $limit = 6): array
    {
        $have = [];
        foreach ($cartItems as $i) {
            if (($i['type'] ?? 'product') !== 'product' || empty($i['wp_product_id'])) continue;
            if ($h = $this->part((int) $i['wp_product_id'])) $have[] = $h;
        }
        $inCart = array_column($have, 'id');
        $out = []; $picked = [];
        // each cart line offers its best match in turn, so the first line does not take every place
        $lists = array_map(fn ($h) => $this->matches($h, $have), $have);
        for ($round = 0; count($out) < $limit && $round < 4; $round++) {
            foreach ($lists as $list) {
                if (count($out) >= $limit || !isset($list[$round])) continue;
                [$c, $reason] = $list[$round];
                if (in_array($c['id'], $inCart, true) || isset($picked[$c['id']])) continue;
                $picked[$c['id']] = true;
                $out[] = ['id' => $c['id'], 'sku' => $c['sku'], 'name' => $c['name'], 'image' => $c['image'],
                    'price' => ShopController::shopperPrice($c['price']), 'in_stock' => $c['in_stock'], 'unit' => $c['unit'], 'reason' => $reason];
            }
        }
        // people buying weld yokes or tube are building a shaft: offer to build it for them (no fitment claim)
        $build = (bool) array_filter($have, fn ($h) => in_array($h['cat'], ['weld-yoke', 'tubing'], true));
        return ['items' => $out, 'build' => $build];
    }

    /**
     * Product page: the proven fits ("Fits this part", with the reason) and, apart from them, what other customers
     * ordered with it, minus other series and same-kind substitutes. The second list makes no fitment claim.
     *
     * @return array{fits: array<int,array>, also: array<int,array>} catalog list rows; fits rows carry 'why'
     */
    public function forProduct(int $id, int $fitLimit = 6, int $alsoLimit = 6): array
    {
        $me = $this->part($id);
        if (!$me) return ['fits' => [], 'also' => []];
        $why = [];
        foreach ($this->matches($me, [$me]) as [$c, $reason]) {
            if (count($why) >= $fitLimit) break;
            $why[$c['id']] ??= $reason;
        }
        $also = [];
        foreach ($this->catalog->alsoBought($id, 24) as $r) {
            if (count($also) >= $alsoLimit) break;
            $rid = (int) $r['id'];
            if ($rid === $id || isset($why[$rid]) || (float) $r['price'] <= 0) continue;
            $d = $this->part($rid);
            if (!$d || $d['cat'] === $me['cat']) continue;   // same kind = a substitute, listed as an alternative
            if ($me['seriesSet'] && $d['seriesSet'] && !array_intersect($me['seriesSet'], $d['seriesSet'])) continue;
            $also[] = $r;
        }
        $fits = [];
        foreach ($why ? $this->catalog->rows(array_keys($why)) : [] as $r) if ((float) $r['price'] > 0) $fits[] = $r + ['why' => $why[(int) $r['id']]];
        return ['fits' => $fits, 'also' => $also];
    }

    /** Proven matches for one part, best sellers first: [[candidate, reason], …]. $have = what the customer already has. */
    private function matches(array $h, array $have): array
    {
        $out = [];
        $sku = $h['sku'];
        // 1. tube size
        if ($h['tube'] && $h['material'] !== '') {
            $size = self::size($h['tube']);
            $covered = fn ($cat) => (bool) array_filter($have, fn ($x) => $x['id'] !== $h['id'] && self::sameTube($x, $h) && ($cat === 'tubing' ? $x['cat'] === 'tubing' : in_array($x['cat'], self::TUBE_ENDS, true)));
            if (in_array($h['cat'], self::TUBE_ENDS, true) && !$covered('tubing')) {
                foreach ($this->pool('tubing') as $c) {
                    if (self::sameTube($c, $h)) $out[] = [$c, __('Tube that fits your :sku: :size, :material.', ['sku' => $sku, 'size' => $size, 'material' => self::material($h['material'])])];
                }
            }
            if ($h['cat'] === 'tubing' && !$covered('end')) {
                foreach (['weld-yoke', 'splined-sleeves', 'stub-midship-shaft', 'cv-double-joints-heads'] as $cat) {
                    foreach ($this->pool($cat) as $c) {
                        if (!self::sameTube($c, $h)) continue;
                        $kind = ['weld-yoke' => __('Weld yoke'), 'splined-sleeves' => __('Splined sleeve'), 'stub-midship-shaft' => __('Midship stub'), 'cv-double-joints-heads' => __('CV head')][$cat];
                        $out[] = [$c, $c['series'] !== ''
                            ? __(':kind for your :size tube, :series series.', ['kind' => $kind, 'size' => $size, 'series' => $c['series']])
                            : __(':kind for your :size tube.', ['kind' => $kind, 'size' => $size])];
                    }
                }
            }
        }
        // 2. the manufacturer's "use with" list, both ways
        foreach ($this->useWith() as $from => $tos) {
            if ($from === $h['id']) foreach ($tos as $to) if ($c = $this->part($to)) $out[] = [$c, __('Made to be used with your :sku.', ['sku' => $sku])];
            if (in_array($h['id'], $tos, true) && ($c = $this->part($from))) $out[] = [$c, __('Made to be used with your :sku.', ['sku' => $sku])];
        }
        $seen = [];
        return array_values(array_filter($out, function ($m) use (&$seen) {
            if ($m[0]['price'] <= 0 || isset($seen[$m[0]['id']])) return false;
            return $seen[$m[0]['id']] = true;
        }));
    }

    private static function sameTube(array $a, array $b): bool
    {
        return $a['tube'] && $b['tube'] && $a['material'] !== '' && $a['material'] === $b['material']
            && abs($a['tube'][0] - $b['tube'][0]) < 0.0015 && abs($a['tube'][1] - $b['tube'][1]) < 0.0006;
    }

    /** product id => [ids it is made to be used with], from pa_use-with part numbers that exist in the catalog. */
    private function useWith(): array
    {
        static $map = null;
        if ($map !== null) return $map;
        return $map = \Illuminate\Support\Facades\Cache::remember('cjob:usewith', 3600, function () {
            $w = \Illuminate\Support\Facades\DB::connection('wp');
            $rows = $w->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'pa_use-with')->get(['tr.object_id', 't.name']);
            $out = [];
            foreach ($rows as $r) {
                foreach (preg_split('/[\s,;.]+|\band\b/i', html_entity_decode((string) $r->name)) as $tok) {
                    $tok = trim($tok);
                    if (!preg_match('/\d/', $tok) || strlen($tok) < 4) continue;
                    if (($p = $this->catalog->productBySku($tok)) && (int) $p['id'] !== (int) $r->object_id) $out[(int) $r->object_id][] = (int) $p['id'];
                }
            }
            return $out;
        });
    }

    /** One catalog part, described for matching (null when it is not a published part). */
    private function part(int $id): ?array
    {
        static $cache = [];
        if (array_key_exists($id, $cache)) return $cache[$id];
        $cat = $this->catalog->primaryCategory($id)['slug'] ?? null;
        $row = $this->catalog->rows([$id], self::KEYS)[0] ?? null;
        return $cache[$id] = ($cat && $row) ? $this->describe($cat, $row) : null;
    }

    /** A category's parts, best sellers first, described (the catalog caches the list for 10 minutes). */
    private function pool(string $cat): array
    {
        static $pools = [];
        return $pools[$cat] ??= array_map(fn ($r) => $this->describe($cat, $r), $this->catalog->products($cat, [], 1, 500, 'popular', '', self::KEYS)['items'] ?? []);
    }

    private function describe(string $cat, array $r): array
    {
        $a = $r['attrs'] ?? [];
        $tube = null;
        $dw = (string) ($a['pa_tube-dia-wall'] ?? $a['pa_fits-to-tube'] ?? '');
        if (preg_match('/^\s*([\d.]+)\s*"?\s*[xX×]\s*([\d.]+)\s*"?\s*$/', $dw, $m)) $tube = [self::od($m[1]), self::wall($m[2])];
        elseif (!empty($a['pa_tube-dia']) && !empty($a['pa_tube-wall'])) $tube = [self::od($a['pa_tube-dia']), self::wall($a['pa_tube-wall'])];
        if ($tube && (!$tube[0] || !$tube[1])) $tube = null;
        $set = array_values(array_filter(array_map(fn ($x) => strtoupper(trim($x)), preg_split('/\s*(?:[,\/&;]|\band\b)\s*/i', html_entity_decode((string) ($a['pa_series'] ?? $r['series'] ?? ''))))));
        $mat = strtoupper(trim((string) ($a['pa_material'] ?? '')));
        $mat = str_starts_with($mat, 'ALUM') ? 'ALUMINUM' : (str_starts_with($mat, 'STEEL') || str_contains($mat, 'DOM') ? 'STEEL' : ($mat === '' ? '' : $mat));
        return [
            'id' => (int) $r['id'], 'sku' => (string) $r['sku'], 'cat' => $cat, 'series' => $set[0] ?? '', 'seriesSet' => $set, 'tube' => $tube, 'material' => $mat,
            'name' => $this->catalog->cleanName($r), 'image' => (string) ($r['image'] ?? ''), 'price' => (float) ($r['price'] ?? 0),
            'in_stock' => ($r['stock_status'] ?? '') === 'instock' || (int) ($r['stock'] ?? 0) > 0,
            // tube sold by the foot is priced per foot: the cart sends the customer to pick a length
            'unit' => $cat === 'tubing' && stripos((string) ($a['pa_notes'] ?? ''), 'foot') !== false && self::num($a['pa_tube-length'] ?? '') <= 12 ? 'ft' : '',
        ];
    }

    private static function num($v): float
    {
        return preg_match('/\d*\.?\d+/', (string) $v, $m) ? (float) $m[0] : 0.0;
    }

    /** Tube OD in inches; only plain inch values (0.5 to 10) are trusted. */
    private static function od($v): float
    {
        $f = self::num($v);
        return $f >= 0.5 && $f <= 10 ? round($f, 3) : 0.0;
    }

    /** Tube wall in inches: .083 / 0.083" as typed, or 49–300 as thousandths (83 = .083). Anything else is unknown. */
    private static function wall($v): float
    {
        $f = self::num($v);
        if ($f > 0.02 && $f < 0.5) return round($f, 3);
        if ($f >= 49 && $f <= 300 && floor($f) == $f) return round($f / 1000, 3);
        return 0.0;
    }

    private static function size(array $t): string
    {
        return number_format($t[0], 3) . '" × ' . ltrim(number_format($t[1], 3), '0') . '"';
    }

    private static function material(string $m): string
    {
        return $m === 'ALUMINUM' ? __('aluminum') : ($m === 'STEEL' ? __('steel') : mb_strtolower($m));
    }
}
