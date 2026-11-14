<?php

namespace App\Services;

use App\Support\AdminLog;
use App\Support\OrderEvents;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Buys outbound and return labels through ShipStation's API (v1, API key + secret from ShipStation → Settings → Account
 * → API Settings), spending the ShipStation balance. Same shape as EasyPostLabelService so the order page's label modal
 * and LabelsController can use either one; Settings → Shipping "Labels are bought with" picks which, and Auto falls back
 * to ShipStation when EasyPost refuses a label for balance or payment.
 *
 * A label bought here is not tied to the ShipStation copy of the order: the order is marked shipped here, and the Custom
 * Store export tells ShipStation it shipped on its next sync (ShipStationController::status).
 */
class ShipStationLabelService
{
    private const BASE = 'https://ssapi.shipstation.com';
    private const DISK = 'local';

    public function enabled(): bool
    {
        return (bool) config('services.shipstation.api_key') && (bool) config('services.shipstation.api_secret');
    }

    /** ShipStation makes free test labels with testLabel=true, so staging never spends the balance by accident. */
    public function testMode(): bool
    {
        return !app()->isProduction();
    }

    private function http()
    {
        return Http::withBasicAuth((string) config('services.shipstation.api_key'), (string) config('services.shipstation.api_secret'))->acceptJson()->asJson()->timeout(45);
    }

    /* ---------- carriers ---------- */

    /** Connected carriers: code => [name, balance (funded carriers), funded]. */
    public function carrierList(): array
    {
        if (!$this->enabled()) return [];
        return Cache::remember('shipstation_carriers', 600, function () {
            try {
                $r = $this->http()->timeout(20)->get(self::BASE . '/carriers');
                if (!$r->successful()) return [];
                $out = [];
                foreach ((array) $r->json() as $c) {
                    if (empty($c['code'])) continue;
                    $out[$c['code']] = ['name' => trim((string) ($c['nickname'] ?? '') ?: (string) ($c['name'] ?? $c['code'])), 'balance' => isset($c['balance']) ? (float) $c['balance'] : null, 'funded' => (bool) ($c['requiresFundedAccount'] ?? false)];
                }
                return $out;
            } catch (\Throwable $e) {
                report($e);
                return [];
            }
        });
    }

    /** @return array<string,string> code => name */
    public function carriers(): array
    {
        return array_map(fn ($c) => $c['name'], $this->carrierList());
    }

    /** "ups_walleted" → ups, "stamps_com" → usps: the carrier behind a ShipStation code, to match an EasyPost service. */
    public static function family(string $code): string
    {
        $c = strtolower($code);
        return match (true) {
            str_contains($c, 'ups') && !str_contains($c, 'usps') => 'ups',
            str_contains($c, 'fedex') => 'fedex',
            str_contains($c, 'dhl') => 'dhl',
            str_contains($c, 'stamps'), str_contains($c, 'usps'), str_contains($c, 'endicia') => 'usps',
            default => $c,
        };
    }

    /** "UPS® Ground" and EasyPost's "Ground" both → "ground"; "USPS Priority Mail Express" and "Express" → "priorityexpress". */
    public static function serviceKey(string $family, string $name): string
    {
        $k = preg_replace('/[^a-z0-9]/', '', str_replace(['ups', 'fedex', 'usps', 'dhl', 'mail'], '', strtolower(str_replace(['®', '™'], '', $name))));
        return match ("$family:$k") {
            'usps:express' => 'priorityexpress',
            'fedex:groundhomedelivery' => 'homedelivery',
            default => $k,
        };
    }

    /* ---------- addresses ---------- */

