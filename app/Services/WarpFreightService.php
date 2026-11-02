<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LTL freight rates through Warp's marketplace, for the loads that leave on a pallet.
 *
 * EasyPost sells no LTL at all — the only heavy service it can price is FedEx *Express*
 * Freight, which quoted $1,732 for a pallet the shop actually shipped for $332. Warp
 * quotes the real LTL market, FedEx Freight included, so a palletised order is priced
 * the way it is really going to travel.
 *
 * Two traps this class exists to avoid:
 *  - /ltl/quote silently substitutes a dedicated truck when a lane has no LTL service
 *    and answers with an FTL price (it returned $7,644 for that same pallet). Only
 *    /ltl/market-options is used here, and only its per-carrier LTL options.
 *  - the keyless endpoint prices through a shared account, so its rates are indicative.
 *    They are good enough to charge from, but booking happens outside this code.
 */
class WarpFreightService
{
    private const BASE = 'https://www.wearewarp.com/api/v1';
    private const FROM_ZIP = '33556';
    private const TIMEOUT = 45;
    private const CACHE_MINUTES = 60;

    /** NMFC density bands as [min lb per cubic foot, freight class] — a list, because PHP
     *  array keys cannot hold the 22.5 / 13.5 / 10.5 boundaries. */
    private const CLASS_BY_DENSITY = [
        [50, 50], [35, 55], [30, 60], [22.5, 65], [15, 70], [13.5, 85], [12, 92.5],
        [10.5, 100], [9, 110], [8, 125], [7, 150], [6, 175], [5, 200], [4, 250], [3, 300], [2, 400],
    ];

    /** Which LTL carriers the shop sells — Admin → Settings → Shipping ("LTL carriers"). */
    public const CARRIER_MODES = [
        'fedex' => 'FedEx Freight only',
        'fedex_first' => 'FedEx Freight first; other carriers only where FedEx doesn\'t serve the lane',
        'all' => 'All carriers, cheapest first',
        'no_fedex' => 'Every carrier except FedEx, cheapest first',   // owner, 2026-10-24
        'chosen' => 'Only Warp, Old Dominion, Estes, XPO, Saia and Forward Air, cheapest first',   // owner, 2026-10-25
    ];

    public static function carrierMode(): string
    {
        $m = (string) \App\Support\Settings::get('freight.carriers', 'fedex');
        return array_key_exists($m, self::CARRIER_MODES) ? $m : 'fedex';
    }

    /** Plain-English list of the LTL carriers customers are offered, for the assistant and help text. */
    public static function carrierSummary(): string
    {
        return match (self::carrierMode()) {
            'fedex' => 'FedEx Freight',
            'fedex_first' => 'FedEx Freight, or another LTL carrier where FedEx Freight does not serve the lane',
            'all' => 'several LTL trucking carriers, cheapest first',
            'no_fedex' => 'several LTL trucking carriers (not FedEx), cheapest first',
            default => 'LTL trucking carriers such as Old Dominion, Estes, XPO, Saia, Forward Air and Warp, cheapest first',
        };
    }

    /** The carriers the owner picked for LTL (2026-10-25; Forward Air added the same day), matched on Warp's carrier names. */
    private const CHOSEN = ['/^WARP\b/', '/^OLD DOMINION/', '/^ESTES/', '/^XPO/', '/^SAIA/', '/^FORWARD AIR/'];

    private static function isChosen(?string $name): bool
    {
        $n = strtoupper(trim((string) $name));
        foreach (self::CHOSEN as $re) if (preg_match($re, $n)) return true;
        return false;
    }

    private static function isFedex(?string $name): bool
    {
        return str_contains(strtoupper((string) $name), 'FEDEX FREIGHT');
    }

    public function enabled(): bool
    {
        return (bool) config('services.warp.enabled');
    }

    /**
     * LTL options for a palletised shipment, cheapest first.
     *
     * @param  array $pack  a PackingService::packageSummary() result
     * @return array<int,array{id:string,label:string,cost:float,freight:bool,carrier:string,transit_days:?int}>
     */
    /**
     * How the truck has to deliver, chosen by the customer at checkout. A freight quote with
     * no accessorials assumes a business with a loading dock; a home or a shop without a dock
     * adds a liftgate (and a residential surcharge) — $452 became $750 on one test lane.
     */
    public const DELIVERY = [
        'dock' => [],
        'liftgate' => ['liftgate-delivery'],
        'residential' => ['residential-delivery', 'liftgate-delivery'],
    ];

