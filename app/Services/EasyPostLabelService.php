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
 * Buys outbound and return labels through EasyPost, straight from the admin order page,
 * and keeps the PDF with the order. An outbound label is the shipment: its tracking number
 * goes onto the order and the customer is told.
 *
 * Money safety: EasyPost only makes free fake labels against a *test* key (EZTK…).
 * With a live key every label is real postage the moment it is bought, so outside
 * production a label is refused unless the packer ticks "buy a real label".
 */
class EasyPostLabelService
{
    private const BASE = 'https://api.easypost.com/v2';
    private const DISK = 'local';

    /** Odessa shop — the ship-from on every outbound label and the destination of every return. */
    public const SHOP = [
        'name' => 'Universal Driveshafts', 'company' => 'Universal Driveshafts, Inc.',
        'street1' => '11611 Prospect Drive STE 4', 'city' => 'Odessa', 'state' => 'FL',
        'zip' => '33556', 'country' => 'US', 'phone' => '8135246491',
    ];

    public function enabled(): bool
    {
        return (bool) config('services.easypost.enabled') && config('services.easypost.key');
    }

    /** Outside production a label is a free test label — but only if a test key exists. */
    public function testMode(): bool
    {
        return !app()->isProduction();
    }

    /** Free fake labels need EasyPost's test key; without one, staging can only buy real postage. */
    public function supportsTestLabels(string $carrier = ''): bool
    {
        return (bool) config('services.easypost.test_key');
    }

    private function key(bool $test = false): string
    {
        return (string) ($test ? config('services.easypost.test_key') : config('services.easypost.key'));
    }

    private function http(bool $test = false)
    {
        return Http::withBasicAuth($this->key($test), '')->acceptJson()->timeout(45);
    }

    /* ---------- carrier reference data ---------- */

    /**
     * The shop's connected carrier accounts, keyed by a stable slug ("ups", "fedex") so the
     * stored carrier_code stays readable and the EasyPost account id is an implementation detail.
     *
     * @return array<string,array{id:string,name:string,type:string}>
     */
    public function carrierAccounts(): array
    {
        if (!$this->enabled()) return [];
        return Cache::remember('ep:carriers', 3600, function () {
            $r = $this->http()->get(self::BASE . '/carrier_accounts');
            if (!$r->successful()) return [];
            $out = [];
            $skip = (array) config('uds.shipping_exclude.carriers', []);
            foreach ($r->json() ?: [] as $c) {
                $slug = $this->slug((string) ($c['type'] ?? ''));
                if ($slug === '' || isset($out[$slug]) || in_array($slug, $skip, true)) continue;
                $out[$slug] = ['id' => (string) ($c['id'] ?? ''), 'name' => self::NAMES[$slug] ?? (string) ($c['readable'] ?: $slug), 'type' => (string) ($c['type'] ?? '')];
            }
            uksort($out, function ($a, $b) {
                $ia = array_search($a, self::ORDER, true); $ib = array_search($b, self::ORDER, true);
                return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
            });
            return $out;
        });
    }

    /** The carriers the packer actually reaches for, first. */
    private const ORDER = ['ups', 'fedex', 'usps', 'dhl_express', 'dhl_ecs', 'canada_post'];

    /** EasyPost's own "readable" is account-flavoured ("UPSDAP", "FedEx Default"). */
    private const NAMES = [
        'ups' => 'UPS', 'fedex' => 'FedEx', 'usps' => 'USPS',
        'dhl_express' => 'DHL Express', 'dhl_ecs' => 'DHL eCommerce',
        'canada_post' => 'Canada Post', 'usa_export_pba' => 'USA Export (Asendia)',
    ];

    /** "UpsDapAccount" → "ups", "FedexDefaultAccount" → "fedex". */
    private function slug(string $type): string
    {
        $t = preg_replace('/(Default|Dap|Account)$/', '', $type);
        $t = preg_replace('/(Default|Dap)$/', '', $t);
        return Str::snake($t);
    }

    /** @return array<string,string> slug => name */
    public function carriers(): array
    {
        return array_map(fn ($c) => $c['name'], $this->carrierAccounts());
    }

    /**
     * EasyPost has no service catalogue to list — a service only exists if the carrier
     * priced this exact box, so the modal fills its list from the live rates instead.
     *
     * @return array<string,string>
     */
    public function services(string $carrier): array
    {
        return [];
    }

    /** The shop packs driveshafts in its own boxes; carrier flat-rate packaging never fits one. */
    public function packages(string $carrier): array
    {
        return ['parcel' => 'My own box (size below)'];
    }

    /* ---------- addresses ---------- */

