<?php

namespace App\Support;

use App\Services\ProductService;

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
