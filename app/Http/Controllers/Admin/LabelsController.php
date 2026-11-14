<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CatalogWriter;
use App\Services\EasyPostLabelService;
use App\Services\ShipStationLabelService;
use App\Support\AdminLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Buy outbound and return labels for an order without leaving the admin, through EasyPost or ShipStation. */
class LabelsController extends Controller
{
    public const MODES = ['auto' => 'Auto: EasyPost, then ShipStation if EasyPost has no balance', 'easypost' => 'EasyPost only', 'shipstation' => 'ShipStation only'];

    public function __construct(private EasyPostLabelService $ss, private ShipStationLabelService $ssl) {}

    /** Settings → Shipping "Labels are bought with" (owner, 2026-11-14: ShipStation too, or EasyPost first and ShipStation when it has no balance). */
    public static function mode(?string $asked = null): string
    {
        $m = $asked ?: (string) Settings::get('labels.provider', 'auto');
        return isset(self::MODES[$m]) ? $m : 'auto';
    }

    /** Which account prices and buys: ShipStation when chosen (or when EasyPost is not set up), EasyPost otherwise. */
    private function provider(string $mode): string
    {
        if ($mode === 'shipstation') return 'shipstation';
        return $this->ss->enabled() || !$this->ssl->enabled() ? 'easypost' : 'shipstation';
    }

    private function order(int $id): object
    {
        $o = DB::table('orders')->find($id);
        abort_unless($o, 404);
        return $o;
    }

    /** Live prices for the box the packer typed in. */
    public function rates(Request $r, int $id)
    {
        $order = $this->order($id);
        $d = $r->validate([
            'carrier_code' => 'nullable|string|max:60',
            'weight_lb' => 'required|numeric|min:0.1|max:2000',
            'length_in' => 'nullable|numeric|min:0|max:300',
            'width_in' => 'nullable|numeric|min:0|max:108',
            'height_in' => 'nullable|numeric|min:0|max:108',
            'mode' => 'nullable|in:auto,easypost,shipstation',
        ]);
        $provider = $this->provider(self::mode($d['mode'] ?? null));
        $note = null;
        if ($provider === 'shipstation') {
            $rates = $this->ssl->rates($order, (string) ($d['carrier_code'] ?? ''), $d);
            if (!$this->ssl->enabled()) $note = 'ShipStation is not connected yet: its API key and secret (ShipStation → Settings → Account → API Settings) have to be added on the server.';
        } else {
            $rates = array_map(fn ($r) => $r + ['provider' => 'easypost'], $this->ss->rates($order, (string) ($d['carrier_code'] ?? ''), $d));
        }
        return response()->json(['rates' => $rates, 'provider' => $provider, 'note' => $note, 'freight' => $this->freightQuotes($order, $d)]);
    }

