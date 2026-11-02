<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EasyPostLabelService;
use App\Services\WarpFreightService;
use App\Support\AdminLog;
use App\Support\OrderEvents;
use App\Support\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Book an order's pallet(s) as LTL freight through Warp, straight from the order page:
 * fresh bookable quotes for the load the packer confirms, pickup date and dock hours,
 * then one confirmed booking that charges the card and dispatches the carrier.
 */
class FreightBookingsController extends Controller
{
    private const DELIVERY_ACC = ['liftgate-delivery', 'inside-delivery', 'residential-delivery', 'limited-access-delivery', 'delivery-appointment'];
    private const PICKUP_ACC = ['liftgate-pickup', 'inside-pickup', 'limited-access-pickup', 'pickup-appointment'];

    public function __construct(private WarpFreightService $warp) {}

    private function order(int $id): object
    {
        $o = DB::table('orders')->find($id);
        abort_unless($o, 404);
        return $o;
    }

    /** Which key this request books with — and whether that's allowed at all. */
    private function mode(Request $r): array
    {
        $test = $this->warp->testMode() && !$r->boolean('allow_real');
        if ($test && !$this->warp->hasTestKey()) return [$test, 'No Warp sandbox key is set, so a booking here would be real. Tick "book a real truck" to go ahead.'];
        if (!$test && !config('services.warp.key')) return [$test, 'No live Warp key is configured.'];
        return [$test, null];
    }

    private function loadRules(): array
    {
        return [
            'pallets' => 'required|integer|min:1|max:26',
            'weight_lb' => 'required|numeric|min:1|max:2500',
            'length_in' => 'required|numeric|min:1|max:300',
            'width_in' => 'required|numeric|min:1|max:108',
            'height_in' => 'required|numeric|min:1|max:108',
            'pickup_date' => 'required|date|after_or_equal:today',
            'delivery_acc' => 'nullable|array', 'delivery_acc.*' => 'in:' . implode(',', self::DELIVERY_ACC),
            'pickup_acc' => 'nullable|array', 'pickup_acc.*' => 'in:' . implode(',', self::PICKUP_ACC),
            'allow_real' => 'nullable|boolean',
        ];
    }

    private function accessorials(array $d): array
    {
        return array_filter(['pickup' => array_values($d['pickup_acc'] ?? []), 'delivery' => array_values($d['delivery_acc'] ?? [])]);
    }

    /** JSON: bookable carriers for this load, lane and pickup date. */
    public function quotes(Request $r, int $id)
    {
        $order = $this->order($id);
        $d = $r->validate($this->loadRules());
        [$test, $err] = $this->mode($r);
        if ($err) return response()->json(['ok' => false, 'error' => $err], 422);

        $to = app(EasyPostLabelService::class)->customerAddress($order);
        if (strtoupper($to['country'] ?? 'US') !== 'US') return response()->json(['ok' => false, 'error' => 'Warp books U.S. LTL only.'], 422);

        $res = $this->warp->bookingQuotes(
            ['pallets' => (int) $d['pallets'], 'weight_lb' => (float) $d['weight_lb'], 'dims' => [(float) $d['length_in'], (float) $d['width_in'], (float) $d['height_in']]],
            (string) ($to['zip'] ?? ''), (string) $d['pickup_date'], $this->accessorials($d), $test
        );
        return response()->json($res + ['test' => $test, 'freight_class' => $this->warp->freightClass((float) $d['weight_lb'], [(float) $d['length_in'], (float) $d['width_in'], (float) $d['height_in']])], $res['ok'] ? 200 : 422);
    }

