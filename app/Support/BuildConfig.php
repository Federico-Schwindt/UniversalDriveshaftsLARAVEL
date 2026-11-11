<?php

namespace App\Support;

use App\Services\DsbCatalog;

/**
 * One place that turns a driveshaft-builder configuration into readable rows.
 *
 * The builder stores everything it collected in the cart line's meta['config']; every surface (cart, checkout,
 * order confirmation, invoice, e-mails, the admin order screen and the shop's check-in bridge) renders the same
 * rows from here, so what the customer confirms is exactly what the shop builds.
 */
class BuildConfig
{
    public const POSITIONS = [
        'rear_2wd' => 'Rear shaft, 2WD (RWD)', 'rear_4wd' => 'Rear shaft, 4WD / AWD',
        'front_4wd' => 'Front shaft, 4WD', 'other' => 'Other / not a vehicle',
    ];

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
        $add('Vehicle', self::vehicle($c['vehicle'] ?? null));
        $add('Shaft', self::POSITIONS[$c['shaft_position'] ?? ''] ?? '');

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
            // the question-tree answers (output type, make, model, spline count…) in the order they were asked
            foreach ((array) ($c['answers'] ?? []) as $qa) {
                if (is_array($qa) && ($qa['q'] ?? '') !== '' && ($qa['a'] ?? '') !== '') $add((string) $qa['q'], (string) $qa['a']);
            }
            $add('Duty rating', $c['duty_label'] ?? $c['duty_key'] ?? '');
            $add('Series', trim(($c['series_label'] ?? strtoupper((string) ($c['series_key'] ?? ''))) . (!empty($c['series_variant']) ? ' (' . $c['series_variant'] . ')' : '')));
            $add('Tube material', ucfirst((string) ($c['material'] ?? '')));
            $add('Tube OD', $c['tube_od'] ?? '');
            $add('Component grade', ($c['price_tier'] ?? '') === 'new' ? 'Brand new' : 'Certified reconditioned');
            // the label the builder resolved server-side ("Slip Yoke with Flange Yoke"), not its key ("slip_flange")
            $add('Front end', ($c['front_app_label'] ?? '') ?: ($c['front_app'] ?? ''));
            $add('Front yoke', self::yoke($c['front_yoke'] ?? null, true));
            $add('Rear end', ($c['rear_app_label'] ?? '') ?: ($c['rear_app'] ?? ''));
            $add('Rear yoke', self::yoke($c['rear_yoke'] ?? null, false));
            $add('Rear u-joint hardware', self::hardware($c['hardware'] ?? null));
            foreach ((array) ($c['build_options'] ?? []) as $label => $value) $add((string) $label, (string) $value);   // supplier-priced builds
            // builds saved before Nov 2026 kept the add-ons under the old names
            $add('Front yoke add-on', self::yoke($c['flange_yoke'] ?? null, false));
            $add('Rear yoke add-on', self::yoke($c['trans_yoke'] ?? null, false));
            $add('Measurement A1', self::inches($c['a1'] ?? 0));
            $add('Critical speed check', self::speed($c['speed_check'] ?? null));
            $add('Measurement B1', self::inches($c['b1'] ?? 0));
            $add('Measurement C1', self::inches($c['c1'] ?? 0));
            $add('Overall length', self::inches($c['length'] ?? 0));
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

    public static function vehicle($v): string
    {
        if (!is_array($v)) return '';
        if (!empty($v['skipped'])) return 'Not a vehicle / skipped';
        $s = trim(implode(' ', array_filter([(string) ($v['year'] ?? ''), (string) ($v['make'] ?? ''), (string) ($v['model'] ?? '')])));
        return $s;
    }

    private static function yoke($y, bool $withSpline): string
    {
        if (!is_array($y)) return '';
        $kind = (string) ($y['kind'] ?? 'none');
        if ($kind === 'none' || $kind === '') return '';
        $name = !empty($y['product_id']) ? self::productName((int) $y['product_id']) : '';
        $label = match ($kind) {
            'own' => 'Customer\'s own yoke (sent in or brought to the shop)',
            'used' => 'Good used yoke from our shop',
            default => $name ? 'New: ' . $name : 'New yoke from our catalog',
        };
        if ($withSpline) {
            $extra = array_filter([
                !empty($y['spline']) ? $y['spline'] . ' splines' : '',
                !empty($y['model']) ? (string) $y['model'] : '',
            ]);
            if ($extra) $label .= ' — ' . implode(', ', $extra);
        }
        return $label;
    }

    private static function hardware($h): string
    {
        if (!is_array($h)) return '';
        $kind = (string) ($h['kind'] ?? 'none');
        if ($kind === 'none' || $kind === '') return '';
        if ($kind === 'own') return 'Customer has the strap / u-bolt kit';
        $name = !empty($h['product_id']) ? self::productName((int) $h['product_id']) : '';
        return ($kind === 'ubolt' ? 'U-bolt kit' : 'Strap kit') . ($name ? ': ' . $name : '');
    }

    private static function speed($sp): string
    {
        if (!is_array($sp) || empty($sp['critical_rpm'])) return '';
        $s = sprintf('%s" x %s" %s tube at %s": critical %s RPM, safe to %s RPM', rtrim(rtrim(number_format((float) $sp['tube_od'], 3, '.', ''), '0'), '.'), $sp['wall'], $sp['material'] ?? '', rtrim(rtrim(number_format((float) $sp['length'], 3, '.', ''), '0'), '.'), number_format((int) $sp['critical_rpm']), number_format((int) $sp['safe_rpm']));
        if (!empty($sp['shaft_rpm'])) $s .= sprintf('; %s mph x %s ratio x 336 / %s" tire = %s RPM (%s)', $sp['mph'], $sp['ratio'], $sp['tire'], number_format((int) $sp['shaft_rpm']), ['ok' => 'OK', 'warn' => 'near the limit', 'bad' => 'over the safe speed'][$sp['status']] ?? '');
        return trim(str_replace('  ', ' ', $s));
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