    /**
     * LTL prices for the same box. EasyPost sells no LTL — its only heavy service is FedEx
     * Express Freight, which runs several times the real rate — so the packer sees what the
     * load would actually cost on a pallet before buying a four-figure express label.
     */
    private function freightQuotes(object $order, array $d): array
    {
        $lb = (float) $d['weight_lb'];
        $dims = [(float) ($d['length_in'] ?? 0), (float) ($d['width_in'] ?? 0), (float) ($d['height_in'] ?? 0)];
        if (min($dims) <= 0 || ($lb < 100 && $dims[0] <= 108)) return [];
        $to = $this->ss->customerAddress($order);
        $pack = ['weight_lb' => $lb, 'boxes' => [['kind' => 'pallet', 'weight' => $lb, 'dims' => $dims, 'excluded' => true]]];
        try {
            return app(\App\Services\WarpFreightService::class)->rates($pack, (string) ($to['zip'] ?? ''), (string) ($to['country'] ?? 'US'));
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    public function store(Request $r, int $id)
    {
        $order = $this->order($id);
        $d = $r->validate([
            'kind' => 'required|in:outbound,return',
            'carrier_code' => 'required|string|max:60',
            'service_code' => 'required|string|max:60',
            'package_code' => 'nullable|string|max:60',
            'weight_lb' => 'required|numeric|min:0.1|max:150',
            'length_in' => 'nullable|numeric|min:0|max:300',
            'width_in' => 'nullable|numeric|min:0|max:108',
            'height_in' => 'nullable|numeric|min:0|max:108',
            'confirmation' => 'nullable|in:none,delivery,signature,adult_signature',
            'insure_value' => 'nullable|numeric|min:0|max:50000',
            'save_dims' => 'nullable|boolean',
            'notify' => 'nullable|boolean',
            'allow_real' => 'nullable|boolean',
            'mode' => 'nullable|in:auto,easypost,shipstation',
            'provider' => 'nullable|in:easypost,shipstation',
            'service_name' => 'nullable|string|max:120',
        ]);
        $mode = self::mode($d['mode'] ?? null);
        $provider = $d['provider'] ?? $this->provider($mode);
        $d['allow_real'] = $r->boolean('allow_real');
        // a real label is real postage on the shop's account: roles with the labels section (staff included)
        if (!($this->ss->testMode() && !$d['allow_real'])) abort_unless(\App\Support\AdminAccess::can('labels'), 403, 'Your admin role can only buy TEST labels.');

        // two tabs / a double click must not buy the same shipment twice
        $lock = Cache::lock("label-buy:{$order->id}", 60);
        if (!$lock->get()) return back()->with('admin_err', 'A label for this order is being bought right now — wait a moment and refresh.');
        try {
            if ($d['kind'] === 'outbound' && !$r->boolean('confirm_duplicate')) {
                $recent = DB::table('shipping_labels')->where('order_id', $order->id)->where('kind', 'outbound')->whereNull('voided_at')->where('created_at', '>=', now()->subMinutes(2))->first();
                if ($recent) return back()->with('admin_err', 'An outbound label (' . ($recent->tracking_number ?: '#' . $recent->id) . ') was bought for this order under 2 minutes ago. Tick "buy another label anyway" to buy a second one.');
            }
            $fellBack = false;
            if ($provider === 'shipstation') {
                $res = $this->ssl->buy($order, $d, auth()->id());
            } else {
                $res = $this->ss->buy($order, $d, auth()->id());
                // Auto: EasyPost has no balance (or its card failed) → the same carrier and service from the ShipStation balance
                if (!$res['ok'] && !empty($res['funds']) && $mode === 'auto' && $this->ssl->enabled()) {
                    $box = ['weight_lb' => $d['weight_lb'], 'length_in' => $d['length_in'] ?? 0, 'width_in' => $d['width_in'] ?? 0, 'height_in' => $d['height_in'] ?? 0];
                    $alt = $this->ssl->equivalent($order, $box, $d['kind'], $d['carrier_code'], $this->ss->serviceName($d['service_code']));
                    if (!$alt) {
                        $res['error'] .= ' — ShipStation does not price that same service for this box. Switch "Buy through" to ShipStation and pick one of its rates.';
                    } else {
                        $alt = $this->ssl->buy($order, ['carrier_code' => $alt['carrier'], 'service_code' => $alt['serviceCode'], 'service_name' => $alt['serviceName']] + $d, auth()->id());
                        if ($alt['ok']) { $res = $alt; $fellBack = true; }
                        else $res['error'] .= ' — ShipStation refused it too: ' . $alt['error'];
                    }
                }
            }
        } finally { $lock->release(); }
        if (!$res['ok']) return back()->with('admin_err', 'Label not created — ' . $res['error']);
        $label = $res['label'];
        AdminLog::record('order.label', ucfirst($label->kind) . ' label ' . ($label->tracking_number ?: '#' . $label->id) . ' on ' . $order->order_number . ($label->is_test ? ' (test)' : ' · ' . money((float) $label->cost)), 'order', (string) $order->id);

        $msg = ucfirst($label->kind) . ' label created' . ($label->provider === 'shipstation' ? ' through ShipStation' : '') . ($label->tracking_number ? ' · ' . $label->tracking_number : '') . ($label->is_test ? ' (TEST label — no postage charged)' : ' · ' . money((float) $label->cost)) . '.';
        if ($fellBack) $msg .= ' EasyPost had no balance, so it was paid from the ShipStation balance.';

        // An outbound label IS the shipment: put its tracking on the order and tell the customer.
        if ($label->kind === 'outbound' && $label->tracking_number) {
            \App\Support\OrderTracking::markShipped($order, $label->tracking_number, $this->carrierName($label->carrier_code), $label->service_label, $r->boolean('notify'));
            Cache::forget('adm_order_tabs');
            if ($r->boolean('notify')) $msg .= ' Customer notified.';
        }

        if ($r->boolean('save_dims')) {
            $saved = $this->writeBackBox($order, $label);
            if ($saved) $msg .= ' ' . $saved;
        }

        return back()->with('admin_ok', $msg);
    }

    private function carrierName(string $code): string
    {
        // "usps" contains "ups": the family check tells them apart (EasyPost slugs and ShipStation codes alike)
        return match (ShipStationLabelService::family($code)) {
            'ups' => 'UPS', 'fedex' => 'FedEx', 'dhl' => 'DHL', 'usps' => 'USPS',
            default => strtoupper(explode('_', $code)[0]),
        };
    }

    /**
     * One line, one unit: the box just measured is this product's real box, so store it
     * on the product and every later shipping quote for it stops guessing.
     */
    private function writeBackBox(object $order, object $label): ?string
    {
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        if ($items->count() !== 1 || (int) $items[0]->qty !== 1) return null;
        $it = $items[0];

        if ($it->wp_product_id) {
            $written = app(CatalogWriter::class)->setShipBox((int) $it->wp_product_id, (float) $label->weight_lb, (float) $label->length_in, (float) $label->width_in, (float) $label->height_in);
            if (!$written) return null;
            AdminLog::record('product.shipbox', 'Shipping box for ' . ($it->sku ?: $it->name) . ' set from order ' . $order->order_number, 'product', (string) $it->wp_product_id);
            return 'Saved ' . implode(' / ', $written) . ' to ' . ($it->sku ?: $it->name) . '.';
        }

        // vehicle-finder driveshafts live in ds_parts, keyed by part number
        $part = $it->sku ? \App\Services\DriveshaftFinder::pn($it->sku) : null;
        if ($part && DB::table('ds_parts')->where('part_number', $part)->exists()) {
            DB::table('ds_parts')->where('part_number', $part)->update(array_filter([
                'weight' => (float) $label->weight_lb ?: null,
                'box_length' => (float) $label->length_in ?: null,
                'box_width' => (float) $label->width_in ?: null,
                'box_height' => (float) $label->height_in ?: null,
                'updated_at' => now(),
            ], fn ($v) => $v !== null));
            AdminLog::record('product.shipbox', 'Shipping box for driveshaft ' . $part . ' set from order ' . $order->order_number, 'driveshaft', $part);
            return 'Saved the box to driveshaft ' . $part . '.';
        }
        return null;
    }

    public function pdf(int $labelId)
    {
        $label = DB::table('shipping_labels')->find($labelId);
        abort_unless($label, 404);
        $pdf = $this->ss->pdf($label);
        abort_unless($pdf, 404);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $label->kind . '-label-' . ($label->tracking_number ?: $label->id) . '.pdf"',
        ]);
    }

