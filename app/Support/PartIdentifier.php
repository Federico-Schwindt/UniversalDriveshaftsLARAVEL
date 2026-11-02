<?php

namespace App\Support;

use App\Services\ProductService;

/**
 * Turns what a customer can tell us about a part — a number off a box that is not ours, a photo, two
 * measurements, a vehicle — into something we can sell: the u-joint series it belongs to, the catalogue
 * parts in that series, and the one question that would settle it when the answer is not yet known.
 *
 * The series chart is the one on the u-joint identification guide (cap diameter + width across the caps,
 * snap-ring groove to snap-ring groove). Widths are nominal; the tolerances below cover worn and new joints.
 */
class PartIdentifier
{
    /** series => [cap dia in, width in, snap ring, typical use] */
    public const SERIES = [
        '1210' => [1.062, 2.625, 'outside', 'Small cars, light SUVs, Nissan/Subaru flange shafts'],
        '1310' => [1.062, 3.219, 'outside', 'Jeep, half-ton trucks, most light-duty shafts, Ford 8.8 flange yokes'],
        '1330' => [1.062, 3.622, 'outside', 'Half-ton and some 3/4-ton trucks, Ford and Dodge pickups'],
        '1350' => [1.188, 3.622, 'outside', '3/4-ton trucks, Toyota flange shafts, performance and off-road builds'],
        '1410' => [1.188, 4.188, 'outside', 'One-ton trucks, racing, high-torque street cars'],
        '1480' => [1.375, 4.188, 'outside', 'One-ton and medium duty, drag race'],
        '1550' => [1.375, 4.875, 'outside', 'Medium duty, Class 5 to 6, heavy off-road'],
        '7260' => [1.078, 2.125, 'inside', 'Chrysler small: Dart, Duster, A-body, Jeep CJ rear'],
        '7290' => [1.125, 2.625, 'inside', 'Chrysler large: B/E-body, Dodge trucks, Ram, Dakota'],
        '3R/S44' => [1.125, 2.556, 'inside', 'GM Saginaw: Chevelle, Camaro, C10, S10, Blazer'],
    ];

    /** How far a measured joint may sit from the nominal numbers and still be called that series. */
    private const CAP_TOL = 0.010;   // 1.062 and 1.078 caps are 0.016 apart
    private const WIDTH_TOL = 0.10;

    /**
     * What a part number's shape says about it when it is not in our catalogue. Patterns are the common
     * numbering schemes seen on boxes; the hint is only a starting point the chat must confirm.
     */
    public const NUMBER_HINTS = [
        ['/^5-\d{3,4}X/i',            'u-joint',        'Spicer-style u-joint number (5-xxxX). The digits usually name the series: 5-1310X = 1310, 5-153X = 1310 greaseable, 5-178X = 1350, 5-160X = 1410.'],
        ['/^0?4371-/i',               'u-joint',        'Toyota OE-style u-joint number (04371-…). Toyota pickups, 4Runner, Tacoma, Tundra and Hilux shafts; aftermarket brands (Toyako, GMB, Febest) copy the OE number. Usually a 1310-size joint with 1.062 in caps, but the width must be measured.'],
        ['/^(GUT|GUM|GUN|GUIS|GU)-?\d/i', 'u-joint',    'GMB-style u-joint number (GUT = Toyota, GUM = Mazda/Mitsubishi, GUN = Nissan, GUIS = Isuzu). Import light-truck joints; measure caps and width.'],
        ['/^(369|370|371|372|373|374|375|376|377)-?\d*/', 'u-joint', 'Precision/Moog-style u-joint number (3xx). 369 = 1310, 372 = 1330, 373 = 1350, 374 = 1410, 375 = 1480 are the common ones.'],
        ['/^2-3-\d/i',                'slip yoke',      'Spicer-style slip yoke number (2-3-xxxx).'],
        ['/^3-4-\d/i',                'end yoke',       'Spicer-style end yoke number (3-4-xxxx).'],
        ['/^(2-2-|3-2-)\d/i',         'flange yoke',    'Spicer-style flange yoke number (2-2-/3-2-xxxx).'],
        ['/^3-28-\d/i',               'weld yoke',      'Spicer-style tube weld yoke number (3-28-xxx).'],
        ['/^(HB|HB-)?\d{3,4}-?\d*$/i','center support bearing', 'Could be a center support bearing number (HBxxx style) — only if the part has a rubber-cushioned bearing in a bracket.'],
    ];