    private function address(array $a): array
    {
        return array_filter([
            'name' => $a['name'] ?? 'Customer', 'company' => $a['company'] ?? null,
            'street1' => $a['street1'] ?? '', 'street2' => $a['street2'] ?? null,
            'city' => $a['city'] ?? '', 'state' => $a['state'] ?? '', 'postalCode' => $a['zip'] ?? '',
            'country' => $a['country'] ?? 'US', 'phone' => $a['phone'] ?? null,
            'residential' => empty($a['company']),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function parties(object $order, string $kind): array
    {
        $customer = app(EasyPostLabelService::class)->customerAddress($order);
        $shop = EasyPostLabelService::SHOP;
        return $kind === 'return' ? [$customer, $shop] : [$shop, $customer];
    }

    private function weight(array $box): array
    {
        return ['value' => max(0.1, round((float) ($box['weight_lb'] ?? 0) * 16, 2)), 'units' => 'ounces'];
    }

    private function dimensions(array $box): ?array
    {
        $l = (float) ($box['length_in'] ?? 0); $w = (float) ($box['width_in'] ?? 0); $h = (float) ($box['height_in'] ?? 0);
        return $l > 0 && $w > 0 && $h > 0 ? ['units' => 'inches', 'length' => round($l, 2), 'width' => round($w, 2), 'height' => round($h, 2)] : null;
    }

    /* ---------- rates ---------- */

    /**
     * Live prices for the box, from every connected carrier (or just $carrier). Same rows as EasyPostLabelService::rates.
     * @return array<int,array{carrier:string,carrierName:string,quoteId:string,serviceCode:string,serviceName:string,cost:float,provider:string}>
     */
    public function rates(object $order, string $carrier, array $box, string $kind = 'outbound'): array
    {
        $carriers = $this->carriers();
        if (!$carriers) return [];
        $codes = $carrier !== '' ? array_intersect([$carrier], array_keys($carriers)) : array_keys($carriers);
        [$from, $to] = $this->parties($order, $kind);
        $base = array_filter([
            'fromPostalCode' => $from['zip'] ?? '', 'toState' => $to['state'] ?? '', 'toCountry' => $to['country'] ?? 'US',
            'toPostalCode' => $to['zip'] ?? '', 'toCity' => $to['city'] ?? '', 'weight' => $this->weight($box),
            'dimensions' => $this->dimensions($box), 'residential' => empty($to['company']),
        ], fn ($v) => $v !== null && $v !== '');
        $key = (string) config('services.shipstation.api_key'); $secret = (string) config('services.shipstation.api_secret');
        try {
            $answers = Http::pool(fn ($pool) => array_map(fn ($code) => $pool->as($code)->withBasicAuth($key, $secret)->acceptJson()->asJson()->timeout(30)
                ->post(self::BASE . '/shipments/getrates', $base + ['carrierCode' => $code]), $codes));
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
        $out = [];
        foreach ($answers as $code => $r) {
            if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) continue;
            foreach ((array) $r->json() as $x) {
                $cost = round((float) ($x['shipmentCost'] ?? 0) + (float) ($x['otherCost'] ?? 0), 2);
                if (empty($x['serviceCode']) || $cost <= 0) continue;
                $name = trim(preg_replace('/^(UPS|FedEx|USPS|DHL)\s*®?\s*/i', '', str_replace(['®', '™'], '', (string) ($x['serviceName'] ?? $x['serviceCode'])))) ?: (string) $x['serviceCode'];
                $out[] = ['carrier' => $code, 'carrierName' => $carriers[$code] ?? $code, 'quoteId' => Str::slug(self::family($code) . '_' . $x['serviceCode']),
                    'serviceCode' => (string) $x['serviceCode'], 'serviceName' => $name, 'cost' => $cost, 'provider' => 'shipstation'];
            }
        }
        usort($out, fn ($a, $b) => $a['cost'] <=> $b['cost']);
        return $out;
    }

    /** The ShipStation rate for the same carrier and service as an EasyPost rate (Auto mode's fallback), or null. */
    public function equivalent(object $order, array $box, string $kind, string $epCarrier, string $epServiceName): ?array
    {
        $fam = self::family($epCarrier);
        $want = self::serviceKey($fam, $epServiceName);
        foreach ($this->rates($order, '', $box, $kind) as $r) {
            if (self::family($r['carrier']) === $fam && self::serviceKey($fam, $r['serviceName']) === $want) return $r;
        }
        return null;
    }

    /* ---------- buying ---------- */

    /**
     * Buy one label. $d: kind, carrier_code, service_code, weight_lb, length_in, width_in, height_in, confirmation, insure_value, allow_real.
     * Returns ['ok'=>bool, 'error'=>?string, 'label'=>?object].
     */
    public function buy(object $order, array $d, ?int $userId = null): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'ShipStation is not connected: its API key and secret are missing.'];
        $kind = ($d['kind'] ?? 'outbound') === 'return' ? 'return' : 'outbound';
        $test = $this->testMode() && empty($d['allow_real']);
        $box = ['weight_lb' => $d['weight_lb'] ?? 0, 'length_in' => $d['length_in'] ?? 0, 'width_in' => $d['width_in'] ?? 0, 'height_in' => $d['height_in'] ?? 0];
        $sigOver = (float) \App\Support\Settings::get('shipping.signature_over', 500);
        $confirmation = $d['confirmation'] ?? (($sigOver > 0 && (float) ($order->total ?? 0) > $sigOver) ? 'signature' : 'none');
        [$from, $to] = $this->parties($order, $kind);

        $payload = array_filter([
            'carrierCode' => $d['carrier_code'], 'serviceCode' => $d['service_code'], 'packageCode' => 'package',
            'confirmation' => in_array($confirmation, ['delivery', 'signature', 'adult_signature'], true) ? $confirmation : 'none',
            'shipDate' => now('America/New_York')->toDateString(),
            'weight' => $this->weight($box), 'dimensions' => $this->dimensions($box),
            'shipFrom' => $this->address($from), 'shipTo' => $this->address($to),
            'insuranceOptions' => !empty($d['insure_value']) ? ['provider' => 'carrier', 'insureShipment' => true, 'insuredValue' => round((float) $d['insure_value'], 2)] : null,
            'internationalOptions' => ($to['country'] ?? 'US') !== 'US' ? $this->customs($order) : null,
            'testLabel' => $test,
        ], fn ($v) => $v !== null);

        try {
            $r = $this->http()->post(self::BASE . '/shipments/createlabel', $payload);
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'error' => 'ShipStation could not be reached.'];
        }
        if (!$r->successful()) return ['ok' => false, 'error' => $this->errorFrom($r)];

        $pdf = null;
        if ($data = base64_decode((string) $r->json('labelData'), true)) {
            $pdf = 'labels/' . $order->id . '/' . $kind . '-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.pdf';
            Storage::disk(self::DISK)->put($pdf, $data);
        }
        $carrierName = $this->carriers()[$d['carrier_code']] ?? strtoupper(self::family($d['carrier_code']));
        $service = (string) ($d['service_name'] ?? '') ?: $d['service_code'];
        $id = DB::table('shipping_labels')->insertGetId([
            'order_id' => $order->id, 'kind' => $kind, 'provider' => 'shipstation',
            'carrier_code' => $d['carrier_code'], 'service_code' => $d['service_code'], 'package_code' => 'parcel',
            'service_label' => Str::limit(trim($carrierName . ' ' . $service), 120, ''),
            'tracking_number' => $r->json('trackingNumber') ?: null,
            'cost' => round((float) $r->json('shipmentCost'), 2),
            'insurance_cost' => round((float) ($r->json('insuranceCost') ?: 0), 2),
            'weight_lb' => round((float) $box['weight_lb'], 2) ?: null,
            'length_in' => (float) $box['length_in'] ?: null, 'width_in' => (float) $box['width_in'] ?: null, 'height_in' => (float) $box['height_in'] ?: null,
            'provider_shipment_id' => (string) $r->json('shipmentId') ?: null,
            'pdf_path' => $pdf, 'is_test' => $test, 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $label = DB::table('shipping_labels')->find($id);
        OrderEvents::record($order, $kind === 'return' ? 'label:return' : 'label:outbound', $kind === 'return' ? 'Return label created' : 'Shipping label created',
            trim(($label->service_label ?: '') . ' · ' . ($label->tracking_number ?: 'no tracking') . ' · ' . money((float) $label->cost) . ' · ShipStation' . ($test ? ' · TEST' : '')));
        AdminLog::record('order.label', ucfirst($kind) . ' label for ' . $order->order_number . ' via ShipStation (' . ($label->tracking_number ?: '—') . ')', 'orders', (string) $order->id);
        Cache::forget('shipstation_carriers');   // the balance just changed
        return ['ok' => true, 'label' => $label];
    }

    /** Customs for a label leaving the U.S., from the order's own lines (ShipStation's internationalOptions). */
    private function customs(object $order): array
    {
        $items = [];
        foreach (DB::table('order_items')->where('order_id', $order->id)->get() as $it) {
            $qty = max(1, (int) ($it->qty ?? 1));
            $items[] = ['description' => Str::limit(trim(($it->sku ? $it->sku . ' ' : '') . ($it->name ?: 'Driveshaft part')), 50, ''), 'quantity' => $qty,
                'value' => round((float) ($it->price ?? 0), 2), 'harmonizedTariffCode' => (string) config('uds.customs_hs_code', '870899'), 'countryOfOrigin' => 'US'];
        }
        return ['contents' => 'merchandise', 'nonDelivery' => 'return_to_sender',
            'customsItems' => $items ?: [['description' => 'Driveshaft parts', 'quantity' => 1, 'value' => round((float) ($order->total ?? 0), 2) ?: 1.0, 'harmonizedTariffCode' => '870899', 'countryOfOrigin' => 'US']]];
    }

    private function errorFrom($r): string
    {
        $msg = (string) ($r->json('ExceptionMessage') ?: $r->json('Message') ?: $r->json('message') ?: '');
        return Str::limit(trim($msg), 300) ?: ('ShipStation refused the label (HTTP ' . $r->status() . ').');
    }

    /** Void a ShipStation label; the carrier refunds the postage to the ShipStation balance. */
    public function void(object $label): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'ShipStation is not connected.'];
        if ($label->voided_at) return ['ok' => false, 'error' => 'That label is already voided.'];
        if (!$label->provider_shipment_id) {
            DB::table('shipping_labels')->where('id', $label->id)->update(['voided_at' => now(), 'updated_at' => now()]);
            return ['ok' => true, 'error' => null];
        }
        try {
            $r = $this->http()->post(self::BASE . '/shipments/voidlabel', ['shipmentId' => (int) $label->provider_shipment_id]);
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'error' => 'ShipStation could not be reached.'];
        }
        if (!$r->successful() || !$r->json('approved')) return ['ok' => false, 'error' => (string) ($r->json('message') ?: $this->errorFrom($r))];
        DB::table('shipping_labels')->where('id', $label->id)->update(['voided_at' => now(), 'updated_at' => now()]);
        Cache::forget('shipstation_carriers');
        return ['ok' => true, 'error' => null];
    }
}