    public function store(Request $r, int $id)
    {
        $order = $this->order($id);
        $d = $r->validate($this->loadRules() + [
            'quote_id' => 'required|string|max:80',
            'carrier' => 'nullable|string|max:80',
            'service_level' => 'nullable|string|max:40',
            'price' => 'nullable|numeric|min:0',
            'transit_days' => 'nullable|integer|min:0|max:60',
            'pickup_ready' => 'required|date_format:H:i',
            'pickup_close' => 'required|date_format:H:i|after:pickup_ready',
            'pickup_contact' => 'required|string|max:80',
            'pickup_phone' => 'required|string|max:30',
            'pickup_note' => 'nullable|string|max:300',
            'to_name' => 'required|string|max:120',
            'to_phone' => 'required|string|max:30',
            'to_email' => 'nullable|email|max:190',
            'to_street' => 'required|string|max:190',
            'to_city' => 'required|string|max:120',
            'to_state' => 'required|string|max:40',
            'to_zip' => 'required|string|max:12',
            'delivery_note' => 'nullable|string|max:300',
            'confirm' => 'accepted',
            'notify' => 'nullable|boolean',
            'confirm_duplicate' => 'nullable|boolean',
        ], ['confirm.accepted' => 'Tick the confirmation box — booking dispatches a truck.']);
        [$test, $err] = $this->mode($r);
        if ($err) return back()->with('admin_err', 'Freight not booked — ' . $err);

        $shop = config('uds.pickup', []);
        $window = 'Pickup window ' . $d['pickup_ready'] . '–' . $d['pickup_close'] . ' ET.';
        $pickup = [
            'street' => '11611 Prospect Drive STE 4', 'city' => 'Odessa', 'state' => 'FL', 'zipCode' => '33556',
            'contactName' => $d['pickup_contact'], 'phone' => preg_replace('/[^0-9+]/', '', $d['pickup_phone']),
            'email' => (string) config('uds.email'),
            'specialInstruction' => trim($window . ' ' . ($d['pickup_note'] ?? '')),
        ];
        $delivery = [
            'street' => $d['to_street'], 'city' => $d['to_city'], 'state' => strtoupper($d['to_state']), 'zipCode' => $d['to_zip'],
            'contactName' => $d['to_name'], 'phone' => preg_replace('/[^0-9+]/', '', $d['to_phone']), 'email' => $d['to_email'] ?? null,
            'specialInstruction' => $d['delivery_note'] ?? null,
        ];
        $acc = $this->accessorials($d);

        // a double submit must not dispatch two trucks: one booking at a time per order, and the same quote / a
        // booking from the last 2 minutes needs an explicit "book another" tick
        $lock = Cache::lock("freight-book:{$order->id}", 90);
        if (!$lock->get()) return back()->with('admin_err', 'Freight for this order is being booked right now — wait a moment and refresh.');
        try {
            if (!$r->boolean('confirm_duplicate')) {
                $dup = DB::table('freight_bookings')->where('order_id', $order->id)->where('status', '!=', 'cancelled')
                    ->where(fn ($w) => $w->where('quote_id', $d['quote_id'])->orWhere('created_at', '>=', now()->subMinutes(2)))->first();
                if ($dup) { $lock->release(); return back()->with('admin_err', 'Freight was already booked for this order (' . ($dup->tracking_number ?: $dup->shipment_number ?: '#' . $dup->id) . ($dup->quote_id === $d['quote_id'] ? ', same quote' : ', under 2 minutes ago') . '). Tick "book another truck anyway" to book a second one.'); }
            }
            $res = $this->warp->book($d['quote_id'], $acc, (string) $order->order_number, $pickup, $delivery, 'Order ' . $order->order_number . ' — driveshafts and tubing', $test);
        } catch (\Throwable $e) { $lock->release(); throw $e; }
        // held until the booking row exists, so the duplicate check above always sees it
        if (!$res['ok']) $lock->release();
        if (!$res['ok']) {
            $hint = str_contains(strtolower((string) $res['error']), 'expir') ? ' Quotes are held about 15 minutes — get fresh prices and book again.' : '';
            return back()->with('admin_err', 'Freight not booked — ' . $res['error'] . $hint);
        }
        $b = $res['data'];
        $tracking = (string) ($b['tracking_number'] ?? $b['shipment_number'] ?? '');

        $bookingId = DB::table('freight_bookings')->insertGetId([
            'order_id' => $order->id, 'provider' => 'warp', 'quote_id' => $d['quote_id'],
            'shipment_id' => $b['shipment_id'] ?? null, 'shipment_number' => $b['shipment_number'] ?? null,
            'tracking_number' => $tracking ?: null, 'lane_id' => $b['lane_id'] ?? null,
            'carrier' => $d['carrier'] ?? null, 'service_level' => $d['service_level'] ?? null,
            'price' => round((float) ($d['price'] ?? 0), 2), 'pallets' => (int) $d['pallets'], 'weight_lb' => (float) $d['weight_lb'],
            'dims' => json_encode([(float) $d['length_in'], (float) $d['width_in'], (float) $d['height_in']]),
            'accessorials' => json_encode($acc), 'pickup_date' => $b['service']['pickup_date'] ?? $d['pickup_date'],
            'pickup_ready' => $d['pickup_ready'], 'pickup_close' => $d['pickup_close'], 'reference' => $order->order_number,
            'status' => 'booked', 'bol_url' => $b['sandbox_lifecycle']['bol_url'] ?? ($b['bol_url'] ?? null),
            'tracking_url' => $b['tracking_dashboard'] ?? null, 'raw' => json_encode($b),
            'is_test' => $test, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lock->release();

        $what = trim(($d['carrier'] ?? 'LTL') . ' ' . ($d['service_level'] ?? '')) . ' · ' . money((float) ($d['price'] ?? 0)) . ' · pickup ' . \Carbon\Carbon::parse($d['pickup_date'])->format('D M j') . ' ' . $d['pickup_ready'] . '–' . $d['pickup_close'];
        OrderEvents::record($order, 'freight:booked', $test ? 'Freight booked (SANDBOX — no truck)' : 'Freight booked', $what . ($tracking ? ' · ' . $tracking : ''));
        AdminLog::record('order.freight', 'Freight booked for ' . $order->order_number . ($test ? ' (sandbox)' : '') . ' — ' . $what, 'orders', (string) $order->id);

        $msg = ($test ? 'SANDBOX freight booking created (no charge, no truck)' : 'Freight booked') . ' — ' . $what . '.';
        // A real booking IS the shipment. A sandbox one carries a fake number: never e-mail a customer about it.
        if (!$test && $tracking) {
            OrderTracking::markShipped($order, $tracking, 'Warp Freight', trim(($d['carrier'] ?? '') . ' ' . ($d['service_level'] ?? '')), $r->boolean('notify'));
            Cache::forget('adm_order_tabs');
            if ($r->boolean('notify')) $msg .= ' Customer notified.';
        }
        if (!$test && in_array($order->freight_delivery, [null, ''], true)) {
            // remember what was booked so a rebook / label later starts from the same answer
            $kind = in_array('residential-delivery', $acc['delivery'] ?? [], true) ? 'residential' : (in_array('liftgate-delivery', $acc['delivery'] ?? [], true) ? 'liftgate' : 'dock');
            DB::table('orders')->where('id', $order->id)->update(['freight_delivery' => $kind]);
        }
        return back()->with('admin_ok', $msg);
    }

    /** Pull live status and any new documents (BOL after pickup, POD after delivery). */
    public function refresh(int $bookingId)
    {
        $b = DB::table('freight_bookings')->find($bookingId);
        abort_unless($b, 404);
        $id = (string) ($b->shipment_id ?: $b->shipment_number);
        $t = $id ? $this->warp->track($id, (bool) $b->is_test) : null;
        if (!$t) return back()->with('admin_err', 'Warp did not return a status for that booking right now.');

        $status = in_array($t['status'] ?? '', ['booked', 'in_transit', 'delivered', 'cancelled'], true) ? $t['status'] : $b->status;
        $docs = $this->warp->documents($id, (bool) $b->is_test);
        $bol = collect($docs)->first(fn ($x) => stripos($x['type'], 'bol') !== false || stripos($x['type'], 'bill') !== false)['url'] ?? $b->bol_url;
        $raw = json_decode((string) $b->raw, true) ?: [];
        $raw['last_track'] = $t;
        $raw['documents'] = $docs;
        DB::table('freight_bookings')->where('id', $b->id)->update([
            'status' => $status, 'bol_url' => $bol, 'raw' => json_encode($raw), 'status_checked_at' => now(), 'updated_at' => now(),
        ]);
        if ($status !== $b->status) {
            $order = DB::table('orders')->find($b->order_id);
            if ($order) {
                OrderEvents::record($order, 'freight:' . $status, 'Freight ' . str_replace('_', ' ', $status) . ($b->is_test ? ' (sandbox)' : ''), $b->tracking_number);
                if ($status === 'delivered' && !$b->is_test && !$order->delivered_at) OrderEvents::markDelivered($order, 'Warp reported delivery');
            }
        }
        return back()->with('admin_ok', 'Freight status: ' . str_replace('_', ' ', $status) . ($docs ? ' · ' . count($docs) . ' document(s)' : '') . '.');
    }
}
