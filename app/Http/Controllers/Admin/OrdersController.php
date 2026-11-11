<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\OrderBuilder;
use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use App\Support\AdminLog;
use App\Support\OrderEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\PayLink;

class OrdersController extends Controller
{
    public const STATUSES = ['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'failed' => 'Failed'];
    public const PAY = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'partially_refunded' => 'Partially refunded', 'refunded' => 'Refunded'];
    /** What staff may pick by hand — refund states only come from the Refund action, which moves the money. */
    public const MANUAL_PAY = ['unpaid' => 'Unpaid', 'paid' => 'Paid'];
    // net_terms is what checkout and the assistant write (Invoices tab, B2B credit); net30 kept for older rows
    public const PAY_METHODS = ['stripe' => 'Card (Stripe)', 'manual' => 'Manual / invoice', 'payment_link' => 'Payment link', 'call' => 'Phone', 'pay_at_pickup' => 'Pay at pickup', 'check' => 'Check', 'wire' => 'Wire / ACH', 'net_terms' => 'Net terms (invoice)', 'net30' => 'Net 30 (legacy)', 'cash' => 'Cash', 'authnet' => 'Card (Authorize.Net, old store)'];

    public function __construct(private OrderBuilder $builder, private CatalogService $catalog) {}

    public function index(Request $r)
    {
        $q = DB::table('orders');
        $tab = $r->query('tab', 'all');
        match ($tab) {
            'open' => $q->whereIn('status', ['pending', 'processing']),
            'unpaid' => $q->where('payment_status', 'unpaid')->whereNotIn('status', ['cancelled', 'failed']),
            // Net terms invoices still open, the oldest due date first
            'invoices' => $q->where('payment_method', 'net_terms')->where('payment_status', 'unpaid')->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->reorder()->orderBy('terms_due_at'),
            'to_ship' => $q->whereIn('status', ['pending', 'processing'])->whereNull('shipped_at')->where('shipping_method', '!=', CartService::PICKUP_ID),
            'pickup' => $q->where('shipping_method', CartService::PICKUP_ID)->whereIn('status', ['pending', 'processing']),
            'shipped' => $q->whereNotNull('shipped_at')->whereNull('delivered_at'),
            'completed' => $q->where('status', 'completed'),
            'cancelled' => $q->whereIn('status', ['cancelled', 'failed']),
            default => null,
        };
        if ($s = trim((string) $r->query('q', ''))) {
            $like = "%$s%";
            $q->where(fn ($w) => $w->where('order_number', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                ->orWhereRaw("CONCAT(billing_first_name,' ',billing_last_name) LIKE ?", [$like])->orWhere('tracking_number', 'like', $like)
                ->orWhere('po_number', 'like', $like)->orWhere('billing_company', 'like', $like)
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id')->where(fn ($x) => $x->where('sku', 'like', $like)->orWhere('name', 'like', $like))));
        }
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($ps = $r->query('payment')) $q->where('payment_status', $ps);
        if ($src = $r->query('source')) $q->where('source', $src);
        if ($from = $r->query('from')) $q->whereDate('created_at', '>=', $from);
        if ($to = $r->query('to')) $q->whereDate('created_at', '<=', $to);
        if ($tag = $r->query('tag')) $q->where('tags', 'like', "%$tag%");
        $sort = $r->query('sort', 'newest');
        if ($tab !== 'invoices' || $r->has('sort')) match ($sort) { 'oldest' => $q->orderBy('id'), 'total' => $q->orderByDesc('total'), 'total_asc' => $q->orderBy('total'), default => $q->orderByDesc('id') };

        if ($r->query('export') === 'csv') { abort_unless(adm_can('exports'), 403, 'Your admin role cannot export orders.'); return $this->exportCsv((clone $q)->limit(5000)->get()); }

        $rows = $q->paginate(30)->withQueryString();
        $ids = collect($rows->items())->pluck('id');
        $itemCounts = DB::table('order_items')->whereIn('order_id', $ids)->selectRaw('order_id, SUM(qty) n')->groupBy('order_id')->pluck('n', 'order_id');
        $counts = Cache::remember('adm_order_tabs', 30, fn () => [
            'all' => DB::table('orders')->count(),
            'open' => DB::table('orders')->whereIn('status', ['pending', 'processing'])->count(),
            'unpaid' => DB::table('orders')->where('payment_status', 'unpaid')->whereNotIn('status', ['cancelled', 'failed'])->count(),
            'invoices' => DB::table('orders')->where('payment_method', 'net_terms')->where('payment_status', 'unpaid')->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->count(),
            'to_ship' => DB::table('orders')->whereIn('status', ['pending', 'processing'])->whereNull('shipped_at')->where('shipping_method', '!=', CartService::PICKUP_ID)->count(),
            'pickup' => DB::table('orders')->where('shipping_method', CartService::PICKUP_ID)->whereIn('status', ['pending', 'processing'])->count(),
            'shipped' => DB::table('orders')->whereNotNull('shipped_at')->whereNull('delivered_at')->count(),
            'completed' => DB::table('orders')->where('status', 'completed')->count(),
            'cancelled' => DB::table('orders')->whereIn('status', ['cancelled', 'failed'])->count(),
        ]);
        return view('admin.orders.index', compact('rows', 'itemCounts', 'counts', 'tab', 'sort') + ['f' => $r->query()]);
    }

    private function exportCsv($rows)
    {
        $out = fopen('php://temp', 'w+');
        \App\Support\Csv::put($out, ['Order', 'Date', 'Status', 'Payment', 'Method', 'Customer', 'Email', 'Phone', 'Company', 'Ship to', 'City', 'State', 'ZIP', 'Country', 'Shipping', 'Subtotal', 'Discount', 'Tax', 'Shipping $', 'Total', 'Tracking', 'PO', 'Source']);
        foreach ($rows as $o) {
            $sh = $o->ship_to_different ? 'shipping' : 'billing';
            \App\Support\Csv::put($out, [$o->order_number, $o->created_at, $o->status, $o->payment_status, $o->payment_method, trim($o->billing_first_name . ' ' . $o->billing_last_name), $o->email, $o->phone, $o->billing_company,
                trim($o->{$sh . '_address_1'} . ' ' . $o->{$sh . '_address_2'}), $o->{$sh . '_city'}, $o->{$sh . '_state'}, $o->{$sh . '_postcode'}, $o->{$sh . '_country'} ?: $o->billing_country,
                $o->shipping_label, $o->subtotal, $o->discount_total, $o->tax_total, $o->shipping_total, $o->total, $o->tracking_number, $o->po_number, $o->source]);
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="orders-' . date('Ymd') . '.csv"']);
    }

    public function show(int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $items = DB::table('order_items')->where('order_id', $id)->orderBy('id')->get();
        $thumbs = $this->catalog->thumbsFor($items->pluck('wp_product_id')->filter()->all());
        $specs = [];
        foreach ($items as $it) {
            $it->image = $thumbs[(int) $it->wp_product_id] ?? '';
            $it->metaArr = json_decode($it->meta ?: '{}', true) ?: [];
            if ($it->sku && str_starts_with($it->sku, 'U') && ($pn = DriveshaftFinder::pn($it->sku))) {
                $it->part_number = $pn;
                $specs[$pn] = DB::table('ds_specs')->where('part_number', $pn)->first();
                if (!$it->image) { $img = DB::table('ds_parts')->where('part_number', $pn)->value('main_image'); if ($img) $it->image = media_url($img, 160); }
            }
        }
        $events = OrderEvents::for($order);
        $notes = DB::table('order_notes as n')->leftJoin('users as u', 'u.id', '=', 'n.user_id')->where('n.order_id', $id)->orderByDesc('n.id')->get(['n.*', 'u.name as user_name']);
        $customerOrders = DB::table('orders')->where('email', $order->email)->where('id', '!=', $id)->orderByDesc('id')->limit(6)->get();
        $customer = DB::table('users')->where('email', $order->email)->first();
        $custStats = DB::table('orders')->where('email', $order->email)->where('status', '!=', 'cancelled')->selectRaw('COUNT(*) n, SUM(total) t')->first();
        $activity = AdminLog::for('order', $id, 12);
        $pack = null;
        try { $pack = app(\App\Services\PackingService::class)->packageSummary($items->map(fn ($i) => ['type' => $i->type, 'wp_product_id' => $i->wp_product_id, 'sku' => $i->sku, 'name' => $i->name, 'qty' => $i->qty, 'price' => $i->price, 'meta' => $i->metaArr])->all()); } catch (\Throwable $e) {}
        $ssLabels = app(\App\Services\EasyPostLabelService::class);
        $labels = DB::table('shipping_labels')->where('order_id', $id)->orderByDesc('id')->get();
        $ship = [
            'enabled' => $ssLabels->enabled(), 'test' => $ssLabels->testMode(),
            'carriers' => $ssLabels->carriers(),
            'provider' => 'EasyPost',
            // so the label modal can preselect the very service the customer paid for
            'paid_method' => (string) ($order->shipping_method ?? ''),
            'paid_label' => (string) ($order->shipping_label ?? ''),
            'paid_total' => (float) ($order->shipping_total ?? 0),
            'test_labels' => $ssLabels->supportsTestLabels(),
            // one line, one unit: the measured box can be written back onto that product
            'writeback' => $items->count() === 1 && (int) $items[0]->qty === 1 ? ($items[0]->sku ?: $items[0]->name) : null,
        ];
        $freight = $this->freightPanel($order, $pack);
        $ptiDs = app(\App\Services\PtiClient::class)->configured() ? [
            'lines' => app(\App\Services\PtiDropship::class)->lines($order),
            'ship_to' => app(\App\Services\PtiDropship::class)->shipTo($order),
            'rows' => DB::table('pti_dropships')->where('order_id', $order->id)->whereIn('status', ['placed', 'prepared'])->orderBy('id')->get(),
        ] : null;
        $rmas = \App\Support\Rma::forOrder($order);   // return / exchange / cancellation requests on this order
        return view('admin.orders.show', compact('order', 'items', 'events', 'notes', 'customerOrders', 'customer', 'custStats', 'activity', 'specs', 'pack', 'labels', 'ship', 'freight', 'ptiDs', 'rmas'));
    }

    /** What the "Book freight" card and modal need: the pallets, the customer's delivery answer, pickup days. */
    private function freightPanel(object $order, ?array $pack): array
    {
        $warp = app(\App\Services\WarpFreightService::class);
        $bookings = DB::table('freight_bookings')->where('order_id', $order->id)->orderByDesc('id')->get();
        $pallets = array_values(array_filter($pack['boxes'] ?? [], fn ($b) => ($b['kind'] ?? '') === 'pallet'));
        $isFreight = $pallets || \App\Services\CartService::isFreight((string) $order->shipping_method) || $bookings->count();
        $first = $pallets[0] ?? null;
        $delivery = \App\Services\WarpFreightService::DELIVERY[$order->freight_delivery ?: 'residential'] ?? [];
        $days = [];
        $d = now('America/New_York')->startOfDay();
        while (count($days) < 6) { $d->addDay(); if ($d->isWeekday()) $days[] = $d->copy(); }
        $to = app(\App\Services\EasyPostLabelService::class)->customerAddress($order);
        return [
            'show' => (bool) $isFreight && strtoupper($to['country'] ?? 'US') === 'US',
            'enabled' => $warp->bookingEnabled(),
            'test' => $warp->testMode(), 'test_key' => $warp->hasTestKey(),
            'bookings' => $bookings,
            'pallets' => max(1, count($pallets)),
            'weight' => $first ? round((float) $first['weight'], 1) : round((float) ($pack['weight_lb'] ?? 0) + 40, 1),
            'dims' => $first['dims'] ?? [48, 40, 48],
            'delivery' => $delivery, 'delivery_answer' => $order->freight_delivery,
            'days' => $days, 'to' => $to,
        ];
    }

    public function update(Request $r, int $id)
    {
        $before = DB::table('orders')->find($id); abort_unless($before, 404);
        // an unchanged value is never re-validated or rewritten: a method we don't list (or a refund state) survives a status save
        $same = fn ($k) => $r->has($k) && (string) $r->input($k) === (string) $before->$k;
        $d = $r->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(self::STATUSES)),
            'payment_status' => $same('payment_status') ? 'nullable' : 'nullable|in:' . implode(',', array_keys(self::MANUAL_PAY)),
            'payment_method' => $same('payment_method') ? 'nullable' : 'nullable|in:' . implode(',', array_keys(self::PAY_METHODS)),
            'tags' => 'nullable|string|max:255', 'po_number' => 'nullable|string|max:80',
            'notify' => 'nullable',
        ], ['payment_status.in' => 'Refunds are recorded with the Refund action, which also returns the money.']);
        $upd = ['updated_at' => now()];
        foreach (['status', 'payment_status', 'payment_method', 'po_number'] as $k) if ($r->has($k) && $d[$k] !== null && !$same($k)) $upd[$k] = $d[$k];
        if ($r->has('tags')) $upd['tags'] = $d['tags'] ?: null;
        if (($upd['payment_method'] ?? null) === 'net_terms' && empty($before->terms_due_at)) $upd['terms_due_at'] = now('America/New_York')->addDays(\App\Services\B2bAccounts::termsDays())->toDateString();
        if (($upd['payment_status'] ?? null) === 'paid' && $before->payment_status !== 'paid') $upd['paid_at'] = now();
        DB::table('orders')->where('id', $id)->update($upd);
        $newPay = $upd['payment_status'] ?? $before->payment_status; $newStatus = $upd['status'] ?? $before->status;
        if ($newPay === 'paid' && $before->payment_status !== 'paid') OrderEvents::record($before, 'paid', 'Payment confirmed', self::PAY_METHODS[$upd['payment_method'] ?? $before->payment_method] ?? ucfirst(str_replace('_', ' ', $before->payment_method)), true);
        if ($newPay === 'paid' && $before->payment_status !== 'paid' && $newStatus !== 'cancelled') \App\Support\StockLedger::deduct($before);
        if ($newStatus !== $before->status) {
            if ($newStatus === 'processing') OrderEvents::record($before, 'processing', 'In production / packing', null, true);
            if ($newStatus === 'completed') OrderEvents::record($before, 'completed', $before->shipping_method === CartService::PICKUP_ID ? 'Ready for pickup' : 'Completed', null, true);
            if ($newStatus === 'cancelled') { OrderEvents::record($before, 'cancelled', 'Order cancelled', null, true); \App\Support\StockLedger::restore($before, 'Cancelled'); }
            AdminLog::record('order.status', "Order {$before->order_number}: " . ($before->status) . ' → ' . $newStatus, 'order', $id);
            // pickup orders: tell the customer it is ready
            if ($newStatus === 'completed' && $before->shipping_method === CartService::PICKUP_ID && $r->boolean('notify')) OrderEvents::notifyPickupReady($before);
        }
        if ($newPay !== $before->payment_status) AdminLog::record('order.payment', "Order {$before->order_number}: payment {$before->payment_status} → {$newPay}", 'order', $id);
        Cache::forget('adm_nav_counts'); Cache::forget('adm_order_tabs');
        return back()->with('admin_ok', 'Order updated.');
    }

