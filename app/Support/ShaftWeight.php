<?php

namespace App\Support;

use App\Services\ProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shipping weight and box of a driveshaft made in the online builder. A builder shaft is no catalog product, so it has no
 * stored weight or box; before this it shipped as the 15 lb, 48 x 8 x 8 placeholder whatever its length (owner, 2026-11-11:
 * a 55" 1350 aluminum shaft quoted $12.63 UPS Ground). Here it is weighed the way the shop builds it:
 *
 *   tube      the steel or aluminum tube's own weight over the whole length A1 (OD and wall from the critical-speed check)
 *   ends      at each end a weld yoke and a u-joint, plus what that end carries: a slip yoke, a flange yoke (the catalog
 *             yoke picked in the builder when there is one), a carrier bearing
 *   kit       the strap / u-bolt kit when one was picked
 *   box       a carton A1 + 4" long and as wide as the flange or tube plus padding, with the carton's own weight
 *
 * Component weights are the medians of our own catalog by series (yoke and u-joint products, Nov 2026), rounded up.
 * It is an estimate made to err heavy: the carrier bills the real weight anyway, and a light quote is the store's loss.
 */
class ShaftWeight
{
    /** lb per cubic inch */
    private const DENSITY = ['steel' => 0.2836, 'aluminum' => 0.0975];
    private const WALL = ['steel' => 0.083, 'aluminum' => 0.125];

    /** series => [weld yoke, slip yoke, flange yoke, u-joint] in lb */
    private const PARTS = [
        '1310' => [1.8, 3.0, 3.0, 1.4], '1330' => [3.0, 3.0, 3.0, 1.5], '1350' => [3.0, 3.5, 3.0, 1.7],
        '1410' => [3.0, 6.0, 3.5, 2.3], '1480' => [3.5, 6.0, 4.0, 2.6], '1550' => [5.0, 6.0, 6.2, 2.8],
        '1610' => [7.8, 15.0, 8.3, 7.0], '1710' => [11.4, 15.0, 11.4, 9.0], '1760' => [12.0, 15.0, 14.7, 10.0], '1810' => [14.7, 29.0, 13.4, 12.0],
    ];
    /** series => widest point of the end (flange / yoke swing), inches */
    private const END_DIA = ['1310' => 4.5, '1330' => 4.75, '1350' => 5.25, '1410' => 5.75, '1480' => 6.75, '1550' => 7.5, '1610' => 8.0, '1710' => 8.5, '1760' => 9.0, '1810' => 9.5];
    private const CARRIER_LB = 5.0;
    private const PAD_IN = 2.0;          // foam / cardboard at each end and around

    /** ['weight' => lb, 'dims' => [L, W, H]] or null when the build has no length (the caller keeps its fallback). */
    public static function estimate(array $cfg): ?array
    {
        $len = (float) ($cfg['a1'] ?? 0);
        if ($len <= 0) return null;
        $material = ($cfg['material'] ?? 'steel') === 'aluminum' ? 'aluminum' : 'steel';
        $series = self::series((string) ($cfg['series_key'] ?? ''));
        [$weld, $slip, $flange, $joint] = self::PARTS[$series];

        $speed = (array) ($cfg['speed_check'] ?? []);
        $od = (float) ($speed['tube_od'] ?? 0) ?: (float) preg_replace('/[^0-9.]/', '', (string) ($cfg['tube_od'] ?? ''));
        if ($od <= 0) $od = $material === 'aluminum' ? 4.0 : 3.5;
        $wall = (float) ($speed['wall'] ?? 0) ?: self::WALL[$material];
        $tube = M_PI * ($od - $wall) * $wall * $len * self::DENSITY[$material];

        $lb = $tube;
        foreach (['front' => (string) ($cfg['front_app'] ?? ''), 'rear' => (string) ($cfg['rear_app'] ?? '')] as $end => $app) {
            $lb += $weld + $joint;
            if (str_starts_with($app, 'slip') || in_array($app, ['trans', 'yoke_shaft', 'carrier_slip'], true)) $lb += $slip;
            if (str_contains($app, 'flange')) $lb += self::part((array) ($cfg[$end . '_yoke'] ?? []), $flange);
            if (str_starts_with($app, 'carrier')) $lb += self::CARRIER_LB + ($app === 'carrier_end' ? $weld : 0);
        }
        $lb += self::part((array) ($cfg['hardware'] ?? []), 0.0);

        $w = max(6.0, ceil((max($od, self::END_DIA[$series]) + self::PAD_IN) * 2) / 2);
        $boxLen = ceil($len + 2 * self::PAD_IN);
        $lb += 1.5 + 0.03 * $boxLen;   // the carton itself

        return ['weight' => ceil($lb), 'dims' => [$boxLen, $w, $w]];
    }

    /** PTI's own long shaft cartons, shortest first: [L, W, H] (4,400 complete shafts, Nov 2026). */
    private const PTI_BOXES = [[31.25, 5.25, 5.5], [47.75, 5.5, 5.5], [59.13, 7.25, 7.38], [71.38, 7.63, 7.63], [83.25, 7.75, 7.5], [95.13, 7.63, 7.63]];
    /** PTI's carton is this much longer than the shaft's weld-to-weld length, at least (5th percentile of 291 shafts). */
    private const WTW_MARGIN_IN = 9.0;

    /**
     * Weight and box of a vehicle-finder shaft (ds_parts), with the gaps PTI left filled in: a few hundred of its shafts
     * carry no weight or no carton, and those shipped as the 15 lb, 48 x 8 x 8 placeholder (owner, 2026-11-11).
     *
     *   box      PTI's own, else the smallest PTI carton that holds the weld-to-weld length + 9", else the carton the same
     *            kind of shaft (same description) ships in at the nearest weight, else one sized from a length in the
     *            description (boat shafts: "1310 Boat Shaft, 7.438"")
     *   weight   PTI's own, else the median of the same kind of shaft in the same carton, else of every shaft in that carton
     *
     * ['weight' => lb|null, 'dims' => [L, W, H]|null, 'estimated' => [field, …]] or null for a part we do not have.
     */
    public static function finderPart(string $pn): ?array
    {
        $p = DB::table('ds_parts')->where('part_number', $pn)->first(['part_number', 'description', 'weight', 'box_length', 'box_width', 'box_height']);
        if (!$p) return null;
        $w = (float) $p->weight > 0 ? (float) $p->weight : null;
        $box = min((float) $p->box_length, (float) $p->box_width, (float) $p->box_height) > 0 ? self::sorted([(float) $p->box_length, (float) $p->box_width, (float) $p->box_height]) : null;
        if ($w && $box) return ['weight' => $w, 'dims' => $box, 'estimated' => []];

        $peers = self::peers();
        $stem = self::stem((string) $p->description);
        $est = [];
        if (!$box) {
            $wtw = (float) DB::table('ds_fitments')->where('part_number', $pn)->max(DB::raw('weld_to_weld + 0'));
            if ($wtw > 0) {
                $need = $wtw + self::WTW_MARGIN_IN;
                $box = collect(self::PTI_BOXES)->first(fn ($b) => $b[0] >= $need) ?? [ceil($need), 7.63, 7.63];
            } elseif ($same = array_filter($peers, fn ($x) => $x['stem'] === $stem)) {
                $box = self::commonBox($same, $w);
            } elseif (str_contains($stem, 'BOAT SHAFT') && preg_match('/(\d+\.\d+)/', (string) $p->description, $m) && (float) $m[1] < 40) {
                $box = [ceil((float) $m[1] + 6), 6.0, 6.0];   // a boat shaft names its own length: "1350 Boat Shaft, 8.188 flange to end"
            } else {
                $box = self::commonBox($peers, $w);   // any shaft of about this weight
            }
            if ($box) $est[] = 'box';
        }
        if (!$w && $box) {
            $key = self::boxKey($box);
            $inBox = array_filter($peers, fn ($x) => $x['key'] === $key);
            $same = array_filter($inBox, fn ($x) => $x['stem'] === $stem);
            $pool = $same ?: (count($inBox) >= 5 ? $inBox : []);
            if ($pool) { $w = ceil(self::median(array_column($pool, 'w'))); $est[] = 'weight'; }
        }
        return ['weight' => $w, 'dims' => $box, 'estimated' => $est];
    }

    /**
     * The carton most of these shafts ship in, among those within 20% of this weight when the weight is known (the
     * shorter carton on a tie). Weight tracks length within one kind of shaft, and PTI picks the carton by length.
     */
    private static function commonBox(array $peers, ?float $w): ?array
    {
        $near = $w ? array_filter($peers, fn ($x) => abs($x['w'] - $w) <= 0.2 * $w) : [];
        $count = [];
        foreach ($near ?: $peers as $x) $count[$x['key']] = ($count[$x['key']] ?? ['n' => 0, 'dims' => $x['dims']]) + [];
        foreach ($near ?: $peers as $x) $count[$x['key']]['n']++;
        uasort($count, fn ($a, $b) => $b['n'] <=> $a['n'] ?: $a['dims'][0] <=> $b['dims'][0]);
        return $count ? reset($count)['dims'] : null;
    }

    /** Every PTI shaft that has both a weight and a carton: [stem, box key, weight, dims] (cached a day). */
    private static function peers(): array
    {
        return Cache::remember('ds_ship_peers_v1', 86400, fn () => DB::table('ds_parts')->where('weight', '>', 0)->where('box_length', '>', 0)->where('box_width', '>', 0)->where('box_height', '>', 0)
            ->get(['description', 'weight', 'box_length', 'box_width', 'box_height'])
            ->map(function ($r) {
                $d = self::sorted([(float) $r->box_length, (float) $r->box_width, (float) $r->box_height]);
                return ['stem' => self::stem((string) $r->description), 'key' => self::boxKey($d), 'w' => (float) $r->weight, 'dims' => $d];
            })->all());
    }

    /** "1310 Boat Shaft, 7.438"" and "1310 BOAT SHAFT" are the same kind of shaft. */
    private static function stem(string $d): string
    {
        $d = strtoupper(trim(explode(',', str_replace('""', '"', $d))[0]));
        return trim(preg_replace('/\s+/', ' ', preg_replace('/\d+(?:\.\d+)?\s*"/', '', $d)));
    }

    private static function boxKey(array $d): string
    {
        return round($d[0]) . 'x' . round(max($d[1], $d[2]));
    }

    private static function sorted(array $d): array
    {
        rsort($d);
        return $d;
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n ? ($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2) : 0.0;
    }

    /** The catalog product's weight when the builder picked one (a yoke, a kit), else the series figure. */
    private static function part(array $pick, float $fallback): float
    {
        $id = (int) ($pick['product_id'] ?? 0);
        $w = $id > 0 ? app(ProductService::class)->weight($id) : 0.0;
        return $w > 0 && $w < 40 ? $w : $fallback;
    }

    /** "1350", "1310 Ford", "1410HD" → a series in the table; unknown → 1350, the middle of the builder's range. */
    private static function series(string $key): string
    {
        return preg_match('/(1310|1330|1350|1410|1480|1550|1610|1710|1760|1810)/', $key, $m) ? $m[1] : '1350';
    }
}
