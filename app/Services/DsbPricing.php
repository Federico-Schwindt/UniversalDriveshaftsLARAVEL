<?php

namespace App\Services;

/**
 * Server-side pricing engine for the driveshaft configurator.
 * Ports the canonical formula of ubs-driveshaft-builder v2.9.1 (§3 of SPEC-BACKEND)
 * INCLUDING the aluminum branch that the original computed client-side only.
 * The client never dictates the price here — everything is recomputed from settings.json + WP DB.
 */
class DsbPricing
{
    /** WC product 58843 ("Custom Driveshaft") _price — the original's basePrice fallback. */
    public const BASE_PRICE = 568.58;

    private array $s;

    public function __construct(private DsbCatalog $catalog)
    {
        $this->s = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    }

    public function settings(): array
    {
        return $this->s;
    }

    /**
     * Compute the full price breakdown for a configuration.
     * $cfg keys: build_mode(custom|premade), premade_product_id, duty_key, series_key, series_label,
     *            material(steel|aluminum), price_tier(certified|new), a1(float), length(float),
     *            flange_yoke: ['kind'=>'used'|'new'|'none', 'product_id'=>int],
     *            trans_yoke:  same, premade_extras: [{label,value,cost?}...]
     */
    public function compute(array $cfg): array
    {
        // ---- PREMADE: price = the premade product's live WP price + selected extras cost
        if (($cfg['build_mode'] ?? '') === 'premade') {
            $pid = (int) ($cfg['premade_product_id'] ?? 0);
            // only real pre-made driveshafts (published, in the pre-made category) — any other ID prices at 0 → refused
            $base = ($pid && $this->catalog->isPremade($pid)) ? $this->catalog->price($pid) : 0.0;
            $extras = 0.0;
            foreach ((array) ($cfg['premade_extras'] ?? []) as $ex) {
                $extras += round((float) ($ex['cost'] ?? 0), 2);
            }
            return [
                'base' => $base, 'tube_addon' => 0.0, 'flange_addon' => 0.0, 'trans_addon' => 0.0,
                'extras' => $extras, 'final' => $base > 0 ? $base + $extras : 0.0,
                'breakdown' => ['premade_product_id' => $pid],
            ];
        }

        $zero = ['base' => 0.0, 'tube_addon' => 0.0, 'flange_addon' => 0.0, 'trans_addon' => 0.0, 'extras' => 0.0, 'final' => 0.0, 'breakdown' => []];
        // exact (case-sensitive) tree match only: "SPL250" or an unknown duty must never price at the base fallback
        $hit = $this->resolveSeries((string) ($cfg['duty_key'] ?? ''), (string) ($cfg['subcat_key'] ?? ''), (string) ($cfg['series_key'] ?? ''));
        if (!$hit) return $zero;
        $seriesKey = $hit['series_key'];
        $series = strtolower($seriesKey);
        $tier = in_array($cfg['price_tier'] ?? '', ['certified', 'new'], true) ? $cfg['price_tier'] : 'certified';
        $material = in_array($cfg['material'] ?? '', ['steel', 'aluminum'], true) ? $cfg['material'] : 'steel';
        if (!$this->aluminumEligible($hit['duty_key'], $seriesKey)) $material = 'steel'; // builder offers aluminum on light/medium only
        if ($material === 'aluminum') $tier = 'new';                                     // aluminum is sold brand new only (dsb.js)
        $a1 = (float) ($cfg['a1'] ?? 0);
        $length = (float) ($cfg['length'] ?? 0);

        // ---- port of DSBBuilder.computePrice() (dsb-builder.js:2566-2617), minus the BASE_PRICE fallback:
        // a tier no price source covers is unpriceable (0 → "call for a quote"), never the $568.58 product price
        $certifiedBase = 0.0;
        $brandNewBase = 0.0;
        $node = $hit['node'];
        if (!empty($node['price']) && (float) $node['price'] > 0) $certifiedBase = (float) $node['price'];
        if (!empty($node['new_price']) && (float) $node['new_price'] > 0) $brandNewBase = (float) $node['new_price'];
        $pricingMap = $material === 'aluminum' ? ($this->s['aluminum_tube_od_pricing'] ?? []) : ($this->s['tube_od_pricing'] ?? []);
        $tierList = $pricingMap[$series] ?? [];
        if ($material === 'aluminum' && isset($this->s['aluminum_flat_pricing'][$series])) {
            $flat = $this->s['aluminum_flat_pricing'][$series];
            if ((float) ($flat['cert_price'] ?? 0) > 0) $certifiedBase = (float) $flat['cert_price'];
            if ((float) ($flat['new_price'] ?? 0) > 0) $brandNewBase = (float) $flat['new_price'];
        }
        $tubeOd = '';
        if ($tierList) {
            // first tier whose max_a1 is 0 or >= A1; if none fits, the LAST (highest) tier
            $row = null;
            foreach ($tierList as $r) {
                $max = (float) ($r['max_a1'] ?? 0);
                if ($max == 0 || $max >= $a1) { $row = $r; break; }
            }
            if (!$row) $row = end($tierList);
            if ($row) {
                if ((float) ($row['cert_price'] ?? 0) > 0) $certifiedBase = (float) $row['cert_price'];
                if ((float) ($row['new_price'] ?? 0) > 0) $brandNewBase = (float) $row['new_price'];
                $tubeOd = (string) ($row['od'] ?? '');
            }
        }
        $base = $tier === 'new' ? $brandNewBase : $certifiedBase;
        if ($base <= 0) return $zero;

        // ---- tube rule upcharge (by length; live rules)
        $tubeAddon = 0.0; $ruleOd = '';
        if (!empty($this->s['tube_rules_enabled']) && $length > 0) {
            $rule = $this->matchTubeRule((array) ($this->s['tube_rules'] ?? []), $length);
            if ($rule) { $tubeAddon = (float) ($rule['price'] ?? 0); $ruleOd = (string) ($rule['tube_od'] ?? ''); }
        }

        // ---- yoke add-ons: used = settings map by series; new = live WP product price by ID
        [$flangeAddon, $flangeLabel] = $this->yokeAddon($cfg['flange_yoke'] ?? [], 'used_flange_prices', $series);
        [$transAddon, $transLabel]   = $this->yokeAddon($cfg['trans_yoke'] ?? [], 'used_trans_prices', $series);

        $final = $base + $tubeAddon + $flangeAddon + $transAddon;

        return [
            'base' => $base, 'tube_addon' => $tubeAddon, 'flange_addon' => $flangeAddon,
            'trans_addon' => $transAddon, 'extras' => 0.0, 'final' => $final,
            'breakdown' => [
                'tube_od' => $tubeOd ?: $ruleOd, 'tier' => $tier, 'material' => $material,
                'flange_label' => $flangeLabel, 'trans_label' => $transLabel,
            ],
        ];
    }

