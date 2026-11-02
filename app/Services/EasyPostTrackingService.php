<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Carrier tracking through EasyPost. A label bought here already has a tracker attached,
 * and a tracking number typed in by hand gets one created for it, so "where is it" is
 * answered by the carrier's own scans rather than by guessing from the order status.
 */
class EasyPostTrackingService
{
    private const BASE = 'https://api.easypost.com/v2';

    /** EasyPost's own statuses, mapped to the wording the order timeline uses. */
    private const STATUSES = [
        'pre_transit' => 'Label created',
        'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'available_for_pickup' => 'Ready for pickup at the carrier',
        'return_to_sender' => 'Being returned to sender',
        'failure' => 'Carrier reported a problem',
        'cancelled' => 'Cancelled',
        'error' => 'Carrier could not be read',
        'unknown' => 'Unknown',
    ];

    public function enabled(): bool
    {
        return $this->easypostOn() || $this->shippoOn();
    }

    private function easypostOn(): bool
    {
        return (bool) config('services.easypost.enabled') && config('services.easypost.key');
    }

    /** Shippo's tracking API reads any UPS / FedEx / USPS / DHL number; used when EasyPost can't answer. */
    private function shippoOn(): bool
    {
        return (bool) config('services.shippo.token');
    }

    private function http(bool $test = false)
    {
        $key = $test ? config('services.easypost.test_key') : config('services.easypost.key');
        return Http::withBasicAuth((string) $key, '')->acceptJson()->timeout(25);
    }

    /**
     * Current carrier status for one tracking number.
     * events: every scan, newest first — [at (carrier local time, ISO), status, message, place]
     * @return array{status:string,delivered:bool,raw:string,carrier:?string,est:?string,events:array}|null
     */
    public function status(string $tracking, ?string $carrier = null, bool $test = false): ?array
    {
        if (!$this->enabled() || trim($tracking) === '') return null;
        if ($test) return $this->easypost($tracking, $carrier, true);
        // EasyPost first: we ship with EasyPost, and its labels carry their own tracker. Shippo only steps in when
        // EasyPost can't answer (down, out of funds, number unknown), so a delivered parcel is never left showing
        // "shipped". Shippo bills about 2¢ a lookup, so each number is asked at most once every 3 hours.
        $st = Cache::has('easypost:paused') ? null : $this->easypost($tracking, $carrier, false);
        if ($st === null && $this->shippoOn()) {
            $key = 'shippo:trk:' . strtoupper(trim($tracking));
            $st = Cache::get($key);
            if ($st === null && Cache::add($key . ':lock', 1, now()->addHours(3))) {
                $st = $this->shippo($tracking, $carrier);
                if ($st) Cache::put($key, $st, now()->addHours(3));
            }
        }
        return $st;
    }

    private function easypost(string $tracking, ?string $carrier, bool $test): ?array
    {
        if (!$this->easypostOn()) return null;
        try {
            // A tracker is reusable: creating one for a number that already has it returns the same object.
            $r = $this->http($test)->asJson()->post(self::BASE . '/trackers', ['tracker' => array_filter([
                'tracking_code' => trim($tracking),
                'carrier' => $carrier ?: null,
            ])]);
        } catch (\Throwable $e) {
            Log::warning('EasyPost tracker failed for ' . $tracking . ': ' . $e->getMessage());
            return null;
        }
        if (!$r->successful()) {
            Log::warning('EasyPost tracker ' . $r->status() . ' for ' . $tracking . ': ' . \Illuminate\Support\Str::limit((string) ($r->json('error.message') ?: $r->body()), 200));
            // billing or key problem: every number fails the same way, so stop asking for a while (Shippo answers)
            if (in_array($r->status(), [401, 402, 403], true) && Cache::add('easypost:paused', $r->status(), now()->addHours(6))) {
                Log::error('EasyPost tracking paused for 6 h (HTTP ' . $r->status() . ': ' . $r->json('error.message') . '). Tracking falls back to Shippo; check the EasyPost billing settings.');
            }
            return null;
        }
        $raw = (string) ($r->json('status') ?: 'unknown');
        if ($raw === 'unknown' && !$r->json('tracking_details')) return null;   // nothing scanned yet: leave the order alone
        return [
            'status' => self::STATUSES[$raw] ?? ucfirst(str_replace('_', ' ', $raw)),
            'delivered' => $raw === 'delivered',
            'raw' => $raw,
            'carrier' => $r->json('carrier') ?: null,
            'est' => $r->json('est_delivery_date') ?: null,
            'events' => self::events((array) $r->json('tracking_details')),
        ];
    }