    public function rates(array $pack, string $toZip, string $toCountry = 'US', string $delivery = 'residential'): array
    {
        $pallets = array_values(array_filter($pack['boxes'] ?? [], fn ($b) => ($b['kind'] ?? '') === 'pallet'));
        if (!$this->enabled() || !$pallets || strtoupper($toCountry ?: 'US') !== 'US') return [];
        $toZip = substr(preg_replace('/\D/', '', $toZip), 0, 5);
        if (strlen($toZip) !== 5) return [];

        // Decks can differ (a long tube deck plus a standard one): quote the largest, since
        // that is what the carrier has to find room for.
        $dims = [
            max(array_map(fn ($p) => (float) $p['dims'][0], $pallets)),
            max(array_map(fn ($p) => (float) $p['dims'][1], $pallets)),
            max(array_map(fn ($p) => (float) $p['dims'][2], $pallets)),
        ];
        $weight = round(array_sum(array_column($pallets, 'weight')) / count($pallets), 2);
        $body = [
            'origin_zip' => self::FROM_ZIP,
            'destination_zip' => $toZip,
            'pickup_date' => $this->pickupDate(),
            'pallets' => count($pallets),
            'weight_lbs_per_pallet' => (int) ceil($weight),
            'mode' => 'LTL',
            'commodity' => 'Driveshafts and tubing',
            'length_in' => (int) ceil((float) $dims[0]),
            'width_in' => (int) ceil((float) $dims[1]),
            'height_in' => (int) ceil((float) $dims[2]),
            'freight_class' => (string) $this->freightClass($weight, $dims),
        ];
        // unknown answers are priced the expensive way: a quote must never be below the bill
        $acc = self::DELIVERY[$delivery] ?? self::DELIVERY['residential'];
        if ($acc) $body['accessorials'] = ['delivery' => $acc];

        // When Warp's live comparison times out it answers with "the most recent spread for this
        // lane" (cached: true) — a stale price that ignores the accessorials asked for (a
        // residential + liftgate request came back at the dock price). Only a live answer is
        // kept for the hour; a stale one is retried a moment later instead of being sold.
        $key = 'warp:' . md5(json_encode($body));
        $options = Cache::get($key);
        if ($options === null) {
            [$options, $stale] = $this->marketOptions($body);
            if ($stale && !empty($body['accessorials'])) {
                [$retry, $stale] = $this->marketOptions($body);
                if ($retry && !$stale) $options = $retry;
            }
            if ($options) Cache::put($key, $options, $stale ? now()->addMinutes(2) : now()->addMinutes(self::CACHE_MINUTES));
        }
        if (!$options) return [];

        // Which carriers the customer is offered is the shop's call (Settings → LTL carriers).
        $fedex = array_values(array_filter($options, fn ($o) => self::isFedex($o['carrier_name'] ?? '')));
        usort($options, fn ($a, $b) => (float) ($a['price_usd'] ?? 0) <=> (float) ($b['price_usd'] ?? 0));
        $use = match (self::carrierMode()) {
            'fedex' => $fedex,                                        // no FedEx on the lane: no LTL offer at all
            'fedex_first' => $fedex ?: array_slice($options, 0, 2),
            // every bookable carrier but FedEx, cheapest first (owner: "elimina fedex y lista todos los demás")
            'no_fedex' => array_values(array_filter($options, fn ($o) => !self::isFedex($o['carrier_name'] ?? ''))),
            'chosen' => array_values(array_filter($options, fn ($o) => self::isChosen($o['carrier_name'] ?? ''))),
            default => array_slice($options, 0, 3),
        };
        if (!$use) return [];

        $out = [];
        // a carrier listed twice (e.g. Estes standard and guaranteed) says which service each one is
        $seen = array_count_values(array_map(fn ($o) => strtoupper((string) ($o['carrier_name'] ?? '')), $use));
        foreach ($use as $o) {
            $cost = round((float) ($o['price_usd'] ?? 0), 2);
            if ($cost <= 0) continue;
            $carrier = $this->carrierLabel((string) ($o['carrier_name'] ?? 'Freight'));
            $svc = trim((string) ($o['service_level'] ?? ''));
            $name = ($seen[strtoupper((string) ($o['carrier_name'] ?? ''))] ?? 1) > 1 && $svc !== '' ? $carrier . ' ' . $this->carrierLabel($svc) : $carrier;
            $out[] = [
                'id' => 'warp-' . Str::slug(($o['carrier_name'] ?? 'freight') . '-' . ($o['service_level'] ?? '')),
                'label' => $name . ' · ' . (count($pallets) === 1 ? 'on a pallet' : count($pallets) . ' pallets'),
                'cost' => $cost,
                'freight' => true,
                'carrier' => $carrier,
                'transit_days' => isset($o['transit_days']) ? (int) $o['transit_days'] : null,
                'days' => isset($o['transit_days']) ? (int) $o['transit_days'] : null,
                'delivery' => $delivery,
            ];
        }
        usort($out, fn ($a, $b) => $a['cost'] <=> $b['cost']);
        return $out;
    }