    /**
     * The tree node for an exact duty/subcategory/series key triple, with server-side labels — null when any
     * key is unknown. subcat_key may be omitted (searched), but when given it must hold the series.
     */
    public function resolveSeries(string $dutyKey, string $subcatKey, string $seriesKey): ?array
    {
        $duty = $dutyKey !== '' ? ($this->s['tree'][$dutyKey] ?? null) : null;
        if (!is_array($duty) || $seriesKey === '') return null;
        $node = null; $sub = null;
        if (!empty($duty['subcategories'])) {
            if ($subcatKey !== '') {
                $node = $duty['subcategories'][$subcatKey]['series'][$seriesKey] ?? null;
                $sub = $node ? $subcatKey : null;
            } else {
                foreach ((array) $duty['subcategories'] as $k => $c) {
                    if (isset($c['series'][$seriesKey])) { $node = $c['series'][$seriesKey]; $sub = (string) $k; break; }
                }
            }
        } elseif ($subcatKey === '') {
            $node = $duty['series'][$seriesKey] ?? null;
        }
        if (!is_array($node)) return null;
        return [
            'duty_key' => $dutyKey, 'subcat_key' => $sub, 'series_key' => $seriesKey, 'node' => $node,
            'duty_label' => (string) ($duty['label'] ?? $dutyKey), 'series_label' => (string) ($node['label'] ?? $seriesKey),
        ];
    }

