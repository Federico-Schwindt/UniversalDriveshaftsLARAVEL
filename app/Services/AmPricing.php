<?php

namespace App\Services;

/**
 * Reference pricing for the builder's light-duty leaves: our supplier Action Machine lists a product per
 * (family, u-joint series, material, tube OD) with option variants (rear u-joint hardware, build options, and on
 * some products a length question). We sell the same configurations at MARKDOWN × their price (owner, 2026-11: 15 % below, i.e. 0.85).
 * The table lives in storage/app/builder/am-prices.json (built from their public product JSON, refreshed by
 * `php artisan builder:sync-prices`). Anything not in the table is not priced here: the caller falls back to
 * our own engine or says "call for a quote".
 */
class AmPricing
{
    /** Our price as a share of the supplier's (owner, 2026-11-10: 15 % below). The single place to change it. */
    public const MARKDOWN = 0.85;

    public const FILE = 'builder/am-prices.json';

    private ?array $table = null;

    public function table(): array
    {
        return $this->table ??= (json_decode((string) @file_get_contents(storage_path('app/' . self::FILE)), true) ?: ['products' => [], 'markdown' => self::MARKDOWN]);
    }

    public function save(array $table): void
    {
        $this->table = $table;
        file_put_contents(storage_path('app/' . self::FILE), json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Our prices are always worked out from the supplier's at read time, so a new MARKDOWN applies at once (no re-sync). */
    private static function priced(array $p): array
    {
        foreach ($p['variants'] ?? [] as $id => $v) $p['variants'][$id]['our_price'] = self::ours((float) ($v['am_price'] ?? 0));
        return $p;
    }

    public static function ours(float $amPrice, float $markdown = self::MARKDOWN): float
    {
        return round($amPrice * $markdown, 2);
    }

    /** Every available product of a family, with variants, keyed by handle. */
    public function productsFor(string $family): array
    {
        return array_map([self::class, 'priced'], array_filter($this->table()['products'], fn ($p) => in_array($family, $p['families'] ?? [], true) && ($p['available'] ?? true) && !empty($p['variants'])));
    }

    /**
     * The supplier product for a configuration: same family, series and material, the smallest tube OD whose
     * published max length covers the measured length (0 = any). Null when the table has nothing for it.
     */
    public function resolve(string $family, string $series, string $material, float $length): ?array
    {
        $cands = array_filter($this->productsFor($family), fn ($p) => strtolower((string) $p['series']) === strtolower($series) && ($p['material'] ?? '') === $material && !empty($p['od']));
        usort($cands, fn ($a, $b) => $a['od'] <=> $b['od']);
        foreach ($cands as $p) {
            $max = self::maxLength($p);
            if ($length <= 0 || $max === null || $length <= $max) return $p;
        }
        return null;
    }

    /** family => series => [materials] that the table can price (products with synced variants). */
    public function coverage(): array
    {
        $out = [];
        foreach ($this->table()['products'] as $p) {
            if (!($p['available'] ?? true) || empty($p['variants']) || empty($p['series']) || empty($p['material']) || empty($p['od'])) continue;
            foreach ($p['families'] ?? [] as $f) {
                $out[$f][strtolower($p['series'])][$p['material']] = true;
            }
        }
        foreach ($out as &$bySeries) foreach ($bySeries as &$mats) $mats = array_keys($mats);
        return $out;
    }

    /** Lowest price (ours, markdown applied) per family → series → material: the builder's "from" price before a length is known. */
    public function fromPrices(): array
    {
        $out = [];
        foreach ($this->table()['products'] as $p) {
            if (!($p['available'] ?? true) || empty($p['variants']) || empty($p['series']) || empty($p['material'])) continue;
            $low = null;
            foreach ($p['variants'] as $v) if (($v['available'] ?? true) && !empty($v['am_price'])) $low = min($low ?? INF, self::ours((float) $v['am_price']));
            if ($low === null) continue;
            foreach ($p['families'] ?? [] as $f) {
                $k = strtolower($p['series']);
                $out[$f][$k][$p['material']] = min($out[$f][$k][$p['material']] ?? INF, $low);
            }
        }
        return $out;
    }

    /** Our URL slug of a product page (the supplier handle is unique; our words go in the title). */
    public static function slug(array $product): string
    {
        return \Str::slug((string) $product['handle']);
    }

    /** The product by handle, or null. */
    public function product(string $handle): ?array
    {
        $p = $this->table()['products'][$handle] ?? null;
        return $p && ($p['available'] ?? true) ? self::priced($p) : null;
    }

    /**
     * The variant that matches the chosen option values (one value per product option, in order), or null.
     * An option the customer did not answer takes the product's first value only when that option has a single value.
     */
    public function variant(array $product, array $values): ?array
    {
        $options = $product['options'] ?? [];
        $want = [];
        foreach ($options as $i => $o) {
            $v = $values[$o['name']] ?? ($values[$i] ?? null);
            if ($v === null || $v === '') { if (!empty($o['values']) && (count($o['values']) === 1 || !$values)) $v = $o['values'][0]; else return null; } // no answers at all = the plain build
            if (!in_array($v, $o['values'] ?? [], true)) return null;
            $want[] = $v;
        }
        foreach ($product['variants'] ?? [] as $var) {
            if (($var['available'] ?? true) && array_values($var['options'] ?? []) === $want) return ['our_price' => self::ours((float) $var['am_price'])] + $var;
        }
        return null;
    }

    /** The length question of a product ("Is your driveshaft 88" or over…"), if it has one. */
    /**
     * The longest shaft the product is sold in: its tube's max length, or the top of its long-shaft band when it has one
     * (5 in aluminum 1550/1555: "87.75 in or less" at the base price, "88 to 93.50 in" at a higher one, seal to flange face).
     */
    public static function maxLength(array $product): ?float
    {
        $max = !empty($product['max_len']) ? (float) $product['max_len'] : null;
        if ($o = self::lengthOption($product)) {
            foreach ($o['values'] as $v) {
                if (preg_match_all('/\d+(?:\.\d+)?/', $v, $m)) $max = max($max ?? 0, ...array_map('floatval', $m[0]));
            }
        }
        return $max;
    }

    public static function lengthOption(array $product): ?array
    {
        foreach ($product['options'] ?? [] as $o) {
            if (preg_match('/\b88\b|or over|length/i', (string) $o['name'])) return $o;
        }
        return null;
    }

    /**
     * The length answer the measured length implies, from the option's own values ('87.75" or Less', '88" - 93.50"'):
     * each value is parsed as a range; null when the length fits none (over the product's limit).
     */
    public static function lengthAnswer(array $option, float $length): ?string
    {
        foreach ($option['values'] as $v) {
            $nums = array_map('floatval', preg_match_all('/\d+(?:\.\d+)?/', $v, $m) ? $m[0] : []);
            if (count($nums) >= 2 && $length >= $nums[0] - 0.001 && $length <= $nums[1] + 0.001) return $v;               // "88 - 93.50 Inches"
            if (count($nums) === 1 && preg_match('/or\s*less|and\s*under|or\s*shorter/i', $v) && $length <= $nums[0] + 0.001) return $v; // "87.75 Inch or Less"
            if (count($nums) === 1 && preg_match('/or\s*(over|more|longer)/i', $v) && $length >= $nums[0] - 0.001) return $v;
        }
        return null;
    }
}