    /** Shippo tracking (GET /tracks/{carrier}/{number}), in the same shape as EasyPost's answer. */
    private function shippo(string $tracking, ?string $carrier): ?array
    {
        $code = match (strtoupper((string) $carrier)) {
            'UPSDAP', 'UPS' => 'ups', 'FEDEX' => 'fedex', 'USPS' => 'usps', 'DHLEXPRESS', 'DHL' => 'dhl_express',
            default => preg_match('/^1Z[0-9A-Z]{16}$/i', trim($tracking)) ? 'ups' : null,
        };
        if (!$code) return null;
        try {
            $r = Http::withHeaders(['Authorization' => 'ShippoToken ' . config('services.shippo.token')])->acceptJson()->timeout(25)
                ->get('https://api.goshippo.com/tracks/' . $code . '/' . rawurlencode(trim($tracking)));
        } catch (\Throwable $e) {
            Log::warning('Shippo tracking failed for ' . $tracking . ': ' . $e->getMessage());
            return null;
        }
        if (!$r->successful()) {
            Log::warning('Shippo tracking ' . $r->status() . ' for ' . $tracking . ': ' . \Illuminate\Support\Str::limit((string) $r->body(), 200));
            return null;
        }
        $map = fn (array $t) => match (strtoupper((string) ($t['status'] ?? ''))) {
            'PRE_TRANSIT' => 'pre_transit',
            'TRANSIT' => (($t['substatus']['code'] ?? '') === 'out_for_delivery' || stripos((string) ($t['status_details'] ?? ''), 'out for delivery') !== false) ? 'out_for_delivery' : 'in_transit',
            'DELIVERED' => 'delivered', 'RETURNED' => 'return_to_sender', 'FAILURE' => 'failure',
            default => 'unknown',
        };
        $history = (array) $r->json('tracking_history');
        $now = (array) $r->json('tracking_status');
        $raw = $now ? $map($now) : 'unknown';
        if ($raw === 'unknown' && !$history) return null;
        $events = [];
        foreach ($history as $h) {
            $loc = (array) ($h['location'] ?? []);
            $city = trim((string) ($loc['city'] ?? ''));
            $city = $city === mb_strtoupper($city) ? ucwords(mb_strtolower($city)) : $city;
            $c = strtoupper((string) ($loc['country'] ?? ''));
            $events[] = ['at' => (string) ($h['status_date'] ?? ''), 'status' => $map((array) $h), 'message' => trim((string) ($h['status_details'] ?? '')),
                'place' => implode(', ', array_filter([$city, strtoupper((string) ($loc['state'] ?? '')), $c && !in_array($c, ['US', 'USA'], true) ? $c : null]))];
        }
        usort($events, fn ($a, $b) => strcmp($b['at'], $a['at']));
        $eta = $r->json('eta');
        return [
            'status' => self::STATUSES[$raw] ?? ucfirst(str_replace('_', ' ', $raw)),
            'delivered' => $raw === 'delivered',
            'raw' => $raw,
            'carrier' => $code,
            'est' => $eta ? substr((string) $eta, 0, 10) : null,
            'events' => $events,
        ];
    }

    /** The carrier's scans, newest first, with the scan's own local time (what UPS shows on its page). */
    public static function events(array $details): array
    {
        $out = [];
        foreach ($details as $d) {
            $loc = (array) ($d['tracking_location'] ?? []);
            $city = trim((string) ($loc['city'] ?? ''));
            $city = $city === mb_strtoupper($city) ? ucwords(mb_strtolower($city)) : $city;
            $place = implode(', ', array_filter([$city, strtoupper((string) ($loc['state'] ?? '')), ($c = strtoupper((string) ($loc['country'] ?? ''))) && !in_array($c, ['US', 'USA', 'UNITED STATES'], true) ? $c : null]));
            $msg = trim((string) ($d['message'] ?: $d['description'] ?: ''));
            if ($msg !== '' && $msg === mb_strtoupper($msg)) $msg = ucfirst(mb_strtolower($msg));
            $out[] = ['at' => (string) ($d['datetime_local'] ?? $d['datetime'] ?? ''), 'status' => (string) ($d['status'] ?? 'unknown'), 'message' => $msg, 'place' => $place];
        }
        usort($out, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return $out;
    }

    /**
     * Refresh the scans stored on an order when they are older than $minutes (and the parcel is not delivered
     * yet, or nothing is stored). Called by the status page and the hourly poll.
     */
    public function refreshOrder(object $order, int $minutes = 30): object
    {
        if (!$this->enabled() || !$order->tracking_number) return $order;
        $fresh = $order->tracking_events_at && \Carbon\Carbon::parse($order->tracking_events_at)->gt(now()->subMinutes($minutes));
        if ($order->tracking_events && ($fresh || $order->delivered_at)) return $order;
        $lock = cache()->lock('trk:' . $order->id, 30);
        if (!$lock->get()) return $order;
        try {
            $carrier = match (strtoupper((string) $order->tracking_carrier)) { 'UPS' => 'UPSDAP', 'FEDEX' => 'FedEx', 'USPS' => 'USPS', 'DHL', 'DHL EXPRESS' => 'DHLExpress', default => null };
            $st = $this->status((string) $order->tracking_number, $carrier);
            $f = ['tracking_events_at' => now()];
            if ($st) { $f['tracking_events'] = json_encode($st['events'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $f['tracking_est'] = $st['est']; }
            \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update($f);
            foreach ($f as $k => $v) $order->$k = $v;
        } finally {
            $lock->release();
        }
        return $order;
    }
}