    public function tracking(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $d = $r->validate(['tracking_number' => 'nullable|string|max:80', 'tracking_carrier' => 'nullable|string|max:40', 'tracking_service' => 'nullable|string|max:80', 'notify' => 'nullable']);
        if (empty($d['tracking_number'])) {
            DB::table('orders')->where('id', $id)->update(['tracking_number' => null, 'tracking_carrier' => null, 'tracking_service' => null, 'shipped_at' => null, 'updated_at' => now()]);
            AdminLog::record('order.tracking', "Order {$order->order_number}: tracking cleared", 'order', $id);
            return back()->with('admin_ok', 'Tracking cleared.');
        }
        \App\Support\OrderTracking::markShipped($order, trim($d['tracking_number']), $d['tracking_carrier'] ?? null, $d['tracking_service'] ?? null, $r->boolean('notify'));
        AdminLog::record('order.tracking', "Order {$order->order_number}: shipped via " . ($d['tracking_carrier'] ?: 'UPS') . ' ' . $d['tracking_number'], 'order', $id);
        Cache::forget('adm_order_tabs');
        return back()->with('admin_ok', 'Tracking saved' . ($r->boolean('notify') ? ' and customer notified.' : '.'));
    }

    /** Copy/send the hosted payment link (e-mail, optional SMS). */
    public function payLink(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        if (!\App\Support\PayLink::payable($order)) return back()->with('admin_err', 'This order has no balance to pay.');
        $sent = \App\Support\PayLink::send($order, $r->boolean('sms'), trim((string) $r->input('note')) ?: null);
        AdminLog::record('order.paylink', "Order {$order->order_number}: payment link sent (" . implode('+', $sent) . ')', 'order', $id);
        return back()->with($sent ? 'admin_ok' : 'admin_err', $sent ? 'Payment link sent by ' . implode(' and ', $sent) . ' to ' . $order->email : 'Could not send the link.');
    }

