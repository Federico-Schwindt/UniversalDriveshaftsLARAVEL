<?php

namespace App\Support;

/**
 * Florida sales tax (owner, 2026-11-13: "make the website charge Florida tax automatically, only to Florida customers,
 * and skip it for shops that send us their resale certificate").
 *
 * - Orders delivered to a Florida address pay 6% plus the surtax of the county they are delivered to; a pickup at the
 *   shop pays the shop's county (Pasco). Orders shipped out of Florida pay none: the store is far under every other
 *   state's economic-nexus threshold.
 * - The county surtax applies to the first $5,000 of each item only (Fla. Stat. 212.054(2)(b)).
 * - Shipping is separately stated and avoidable (pickup is offered), so it is not taxed (Rule 12A-1.045); the
 *   accountant can turn that on in Settings (tax.shipping).
 * - An account with an approved, unexpired Florida resale or exemption certificate pays none.
 * Rates and ZIP → county: resources/data/fl-sales-tax.json (DR-15DSS for the calendar year; Census ZCTA files).
 */
class SalesTax
{
    public const PICKUP_COUNTY = 'Pasco';

    public static function data(): array
    {
        static $d = null;
        return $d ??= json_decode((string) file_get_contents(resource_path('data/fl-sales-tax.json')), true);
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('tax.enabled', true);
    }

    public static function taxesShipping(): bool
    {
        return (bool) Settings::get('tax.shipping', false);
    }

    public static function isFloridaZip(string $zip): bool
    {
        return (bool) preg_match('/^3[234]\d{3}$/', substr(preg_replace('/\D/', '', $zip), 0, 5));
    }

    /** The county a Florida ZIP lies in (most of its people, for a ZIP across a county line); null outside Florida. */
    public static function county(string $zip): ?string
    {
        $zip = substr(preg_replace('/\D/', '', $zip), 0, 5);
        if (!self::isFloridaZip($zip)) return null;
        $zips = self::data()['zips'];
        if (isset($zips[$zip])) return $zips[$zip];
        // a post-office-box or company ZIP has no Census area: the nearest ZIP of the same three-digit area
        $best = null; $gap = PHP_INT_MAX;
        foreach ($zips as $z => $c) {
            if (strncmp((string) $z, $zip, 3) === 0 && abs((int) $z - (int) $zip) < $gap) { $gap = abs((int) $z - (int) $zip); $best = $c; }
        }
        return $best;
    }

    /** County surtax in percent (0 for a county without one). */
    public static function surtax(?string $county): float
    {
        return (float) (self::data()['surtax'][$county] ?? 0);
    }

    /** The account's approved certificate that is still valid, or null. */
    public static function certificate(?object $user): ?object
    {
        if (!$user || ($user->tax_exempt_status ?? null) !== 'approved') return null;
        if (!empty($user->tax_cert_expires) && \Carbon\Carbon::parse($user->tax_cert_expires)->endOfDay()->isPast()) return null;
        return $user;
    }

    /**
     * How an order to this place is taxed, or null when it pays no Florida tax.
     * @return array{state: float, surtax: float, rate: float, county: ?string, exempt: bool, shipping: bool}|null
     */
    public static function profile(string $country, string $state, string $zip, bool $pickup, ?object $user = null): ?array
    {
        if (!self::enabled()) return null;
        if ($pickup) {
            $county = self::PICKUP_COUNTY;
        } else {
            $country = strtoupper(trim($country)) ?: 'US';
            $state = strtoupper(trim($state));
            if ($country !== 'US') return null;
            $county = self::county($zip);
            // a Florida address with a ZIP the table does not know still pays the state's 6% (the county is unknown)
            if (!$county && !in_array($state, ['FL', 'FLORIDA'], true)) return null;
        }
        $st = (float) self::data()['state_rate'];
        $sur = self::surtax($county);
        return ['state' => $st, 'surtax' => $sur, 'rate' => $st + $sur, 'county' => $county,
            'exempt' => (bool) self::certificate($user), 'shipping' => self::taxesShipping()];
    }

    /**
     * The tax on these cart lines after an order-level discount, plus shipping when shipping is taxed.
     * @param  array<int, array{price: float, qty: int}>  $items
     */
    public static function amount(?array $profile, array $items, float $discount = 0.0, float $shipping = 0.0): float
    {
        if (!$profile || $profile['exempt']) return 0.0;
        $sub = array_sum(array_map(fn ($i) => (float) $i['price'] * (int) $i['qty'], $items));
        if ($sub <= 0) return 0.0;
        // the discount lowers every line in proportion; the surtax counts the first $5,000 of each piece
        $keep = max(0.0, $sub - $discount) / $sub;
        $cap = (float) (self::data()['surtax_cap'] ?? 5000);
        $tax = 0.0;
        foreach ($items as $i) {
            $unit = (float) $i['price'] * $keep;
            $tax += (int) $i['qty'] * ($unit * $profile['state'] + min($unit, $cap) * $profile['surtax']) / 100;
        }
        if ($profile['shipping']) $tax += $shipping * $profile['rate'] / 100;
        return round($tax + 1e-9, 2);
    }

    /** "FL sales tax 7% (Pasco)" for the totals. */
    public static function label(?array $profile): string
    {
        if (!$profile) return __('Sales tax');
        $pct = rtrim(rtrim(number_format($profile['rate'], 2), '0'), '.');
        return __('FL sales tax :rate%', ['rate' => $pct]) . ($profile['county'] ? ' (' . $profile['county'] . ')' : '');
    }
}