    public function void(int $labelId)
    {
        $label = DB::table('shipping_labels')->find($labelId);
        abort_unless($label, 404);
        $res = $label->provider === 'shipstation' ? $this->ssl->void($label) : $this->ss->void($label);
        if (!$res['ok']) return back()->with('admin_err', 'Void failed — ' . $res['error']);
        $order = DB::table('orders')->find($label->order_id);
        $note = '';
        if ($order) {
            \App\Support\OrderEvents::record($order, 'label:void', 'Label voided', trim(($label->service_label ?: '') . ' · ' . ($label->tracking_number ?: '')));
            AdminLog::record('order.label.void', 'Voided label ' . ($label->tracking_number ?: $label->id) . ' on ' . $order->order_number, 'order', (string) $order->id);
            // the order still pointed at this label: it is not shipped any more — drop the dead tracking and reopen it
            if ($label->kind === 'outbound' && $label->tracking_number && $label->tracking_number === $order->tracking_number) {
                DB::table('orders')->where('id', $order->id)->update(['tracking_number' => null, 'tracking_carrier' => null, 'tracking_service' => null, 'shipped_at' => null,
                    'status' => in_array($order->status, ['completed', 'processing', 'pending'], true) ? 'processing' : $order->status, 'updated_at' => now()]);
                \App\Support\OrderEvents::record($order, 'unshipped', 'Shipment cancelled', 'Label ' . $label->tracking_number . ' voided — back to processing');
                // the next label is a new shipment: let it record "shipped" and tell the customer again
                foreach (['shipped', 'notify:email:shipped', 'notify:sms:shipped'] as $t) \App\Support\OrderEvents::forget($order, $t);
                Cache::forget('adm_order_tabs'); Cache::forget('adm_nav_counts');
                // no automatic e-mail: staff decide what to tell a customer who already got "your order shipped"
                $notified = DB::table('order_events')->where('order_id', $order->id)->where('type', 'like', 'notify:email:shipped%')->exists();
                $note = ' Tracking removed and the order is back to processing.' . ($notified ? ' Heads-up: the customer already got a "shipped" e-mail with that tracking number — let them know.' : '');
            }
        }
        return back()->with('admin_ok', 'Label voided — the postage is refunded by the carrier.' . $note);
    }
}
