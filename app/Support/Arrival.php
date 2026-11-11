<?php

namespace App\Support;

use App\Services\CartService;
use App\Services\EasyPostService;
use App\Services\PackingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Arrives Thursday, Nov 14": the next ship day (same-day cutoff, weekends) plus the transit days UPS quotes for the
 * cheapest parcel service to the customer's ZIP. The ZIP is the visitor's last one (browser) or, signed in, their
 * saved address or last order (owner, 2026-11-11: "sobre todo para clientes que tienen guardado el zip").
 * Holidays are not counted; the checkout confirms the rate and date.
 */
class Arrival
{
    /** The ZIP a signed-in customer ships to: default saved address, else the last order, else the billing ZIP. */
    public static function savedZip($user): ?string
    {
        if (!$user) return null;
        $z = DB::table('dropship_addresses')->where('user_id', $user->id)->where('kind', '!=', 'dropship')
            ->orderByDesc('is_default')->orderByDesc('id')->value('postcode');
        if (!$z) {
            $o = DB::table('orders')->where('user_id', $user->id)->orderByDesc('id')->first(['shipping_postcode', 'billing_postcode']);
            $z = $o ? ($o->shipping_postcode ?: $o->billing_postcode) : null;
        }
        $z = substr(preg_replace('/\D/', '', (string) ($z ?: ($user->billing_postcode ?? ''))), 0, 5);
        return strlen($z) === 5 ? $z : null;
    }

    /**
     * Arrival for these cart-shaped items (type, wp_product_id, qty, price) to a US ZIP, or null when the carrier gives
     * no transit time. The carrier answer is cached per items + ZIP for 6 hours; the ship day is worked out every time.
     *
     * @return array{date: Carbon, days: int, service: string, zip: string}|null
     */
    public static function forItems(array $items, string $zip): ?array
    {
        if (!preg_match('/^\d{5}$/', $zip) || !$items) return null;
        $key = 'arrive:' . md5(json_encode(array_map(fn ($i) => [$i['type'] ?? 'product', $i['wp_product_id'] ?? null, (int) ($i['qty'] ?? 1)], $items))) . ':' . $zip;
        $rate = Cache::get($key);
        if (!$rate) {
            try {
                $rates = app(EasyPostService::class)->ratesForPack(app(PackingService::class)->packageSummary($items), '', $zip, 'US');
            } catch (\Throwable $e) {
                Log::warning('arrival: ' . $e->getMessage());
                return null;
            }
            // the cheapest parcel service is the one most orders ship with (UPS Ground)
            $with = array_values(array_filter($rates, fn ($r) => !empty($r['days']) && empty($r['freight'])));
            usort($with, fn ($a, $b) => $a['cost'] <=> $b['cost']);
            if (!$with) return null;   // a carrier outage or no transit time: never cached
            $rate = ['service' => preg_replace('/ · .*$/u', '', (string) $with[0]['label']), 'days' => (int) $with[0]['days']];
            Cache::put($key, $rate, 6 * 3600);
        }
        $d = CartService::shipDate()->copy();
        for ($i = 0; $i < $rate['days']; $i++) { do { $d->addDay(); } while ($d->isWeekend()); }
        return ['date' => $d, 'days' => $rate['days'], 'service' => $rate['service'], 'zip' => $zip];
    }

    /** "Thursday, Nov 14" in the visitor's language. */
    public static function format(Carbon $d): string
    {
        $loc = app()->getLocale();
        $fmt = ['es' => 'l j \\d\\e F', 'fr' => 'l j F', 'zh' => 'n月j日 l', 'ja' => 'n月j日(D)'][$loc] ?? 'l, M j';
        return $d->copy()->locale($loc)->translatedFormat($fmt);
    }
}
