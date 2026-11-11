<?php

namespace App\Services;

/**
 * Server-side pricing engine for the driveshaft builder.
 * Ports the canonical formula of ubs-driveshaft-builder v2.9.1 (§3 of SPEC-BACKEND) including the aluminum
 * branch, plus the Nov 2026 end options: a new, good-used or customer-owned yoke on each end and a u-joint
 * hardware kit on a weld-yoke rear. The client never dictates a price here — everything is recomputed from
 * settings.json and the catalog's own prices.
 */
class DsbPricing
{
    /** WC product 58843 ("Custom Driveshaft") _price — the original's basePrice fallback. */
    public const BASE_PRICE = 568.58;

    /** Yoke choice per end: a new part from the catalog, a good used one from the shop, or the customer's own. */
    public const YOKE_KINDS = ['none', 'new', 'used', 'own'];
    /** Hardware on a weld-yoke (open u-joint) rear. */
    public const HARDWARE_KINDS = ['none', 'strap', 'ubolt', 'own'];

    private array $s;

    public function __construct(private DsbCatalog $catalog, private AmPricing $am = new AmPricing())
    {
        $this->s = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    }

    public function settings(): array
    {
        return $this->s;
    }

    /**
     * Compute the full price breakdown for a configuration.
     * $cfg keys: duty_key, subcat_key, series_key,
     *            material(steel|aluminum), price_tier(certified|new), a1(float), length(float), front_app, rear_app,
     *            front_yoke / rear_yoke: ['kind' => none|new|used|own, 'product_id' => int],
     *            hardware: ['kind' => none|strap|ubolt|own, 'product_id' => int]
     */
    public function compute(array $cfg): array
    {
        // exact (case-sensitive) tree match only: "SPL250" or an unknown duty must never price at the base fallback
        $hit = $this->resolveSeries((string) ($cfg['duty_key'] ?? ''), (string) ($cfg['subcat_key'] ?? ''), (string) ($cfg['series_key'] ?? ''));
        if (!$hit) return $this->zero();
        $seriesKey = $hit['series_key'];
        $series = strtolower($seriesKey);
        $tier = in_array($cfg['price_tier'] ?? '', ['certified', 'new'], true) ? $cfg['price_tier'] : 'certified';
        $material = in_array($cfg['material'] ?? '', ['steel', 'aluminum'], true) ? $cfg['material'] : 'steel';
        if (!$this->aluminumEligible($hit['duty_key'], $seriesKey)) $material = 'steel'; // builder offers aluminum on light/medium only
        if ($material === 'aluminum') $tier = 'new';                                     // aluminum is sold brand new only
        $a1 = (float) ($cfg['a1'] ?? 0);
        $length = (float) ($cfg['length'] ?? 0);

        // ---- port of DSBBuilder.computePrice() (dsb-builder.js:2566-2617), minus the BASE_PRICE fallback:
        // a tier no price source covers is unpriceable (0 → "call for a quote"), never the $568.58 product price
        $certifiedBase = 0.0;
        $brandNewBase = 0.0;
        $node = $hit['node'];
        if (!empty($node['price']) && (float) $node['price'] > 0) $certifiedBase = (float) $node['price'];
        if (!empty($node['new_price']) && (float) $node['new_price'] > 0) $brandNewBase = (float) $node['new_price'];
        $tierList = $this->odRows($series, $material);
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
        if ($base <= 0) return $this->zero();

        // ---- tube rule upcharge (by length; live rules)
        $tubeAddon = 0.0; $ruleOd = '';
        if (!empty($this->s['tube_rules_enabled']) && $length > 0) {
            $rule = $this->matchTubeRule((array) ($this->s['tube_rules'] ?? []), $length);
            if ($rule) { $tubeAddon = (float) ($rule['price'] ?? 0); $ruleOd = (string) ($rule['tube_od'] ?? ''); }
        }

        // ---- reference-priced leaf (supplier table at 80 %): the variant price replaces base + hardware + front yoke;
        // our catalog still prices a rear flange yoke add-on, the length question is answered from A1
        $ref = $this->referencePrice($cfg, $seriesKey, $material, $a1);
        if ($ref && !empty($ref['error'])) return array_merge($this->zero(), ['breakdown' => ['pricing' => 'reference', 'error' => $ref['error'], 'product' => $ref['product'] ?? null]]);
        if ($ref) {
            [$rearAddon, $rearLabel] = $this->yokeAddon((array) ($cfg['rear_yoke'] ?? []), $this->usedMapFor((string) ($cfg['rear_app'] ?? '')), $series);
            $max = AmPricing::maxLength($ref['product']);   // includes the 88 to 93.50 in band where the product sells it
            $speed = self::speedCheck((string) $ref['product']['od'], $material, self::span($cfg, $a1), (array) ($cfg['speed'] ?? []));
            return [
                'base' => $ref['our_price'], 'tube_addon' => 0.0, 'front_addon' => 0.0, 'rear_addon' => $rearAddon, 'hardware_addon' => 0.0, 'extras' => 0.0,
                'final' => $ref['our_price'] + $rearAddon,
                'breakdown' => [
                    'pricing' => 'reference', 'tube_od' => rtrim(rtrim(number_format((float) $ref['product']['od'], 2, '.', ''), '0'), '.') . '"', 'tier' => 'new', 'material' => $material,
                    'front_label' => $ref['front_label'], 'rear_label' => $rearLabel, 'hardware_label' => $ref['hardware_label'],
                    'max_length' => $max, 'over_max' => $max !== null && $a1 > $max, 'speed' => $speed,
                    'product' => $ref['product'], 'variant' => $ref['variant'], 'options' => $ref['options'], 'am_price' => $ref['am_price'],
                ],
            ];
        }

        // ---- end options: own = 0, used = settings map by series, new = the catalog product's live price
        [$frontAddon, $frontLabel] = $this->yokeAddon((array) ($cfg['front_yoke'] ?? []), $this->usedMapFor((string) ($cfg['front_app'] ?? '')), $series);
        [$rearAddon, $rearLabel]   = $this->yokeAddon((array) ($cfg['rear_yoke'] ?? []), $this->usedMapFor((string) ($cfg['rear_app'] ?? '')), $series);
        [$hwAddon, $hwLabel]       = $this->hardwareAddon((array) ($cfg['hardware'] ?? []));

        $final = $base + $tubeAddon + $frontAddon + $rearAddon + $hwAddon;
        $max = $this->maxLength($hit['duty_key'], $seriesKey, $material);
        $speed = self::speedCheck($tubeOd ?: $ruleOd, $material, self::span($cfg, $a1), (array) ($cfg['speed'] ?? []));

        return [
            'base' => $base, 'tube_addon' => $tubeAddon, 'front_addon' => $frontAddon, 'rear_addon' => $rearAddon,
            'hardware_addon' => $hwAddon, 'extras' => 0.0, 'final' => $final,
            'breakdown' => [
                'tube_od' => $tubeOd ?: $ruleOd, 'tier' => $tier, 'material' => $material,
                'front_label' => $frontLabel, 'rear_label' => $rearLabel, 'hardware_label' => $hwLabel,
                'max_length' => $max, 'over_max' => $max !== null && $a1 > $max,
                'certified_base' => $certifiedBase, 'new_base' => $brandNewBase,
                'speed' => $speed,
            ],
        ];
    }

