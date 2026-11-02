<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Live shipping rates via EasyPost — the shop's own carrier wallet (UPS DAP, FedEx,
 * USPS, DHL Express). Every connected carrier prices the parcel at once and the
 * cheapest six are offered.
 *
 * Freight is deliberately held back below FREIGHT_MIN_LB: under that weight a parcel
 * service is always the right answer, and an LTL quote in front of a customer buying
 * one u-joint is noise.
 */
class EasyPostService
{
    private const SHIPMENTS = 'https://api.easypost.com/v2/shipments';
    private const ORDERS = 'https://api.easypost.com/v2/orders';

    private const FROM = [
        'company' => 'Universal Driveshafts',
        'street1' => '11611 Prospect Drive STE 4',
        'city' => 'Odessa',
        'state' => 'FL',
        'zip' => '33556',
        'country' => 'US',
        'phone' => '8135246491',
    ];

    private const DIMS = ['length' => 48, 'width' => 8, 'height' => 8];
    private const DEFAULT_WEIGHT_LB = 15;
    private const MAX_RATES = 6;

    /** At and above this the shop ships freight, so freight services are offered alongside parcel. */
    private const FREIGHT_MIN_LB = 100;

    /** Service codes the generic camel-case/underscore splitter gets wrong. */
    private const SERVICE_LABELS = [
        'UPSGroundsaverGreaterThan1lb' => 'Ground Saver',
        'UPSGroundsaverLessThanOrEqualTo1lb' => 'Ground Saver',
        // international: the carrier is already in front of the service
        'ExpressWorldwideNonDoc' => 'Worldwide',
        'ExpressWorldwideDoc' => 'Worldwide (documents)',
        'Expedited' => 'Worldwide Expedited',
        'UPSSaver' => 'Worldwide Saver',
        'Express' => 'Worldwide Express',
        'ExpressPlus' => 'Worldwide Express Plus',
        'UPSStandard' => 'Standard',
        'SMART_POST' => 'Ground Economy',
        'GroundAdvantage' => 'Ground Advantage',
    ];

    private const CARRIER_LABELS = [
        'UPSDAP' => 'UPS',
        'UPS' => 'UPS',
        'FedExDefault' => 'FedEx',
        'FedEx' => 'FedEx',
        'USPS' => 'USPS',
        'DhlExpress' => 'DHL Express',
        'DhlEcs' => 'DHL eCommerce',
        'CanadaPost' => 'Canada Post',
    ];

    public function enabled(): bool
    {
        return (bool) config('services.easypost.enabled') && config('services.easypost.key');
    }

    /** Live rates to any destination. Returns [ ['id','label','cost'], ... ] cheapest-first, or [] on failure. */
    public function rates(string $toState, string $toZip, string $toCountry = 'US', float $weightLb = 0, array $boxes = [], array $dest = []): array
    {
        if (!$this->enabled() || $toZip === '') return [];
        $shape = $this->shape($boxes, $weightLb);
        $to = $this->toAddress($toState, $toZip, $toCountry, $dest);
        $req = $this->request($to, $shape['parcels']);
        return $this->best([$this->rows($this->post($req), $to, $shape)], false);
    }