    /** The series a measured joint belongs to, with the near misses that share its cap diameter. */
    public static function matchUJoint(?float $capIn, ?float $widthIn): array
    {
        $out = ['match' => null, 'candidates' => [], 'note' => ''];
        if (!$capIn && !$widthIn) return $out + ['note' => 'No measurements given.'];
        $scored = [];
        foreach (self::SERIES as $name => [$cap, $width, $ring, $use]) {
            $dc = $capIn ? abs($capIn - $cap) : null;
            $dw = $widthIn ? abs($widthIn - $width) : null;
            $capOk = $dc === null || $dc <= self::CAP_TOL;
            $widthOk = $dw === null || $dw <= self::WIDTH_TOL;
            if (!$capOk) continue;
            $scored[] = ['series' => (string) $name, 'cap_in' => $cap, 'width_in' => $width, 'snap_ring' => $ring, 'typical_use' => $use,
                'width_matches' => $widthOk, 'distance' => ($dc ?? 0) + ($dw ?? 0.5)];
        }
        usort($scored, fn ($a, $b) => $a['distance'] <=> $b['distance']);
        $out['candidates'] = array_map(fn ($s) => array_diff_key($s, ['distance' => 1]), $scored);
        $best = $scored[0] ?? null;
        if ($best && $widthIn && $best['width_matches']) {
            $out['match'] = (string) $best['series'];
            $out['note'] = 'Cap and width both match ' . $best['series'] . '.';
        } elseif ($best && !$widthIn) {
            $names = implode(', ', array_map(fn ($s) => $s['series'], $scored));
            $out['note'] = "Cap diameter alone fits $names — they differ only in width. Ask for the width across the caps, snap-ring groove to snap-ring groove.";
        } elseif ($best) {
            $out['note'] = 'The cap diameter fits ' . $best['series'] . ' but the width does not match any series with that cap; ask them to re-measure the width outside edge to outside edge of the snap-ring grooves.';
        } else {
            $out['note'] = 'No series uses that cap diameter. Common caps are 1.062, 1.078, 1.125, 1.188 and 1.375 in; ask them to re-measure with a caliper (and say whether it was inches or mm).';
        }
        return $out;
    }

    /** Inches from a free-form measurement: "1.062", "27mm", "1 1/16", "26.99 mm". */
    public static function inches(?string $v): ?float
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)/', $v, $m)) return round($m[1] + $m[2] / max(1, $m[3]), 3);
        if (preg_match('/^(\d+)\s*\/\s*(\d+)/', $v, $m)) return round($m[1] / max(1, $m[2]), 3);
        if (!preg_match('/(\d+(?:[.,]\d+)?)/', $v, $m)) return null;
        $n = (float) str_replace(',', '.', $m[1]);
        if (preg_match('/mm|millim/i', $v) || $n > 10) $n = $n / 25.4;   // nobody has a 27-inch u-joint cap
        return round($n, 3);
    }

    /** What a number that is not in our catalogue probably is. */
    public static function numberHint(string $number): ?array
    {
        $n = trim($number);
        if ($n === '') return null;
        foreach (self::NUMBER_HINTS as [$re, $type, $hint]) {
            if (preg_match($re, $n)) return ['part_type' => $type, 'hint' => $hint];
        }
        return null;
    }

    /** Variants of a foreign number worth trying in our catalogue: without a brand suffix, without a brand prefix, core digits. */
    public static function numberVariants(string $number): array
    {
        $n = strtoupper(trim($number));
        $set = [$n];
        $set[] = preg_replace('/-(TO|GMB|TY|NS|MZ|SP|US|UDS)$/', '', $n);        // 04371-AR202-TO → 04371-AR202
        $set[] = preg_replace('/^(GUT|GUM|GUN|GUIS|GU)-?/', '', $n);
        $set[] = preg_replace('/U$/', '', $n);                                     // our own U-suffixed SKUs
        $set[] = $n . 'U';
        if (preg_match('/^5-(\d{3,4})X/', $n, $m)) $set[] = '5-' . $m[1] . 'X';
        return array_values(array_unique(array_filter($set, fn ($s) => strlen($s) >= 3)));
    }

    /** Catalogue parts to offer for a series: our sealed and greaseable joints, Spicer, and conversions that touch it. */
    public static function alternatives(string $series, string $partType = 'u-joint', int $limit = 6): array
    {
        $ps = app(ProductService::class);
        $q = match ($partType) {
            'u-joint' => "universal joint $series",
            'slip yoke' => "slip yoke $series",
            'end yoke' => "end yoke $series",
            'flange yoke' => "flange yoke $series",
            'weld yoke' => "weld yoke $series",
            default => "$partType $series",
        };
        $rows = $ps->search($q, $limit * 2);
        if (!$rows && $series === '3R/S44') $rows = $ps->search(str_replace('3R/S44', '3R', $q), $limit * 2) ?: $ps->search(str_replace('3R/S44', 'S44', $q), $limit * 2);
        // a name that states a cap size ("1.188x2.188") of another series is a conversion or a mislabel: drop it for u-joints
        if ($partType === 'u-joint' && isset(self::SERIES[$series])) {
            $cap = self::SERIES[$series][0];
            $rows = array_values(array_filter($rows, function ($p) use ($cap) {
                if (!preg_match('/(\d\.\d{2,3})\s*[x×]\s*\d/i', (string) ($p['name'] ?? ''), $m)) return true;
                return abs((float) $m[1] - $cap) <= 0.02;
            }));
        }
        // straight joints of that series first, conversions (two series in the name) after, in stock before out, cheapest first
        $core = preg_quote(str_replace('/', '', explode('/', $series)[0]), '/');
        $rank = function (array $p) use ($core): array {
            $n = (string) ($p['name'] ?? '');
            $others = preg_match_all('/\b(1210|1310|1330|1350|1410|1480|1550|7260|7290|3R|S44)\b/i', $n, $m) ? count(array_unique(array_map('strtoupper', $m[1]))) : 0;
            $conversion = $others > 1 || stripos($n, 'conversion') !== false;
            return [$conversion ? 1 : 0, !empty($p['in_stock']) ? 0 : 1, (float) ($p['price'] ?? 0)];
        };
        usort($rows, fn ($a, $b) => $rank($a) <=> $rank($b));
        return array_map(fn ($p) => $p + ['conversion_joint' => $rank($p)[0] === 1], array_slice($rows, 0, $limit));
    }
}