    /**
     * Critical speed of the tube as a simply supported shaft, Nc = 4.705e6 * sqrt(OD^2 + ID^2) / L^2 (RPM; inches),
     * the usual first approximation for steel and aluminum alike (their stiffness-to-weight ratios are about the
     * same). Max safe operating speed = 75 % of Nc. With the vehicle numbers, shaft RPM = mph * ratio * 336 / tire.
     * Walls and constants come from the tree file (storage/app/builder-tree.json → engineering).
     */
    /** Length for the critical speed: u-joint centre to centre (C1) when given and shorter than A1; A1 otherwise (errs safe). */
    private static function span(array $cfg, float $a1): float
    {
        $c1 = (float) ($cfg['c1'] ?? 0);
        return $c1 > 0 && $c1 < $a1 ? $c1 : $a1;
    }

    public static function speedCheck(string $tubeOd, string $material, float $length, array $in): ?array
    {
        $od = (float) preg_replace('/[^0-9.]/', '', $tubeOd);
        if ($od <= 0 || $length <= 0) return null;
        $eng = (array) (\App\Http\Controllers\DsbController::flow()['engineering'] ?? []);
        $wall = (float) (($eng['tube_walls'] ?? [])[$material] ?? ($material === 'aluminum' ? 0.125 : 0.083));
        $k = (float) ($eng['critical_speed_k'] ?? 4705000); $frac = (float) ($eng['safe_fraction'] ?? 0.75);
        $id = max(0.1, $od - 2 * $wall);
        $nc = $k * sqrt($od * $od + $id * $id) / ($length * $length);
        $out = ['tube_od' => $od, 'wall' => $wall, 'tube_id' => round($id, 3), 'length' => $length, 'critical_rpm' => (int) round($nc), 'safe_rpm' => (int) round($nc * $frac), 'status' => null, 'material' => $material];
        $mph = (float) ($in['mph'] ?? 0); $ratio = (float) ($in['ratio'] ?? 0); $tire = (float) ($in['tire'] ?? 0);
        if ($mph > 0 && $ratio > 0 && $tire > 0) {
            $rpm = $mph * $ratio * 336 / $tire;
            $out += ['mph' => $mph, 'ratio' => $ratio, 'tire' => $tire, 'shaft_rpm' => (int) round($rpm)];
            $out['status'] = $rpm <= $nc * $frac ? 'ok' : ($rpm <= $nc * 0.9 ? 'warn' : 'bad');
        }
        return $out;
    }