    public function delivered(int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        OrderEvents::markDelivered($order, 'Marked delivered by ' . auth()->user()->name, true);
        AdminLog::record('order.delivered', "Order {$order->order_number} marked delivered", 'order', $id);
        return back()->with('admin_ok', 'Marked as delivered.');
    }

    /* ---------- line items ---------- */

    public function items(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $act = $r->input('action');
        if ($act === 'add') {
            $r->validate(['qty' => 'nullable|integer|min:1|max:999', 'price' => 'nullable|numeric|min:0']);
            $line = $this->builder->line($r->only(['type', 'id', 'sku', 'qty', 'price', 'name', 'weight']));
            if (!$line) return back()->with('admin_err', 'Could not add that item.');
            DB::table('order_items')->insert(['order_id' => $id, 'type' => $line['type'], 'wp_product_id' => $line['wp_product_id'], 'name' => $line['name'], 'sku' => $line['sku'],
                'price' => $line['price'], 'qty' => $line['qty'], 'line_total' => round($line['price'] * $line['qty'], 2), 'meta' => json_encode($line['meta']), 'created_at' => now(), 'updated_at' => now()]);
            AdminLog::record('order.item', "Order {$order->order_number}: added {$line['qty']}× {$line['sku']}", 'order', $id);
        } elseif ($act === 'update') {
            $d = $r->validate(['item_id' => 'required|integer', 'qty' => 'required|integer|min:1', 'price' => 'required|numeric|min:0']);
            DB::table('order_items')->where('order_id', $id)->where('id', $d['item_id'])->update(['qty' => $d['qty'], 'price' => $d['price'], 'line_total' => round($d['qty'] * $d['price'], 2), 'updated_at' => now()]);
            AdminLog::record('order.item', "Order {$order->order_number}: line #{$d['item_id']} set to {$d['qty']} × " . money($d['price']), 'order', $id);
        } elseif ($act === 'remove') {
            $it = DB::table('order_items')->where('order_id', $id)->where('id', (int) $r->input('item_id'))->first();
            if ($it) { DB::table('order_items')->where('id', $it->id)->delete(); AdminLog::record('order.item', "Order {$order->order_number}: removed {$it->sku}", 'order', $id); }
        }
        $this->builder->recalc($id);
        OrderEvents::record($order, 'edited', 'Order edited by ' . auth()->user()->name, 'Items changed');
        return back()->with('admin_ok', 'Items updated — totals recalculated.');
    }