    /** "FEDEX FREIGHT ECONOMY" → "FedEx Freight Economy". */
    private function carrierLabel(string $name): string
    {
        // the names customers know, not the carriers' legal names
        $known = ['/^WARP\b.*/' => 'Warp', '/^OLD DOMINION.*/' => 'Old Dominion Freight Line', '/^ESTES( EXPRESS( LINES)?)?$/' => 'Estes Express Lines',
            '/^XPO.*/' => 'XPO Logistics', '/^SAIA.*/' => 'Saia LTL Freight'];
        $u = strtoupper(trim($name));
        foreach ($known as $re => $label) if (preg_match($re, $u)) return $label;
        $t = Str::title(strtolower(trim($name)));
        return str_replace('Fedex', 'FedEx', $t);
    }

    /**
     * Only the per-carrier LTL comparison is read. /ltl/quote is deliberately not used:
     * on a lane with no LTL service it answers with a dedicated-truck price instead.
     */
    /** @return array{0:array,1:bool} [options, stale] — stale when Warp served a cached lane spread */
    private function marketOptions(array $body): array
    {
        try {
            $http = Http::acceptJson()->asJson()->timeout(self::TIMEOUT);
            if ($key = config('services.warp.key')) $http = $http->withToken($key);   // keyless rates are display-only
            $r = $http->post(self::BASE . '/ltl/market-options', $body);
        } catch (\Throwable $e) {
            Log::warning('Warp LTL rates failed: ' . $e->getMessage());
            \App\Support\CarrierHealth::failure('Warp', $e->getMessage());
            return [[], false];
        }
        if (!$r->successful()) {
            Log::warning('Warp LTL rates ' . $r->status() . ' for ' . $body['destination_zip'] . ': ' . Str::limit((string) ($r->json('error') ?: $r->body()), 200));
            \App\Support\CarrierHealth::failure('Warp', 'HTTP ' . $r->status());
            return [[], false];
        }
        $options = $r->json('market_options');
        if (!is_array($options) || !$options) {
            Log::warning('Warp returned no LTL options for ' . $body['destination_zip'] . ' — ' . Str::limit((string) $r->json('note'), 150));
            return [[], false];
        }
        return [$options, (bool) $r->json('cached')];
    }

    /** Carriers price LTL off density, so the class is computed rather than asked for. */
    public function freightClass(float $weightLb, array $dims): float
    {
        $cuft = ((float) $dims[0] * (float) $dims[1] * (float) $dims[2]) / 1728;
        if ($cuft <= 0 || $weightLb <= 0) return 250;
        $density = $weightLb / $cuft;
        foreach (self::CLASS_BY_DENSITY as [$min, $class]) {
            if ($density >= $min) return $class;
        }
        return 500;
    }