    /**
     * Price a leaf from the supplier table when it covers family + series + material. Returns null when it does not
     * (our own engine prices the build), an ['error' => …] when the chosen options or the length do not fit a
     * variant (the build is refused rather than guessed), else the variant with our price.
     */
    private function referencePrice(array $cfg, string $seriesKey, string $material, float $a1): ?array
    {
        $family = (string) ($cfg['family'] ?? '');
        if ($family === '' || $a1 <= 0 && !$this->am->resolve($family, $seriesKey, $material, 0)) return null;
        $product = $this->am->resolve($family, $seriesKey, $material, $a1);
        if (!$product) {
            // the family is in the table but no tube reaches this length: say so instead of pricing a shorter tube
            return $this->am->resolve($family, $seriesKey, $material, 0) ? ['error' => 'over_max', 'product' => $this->am->resolve($family, $seriesKey, $material, 0)] : null;
        }
        $chosen = (array) ($cfg['am_options'] ?? []);
        $values = [];
        foreach ($product['options'] ?? [] as $o) {
            $name = $o['name'];
            if (AmPricing::lengthOption($product) === $o) {
                // the length question ("88 in or over, seal to flange face") is always answered from the measured length:
                // whatever the browser sends for it is ignored, so a long shaft can never be priced in the short band
                $implied = $a1 > 0 ? AmPricing::lengthAnswer($o, $a1) : null;
                if ($a1 > 0 && $implied === null) return ['error' => 'over_max', 'product' => $product];
                $values[$name] = $implied ?? $o['values'][0];
            } else {
                $values[$name] = $chosen[$name] ?? (count($o['values']) === 1 ? $o['values'][0] : $o['values'][0]); // first value = the plain build until the customer picks
            }
        }
        $variant = $this->am->variant($product, $values);
        if (!$variant) return ['error' => 'variant', 'product' => $product];
        $build = implode(' / ', $values);
        return [
            'product' => ['handle' => $product['handle'], 'title' => $product['title'], 'series' => $product['series'], 'material' => $product['material'], 'od' => $product['od'], 'max_len' => $product['max_len'], 'options' => $product['options'], 'delay' => $product['delay'] ?? null, 'slug' => AmPricing::slug($product)],
            'variant' => ['id' => $variant['id'], 'sku' => $variant['sku'], 'options' => $variant['options']],
            'options' => $values, 'am_price' => $variant['am_price'], 'our_price' => (float) $variant['our_price'],
            'front_label' => preg_match('/send in/i', $build) ? 'Customer\'s own yoke (credit)' : 'Included with the shaft',
            'hardware_label' => implode('; ', array_filter(array_map(fn ($o, $v) => preg_match('/hardware|kit/i', $o['name'] . ' ' . $v) ? $v : null, $product['options'] ?? [], array_values($values)))),
        ];
    }

