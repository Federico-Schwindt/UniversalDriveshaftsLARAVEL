<?php

namespace App\Services;

use App\Services\Admin\CatalogWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Copies the measurements PTI lists for a part onto our product where ours lacks them (owner, 2026-11-11: "copia los
 * atributos… si el sku es el mismo pero el nuestro termina en U entonces es lo mismo"). Only for the same physical part:
 * our SKU is PTI's number, PTI's number + "U", or the product was created from PTI, AND no measurement both sides
 * already list disagrees. Values we have are never replaced; the one exception is a strap / U-bolt kit whose series list
 * PTI widens for the same cap diameter. Lube stays ours on "U" parts (our U u-joints can be greasable where PTI's are not).
 * Used by `pti:fix-attributes --fill`.
 */
class PtiAttributeFill
{
    /** PTI spec key => our taxonomies for it: any of them filled means we have it; the one our parts of that PTI type use most is written. */
    private const MAP = [
        'series' => ['pa_series', 'pa_style-series'], 'style' => ['pa_style', 'pa_style-series', 'pa_type-sp'], 'material' => ['pa_material'],
        'lube' => ['pa_lube', 'pa_greasable'], 'greasable_' => ['pa_greasable', 'pa_lube'],
        'cap_1_diameter' => ['pa_cap-dia-1'], 'cap_2_diameter' => ['pa_cap-dia-2'], 'stack_1' => ['pa_width-1'], 'stack_2' => ['pa_width-2'],
        'butt_diameter' => ['pa_butt-dia', 'pa_butt-diameter'], 'centerline_to_weld' => ['pa_center-u-j-to-weld'],
        'centerline_to_end' => ['pa_cj-to-end', 'pa_center-u-joint-to-end', 'pa_c-l-to-end', 'pa_cl-to-cl'],
        'centerline_to_face' => ['pa_cj-to-flat', 'pa_c-l-to-face'], 'pilot_diameter' => ['pa_pilot-dia'], 'pilot_type' => ['pa_pilot-type'],
        'bolt_circle_diameter' => ['pa_bolt-circle-dia'], 'bolt_hole_count' => ['pa_bolt-holes', 'pa_bolt-hole-count'], 'bolt_holes' => ['pa_bolt-holes', 'pa_bolt-hole-count'],
        'bolt_hole_diameter' => ['pa_bolt-hole-dia'], 'bolt_hole_pilot_od' => ['pa_bolt-hole-pilot-dia', 'pa_pilot-dia'], 'bolt_hole_pilot_diameter' => ['pa_bolt-hole-pilot-dia', 'pa_pilot-dia'],
        'swing_diameter' => ['pa_swing-dia'],
        'spline_count' => ['pa_splines-count', 'pa_splines-count-dia', 'pa_num--splines', 'pa_spline-size'],
        'spline_diameter' => ['pa_splines-dia', 'pa_splines-count-dia', 'pa_spline-size'], 'spline_length' => ['pa_splines-length'],
        'hub_diameter' => ['pa_hub-dia'], 'thru_hole' => ['pa_through-hole'], 'counterbore_diameter' => ['pa_counter-bore-dia'],
        'boot_kit' => ['pa_boot-part-no'], 'boot' => ['pa_boot-part-no', 'pa_boot-cap-part'], 'end_to_weld' => ['pa_end-to-weld'],
        'bearing_diameter' => ['pa_bearing-dia', 'pa_brg-dia'], 'use_with' => ['pa_use-with'],
        'tube_diameter' => ['pa_tube-dia-wall', 'pa_tube-dia', 'pa_fits-to-tube', 'pa_tube-diameter'], 'tube_wall' => ['pa_tube-dia-wall', 'pa_tube-wall', 'pa_fits-to-tube'],
        'bearing_id' => ['pa_bearing-id'], 'center_to_center_of_mount_holes' => ['pa_ctr-to-ctr-of-mount-holes'], 'centerline_to_base' => ['pa_center-bearing-to-base', 'pa_c-l-to-base'],
        'bracket_width' => ['pa_bracket-wid'], 'application' => ['pa_application'], 'disc_width' => ['pa_disc-width'], 'make' => ['pa_vehicle', 'pa_application'],
        'nose_bushing_diameter' => ['pa_nose-bushing-dia'], 'face_to_weld' => ['pa_face-to-weld'], 'ball_stud_diameter' => ['pa_ball-stud-dia'],
        'ball_stud_length' => ['pa_ball-stud-length-dia'], 'center_to_center' => ['pa_cj-to-cj', 'pa_center-to-center', 'pa_cl-to-cl'], 'face_to_end' => ['pa_face-to-end'],
        'outer_diameter' => ['pa_outer-dia', 'pa_od', 'pa_outside-dia'], 'outer_race_width' => ['pa_outer-race-width'], 'inner_race_width' => ['pa_inner-race-width'],
        'gasket' => ['pa_gasket'], 'bore_diameter' => ['pa_bore-dia'], 'bore_type' => ['pa_bore-type'], 'keyway' => ['pa_keyway'], 'vehicle_class' => ['pa_class'],
        'weight' => ['pa_weight'], 'outside_diameter' => ['pa_outside-dia', 'pa_outside-diameter', 'pa_od', 'pa_outer-dia'], 'inside_diameter' => ['pa_inside-dia', 'pa_inside-diameter'],
        'to_tube' => ['pa_to-tube'], 'from_tube' => ['pa_from-tube'], 'length' => ['pa_length', 'pa_overall-length'], 'thread_size' => ['pa_thread-size', 'pa_bolt-size'],
        'thickness' => ['pa_thickness'], 'self_locking_' => ['pa_self-locking'], 'self_locking' => ['pa_self-locking'], 'hex' => ['pa_hex-size', 'pa_hex'],
        'fits_cap_dia' => ['pa_fits-cap-dia'], 'c_to_c' => ['pa_center-to-center', 'pa_cl-to-cl'], 'bolt_length' => ['pa_bolt-length'], 'thread_dia' => ['pa_thread-dia'],
        'bolt_torque' => ['pa_bolt-torque'], 'strap_no' => ['pa_strap-no'], 'bolt_no' => ['pa_bolt-no'], 'inside_dia' => ['pa_inside-dia', 'pa_washer-od-id'],
        'outside_dia' => ['pa_outside-dia', 'pa_washer-od-id', 'pa_od'], 'o_d_' => ['pa_od', 'pa_outside-dia'], 'height' => ['pa_height'], 'inside_dia_' => ['pa_inside-dia'],
        'stud_bore_diam' => ['pa_stud-bore-dia', 'pa_stud-bore-diam'], 'seal_od' => ['pa_seal-o-d', 'pa_seal-od', 'pa_seal-od-id'], 'seal_height' => ['pa_seal-height'],
        'ball_diam' => ['pa_ball-dia', 'pa_ball-diam'], 'overall_length' => ['pa_overall-length', 'pa_overall-len'], 'nut_torque' => ['pa_nut-torque'],
        'type' => ['pa_type-sp'], 'type_' => ['pa_type-sp'], 'grade' => ['pa_grade'], 'length_under_head' => ['pa_length-under-head'], 'size' => ['pa_size'], 'diameter' => ['pa_diameter'],
        'lg_dia_boot' => ['pa_lg-dia-boot'], 'sm_dia_boot' => ['pa_sm-dia-boot'], 'shaft_neck_dia' => ['pa_shaft-neck-dia'], 'boot_diam_' => ['pa_boot-diam'], 'stub_diam_' => ['pa_stub-diam'],
        'boot_part_no' => ['pa_boot-part-no'], 'lg_clamp_part_no' => ['pa_lg-clamp-part-no'], 'sm_clamp_part_no' => ['pa_sm-clamp-part-no'], 'seal_type' => ['pa_seal-type'],
        'num__splines' => ['pa_num--splines'], 'seal_od_id' => ['pa_seal-od-id'], 'washer_od_id' => ['pa_washer-od-id'], 'fits_brg_dia' => ['pa_fits-brg-dia'], 'color' => ['pa_color'],
        'bolt_size' => ['pa_bolt-size'], 'brg_dia' => ['pa_brg-dia'], 'ujoint_number' => ['pa_ujoint-number'], 'spline_size' => ['pa_spline-size'],
        // PTI fields the old store never had: new attributes (labels in NEW)
        'diff_model' => ['pa_differential'], 'vented' => ['pa_vented'], 'centerline_to_washer' => ['pa_cl-to-washer'], 'counterbore_depth' => ['pa_counter-bore-depth'],
        'hub_length' => ['pa_hub-length'], 'joint_angle' => ['pa_joint-angle'], 'hole_to_hole' => ['pa_hole-to-hole'], 'dust_cap' => ['pa_dust-cap'], 'midship' => ['pa_midship'],
        'end_to_radius' => ['pa_end-to-radius'], 'movement' => ['pa_movement'], 'included_parts' => ['pa_included-parts'], 'end_cap' => ['pa_end-cap'], 'set_screw' => ['pa_set-screw'],
        'flinger' => ['pa_flinger'], 'ubolt_no' => ['pa_u-bolt-no'], 'lock_washer_no' => ['pa_lock-washer-no'], 'nut_no' => ['pa_nut-no'],
        'oem_manufacturer' => ['pa_oem-manufacturer'], 'oem_series' => ['pa_oem-series'],
    ];

    /** Attributes created for PTI's extra fields, with their admin / table label. */
    public const NEW = [
        'pa_differential' => 'Differential', 'pa_vented' => 'Vented', 'pa_cl-to-washer' => 'CL to Washer', 'pa_counter-bore-depth' => 'Counter Bore Depth',
        'pa_hub-length' => 'Hub Length', 'pa_joint-angle' => 'Joint Angle', 'pa_hole-to-hole' => 'Hole to Hole', 'pa_dust-cap' => 'Dust Cap', 'pa_midship' => 'Midship',
        'pa_end-to-radius' => 'End to Radius', 'pa_movement' => 'Movement', 'pa_included-parts' => 'Included Parts', 'pa_end-cap' => 'End Cap', 'pa_set-screw' => 'Set Screw',
        'pa_flinger' => 'Flinger', 'pa_u-bolt-no' => 'U-Bolt No.', 'pa_lock-washer-no' => 'Lock Washer No.', 'pa_nut-no' => 'Nut No.',
        'pa_oem-manufacturer' => 'OEM Manufacturer', 'pa_oem-series' => 'OEM Series',
    ];

    /** Ours by definition: our brand and our own notes text. */
    private const SKIP = ['brand', 'notes'];

    /** Measurements that decide whether a part fits: if both sides list one and they differ, the part is left alone. */
    private const FIT = ['series', 'cap_1_diameter', 'cap_2_diameter', 'stack_1', 'stack_2', 'tube_diameter', 'tube_wall', 'spline_count', 'spline_diameter', 'bolt_circle_diameter',
        'pilot_diameter', 'bolt_hole_count', 'bolt_holes', 'bolt_hole_diameter', 'hub_diameter', 'butt_diameter', 'ball_stud_diameter', 'fits_cap_dia', 'c_to_c', 'style', 'pilot_type'];

    public function __construct(private PtiDropship $dropship, private CatalogWriter $writer) {}

    /**
     * What a fill would write.
     * @return array{changes: array<int,array>, skipped: array<int,array>}  change = id, sku, pti, match, type, tax, old[], new
     */
    public function plan(): array
    {
        $w = DB::connection('wp');
        $parts = DB::table('pti_parts')->whereIn('match', ['sku', 'sku_u', 'created'])->whereNotNull('wp_product_id')->orderBy('part_number')
            ->get(['part_number', 'wp_product_id', 'match', 'pti_type', 'specs']);
        $ids = $parts->pluck('wp_product_id')->map(fn ($v) => (int) $v)->all();
        $ours = []; $skus = [];
        foreach (array_chunk($ids, 800) as $chunk) {
            foreach ($w->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                ->whereIn('tr.object_id', $chunk)->where('tt.taxonomy', 'like', 'pa\_%')->get(['tr.object_id', 'tt.taxonomy', 't.name']) as $r) {
                $ours[(int) $r->object_id][$r->taxonomy][] = html_entity_decode((string) $r->name);
            }
            foreach ($w->table('wp_postmeta')->whereIn('post_id', $chunk)->where('meta_key', '_sku')->where('meta_value', '!=', '')->get(['post_id', 'meta_value']) as $r) $skus[(int) $r->post_id] ??= trim($r->meta_value);
        }
        // per PTI type, which of the candidate taxonomies our products already use (so a fill lands in the category's own column)
        $usage = [];
        foreach ($parts as $p) foreach (($ours[(int) $p->wp_product_id] ?? []) as $tax => $_) $usage[$p->pti_type][$tax] = ($usage[$p->pti_type][$tax] ?? 0) + 1;

        $changes = []; $skipped = [];
        foreach ($parts as $p) {
            $id = (int) $p->wp_product_id; $type = (string) $p->pti_type; $sku = $skus[$id] ?? '';
            $specs = json_decode((string) $p->specs, true) ?: [];
            $base = ['id' => $id, 'sku' => $sku, 'pti' => $p->part_number, 'match' => $p->match, 'type' => $type];
            if (!self::samePartNumber($sku, (string) $p->part_number)) { $skipped[] = $base + ['why' => 'SKU is not PTI\'s number or PTI\'s number + U']; continue; }
            // the same physical part: every measurement both sides list agrees (a different lube is ours to keep, not a different size)
            $v = $this->dropship->verify($id, $type, $specs);
            $bad = array_values(array_filter($v['diffs'], fn ($d) => $d['label'] !== 'Lube'));
            if ($bad) { $skipped[] = $base + ['why' => 'measurements differ: ' . implode('; ', array_map(fn ($d) => "{$d['label']} ours {$d['ours']} / PTI {$d['pti']}", $bad))]; continue; }
            $mine = $ours[$id] ?? [];
            [$conflicts, $out] = $this->conflicts($type, $specs, $mine);
            if ($conflicts) { $skipped[] = $base + ['why' => 'measurements differ: ' . implode('; ', $conflicts)]; continue; }

            foreach ($specs as $key => $raw) {
                if (in_array($key, self::SKIP, true) || !isset(self::MAP[$key])) continue;
                // PTI's numbered catalogue drawings ("style 6"): meaningless without their picture
                if ($key === 'style' && in_array($type, ['small:balance-weights', 'small:lube-zerk-fittings'], true)) continue;
                if (in_array($key, ['lube', 'greasable_'], true) && $p->match === 'sku_u') continue;
                $group = self::MAP[$key];
                if (self::has($mine, $group)) continue;
                $tax = self::target($usage[$type] ?? [], $group);
                $val = self::clean($key, (string) $raw);
                // PTI mixes units on a few small parts: a metric zerk's hex and length are millimetres ("13.500" long);
                // a 1.60" CV-ball seal height is a slip (its siblings are .125–.197) and is not copied
                if ($type === 'small:lube-zerk-fittings' && in_array($key, ['hex', 'overall_length'], true) && is_numeric($val) && stripos((string) ($specs['thread_size'] ?? ''), 'mm') !== false) $val = rtrim(rtrim($val, '0'), '.') . 'mm';
                if ($key === 'seal_height' && is_numeric($val) && (float) $val >= 1) continue;
                // two-number attributes are written once, in our format: "2.500X0.083", "31 X 1.378", "CVJ Flanged"
                if (in_array($tax, ['pa_tube-dia-wall', 'pa_fits-to-tube'], true)) {
                    if ($key !== 'tube_diameter') continue;
                    $wall = self::clean('tube_wall', (string) ($specs['tube_wall'] ?? ''));
                    $val = ($val !== '' && $wall !== '' && is_numeric($val) && is_numeric($wall)) ? sprintf('%.3fX%.3f', $val, $wall) : '';
                } elseif ($tax === 'pa_splines-count-dia') {
                    if ($key !== 'spline_count') continue;
                    $dia = self::clean('spline_diameter', (string) ($specs['spline_diameter'] ?? ''));
                    $val = ($val !== '' && $dia !== '') ? "$val X $dia" : '';
                } elseif ($tax === 'pa_style-series') {
                    if ($key !== 'series') continue;
                    $val = trim($val . ' ' . self::clean('style', (string) ($specs['style'] ?? '')));
                }
                if ($val === '' || isset($out[$tax])) continue;
                $out[$tax] = $val;
            }
            foreach ($out as $tax => $new) $changes[] = $base + ['tax' => $tax, 'old' => $mine[$tax] ?? [], 'new' => $new];
        }
        return ['changes' => $changes, 'skipped' => $skipped];
    }

    /** Write planned changes (new values become terms; PTI's extra fields get their own attributes). */
    public function apply(array $changes): void
    {
        $db = DB::connection('wp');
        foreach (array_unique(array_column($changes, 'tax')) as $tax) {
            if (!isset(self::NEW[$tax])) continue;
            $db->table('wp_woocommerce_attribute_taxonomies')->updateOrInsert(['attribute_name' => substr($tax, 3)],
                ['attribute_label' => self::NEW[$tax], 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => 0]);
        }
        foreach ($changes as $c) $this->writer->syncTerms($c['id'], $c['tax'], [$c['new']], true);
        CatalogWriter::invalidate();
        Cache::forget('cjob:usewith');
    }

    /** "5-1330X" = "5-1330X" or "5-1330XU" (dash / space / case-insensitive). */
    public static function samePartNumber(string $ours, string $pti): bool
    {
        $n = fn ($v) => strtoupper(preg_replace('/[\s\-]+/', '', $v));
        return $ours !== '' && $pti !== '' && ($n($ours) === $n($pti) || $n($ours) === $n($pti) . 'U');
    }

    /**
     * Fitment measurements both sides list that disagree, plus the one correction allowed: a strap / U-bolt kit whose
     * series list PTI widens ("1330" → "1310, 1330") while the cap diameter it fits is the same.
     * @return array{0: string[], 1: array<string,string>}
     */
    private function conflicts(string $type, array $specs, array $mine): array
    {
        $bad = []; $fix = [];
        foreach (self::FIT as $key) {
            $val = self::clean($key, (string) ($specs[$key] ?? ''));
            if ($val === '' || $key === 'lube') continue;
            $have = array_values(array_filter(self::MAP[$key] ?? [], fn ($t) => !empty($mine[$t]) && !self::blank(implode(' ', $mine[$t]))));
            if (!$have) continue;
            $t = $have[0]; $o = implode(' ', $mine[$t]);
            // combined attributes hold two numbers: compare the right one
            if (in_array($t, ['pa_tube-dia-wall', 'pa_fits-to-tube'], true)) { $n = self::nums($o); $o = (string) ($key === 'tube_wall' ? ($n[1] ?? '') : ($n[0] ?? '')); }
            if ($t === 'pa_splines-count-dia') { $n = self::nums($o); $o = (string) ($key === 'spline_count' ? ($n[0] ?? '') : (end($n) ?: '')); }
            if ($o === '' || self::same($o, $val, $key, $t)) continue;
            if ($key === 'series' && in_array($type, ['small:strap-kits', 'small:ubolt-kits'], true) && !array_diff(self::seriesSet($o), self::seriesSet($val))) {
                $cap = self::clean('fits_cap_dia', (string) ($specs['fits_cap_dia'] ?? ''));
                $ourCap = implode(' ', $mine['pa_fits-cap-dia'] ?? []);
                if ($cap !== '' && (self::blank($ourCap) || self::same($ourCap, $cap, 'fits_cap_dia'))) { $fix[$t] = $val; continue; }
            }
            $bad[] = ucfirst(str_replace('_', ' ', $key)) . " ours $o / PTI $val";
        }
        return [$bad, $fix];
    }

    private static function has(array $mine, array $group): bool
    {
        foreach ($group as $t) if (!empty($mine[$t]) && !self::blank(implode(' ', $mine[$t]))) return true;
        return false;
    }

    private static function target(array $usage, array $group): string
    {
        $best = $group[0]; $n = 0;
        foreach ($group as $t) if (($usage[$t] ?? 0) > $n) { $n = $usage[$t]; $best = $t; }
        return $best;
    }

    /** PTI's value as we store it ('' = nothing to copy). */
    public static function clean(string $key, string $v): string
    {
        $v = trim(preg_replace('/\s+/', ' ', html_entity_decode($v)));
        $v = trim(preg_replace('/\bPTI\s+/i', '', $v));          // "23/24 PTI Slip Yokes": our pages never name the supplier
        $v = preg_replace('/\s*,\s*/', ', ', $v);
        $v = str_ireplace('Sepretley', 'Separately', $v);
        if (self::blank($v)) return '';
        if (preg_match('/^\.\d+$/', $v)) $v = '0' . $v;
        switch ($key) {
            case 'joint_angle':
                return is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') . '°' : $v;
            case 'lube': case 'greasable_': case 'self_locking': case 'self_locking_':
                return ['YES' => 'Y', 'NO' => 'N'][strtoupper($v)] ?? $v;
            case 'boot': case 'gasket': case 'end_cap':
                // PTI's "Y" = comes with it; our CV joints say INCLUDED
                return match (strtoupper($v)) { 'Y', 'YES' => 'INCLUDED', 'N', 'NO' => '', default => $v };
            case 'series':
                return preg_replace('#\s*/\s*#', ', ', $v);
            case 'set_screw':
                return trim(preg_replace('/\s*-\s*$/', '', $v));
            case 'boot_kit':
                return preg_replace('/^(\d+)x$/', '$1X', $v);
            case 'included_parts':
                return str_ireplace('Boot, Clamp', 'Boot Clamp', $v);   // a few PTI kits list "Boot, Clamp" after "Boot"
        }
        return $v;
    }

    private static function blank(string $v): bool
    {
        $v = strtoupper(trim($v));
        return $v === '' || in_array($v, ['N/A', 'NA', '-', 'NONE', 'NULL'], true) || (bool) preg_match('/^0+(\.0+)?$/', $v);
    }

    /** "1310 1210 1330" / "1210, 1310, 1330" / "SPL170 - SPL250" → sorted tokens; "3 R" and "14.5 C" stay one token. */
    private static function seriesSet(string $v): array
    {
        $out = [];
        foreach (preg_split('/\s*(?:[,\/&;]|\s-\s)\s*/', strtoupper(trim($v))) as $t) {
            $parts = preg_split('/\s+/', trim($t));
            $split = count($parts) > 1 && !array_filter($parts, fn ($p) => strlen($p) < 3 || !preg_match('/\d/', $p));
            foreach ($split ? $parts : [str_replace(' ', '', $t)] as $p) if ($p !== '') $out[] = $p;
        }
        $out = array_values(array_unique($out)); sort($out);
        return $out;
    }

    private static function nums(string $v): array
    {
        preg_match_all('/\d*\.?\d+/', $v, $m);
        return array_map('floatval', $m[0]);
    }

    /** Same numbers (±0.011, metric ±0.11, "83" = .083) or the same word; series lists in any order; COMBO = any two-series value. */
    private static function same(string $a, string $b, string $key, string $tax = ''): bool
    {
        $A = strtoupper(trim($a)); $B = strtoupper(trim($b));
        if ($tax === 'pa_style-series') {
            $n = fn ($v) => preg_replace(['/\bHR\b/', '/\bFLANGED\b/', '/\s+/'], ['HALF ROUND', 'FLANGE', ' '], $v);
            return str_contains($n($A), $n(str_replace('CV ', '', $B))) || str_contains($n($A), $n($B));
        }
        if ($key === 'series' && ($A === 'COMBO' || $B === 'COMBO')) { $o = $A === 'COMBO' ? $B : $A; return $o === 'COMBO' || (bool) preg_match('#\S+\s*[X×/]\s*\S+#iu', $o); }
        if ($key === 'series' && preg_match('/[,\/ ]/', $A . $B)) {
            $x = self::seriesSet($A); $y = self::seriesSet($B);
            if ($x === $y) return true;
            if (count($x) > 1 || count($y) > 1) return false;
        }
        if (in_array($key, ['style', 'pilot_type'], true)) {
            $n = fn ($v) => ['OUT' => 'OSR', 'OUTSIDE' => 'OSR', 'IN' => 'ISR', 'INSIDE' => 'ISR', 'MALE' => 'M', 'FEMALE' => 'F'][preg_replace('/[^A-Z]/', '', $v)] ?? preg_replace('/[^A-Z0-9]/', '', $v);
            return $n($A) === $n($B);
        }
        $x = self::nums($a); $y = self::nums($b);
        if (!$x || !$y) { $wd = fn ($v) => ['YES' => 'Y', 'NO' => 'N'][preg_replace('/[^A-Z0-9]/', '', $v)] ?? preg_replace('/[^A-Z0-9]/', '', $v); return $wd($A) === $wd($B); }
        if ($key === 'series' || count($x) !== count($y)) { $n = min(count($x), count($y)); $x = array_slice($x, 0, $n); $y = array_slice($y, 0, $n); }
        $tol = (stripos($a, 'mm') !== false || stripos($b, 'mm') !== false) ? 0.11 : 0.011;
        foreach ($x as $i => $p) {
            $q = $y[$i];
            $ok = abs($p - $q) <= $tol || ($p >= 10 && $p < 1000 && $q < 1 && abs($p / 1000 - $q) <= 0.0011) || ($q >= 10 && $q < 1000 && $p < 1 && abs($q / 1000 - $p) <= 0.0011);
            if (!$ok) return false;
        }
        return true;
    }
}