    /**
     * Price a packed shipment every way it can actually travel. A palletised load is also
     * priced as the loose parcels it came from, because freight is not automatically the
     * cheaper answer — ten tubes to California are $810 as parcels and $2,027 on a pallet —
     * and the customer is shown whichever wins. Both shapes are quoted concurrently, so the
     * checkout waits for the slower one rather than for both.
     *
     * @param  array $pack  a PackingService::packageSummary() result
     */
    public function ratesForPack(array $pack, string $toState, string $toZip, string $toCountry = 'US', array $dest = []): array
    {
        if (!$this->enabled() || $toZip === '') return [];
        $to = $this->toAddress($toState, $toZip, $toCountry, $dest);

        $shapes = ['packed' => $this->shape($pack['boxes'], (float) $pack['weight_lb'])];
        if (!empty($pack['loose_boxes'])) {
            $shapes['loose'] = $this->shape($pack['loose_boxes'], array_sum(array_column($pack['loose_boxes'], 'weight')));
        }
        if (count($shapes) === 1) {
            $one = $shapes['packed'];
            return $this->best([$this->rows($this->post($this->request($to, $one['parcels'])), $to, $one)], true);
        }

        try {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($k) => $this->pooled($pool->as($k), $this->request($to, $shapes[$k]['parcels'])),
                array_keys($shapes)
            ));
        } catch (\Throwable $e) {
            Log::warning('EasyPost rates pool failed: ' . $e->getMessage());
            \App\Support\CarrierHealth::failure('EasyPost', $e->getMessage());
            return [];
        }
        $sets = [];
        foreach ($shapes as $k => $shape) $sets[] = $this->rows($responses[$k] ?? null, $to, $shape);
        return $this->best($sets, true);
    }

    /* ---------- building one request ---------- */

    /**
     * What is actually going on the truck: one entry per parcel, each with the box it is
     * really packed in, plus the totals the freight rules are judged against.
     */
    private function shape(array $boxes, float $weightLb): array
    {
        $parcels = [];
        foreach ($boxes as $b) {
            $w = (float) ($b['weight'] ?? 0);
            if ($w <= 0) continue;
            $parcels[] = ['weight' => $w, 'dims' => $this->dims($b['dims'] ?? null)];
        }
        if (!$parcels) $parcels = [['weight' => $weightLb > 0 ? $weightLb : self::DEFAULT_WEIGHT_LB, 'dims' => self::DIMS]];
        return [
            'parcels' => $parcels,
            'total_lb' => array_sum(array_column($parcels, 'weight')),
            'pallets' => count(array_filter($boxes, fn ($b) => ($b['kind'] ?? '') === 'pallet')),
        ];
    }

    /**
     * The real street and city when the customer has typed them — FedEx prices a home
     * (Home Delivery) above a business (Ground), and only the address tells them apart.
     * A business is a delivery with a company name; anything else is quoted as a home,
     * so the price shown is never below what the label will cost.
     */
    private function toAddress(string $toState, string $toZip, string $toCountry, array $dest = []): array
    {
        $toCountry = strtoupper($toCountry ?: 'US');
        $street = trim((string) ($dest['street'] ?? ''));
        return array_filter([
            'street1' => $street !== '' ? mb_substr($street, 0, 60) : 'Address',
            'city' => trim((string) ($dest['city'] ?? '')) ?: null,
            'company' => trim((string) ($dest['company'] ?? '')) ?: null,
            'state' => $toCountry === 'US' ? strtoupper($toState) : '',
            'zip' => $toZip,
            'country' => $toCountry,
            'residential' => $toCountry === 'US' ? trim((string) ($dest['company'] ?? '')) === '' : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** One parcel is a Shipment; several are an Order, which sums per service across them. */
    private function request(array $to, array $parcels): array
    {
        $accounts = $this->carrierAccounts(($to['country'] ?? 'US') === 'US');
        if (count($parcels) > 1) {
            return [self::ORDERS, ['order' => array_filter([
                'to_address' => $to,
                'from_address' => self::FROM,
                'shipments' => array_map(fn ($p) => ['parcel' => $this->parcel($p)], $parcels),
                'carrier_accounts' => $accounts ?: null,
            ])]];
        }
        return [self::SHIPMENTS, ['shipment' => array_filter([
            'to_address' => $to,
            'from_address' => self::FROM,
            'parcel' => $this->parcel($parcels[0]),
            'carrier_accounts' => $accounts ?: null,
        ])]];
    }

    /**
     * Only the carriers that can actually serve the lane are asked. DHL eCommerce, Canada Post
     * and Asendia answer a domestic quote with an error every time — they cost latency and fill
     * the log with noise for rates that never appear.
     *
     * @return array<int,string> EasyPost carrier-account ids
     */
    private function carrierAccounts(bool $domestic): array
    {
        $wanted = $domestic ? ['ups', 'fedex'] : ['ups', 'fedex', 'dhl_express'];
        $all = app(EasyPostLabelService::class)->carrierAccounts();
        $out = [];
        foreach ($wanted as $slug) {
            if (!empty($all[$slug]['id'])) $out[] = $all[$slug]['id'];
        }
        return $out;
    }

    /** EasyPost weighs in ounces, and takes the longest side as the length. */
    private function parcel(array $p): array
    {
        return $p['dims'] + ['weight' => round($p['weight'] * 16, 2)];
    }

    /** @return array{length:float,width:float,height:float} */
    private function dims(?array $d): array
    {
        $d = array_values(array_filter(array_map('floatval', $d ?: []), fn ($v) => $v > 0));
        if (count($d) !== 3) return self::DIMS;
        rsort($d);
        return ['length' => $d[0], 'width' => $d[1], 'height' => $d[2]];
    }

    private function post(array $req)
    {
        [$url, $payload] = $req;
        try {
            return Http::withBasicAuth((string) config('services.easypost.key'), '')
                ->acceptJson()->timeout(25)->asJson()->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('EasyPost rates failed: ' . $e->getMessage());
            \App\Support\CarrierHealth::failure('EasyPost', $e->getMessage());
            return null;
        }
    }

    private function pooled($pool, array $req)
    {
        [$url, $payload] = $req;
        return $pool->withBasicAuth((string) config('services.easypost.key'), '')
            ->acceptJson()->timeout(25)->asJson()->post($url, $payload);
    }

    /* ---------- reading one response ---------- */

    /** One shape's rates: parsed, freight-filtered and labelled with how the load travels. */
    private function rows($resp, array $to, array $shape): array
    {
        $rows = $this->parseRates($resp, $to, $shape['total_lb']);

        // Freight belongs in front of a customer once the load is actually freight: at or above
        // FREIGHT_MIN_LB, or when the box is so big that no carrier will take it as a parcel at
        // all (over the 165" length-plus-girth limit, only freight comes back).
        $parcelRows = array_values(array_filter($rows, fn ($r) => !$r['freight']));
        if ($shape['total_lb'] < self::FREIGHT_MIN_LB && $parcelRows) $rows = $parcelRows;

        $n = count($shape['parcels']);
        if ($shape['pallets'] || $n > 1) {
            $suffix = $shape['pallets']
                ? ' · ' . ($shape['pallets'] === 1 ? 'on a pallet' : $shape['pallets'] . ' pallets')
                : ' · ' . $n . ' packages';
            // the packed (pallet) and loose shapes are both offered, so the same carrier service shows up twice:
            // the id has to say which, or the checkout charges whichever row happens to come first
            $idSuffix = $shape['pallets'] ? '-pallet' : '';
            $rows = array_map(function ($r) use ($n, $suffix, $idSuffix) {
                $r['packages'] = $n;
                $r['id'] .= $idSuffix;
                $r['label'] .= $suffix;
                return $r;
            }, $rows);
        }
        return $rows;
    }

    /**
     * Merge every shape's rates into the list the customer sees: cheapest first, one row per
     * label, capped. When the load palletises, one freight option is always kept even if
     * parcels undercut it — it is the faster service and the way the shop would rather ship.
     *
     * @param  array<int,array<int,array>> $sets
     */
    private function best(array $sets, bool $keepFreight): array
    {
        $all = array_merge(...$sets);
        usort($all, fn ($a, $b) => $a['cost'] <=> $b['cost']);
        $seen = [];
        $all = array_values(array_filter($all, function ($r) use (&$seen) {
            if (isset($seen[$r['label']])) return false;
            $seen[$r['label']] = true;
            return true;
        }));
        $kept = array_slice($all, 0, self::MAX_RATES);
        if ($keepFreight && !array_filter($kept, fn ($r) => $r['freight'])
            && ($freight = array_values(array_filter($all, fn ($r) => $r['freight'])))) {
            if (count($kept) >= self::MAX_RATES) array_pop($kept);
            $kept[] = $freight[0];
        }
        return $kept;
    }

    /** Turns a Shipment/Order response into rate rows, or [] with the reason logged. */
    private function parseRates($resp, array $to, float $weightLb): array
    {
        if (!$resp) return [];
        $context = json_encode(['zip' => $to['zip'] ?? null, 'state' => $to['state'] ?? null, 'country' => $to['country'] ?? null, 'lb' => round($weightLb, 1)]);
        if (!$resp->successful()) {
            Log::warning('EasyPost rates ' . $resp->status() . ' for ' . $context . ': ' . Str::limit((string) ($resp->json('error.message') ?: $resp->body()), 300));
            if ($resp->status() >= 500 || in_array($resp->status(), [401, 403, 429], true)) \App\Support\CarrierHealth::failure('EasyPost', 'HTTP ' . $resp->status());
            return [];
        }
        $rates = $resp->json('rates');
        if (!is_array($rates) || !$rates) {
            $why = collect($resp->json('messages') ?: [])->map(fn ($m) => ($m['carrier'] ?? '?') . ': ' . Str::limit((string) ($m['message'] ?? ''), 80))->implode(' | ');
            Log::warning('EasyPost returned no rates for ' . $context . ($why ? ' — ' . $why : ''));
            return [];
        }

        $out = [];
        foreach ($rates as $r) {
            $cost = round((float) ($r['rate'] ?? 0), 2);
            if ($cost <= 0) continue;
            if (self::excluded((string) ($r['service'] ?? ''))) continue;
            $carrier = $this->carrierLabel((string) ($r['carrier'] ?? ''));
            $service = $this->serviceLabel((string) ($r['service'] ?? ''));
            // several codes carry the carrier themselves ("UPSSaver") — don't say it twice
            $service = preg_replace('/^' . preg_quote($carrier, '/') . '\s+/i', '', $service);
            $out[] = [
                'id' => Str::slug(($r['carrier'] ?? '') . '_' . ($r['service'] ?? '')),
                'label' => trim($carrier . ' ' . $service),
                'cost' => $cost,
                'freight' => stripos((string) ($r['service'] ?? ''), 'freight') !== false,
                // business days in transit, as the carrier quotes it
                'days' => isset($r['delivery_days']) && (int) $r['delivery_days'] > 0 ? (int) $r['delivery_days'] : null,
            ];
        }
        return $out;
    }

    /** A service the shop does not sell (config uds.shipping_exclude.services). */
    public static function excluded(string $service): bool
    {
        foreach ((array) config('uds.shipping_exclude.services', []) as $needle) {
            if ($needle !== '' && stripos($service, (string) $needle) !== false) return true;
        }
        return false;
    }

    private function carrierLabel(string $carrier): string
    {
        return self::CARRIER_LABELS[$carrier] ?? $this->humanize(preg_replace('/(Default|Account)$/', '', $carrier));
    }

    private function serviceLabel(string $service): string
    {
        if (isset(self::SERVICE_LABELS[$service])) return self::SERVICE_LABELS[$service];
        if (str_contains($service, '_')) {
            // FEDEX_2_DAY_AM → 2 Day AM; the carrier name is already in front of it
            $s = preg_replace('/^(FEDEX|UPS|USPS|DHL)_/', '', $service);
            return preg_replace_callback('/\b[A-Z]{2,}\b/', fn ($m) => $m[0], Str::title(str_replace('_', ' ', $s)));
        }
        return $this->humanize($service);
    }

    /** "2ndDayAirAM" → "2nd Day Air AM", "UPSStandard" → "UPS Standard". */
    private function humanize(string $s): string
    {
        return trim(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $s));
    }
}