    /** Aluminum tubes are offered for the light/medium series only (dsb.js isAluminumEligible). */
    public function aluminumEligible(string $dutyKey, string $seriesKey): bool
    {
        return $dutyKey === 'light_medium' && isset($this->s['aluminum_tube_od_pricing'][strtolower($seriesKey)]);
    }

    /** Pick the cheapest qualifying OD row whose max_a1 >= A1 (0 = no restriction). */
    private function fromOdTable(array $rows, float $a1, string $tier): array
    {
        $best = null;
        foreach ($rows as $row) {
            $max = (float) ($row['max_a1'] ?? 0);
            if ($a1 > 0 && $max > 0 && $a1 > $max) continue;      // tube too short for this A1
            $price = (float) ($tier === 'new' ? ($row['new_price'] ?? 0) : ($row['cert_price'] ?? 0));
            if ($price <= 0) $price = (float) ($row['new_price'] ?? 0); // aluminum cert=0 fallback
            if ($price <= 0) continue;
            if ($best === null || $price < $best[0]) $best = [$price, (string) ($row['od'] ?? '')];
        }
        return $best ?? [0.0, ''];
    }

    private function treeFlat(string $dutyKey, string $series, string $tier): float
    {
        $tree = $this->s['tree'] ?? [];
        $node = null;
        $duty = $tree[$dutyKey] ?? null;
        if ($duty) {
            $node = $duty['series'][$series] ?? null;
            if (!$node) {
                foreach ((array) ($duty['subcategories'] ?? []) as $sub) {
                    if (isset($sub['series'][$series])) { $node = $sub['series'][$series]; break; }
                }
            }
        }
        if (!$node) { // search all duties as fallback
            foreach ($tree as $d) {
                if (isset($d['series'][$series])) { $node = $d['series'][$series]; break; }
                foreach ((array) ($d['subcategories'] ?? []) as $sub) {
                    if (isset($sub['series'][$series])) { $node = $sub['series'][$series]; break 2; }
                }
            }
        }
        if (!$node) return 0.0;
        return (float) ($tier === 'new' ? ($node['new_price'] ?? 0) : ($node['price'] ?? 0));
    }

    /** DSB_Data::match_tube_rule port: first rule (asc by max_length) with length<=max; else largest. */
    private function matchTubeRule(array $rules, float $length): ?array
    {
        $valid = array_values(array_filter($rules, fn ($r) => !empty($r['tube_od']) && (float) ($r['max_length'] ?? 0) > 0));
        if (!$valid) return null;
        usort($valid, fn ($a, $b) => (float) $a['max_length'] <=> (float) $b['max_length']);
        foreach ($valid as $r) {
            if ($length <= (float) $r['max_length']) return $r;
        }
        return end($valid) ?: null;
    }

    private function yokeAddon(array $yoke, string $usedMapKey, string $series): array
    {
        $kind = $yoke['kind'] ?? 'none';
        if ($kind === 'used') {
            $price = (float) ($this->s[$usedMapKey][$series] ?? 0);
            $label = 'Used ' . ($usedMapKey === 'used_flange_prices' ? 'Flange Yoke' : 'Transmission Yoke');
            return [$price, $price > 0 ? $label . ' (+$' . number_format($price, 2) . ')' : $label];
        }
        if ($kind === 'new') {
            $pid = (int) ($yoke['product_id'] ?? 0);
            if ($pid > 0) {
                $price = $this->catalog->price($pid);
                $name = $this->catalog->productName($pid) ?? 'Yoke #' . $pid;
                return [$price, $name . ' (+$' . number_format($price, 2) . ')'];
            }
        }
        return [0.0, ''];
    }
}