    public function totals(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $d = $r->validate(['shipping_total' => 'required|numeric|min:0', 'shipping_label' => 'nullable|string|max:120', 'discount_total' => 'required|numeric|min:0', 'tax_total' => 'required|numeric|min:0', 'coupon_code' => 'nullable|string|max:40']);
        DB::table('orders')->where('id', $id)->update(['shipping_total' => $d['shipping_total'], 'shipping_label' => ($d['shipping_label'] ?? null) ?: $order->shipping_label, 'discount_total' => $d['discount_total'], 'tax_total' => $d['tax_total'], 'coupon_code' => ($d['coupon_code'] ?? null) ?: null, 'updated_at' => now()]);
        $this->builder->recalc($id);
        AdminLog::record('order.totals', "Order {$order->order_number}: totals edited", 'order', $id, $d);
        return back()->with('admin_ok', 'Totals updated.');
    }

    public function address(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $which = $r->input('which') === 'shipping' ? 'shipping' : 'billing';
        $d = $r->validate(['first_name' => 'required|string|max:80', 'last_name' => 'nullable|string|max:80', 'company' => 'nullable|string|max:120', 'address_1' => 'nullable|string|max:190', 'address_2' => 'nullable|string|max:190', 'city' => 'nullable|string|max:120', 'state' => 'nullable|string|max:60', 'postcode' => 'nullable|string|max:20', 'country' => 'required|string|size:2', 'email' => 'nullable|email', 'phone' => 'nullable|string|max:40']);
        $upd = ['updated_at' => now()];
        foreach (['first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode'] as $k) $upd[$which . '_' . $k] = $d[$k] ?? null;
        $upd[$which . '_country'] = strtoupper($d['country']);
        if ($which === 'billing') { $upd['billing_company'] = $d['company'] ?? null; if (!empty($d['email'])) $upd['email'] = $d['email']; if ($r->has('phone')) $upd['phone'] = $d['phone'] ?? null; }
        else $upd['ship_to_different'] = 1;
        DB::table('orders')->where('id', $id)->update($upd);
        AdminLog::record('order.address', "Order {$order->order_number}: {$which} address edited", 'order', $id);
        return back()->with('admin_ok', ucfirst($which) . ' address updated.');
    }