    /** Freight moves on a working day; a quote for a Sunday pickup prices nothing useful. */
    private function pickupDate(): string
    {
        $d = now()->addDay();
        while ($d->isWeekend()) $d->addDay();
        return $d->format('Y-m-d');
    }

    /* ================================================================ booking
     *
     * Booking is a different trade from quoting: it charges the shop's card and dispatches a
     * truck. The quote it books must be fresh (Warp holds a quote ~15 minutes) and keyed —
     * a keyless quote_id is display-only. On a non-production host every booking goes to the
     * sandbox key (simulated: no charge, no truck) unless the admin explicitly asks for a real
     * one, the same rule the label modal follows.
     */

    public function bookingEnabled(): bool
    {
        return (bool) (config('services.warp.key') || config('services.warp.test_key'));
    }

    /** Outside production a booking is a sandbox booking unless the admin opts out. */
    public function testMode(): bool
    {
        return !app()->isProduction();
    }

    public function hasTestKey(): bool
    {
        return (bool) config('services.warp.test_key');
    }

    private function bookingHttp(bool $test)
    {
        $key = (string) ($test ? config('services.warp.test_key') : config('services.warp.key'));
        return Http::acceptJson()->asJson()->timeout(self::TIMEOUT)->withToken($key);
    }

    /**
     * Fresh, bookable LTL quotes for the load the packer confirmed — never cached.
     *
     * @param array $load ['pallets'=>int, 'weight_lb'=>per pallet, 'dims'=>[l,w,h]]
     * @return array{ok:bool, error:?string, pickup_date:?string, options:array}
     */
    public function bookingQuotes(array $load, string $toZip, string $pickupDate, array $accessorials, bool $test): array
    {
        $dims = array_map('floatval', $load['dims'] ?? [0, 0, 0]);
        $weight = (float) ($load['weight_lb'] ?? 0);
        $body = array_filter([
            'origin_zip' => self::FROM_ZIP,
            'destination_zip' => substr(preg_replace('/\D/', '', $toZip), 0, 5),
            'pickup_date' => $pickupDate,
            'pallets' => max(1, (int) ($load['pallets'] ?? 1)),
            'weight_lbs_per_pallet' => (int) ceil($weight),
            'mode' => 'LTL',
            'commodity' => 'Driveshafts and tubing',
            'length_in' => (int) ceil($dims[0] ?? 0),
            'width_in' => (int) ceil($dims[1] ?? 0),
            'height_in' => (int) ceil($dims[2] ?? 0),
            'freight_class' => (string) $this->freightClass($weight, $dims),
            'accessorials' => array_filter($accessorials) ?: null,
        ], fn ($v) => $v !== null);

        try {
            $r = $this->bookingHttp($test)->post(self::BASE . '/ltl/market-options', $body);
        } catch (\Throwable $e) {
            \App\Support\CarrierHealth::failure('Warp', $e->getMessage());
            return ['ok' => false, 'error' => 'Warp could not be reached: ' . Str::limit($e->getMessage(), 120), 'pickup_date' => null, 'options' => []];
        }
        if (!$r->successful()) {
            return ['ok' => false, 'error' => $this->errorText($r), 'pickup_date' => null, 'options' => []];
        }
        $opts = [];
        foreach ((array) $r->json('market_options') as $o) {
            if (empty($o['quote_id']) || empty($o['bookable'])) continue;   // only what can actually be booked
            $opts[] = [
                'quote_id' => (string) $o['quote_id'],
                'carrier' => $this->carrierLabel((string) ($o['carrier_name'] ?? 'Carrier')),
                'service_level' => (string) ($o['service_level'] ?? ''),
                'price' => round((float) ($o['price_usd'] ?? 0), 2),
                'transit_days' => isset($o['transit_days']) ? (int) $o['transit_days'] : null,
                'fedex' => str_contains(strtoupper((string) ($o['carrier_name'] ?? '')), 'FEDEX FREIGHT'),
            ];
        }
        // the shop ships FedEx Freight: those lead, then everything else cheapest first
        usort($opts, fn ($a, $b) => [$a['fedex'] ? 0 : 1, $a['price']] <=> [$b['fedex'] ? 0 : 1, $b['price']]);
        $mode = self::carrierMode();
        $hasFedex = (bool) array_filter($opts, fn ($o) => $o['fedex']);
        $note = null;
        if ($test && !$hasFedex) {
            // Warp's sandbox only has test carriers; filtering them out would leave nothing to try
            $note = 'Warp\'s sandbox has no FedEx Freight — these are test carriers. Tick "book a real truck" to see live FedEx Freight prices.';
        } elseif ($mode === 'fedex') {
            $opts = array_values(array_filter($opts, fn ($o) => $o['fedex']));
        } elseif ($mode === 'fedex_first' && $hasFedex) {
            $opts = array_values(array_filter($opts, fn ($o) => $o['fedex']));
        } elseif ($mode === 'no_fedex') {
            $opts = array_values(array_filter($opts, fn ($o) => !$o['fedex']));
            usort($opts, fn ($a, $b) => $a['price'] <=> $b['price']);
        }
        $err = $opts ? null : ($mode === 'fedex' ? 'FedEx Freight has no bookable service on this lane and date. Try another pickup date, or allow other carriers in Settings → Shipping.' : 'No bookable LTL carrier for this lane and date.');
        return ['ok' => true, 'error' => $err, 'pickup_date' => $r->json('pickup_date') ?: $pickupDate, 'options' => $opts, 'sandbox' => (bool) $r->json('sandbox'), 'note' => $note];
    }