    /** The customer's address on this order, in EasyPost's shape. */
    public function customerAddress(object $order): array
    {
        $w = $order->ship_to_different ? 'shipping' : 'billing';
        $get = fn (string $f) => trim((string) ($order->{$w . '_' . $f} ?? '')) ?: trim((string) ($order->{'billing_' . $f} ?? ''));
        // shipping elsewhere (a dropship customer, a home): the buyer's own company does not go on that label
        $company = $order->ship_to_different ? trim((string) ($order->shipping_company ?? '')) : $get('company');
        return array_filter([
            'name' => trim($get('first_name') . ' ' . $get('last_name')) ?: 'Customer',
            'company' => $company ?: null,
            'street1' => $get('address_1'), 'street2' => $get('address_2') ?: null,
            'city' => $get('city'), 'state' => $get('state'), 'zip' => $get('postcode'),
            'country' => strtoupper($get('country') ?: 'US'),
            'phone' => preg_replace('/[^0-9+]/', '', (string) $order->phone) ?: null,
            'email' => $order->email ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /* ---------- rates for one specific box ---------- */

    /**
     * Live prices for the exact box the packer is holding.
     * @return array<int,array{serviceCode:string,serviceName:string,cost:float}>
     */
    public function rates(object $order, string $carrier, array $box): array
    {
        if (!$this->enabled()) return [];
        $resp = $this->createShipment($order, $carrier, $box, 'outbound', false);
        if (!$resp || !$resp->successful()) return [];

        // Which account each price came from, so a packer who never picked a carrier still
        // buys from the right one.
        $slugOf = [];
        foreach ($this->carrierAccounts() as $slug => $acc) $slugOf[$acc['id']] = $slug;

        return collect($resp->json('rates') ?: [])
            ->map(function ($x) use ($slugOf) {
                $slug = $slugOf[$x['carrier_account_id'] ?? ''] ?? '';
                return [
                    'carrier' => $slug,
                    'carrierName' => $slug !== '' ? ($this->carriers()[$slug] ?? $slug) : (string) ($x['carrier'] ?? ''),
                    // the id the checkout used for this same service (EasyPostService builds it
                    // the same way), so the packer can be shown what the customer actually paid for
                    'quoteId' => Str::slug(($x['carrier'] ?? '') . '_' . ($x['service'] ?? '')),
                    'serviceCode' => (string) ($x['service'] ?? ''),
                    'serviceName' => $this->serviceName((string) ($x['service'] ?? '')),
                    'cost' => round((float) ($x['rate'] ?? 0), 2),
                ];
            })
            ->filter(fn ($x) => $x['serviceCode'] !== '' && $x['cost'] > 0 && $x['carrier'] !== '' && !EasyPostService::excluded($x['serviceCode']))
            ->unique(fn ($x) => $x['carrier'] . '|' . $x['serviceCode'])
            ->sortBy('cost')->values()->all();
    }

    /** Service codes the generic splitter gets wrong; the carrier is shown separately. */
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

    private function serviceName(string $service): string
    {
        if (isset(self::SERVICE_LABELS[$service])) return self::SERVICE_LABELS[$service];
        $name = str_contains($service, '_')
            ? Str::title(str_replace('_', ' ', preg_replace('/^(FEDEX|UPS|USPS|DHL)_/', '', $service)))
            : trim(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $service));
        // the carrier already labels the row — "FedEx FedEx Ground" reads like a bug
        return trim(preg_replace('/^(FedEx|UPS|USPS|DHL)\s+/i', '', $name)) ?: $name;
    }

    /** Create the shipment the rates and the purchase both come from. */
    private function createShipment(object $order, string $carrier, array $box, string $kind, bool $test, array $extra = [])
    {
        $account = $carrier !== '' ? ($this->carrierAccounts()[$carrier] ?? null) : null;
        $customer = $this->customerAddress($order);
        $shipment = array_filter([
            'to_address' => $kind === 'return' ? self::SHOP : $customer,
            'from_address' => $kind === 'return' ? $customer : self::SHOP,
            'parcel' => $this->parcelPayload($box),
            'is_return' => $kind === 'return' ?: null,
            'options' => array_filter([
                'label_format' => 'PDF',
                'delivery_confirmation' => $this->confirmation($extra['confirmation'] ?? 'none'),
            ]),
            'carrier_accounts' => $account ? [$account['id']] : null,
            // Crossing a border without this is refused outright by every carrier.
            'customs_info' => ($customer['country'] ?? 'US') !== 'US' ? $this->customsInfo($order) : null,
        ], fn ($v) => $v !== null && $v !== [] && $v !== false);

        try {
            return $this->http($test)->asJson()->post(self::BASE . '/shipments', ['shipment' => $shipment]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EasyPost shipment failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The customs declaration an international label cannot be bought without: what is in the
     * box, what it is worth and who certifies it. Built from the order's own lines so the
     * declaration always matches what the customer actually bought.
     */
    private function customsInfo(object $order): array
    {
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        $products = app(ProductService::class);
        $customs = [];
        $total = 0.0;

        foreach ($items as $it) {
            $qty = max(1, (int) ($it->qty ?? 1));
            $value = round((float) ($it->price ?? 0) * $qty, 2);
            $total += $value;
            $lb = $it->wp_product_id ? $products->weight((int) $it->wp_product_id) : 0.0;
            $customs[] = [
                'description' => Str::limit(trim(($it->sku ? $it->sku . ' ' : '') . ($it->name ?: 'Driveshaft part')), 50, ''),
                'quantity' => $qty,
                'value' => $value,
                'weight' => round(max(1.0, ($lb ?: 1.0) * $qty) * 16, 2),   // ounces
                'origin_country' => 'US',
                'hs_tariff_number' => (string) config('uds.customs_hs_code', '870899'),
            ];
        }
        if (!$customs) {
            $customs[] = ['description' => 'Driveshaft parts', 'quantity' => 1, 'value' => round((float) ($order->total ?? 0), 2) ?: 1.0, 'weight' => 16, 'origin_country' => 'US'];
        }

        return array_filter([
            'contents_type' => 'merchandise',
            'customs_certify' => true,
            'customs_signer' => 'Universal Driveshafts',
            'non_delivery_option' => 'return',
            'restriction_type' => 'none',
            // Under $2,500 per Schedule B line the export needs no AES filing; above it the
            // shop has to file and put the ITN here instead.
            'eel_pfc' => $total < 2500 ? 'NOEEI 30.37(a)' : null,
            'customs_items' => $customs,
        ], fn ($v) => $v !== null);
    }

    private function parcelPayload(array $box): array
    {
        $p = ['weight' => max(0.1, round((float) ($box['weight_lb'] ?? 0) * 16, 2))];   // ounces
        $l = (float) ($box['length_in'] ?? 0); $w = (float) ($box['width_in'] ?? 0); $h = (float) ($box['height_in'] ?? 0);
        if ($l > 0 && $w > 0 && $h > 0) $p += ['length' => round($l, 2), 'width' => round($w, 2), 'height' => round($h, 2)];
        return $p;
    }

    private function confirmation(string $c): ?string
    {
        return match ($c) {
            'delivery' => 'DELIVERY',
            'signature' => 'SIGNATURE',
            'adult_signature' => 'ADULT_SIGNATURE',
            default => null,
        };
    }

    /* ---------- buying ---------- */

    /**
     * Buy one label. $d: kind, carrier_code, service_code, package_code, weight_lb, length_in, width_in, height_in, confirmation, insure_value.
     * Returns ['ok'=>bool, 'error'=>?string, 'label'=>?object].
     */
    public function buy(object $order, array $d, ?int $userId = null): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'EasyPost is not configured.'];
        $kind = ($d['kind'] ?? 'outbound') === 'return' ? 'return' : 'outbound';
        $test = $this->testMode() && empty($d['allow_real']);
        if ($test && !$this->supportsTestLabels()) {
            return ['ok' => false, 'error' => 'no EasyPost test key is set, so a label bought here would be real postage. Tick "buy a real label" to go ahead.'];
        }
        $box = ['weight_lb' => $d['weight_lb'] ?? 0, 'length_in' => $d['length_in'] ?? 0, 'width_in' => $d['width_in'] ?? 0, 'height_in' => $d['height_in'] ?? 0];

        // no choice sent: orders above Settings → Shipping "require signature over" get one
        $sigOver = (float) \App\Support\Settings::get('shipping.signature_over', 500);
        $confirmation = $d['confirmation'] ?? (($sigOver > 0 && (float) ($order->total ?? 0) > $sigOver) ? 'signature' : 'none');
        $created = $this->createShipment($order, $d['carrier_code'], $box, $kind, $test, ['confirmation' => $confirmation]);
        if (!$created || !$created->successful()) return ['ok' => false, 'error' => $this->errorFrom($created)];

        $shipmentId = (string) $created->json('id');
        $rate = collect($created->json('rates') ?: [])->firstWhere('service', $d['service_code']);
        if (!$rate) {
            $why = collect($created->json('messages') ?: [])->map(fn ($m) => Str::limit((string) ($m['message'] ?? ''), 90))->filter()->implode(' · ');
            return ['ok' => false, 'error' => 'the carrier no longer prices ' . $d['service_code'] . ' for this box' . ($why ? ' — ' . $why : '.')];
        }

        $buy = $this->http($test)->asJson()->post(self::BASE . '/shipments/' . $shipmentId . '/buy', array_filter([
            'rate' => ['id' => $rate['id']],
            'insurance' => !empty($d['insure_value']) ? round((float) $d['insure_value'], 2) : null,
        ], fn ($v) => $v !== null));
        if (!$buy->successful()) return ['ok' => false, 'error' => $this->errorFrom($buy)];

        $pdf = $this->storePdf($order, $kind, $shipmentId, (string) $buy->json('postage_label.label_url'), (string) $buy->json('postage_label.label_file_type'), $test);

        $id = DB::table('shipping_labels')->insertGetId([
            'order_id' => $order->id, 'kind' => $kind, 'provider' => 'easypost',
            'carrier_code' => $d['carrier_code'], 'service_code' => $d['service_code'], 'package_code' => $d['package_code'] ?: 'parcel',
            'service_label' => trim(($this->carriers()[$d['carrier_code']] ?? '') . ' ' . $this->serviceName($d['service_code'])),
            'tracking_number' => $buy->json('tracking_code') ?: null,
            'cost' => round((float) $buy->json('selected_rate.rate'), 2),
            'insurance_cost' => round((float) ($buy->json('insurance') ?: 0), 2),
            'weight_lb' => round((float) $box['weight_lb'], 2) ?: null,
            'length_in' => (float) $box['length_in'] ?: null, 'width_in' => (float) $box['width_in'] ?: null, 'height_in' => (float) $box['height_in'] ?: null,
            'provider_shipment_id' => $shipmentId,
            'pdf_path' => $pdf, 'is_test' => $test, 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $label = DB::table('shipping_labels')->find($id);

        OrderEvents::record(
            $order,
            $kind === 'return' ? 'label:return' : 'label:outbound',
            ($kind === 'return' ? 'Return label created' : 'Shipping label created'),
            trim(($label->service_label ?: '') . ' · ' . ($label->tracking_number ?: 'no tracking') . ' · ' . money((float) $label->cost) . ($test ? ' · TEST' : ''))
        );
        AdminLog::record('order.label', ucfirst($kind) . ' label for ' . $order->order_number . ' (' . ($label->tracking_number ?: '—') . ')', 'orders', (string) $order->id);

        return ['ok' => true, 'label' => $label];
    }

    /** EasyPost reports failures as {error:{message}}, where message is sometimes a list of field errors. */
    private function errorFrom($r): string
    {
        if (!$r) return 'EasyPost could not be reached.';
        $msg = $r->json('error.message');
        if (is_array($msg)) $msg = collect($msg)->map(fn ($m) => is_array($m) ? ($m['message'] ?? json_encode($m)) : $m)->implode(' · ');
        $fields = collect($r->json('error.errors') ?: [])->map(fn ($e) => trim(($e['field'] ?? '') . ' ' . ($e['message'] ?? '')))->filter()->implode(' · ');
        return Str::limit(trim((string) $msg . ($fields ? ' (' . $fields . ')' : '')), 300) ?: ('EasyPost refused the label (HTTP ' . $r->status() . ').');
    }

    /** EasyPost hands back a label URL; fetch it once and keep the PDF with the order. */
    private function storePdf(object $order, string $kind, string $shipmentId, string $url, string $type, bool $test): ?string
    {
        if ($url === '') return null;
        // a carrier that cannot print PDF returns PNG — EasyPost converts it on request
        if ($type && !str_contains(strtolower($type), 'pdf')) {
            $conv = $this->http($test)->get(self::BASE . '/shipments/' . $shipmentId . '/label', ['file_format' => 'pdf']);
            if ($conv->successful() && $conv->json('postage_label.label_url')) $url = (string) $conv->json('postage_label.label_url');
        }
        try {
            $raw = Http::timeout(30)->get($url)->body();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$raw) return null;
        $path = 'labels/' . $order->id . '/' . $kind . '-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.pdf';
        Storage::disk(self::DISK)->put($path, $raw);
        return $path;
    }

    public function pdf(object $label): ?string
    {
        if (!$label->pdf_path || !Storage::disk(self::DISK)->exists($label->pdf_path)) return null;
        return Storage::disk(self::DISK)->get($label->pdf_path);
    }

    /** Void a label with the carrier and refund the postage. */
    public function void(object $label): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'EasyPost is not configured.'];
        if ($label->voided_at) return ['ok' => false, 'error' => 'That label is already voided.'];
        if (!$label->provider_shipment_id) {
            DB::table('shipping_labels')->where('id', $label->id)->update(['voided_at' => now(), 'updated_at' => now()]);
            return ['ok' => true, 'error' => null];
        }
        $r = $this->http((bool) $label->is_test)->post(self::BASE . '/shipments/' . $label->provider_shipment_id . '/refund');
        $status = (string) $r->json('refund_status');
        if (!$r->successful() || in_array($status, ['rejected'], true)) {
            return ['ok' => false, 'error' => $this->errorFrom($r) ?: 'the carrier rejected the refund.'];
        }
        DB::table('shipping_labels')->where('id', $label->id)->update(['voided_at' => now(), 'updated_at' => now()]);
        return ['ok' => true, 'error' => null, 'status' => $status];
    }
}