    public function note(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $d = $r->validate(['body' => 'required|string|max:4000', 'kind' => 'nullable|in:internal,customer']);
        $kind = $d['kind'] ?? 'internal';
        DB::table('order_notes')->insert(['order_id' => $id, 'user_id' => auth()->id(), 'kind' => $kind, 'body' => $d['body'], 'created_at' => now()]);
        if ($kind === 'customer') {
            OrderEvents::record($order, 'note', 'Message from Universal Driveshafts', $d['body']);
            try { \App\Support\Mailer::simple($order->email, "About your order {$order->order_number}", ['kicker' => 'Order ' . $order->order_number, 'title' => "A note from Universal Driveshafts", 'notice' => ['kind' => 'info', 'text' => $d['body']], 'button' => ['Order status', OrderEvents::statusUrl($order)], 'signoff' => 'Just reply to this e-mail to answer — it comes straight to the shop.'], ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) { report($e); }
        }
        return back()->with('admin_ok', $kind === 'customer' ? 'Note sent to the customer.' : 'Note added.');
    }

    public function deleteNote(int $id, int $noteId)
    {
        $n = DB::table('order_notes')->where('order_id', $id)->where('id', $noteId)->first();
        DB::table('order_notes')->where('order_id', $id)->where('id', $noteId)->delete();
        if ($n) AdminLog::record('order.note.delete', "Deleted {$n->kind} note on order #{$id}", 'order', $id, ['body' => mb_substr($n->body, 0, 500)]);
        return back()->with('admin_ok', 'Note removed.');
    }

    public function email(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $items = DB::table('order_items')->where('order_id', $id)->get();
        $which = $r->input('which', 'confirmation');
        try {
            if ($which === 'shipped' && $order->tracking_number) app(\App\Services\OrderMailer::class)->sendShipped($order, $order->tracking_carrier ?: 'UPS', $order->tracking_number, $order->tracking_service);
            elseif ($which === 'invoice') {
                $pdf = $this->pdf($order, $items)->output();
                \App\Support\Mailer::simple($order->email, "Invoice {$order->order_number} — Universal Driveshafts", ['kicker' => 'Invoice', 'title' => "Invoice for order {$order->order_number}", 'intro' => 'The PDF is attached. Total ' . money($order->total) . ($order->payment_status === 'paid' ? ' — paid, thank you.' : '.'), 'button' => ['Order status', OrderEvents::statusUrl($order)]] + (PayLink::payable($order) ? ['notice' => ['kind' => 'info', 'title' => 'Pay online', 'html' => '<a href="' . e(PayLink::url($order)) . '" style="color:#1246ab;font-weight:600;">Pay ' . money(PayLink::balance($order)) . ' securely by card</a>']] : []), ['replyTo' => [config('uds.email')], 'attachments' => [['name' => 'invoice-' . $order->order_number . '.pdf', 'data' => $pdf, 'mime' => 'application/pdf']]]);
            } else app(\App\Services\OrderMailer::class)->send($order, $items);
            OrderEvents::record($order, 'notify:email:' . $which . ':resend', ucfirst($which) . ' e-mail re-sent', $order->email);
            AdminLog::record('order.email', "Order {$order->order_number}: {$which} e-mail sent", 'order', $id);
        } catch (\Throwable $e) { report($e); return back()->with('admin_err', 'E-mail failed: ' . $e->getMessage()); }
        return back()->with('admin_ok', ucfirst($which) . ' e-mail sent to ' . $order->email);
    }

    public function refund(Request $r, int $id)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'nullable|string|max:200', 'restock' => 'nullable']);
        $amount = round((float) $d['amount'], 2);
        // one refund per order at a time; the order is re-read inside so a double click sees the first refund
        $lock = Cache::lock("refund:$id", 30);
        try { $lock->block(5); } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) { return back()->with('admin_err', 'Another refund on this order is still running — refresh in a moment.'); }
        try {
            $order = DB::table('orders')->find($id); abort_unless($order, 404);
            $max = round((float) $order->total - (float) $order->refund_total, 2);
            if ($amount > $max) return back()->with('admin_err', 'Refund cannot exceed the remaining ' . money($max) . '.');
            $stripeOk = null;
            if ($order->payment_method === 'stripe' && $order->stripe_payment_intent && config('services.stripe.secret')) {
                try {
                    // same order + amount + refunded-so-far = same key, so a retried request cannot refund twice at Stripe
                    $resp = \Illuminate\Support\Facades\Http::asForm()->withToken(config('services.stripe.secret'))
                        ->withHeaders(['Idempotency-Key' => "refund-$id-" . sha1($amount . '|' . $order->refund_total)])
                        ->post('https://api.stripe.com/v1/refunds', ['payment_intent' => $order->stripe_payment_intent, 'amount' => (int) round($amount * 100)]);
                    $stripeOk = $resp->successful();
                    if (!$stripeOk) return back()->with('admin_err', 'Stripe refused the refund: ' . ($resp->json('error.message') ?? $resp->status()));
                } catch (\Throwable $e) { report($e); return back()->with('admin_err', 'Stripe error: ' . $e->getMessage()); }
            }
            DB::table('orders')->where('id', $id)->increment('refund_total', $amount, ['updated_at' => now()]);
            // dated on its own: net sales take a refund off on the day it was made (as WooCommerce does)
            DB::table('order_refunds')->insert(['order_id' => $id, 'amount' => $amount, 'reason' => $d['reason'] ?? null, 'created_by' => $r->user()->id, 'created_at' => now()]);
            $newTotal = round((float) DB::table('orders')->where('id', $id)->value('refund_total'), 2);
            $full = $newTotal >= (float) $order->total - 0.005;
            DB::table('orders')->where('id', $id)->update(['payment_status' => $full ? 'refunded' : 'partially_refunded', 'status' => $full && in_array($order->status, ['pending', 'processing'], true) ? 'cancelled' : $order->status]);
        } finally { $lock->release(); }
        OrderEvents::record($order, 'refund', ($full ? 'Refunded ' : 'Partial refund ') . money($amount), trim(($d['reason'] ?? '') . ($stripeOk ? ' · via Stripe' : ($stripeOk === false ? '' : ' · recorded manually'))));
        // restock once per order (a second partial refund must not add the parts again)
        if ($r->boolean('restock')) \App\Support\StockLedger::restore($order, 'Refund');
        AdminLog::record('order.refund', "Order {$order->order_number}: refund " . money($amount), 'order', $id, $d);
        Cache::forget('adm_nav_counts'); Cache::forget('adm_order_tabs');
        return back()->with('admin_ok', money($amount) . ' refund recorded' . ($stripeOk ? ' and sent through Stripe.' : '.'));
    }

    public function invoice(int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $items = DB::table('order_items')->where('order_id', $id)->get();
        return $this->pdf($order, $items)->stream('invoice-' . $order->order_number . '.pdf');
    }

    public function packingSlip(int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $items = DB::table('order_items')->where('order_id', $id)->get();
        foreach ($items as $it) $it->metaArr = json_decode($it->meta ?: '{}', true) ?: [];
        $pack = null; try { $pack = app(\App\Services\PackingService::class)->packageSummary($items->map(fn ($i) => ['type' => $i->type, 'wp_product_id' => $i->wp_product_id, 'sku' => $i->sku, 'name' => $i->name, 'qty' => $i->qty, 'price' => $i->price, 'meta' => $i->metaArr])->all()); } catch (\Throwable $e) {}
        $thumbs = $this->itemThumbs($items);
        return view('admin.orders.packing-slip', compact('order', 'items', 'pack', 'thumbs'));
    }

    private function pdf(object $order, $items)
    {
        $html = view('invoice', compact('order', 'items'))->render();
        $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html); $pdf->setPaper('letter'); $pdf->render();
        return $pdf;
    }

    public function bulk(Request $r)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $r->input('ids'))));
        $act = (string) $r->input('bulk');
        if (!$ids) return back()->with('admin_err', 'Nothing selected.');
        if ($act === 'print') return redirect('/admin/orders/print?ids=' . implode(',', $ids));
        if (!in_array($act, ['processing', 'completed', 'cancelled', 'paid', 'tag'], true)) return back()->with('admin_err', 'Choose an action first.');
        $tag = trim((string) $r->input('tag'));
        if ($act === 'tag' && $tag === '') return back()->with('admin_err', 'Type the tag to add.');
        $n = 0; $skipped = [];
        foreach ($ids as $id) {
            $o = DB::table('orders')->find($id); if (!$o) continue;
            if (in_array($act, ['processing', 'completed', 'cancelled'], true)) {
                if ($o->status === $act) continue;
                // cancelling a paid order needs a refund decision — that happens on the order, not in bulk
                if ($act === 'cancelled' && in_array($o->payment_status, ['paid', 'partially_refunded'], true)) { $skipped[] = $o->order_number; continue; }
                DB::table('orders')->where('id', $id)->update(['status' => $act, 'updated_at' => now()]);
                OrderEvents::record($o, $act, $act === 'processing' ? 'In production / packing' : ($act === 'completed' ? 'Completed' : 'Order cancelled'), 'Bulk update', true);
                if ($act === 'cancelled') \App\Support\StockLedger::restore($o, 'Cancelled');
                AdminLog::record('order.status', "Order {$o->order_number}: {$o->status} → {$act} (bulk)", 'order', $id);
            } elseif ($act === 'paid') {
                if ($o->payment_status === 'paid') continue;
                DB::table('orders')->where('id', $id)->update(['payment_status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
                OrderEvents::record($o, 'paid', 'Payment confirmed', 'Bulk update', true);
                if (!in_array($o->status, ['cancelled', 'failed'], true)) \App\Support\StockLedger::deduct($o);
                AdminLog::record('order.payment', "Order {$o->order_number}: payment {$o->payment_status} → paid (bulk)", 'order', $id);
            } else {
                $tags = array_filter(array_map('trim', explode(',', (string) $o->tags))); if (in_array($tag, $tags, true)) continue; $tags[] = $tag;
                DB::table('orders')->where('id', $id)->update(['tags' => implode(',', $tags), 'updated_at' => now()]);
                AdminLog::record('order.tag', "Order {$o->order_number}: tagged {$tag} (bulk)", 'order', $id);
            }
            $n++;
        }
        AdminLog::record('order.bulk', "Bulk {$act} on {$n} orders", 'order', null, ['ids' => $ids, 'skipped' => $skipped]);
        Cache::forget('adm_nav_counts'); Cache::forget('adm_order_tabs');
        $msg = "{$n} order(s) updated.";
        if ($skipped) return back()->with('admin_ok', $msg)->with('admin_err', count($skipped) . ' paid order(s) were not cancelled (' . implode(', ', array_slice($skipped, 0, 8)) . (count($skipped) > 8 ? '…' : '') . ') — open each one to refund and cancel.');
        return back()->with('admin_ok', $msg);
    }

    public function printBatch(Request $r)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $r->query('ids'))));
        $orders = DB::table('orders')->whereIn('id', $ids)->orderBy('id')->get();
        $items = DB::table('order_items')->whereIn('order_id', $ids)->get()->groupBy('order_id');
        $thumbs = $this->itemThumbs($items->flatten(1));
        return view('admin.orders.print-batch', compact('orders', 'items', 'thumbs'));
    }

    /** product id → absolute image URL, so the picker can see what to pull off the shelf (blank when there is no photo). */
    private function itemThumbs($items): array
    {
        try {
            $t = app(\App\Services\CatalogService::class)->thumbsFor($items->pluck('wp_product_id')->all());
        } catch (\Throwable $e) { return []; }
        return array_map(fn ($u) => $u ? (str_starts_with($u, 'http') ? $u : url($u)) : '', $t);
    }

    /* ---------- manual order ---------- */

    public function create(Request $r)
    {
        $customer = null;
        if ($uid = (int) $r->query('customer')) $customer = DB::table('users')->find($uid);
        $prefill = [];
        if ($rid = (int) $r->query('reorder')) {
            $src = DB::table('orders')->find($rid);
            if ($src) {
                $prefill = (array) $src;
                $prefill['lines'] = DB::table('order_items')->where('order_id', $rid)->get()->map(fn ($i) => ['type' => $i->type === 'product' ? 'product' : ($i->type === 'driveshaft' ? 'driveshaft' : 'custom'), 'id' => $i->wp_product_id, 'sku' => $i->sku, 'name' => $i->name, 'qty' => $i->qty, 'price' => $i->price, 'image' => ''])->all();
            }
        }
        return view('admin.orders.create', compact('customer', 'prefill'));
    }

    /** JSON: quote shipping options for the lines + address in the builder. */
    public function quote(Request $r)
    {
        $items = $this->builder->lines($r->input('lines', []));
        $addr = $r->input('address', []);
        $opts = $this->builder->shippingOptions($items, (string) ($addr['country'] ?? 'US'), (string) ($addr['state'] ?? ''), (string) ($addr['postcode'] ?? ''));
        return response()->json(['subtotal' => CartService::subtotalOf($items), 'free_base' => $this->cart()->freeShippingBase($items), 'options' => $opts, 'lines' => $items]);
    }

    private function cart(): CartService { return app(CartService::class); }

    public function store(Request $r)
    {
        $d = $r->validate([
            'email' => 'required|email', 'phone' => 'nullable|string|max:40', 'user_id' => 'nullable|integer',
            'billing_first_name' => 'required|string|max:80', 'billing_last_name' => 'nullable|string|max:80', 'billing_company' => 'nullable|string|max:120',
            'billing_address_1' => 'nullable|string|max:190', 'billing_address_2' => 'nullable|string|max:190', 'billing_city' => 'nullable|string|max:120', 'billing_state' => 'nullable|string|max:60', 'billing_postcode' => 'nullable|string|max:20', 'billing_country' => 'required|string|size:2',
            'ship_to_different' => 'nullable', 'shipping_first_name' => 'nullable|string|max:80', 'shipping_last_name' => 'nullable|string|max:80', 'shipping_address_1' => 'nullable|string|max:190', 'shipping_address_2' => 'nullable|string|max:190', 'shipping_city' => 'nullable|string|max:120', 'shipping_state' => 'nullable|string|max:60', 'shipping_postcode' => 'nullable|string|max:20', 'shipping_country' => 'nullable|string|size:2',
            'shipping_method' => 'required|string|max:80', 'shipping_label' => 'nullable|string|max:160', 'shipping_total' => 'nullable|numeric|min:0',
            'discount_total' => 'nullable|numeric|min:0', 'tax_total' => 'nullable|numeric|min:0', 'coupon_code' => 'nullable|string|max:40',
            'status' => 'required|in:pending,processing,completed', 'payment_status' => 'required|in:unpaid,paid', 'payment_method' => 'required|in:' . implode(',', array_keys(self::PAY_METHODS)),
            'source' => 'nullable|in:admin,phone,wholesale,web', 'po_number' => 'nullable|string|max:80', 'tags' => 'nullable|string|max:255',
            'customer_note' => 'nullable|string|max:2000', 'internal_note' => 'nullable|string|max:4000', 'ship_instructions' => 'nullable|string|max:500', 'signature_required' => 'nullable', 'sms_opt_in' => 'nullable',
            'lines' => 'required|array|min:1', 'send_email' => 'nullable', 'create_account' => 'nullable', 'send_pay_link' => 'nullable',
        ]);
        if ($d['payment_method'] === 'net30') $d['payment_method'] = 'net_terms';   // one name for invoices (Invoices tab, B2B credit)
        $items = $this->builder->lines($d['lines']);
        if (!$items) return back()->withInput()->with('admin_err', 'Add at least one valid line item.');
        // a typed coupon code counts against its limits like a checkout use; a free-form discount needs no code
        $coupon = null;
        if ($code = trim((string) ($d['coupon_code'] ?? ''))) {
            $coupon = DB::table('coupons')->whereRaw('LOWER(code) = ?', [strtolower($code)])->first();
            $why = match (true) {
                !$coupon || !$coupon->active => 'is not an active coupon',
                $coupon->expires_at && Carbon::parse($coupon->expires_at)->isPast() => 'has expired',
                $coupon->starts_at && Carbon::parse($coupon->starts_at)->isFuture() => 'is not active yet',
                $coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit => 'has reached its usage limit',
                default => null,
            };
            if ($why) return back()->withInput()->with('admin_err', "Coupon {$code} {$why}. Clear the code to give the discount by hand.");
        }
        // link / create the customer account
        $user = DB::table('users')->where('email', $d['email'])->first();
        if (!$user && $r->boolean('create_account')) {
            $uid = DB::table('users')->insertGetId(['name' => trim($d['billing_first_name'] . ' ' . ($d['billing_last_name'] ?? '')), 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'company' => $d['billing_company'] ?? null,
                'password' => bcrypt(\Illuminate\Support\Str::random(24)), 'is_admin' => 0, 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
            $user = DB::table('users')->find($uid);
        }
        $d['user_id'] = $user?->id;
        $order = $this->builder->create($d, $items, auth()->user());
        if ($coupon) DB::table('coupons')->where('id', $coupon->id)->increment('used_count');
        if ($r->boolean('send_email')) { try { app(\App\Services\OrderMailer::class)->send($order, DB::table('order_items')->where('order_id', $order->id)->get()); OrderEvents::record($order, 'notify:email:placed', 'Confirmation e-mail sent', $order->email, true); } catch (\Throwable $e) { report($e); } }
        $msg = 'Order ' . $order->order_number . ' created.';
        if ($r->boolean('send_pay_link') && \App\Support\PayLink::payable($order)) { $sent = \App\Support\PayLink::send($order, $r->boolean('sms_opt_in')); if ($sent) $msg .= ' Payment link sent by ' . implode(' and ', $sent) . '.'; }
        Cache::forget('adm_nav_counts'); Cache::forget('adm_order_tabs');
        return redirect('/admin/orders/' . $order->id)->with('admin_ok', $msg);
    }

    /** JSON product/part picker used by the order builder and the spec component list. */
    public function apiProducts(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['items' => []]);
        $like = "%$q%";
        $rows = DB::connection('wp')->table('wp_posts as p')
            ->leftJoin('wp_postmeta as sk', fn ($j) => $j->on('sk.post_id', '=', 'p.ID')->where('sk.meta_key', '_sku'))
            ->leftJoin('wp_postmeta as pr', fn ($j) => $j->on('pr.post_id', '=', 'p.ID')->where('pr.meta_key', '_price'))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->where(fn ($w) => $w->where('p.post_title', 'like', $like)->orWhere('sk.meta_value', 'like', $like))
            ->orderByRaw('CASE WHEN sk.meta_value = ? THEN 0 WHEN sk.meta_value LIKE ? THEN 1 ELSE 2 END', [$q, $q . '%'])
            ->limit(12)->get(['p.ID', 'p.post_title', 'sk.meta_value as sku', 'pr.meta_value as price'])->unique('ID')->values();
        $thumbs = $this->catalog->thumbsFor($rows->pluck('ID')->all());
        $stock = DB::table('inventory')->whereIn('wp_product_id', $rows->pluck('ID')->all())->pluck('stock', 'wp_product_id');
        $items = $rows->map(fn ($p) => ['type' => 'product', 'id' => (int) $p->ID, 'sku' => $p->sku, 'name' => html_entity_decode($p->post_title), 'price' => (float) $p->price, 'image' => $thumbs[(int) $p->ID] ?? '', 'stock' => $stock[$p->ID] ?? null])->all();
        if (!$r->boolean('products_only')) {
            $pn = DriveshaftFinder::pn($q);
            foreach (DB::table('ds_parts')->where('is_active', 1)->where(fn ($w) => $w->where('part_number', 'like', "%$pn%")->orWhere('description', 'like', $like))->orderByRaw('CASE WHEN part_number LIKE ? THEN 0 ELSE 1 END', [$pn . '%'])->limit(8)->get() as $p) {
                $items[] = ['type' => 'driveshaft', 'id' => null, 'sku' => DriveshaftFinder::sku($p->part_number), 'name' => $p->description ?: 'Driveshaft', 'price' => (float) (DriveshaftFinder::customerPrice($p) ?? 0), 'image' => $p->main_image ? media_url($p->main_image, 160) : '', 'stock' => null];
            }
        }
        return response()->json(['items' => $items]);
    }

    public function apiCustomers(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['items' => []]);
        $like = "%$q%";
        $users = DB::table('users')->where('is_admin', 0)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('company', 'like', $like))->limit(8)->get();
        $items = $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'company' => $u->company, 'wholesale' => $u->wholesale_status === 'approved' ? (float) $u->wholesale_discount : 0, 'source' => 'account'])->all();
        // guests from past orders
        foreach (DB::table('orders')->where(fn ($w) => $w->where('email', 'like', $like)->orWhereRaw("CONCAT(billing_first_name,' ',billing_last_name) LIKE ?", [$like])->orWhere('billing_company', 'like', $like))->whereNotIn('email', $users->pluck('email'))->orderByDesc('id')->limit(30)->get()->unique('email')->take(6) as $o) {
            $items[] = ['id' => null, 'name' => trim($o->billing_first_name . ' ' . $o->billing_last_name), 'email' => $o->email, 'phone' => $o->phone, 'company' => $o->billing_company, 'wholesale' => 0, 'source' => 'order', 'address' => ['first_name' => $o->billing_first_name, 'last_name' => $o->billing_last_name, 'company' => $o->billing_company, 'address_1' => $o->billing_address_1, 'address_2' => $o->billing_address_2, 'city' => $o->billing_city, 'state' => $o->billing_state, 'postcode' => $o->billing_postcode, 'country' => $o->billing_country]];
        }
        return response()->json(['items' => $items]);
    }

    /** Last billing address an account used (for the builder's "use last address"). */
    public function apiCustomerAddress(int $id)
    {
        $u = DB::table('users')->find($id); abort_unless($u, 404);
        $o = DB::table('orders')->where('email', $u->email)->orderByDesc('id')->first();
        return response()->json(['customer' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'company' => $u->company, 'wholesale' => $u->wholesale_status === 'approved' ? (float) $u->wholesale_discount : 0],
            'address' => $o ? ['first_name' => $o->billing_first_name, 'last_name' => $o->billing_last_name, 'company' => $o->billing_company, 'address_1' => $o->billing_address_1, 'address_2' => $o->billing_address_2, 'city' => $o->billing_city, 'state' => $o->billing_state, 'postcode' => $o->billing_postcode, 'country' => $o->billing_country] : null]);
    }
}
