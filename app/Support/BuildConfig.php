<?php

namespace App\Support;

use App\Services\DsbCatalog;

/**
 * One place that turns a driveshaft-builder configuration into readable rows.
 *
 * The builder already stored everything it collected in the cart line's
 * meta['config'], but only a one-line summary was ever displayed, so the actual
 * measurements and part selections never reached the cart, the checkout, the
 * order confirmation or the shop. Every surface now renders the same rows from
 * here, so what the customer confirms is exactly what the shop builds.
 */
class BuildConfig
{
    /** @return array<int,array{label:string,value:string}> */
    public static function rows(array $c): array
    {
        $rows = [];
        $add = function (string $label, $value) use (&$rows) {
            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || $value === null) return;
            $rows[] = ['label' => $label, 'value' => (string) $value];
        };

        $premade = ($c['build_mode'] ?? '') === 'premade';
        $add('Build', $premade ? 'Pre-made shaft' : 'Custom build');
        $add('Configuration', ($c['pieces'] ?? 'one') === 'two' ? 'Two-piece' : 'One-piece');

        if ($premade) {
            if (!empty($c['premade_product_id'])) {
                $add('Part', self::productName((int) $c['premade_product_id']) ?: ('#' . $c['premade_product_id']));
            }
            $add('Measurement A', self::inches($c['premade_a'] ?? ''));
            $add('Measurement B', self::inches($c['premade_b'] ?? ''));
            foreach (($c['premade_extras'] ?? []) as $e) {
                if (!is_array($e)) continue;
                $label = trim((string) ($e['label'] ?? 'Option'), ": \t\n");
                $val = (string) ($e['value'] ?? '');
                $cost = (float) ($e['cost'] ?? 0);
                if ($val === '') continue;
                $add($label, $cost > 0 ? $val . ' (+$' . number_format($cost, 2) . ')' : $val);
            }
        } else {
            $add('Duty rating', $c['duty_label'] ?? $c['duty_key'] ?? '');
            $add('Series', $c['series_label'] ?? strtoupper((string) ($c['series_key'] ?? '')));
            $add('Tube material', ucfirst((string) ($c['material'] ?? '')));
            $add('Component grade', ($c['price_tier'] ?? '') === 'new' ? 'Brand new' : 'Certified reconditioned');
            $add('Front application', $c['front_app'] ?? '');
            $add('Rear application', $c['rear_app'] ?? '');
            $add('Measurement A1', self::inches($c['a1'] ?? 0));
            $add('Measurement B1', self::inches($c['b1'] ?? 0));
            $add('Measurement C1', self::inches($c['c1'] ?? 0));
            $add('Overall length', self::inches($c['length'] ?? 0));
            $add('Flange yoke', self::yoke($c['flange_yoke'] ?? null));
            $add('Transmission yoke', self::yoke($c['trans_yoke'] ?? null));
        }

        $add('Customer notes', $c['notes'] ?? '');
        return $rows;
    }

    /** Plain-text block for e-mail. */
    public static function text(array $c, string $indent = '  '): string
    {
        $rows = self::rows($c);
        if (!$rows) return '';
        $w = max(array_map(fn ($r) => strlen($r['label']), $rows));
        return implode("\n", array_map(
            fn ($r) => $indent . str_pad($r['label'], $w) . ' : ' . $r['value'],
            $rows
        ));
    }

    /** A short one-liner for tight spots (cart line, order summary row). */
    public static function inline(array $c, int $max = 4): string
    {
        $rows = self::rows($c);
        array_shift($rows); // "Build" is already implied by the line name
        return implode(' · ', array_map(fn ($r) => $r['label'] . ' ' . $r['value'], array_slice($rows, 0, $max)));
    }

    private static function yoke($y): string
    {
        if (!is_array($y)) return '';
        $kind = (string) ($y['kind'] ?? 'none');
        if ($kind === 'none' || $kind === '') return '';
        $name = !empty($y['product_id']) ? self::productName((int) $y['product_id']) : '';
        $label = $kind === 'used' ? 'Reuse the customer yoke' : 'New yoke supplied by UDS';
        return $name ? $label . ' — ' . $name : $label;
    }

    private static function inches($v): string
    {
        if (is_numeric($v)) {
            $f = (float) $v;
            return $f > 0 ? rtrim(rtrim(number_format($f, 3, '.', ''), '0'), '.') . '"' : '';
        }
        $v = trim((string) $v);
        return $v === '' ? '' : (preg_match('/["\']|in\b/i', $v) ? $v : $v . '"');
    }

    private static function productName(int $id): string
    {
        try {
            return (string) (app(DsbCatalog::class)->productName($id) ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