    private function zero(): array
    {
        return ['base' => 0.0, 'tube_addon' => 0.0, 'front_addon' => 0.0, 'rear_addon' => 0.0, 'hardware_addon' => 0.0, 'extras' => 0.0, 'final' => 0.0, 'breakdown' => []];
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

    /**
     * Longest shaft (A1, inches) the pricing tables can build in a series and material: the largest tube's limit,
     * capped by the steel limit table. Null when no table limits the series (priced by the tree alone).
     */
    public function maxLength(string $dutyKey, string $seriesKey, string $material): ?float
    {
        $series = strtolower($seriesKey);
        if ($material === 'aluminum' && !$this->aluminumEligible($dutyKey, $seriesKey)) $material = 'steel';
        $rows = $this->odRows($series, $material);
        $max = 0.0;
        foreach ($rows as $r) $max = max($max, (float) ($r['max_a1'] ?? 0));
        if ($material === 'steel' && (float) ($this->s['material_steel_limits'][$series] ?? 0) > 0) {
            $lim = (float) $this->s['material_steel_limits'][$series];
            $max = $max > 0 ? min($max, $lim) : $lim;
        }
        return $max > 0 ? $max : null;
    }

    /** Max length per material for every light/medium series — handed to the browser for the material step. */
    public function maxLengths(): array
    {
        $out = [];
        foreach ((array) ($this->s['tree'] ?? []) as $dutyKey => $duty) {
            $all = (array) ($duty['series'] ?? []);
            foreach ((array) ($duty['subcategories'] ?? []) as $sc) $all += (array) ($sc['series'] ?? []);
            foreach (array_keys($all) as $sk) {
                $out[$sk] = ['steel' => $this->maxLength($dutyKey, (string) $sk, 'steel'), 'aluminum' => $this->aluminumEligible($dutyKey, (string) $sk) ? $this->maxLength($dutyKey, (string) $sk, 'aluminum') : null];
            }
        }
        return $out;
    }

    /** Price of a good used yoke from the shop for a series (0 = not offered). */
    public function usedPrice(string $app, string $seriesKey): float
    {
        return (float) ($this->s[$this->usedMapFor($app)][strtolower($seriesKey)] ?? 0);
    }

    /** The yoke category a builder end picks its new part from: flange ends use flange yokes, slip ends transmission yokes. */
    public static function yokeTypeFor(string $app): ?string
    {
        return match ($app) {
            'flange', 'slip_flange' => 'flange',
            'trans', 'slip_no_flange' => 'trans',
            default => null,
        };
    }

    private function usedMapFor(string $app): string
    {
        return self::yokeTypeFor($app) === 'trans' ? 'used_trans_prices' : 'used_flange_prices';
    }

    private function odRows(string $series, string $material): array
    {
        $map = $material === 'aluminum' ? ($this->s['aluminum_tube_od_pricing'] ?? []) : ($this->s['tube_od_pricing'] ?? []);
        return array_values((array) ($map[$series] ?? []));
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
        if ($kind === 'own') return [0.0, 'Customer\'s own yoke'];
        if ($kind === 'used') {
            $price = (float) ($this->s[$usedMapKey][$series] ?? 0);
            return [$price, 'Good used yoke from our shop' . ($price > 0 ? ' (+$' . number_format($price, 2) . ')' : '')];
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

    private function hardwareAddon(array $hw): array
    {
        $kind = $hw['kind'] ?? 'none';
        if ($kind === 'own') return [0.0, 'Customer\'s own hardware'];
        if (($kind === 'strap' || $kind === 'ubolt') && (int) ($hw['product_id'] ?? 0) > 0) {
            $pid = (int) $hw['product_id'];
            $price = $this->catalog->price($pid);
            $name = $this->catalog->productName($pid) ?? 'Kit #' . $pid;
            return [$price, $name . ' (+$' . number_format($price, 2) . ')'];
        }
        return [0.0, ''];
    }
}