    /**
     * Book one quoted carrier. Warp's /book has no pickup-window field (only multistop does),
     * so the dock hours travel in the pickup's special instructions, which the carrier sees.
     *
     * @return array{ok:bool, error:?string, data:array}
     */
    public function book(string $quoteId, array $accessorials, string $reference, array $pickup, array $delivery, string $notes, bool $test): array
    {
        $body = array_filter([
            'quote_id' => $quoteId,
            'reference' => Str::limit($reference, 60, ''),
            'accessorials' => array_filter($accessorials) ?: null,
            'patch' => [
                'pickup' => array_filter($pickup, fn ($v) => $v !== null && $v !== ''),
                'delivery' => array_filter($delivery, fn ($v) => $v !== null && $v !== ''),
                'notes' => Str::limit($notes, 500, ''),
            ],
        ], fn ($v) => $v !== null);
        try {
            $r = $this->bookingHttp($test)->post(self::BASE . '/book', $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Warp could not be reached: ' . Str::limit($e->getMessage(), 120), 'data' => []];
        }
        if (!$r->successful() || !$r->json('booked')) {
            return ['ok' => false, 'error' => $this->errorText($r), 'data' => (array) $r->json()];
        }
        return ['ok' => true, 'error' => null, 'data' => (array) $r->json()];
    }

    /** Live status (booked / in_transit / delivered / cancelled), windows and event history. */
    public function track(string $shipmentId, bool $test): ?array
    {
        try {
            $r = $this->bookingHttp($test)->get(self::BASE . '/track', ['booking_id' => $shipmentId]);
        } catch (\Throwable $e) {
            return null;
        }
        return $r->successful() ? (array) $r->json() : null;
    }

    /** BOL, POD and customs documents as they appear over the shipment's life. */
    public function documents(string $orderId, bool $test): array
    {
        try {
            $r = $this->bookingHttp($test)->get(self::BASE . '/documents', ['order_id' => $orderId]);
        } catch (\Throwable $e) {
            return [];
        }
        if (!$r->successful()) return [];
        $docs = [];
        foreach ((array) ($r->json('documents.data') ?? []) as $d) {
            if (!is_array($d)) continue;
            $url = $d['url'] ?? $d['fileUrl'] ?? $d['downloadUrl'] ?? $d['link'] ?? null;
            if (!$url) continue;
            $docs[] = ['type' => (string) ($d['type'] ?? $d['documentType'] ?? $d['name'] ?? 'Document'), 'url' => (string) $url];
        }
        return $docs;
    }

    private function errorText($r): string
    {
        $e = $r->json('error');
        $msg = is_string($e) ? $e : (is_array($e) ? ($e['message'] ?? json_encode($e)) : '');
        return Str::limit($msg ?: ('Warp refused the request (HTTP ' . $r->status() . ').'), 300);
    }
}
