<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RmaController extends Controller
{
    public const TYPES = ['return', 'exchange', 'cancel'];
    private const REASONS = [
        'Wrong part received', 'Does not fit my vehicle', 'Damaged / defective',
        'Ordered by mistake', 'No longer needed', 'Better price found', 'Other',
    ];

    public function page(Request $r, string $type = 'return')
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'return';
        // Opened from an order's status page (?order=…&e=…): the order and its lines come
        // pre-filled, so the customer picks items instead of typing part numbers. The same
        // order + e-mail match as the status page; anything else is just an empty form.
        $order = null; $items = [];
        $num = trim((string) $r->query('order', ''));
        $email = trim((string) $r->query('e', ''));
        $u = auth()->user();
        if ($num !== '') {
            $o = DB::table('orders')->where('order_number', $num)->first();
            if ($o && (($email !== '' && strcasecmp($o->email, $email) === 0) || ($u && (($o->user_id && (int) $o->user_id === (int) $u->id) || (!empty($u->email_verified_at) && strcasecmp((string) $u->email, (string) $o->email) === 0))))) {
                $order = $o;
                $items = DB::table('order_items')->where('order_id', $o->id)->get(['name', 'sku', 'qty', 'type'])->map(fn ($i) => [
                    'name' => $i->name, 'sku' => $i->sku, 'qty' => (int) $i->qty,
                    // made to the customer's measurements: not returnable (return policy)
                    'custom' => in_array($i->type, ['custom_build', 'premade_build'], true) || stripos((string) $i->name, 'cut to') !== false,
                ])->all();
            }
        }
        $reason = (string) $r->query('reason', '');
        return view('rma', [
            'type' => $type, 'reasons' => self::REASONS, 'order' => $order, 'items' => $items,
            'prefillReason' => in_array($reason, self::REASONS, true) ? $reason : null,
            'prefillName' => $order ? trim($order->billing_first_name . ' ' . $order->billing_last_name) : ($u->name ?? ''),
            'prefillEmail' => $order->email ?? ($u->email ?? ''),
            'prefillPhone' => $order->phone ?? '',
            'orderNumber' => $order->order_number ?? $num,
        ]);
    }

    public function submit(Request $r)
    {
        $d = $r->validate([
            'type' => 'required|in:' . implode(',', self::TYPES),
            'order_number' => 'nullable|string|max:60',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:40',
            'product' => 'nullable|string|max:190',
            'reason' => 'nullable|string|max:120',
            'details' => 'nullable|string|max:2000',
        ]);
        // a request that names a real order must come from that order's e-mail, so nobody can file a
        // return/exchange/cancellation against someone else's order with just its number
        if (!empty($d['order_number'])) {
            $o = DB::table('orders')->where('order_number', $d['order_number'])->first(['email']);
            if ($o && strcasecmp((string) $o->email, (string) $d['email']) !== 0) {
                return response()->json(['success' => false, 'message' => __('That order number is not associated with this e-mail address. Use the e-mail from your order confirmation, or leave the order number blank.')], 422);
            }
        }
        $ref = self::createRequest($d, $r->ip());
        return response()->json(['success' => true, 'ref' => $ref, 'type' => ucfirst($d['type'])]);
    }


    /**
     * Store a return / exchange / cancellation request, link it to its order, put it on the order's timeline and
     * e-mail the customer and the shop (failures are recorded on the request). Used by the form and the assistant.
     */
    public static function createRequest(array $d, ?string $ip = null): string
    {
        $d = array_intersect_key($d, array_flip(['type', 'order_number', 'name', 'email', 'phone', 'product', 'reason', 'details']));
        $ref = 'RMA-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        $order = !empty($d['order_number']) ? DB::table('orders')->where('order_number', $d['order_number'])->first() : null;
        $id = DB::table('rma_requests')->insertGetId($d + [
            'ref' => $ref, 'status' => 'new', 'ip' => $ip, 'order_id' => $order->id ?? null, 'user_id' => auth()->id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rma = DB::table('rma_requests')->find($id);
        \App\Support\Rma::record($id, 'created', \App\Support\Rma::typeLabel($rma->type) . ' request received', $rma->reason ?: null);
        if ($order) {
            \App\Support\OrderEvents::record($order, 'rma', \App\Support\Rma::typeLabel($rma->type) . ' requested · ' . $ref, $rma->reason ?: null);
        }
        \App\Support\Rma::confirmToCustomer($rma);
        \App\Support\Rma::notifyShop($rma);
        return $ref;
    }

    public static function reasons(): array
    {
        return self::REASONS;
    }

    // ---- admin ----
    public function adminList(Request $r)
    {
        $status = (string) $r->query('status', 'open');
        $q = DB::table('rma_requests as r')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->select('r.*', 'o.status as order_status', 'o.total as order_total');
        if ($status === 'open') $q->whereIn('r.status', \App\Support\Rma::OPEN);
        elseif (isset(\App\Support\Rma::STATUSES[$status])) $q->where('r.status', $status);
        if (in_array($t = (string) $r->query('type'), self::TYPES, true)) $q->where('r.type', $t);
        if (($s = trim((string) $r->query('q'))) !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('r.ref', 'like', $like)->orWhere('r.order_number', 'like', $like)->orWhere('r.name', 'like', $like)->orWhere('r.email', 'like', $like)->orWhere('r.product', 'like', $like));
        }
        $rows = $q->orderByDesc('r.id')->paginate(30)->withQueryString();
        $counts = DB::table('rma_requests')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $counts['open'] = array_sum(array_intersect_key($counts, array_flip(\App\Support\Rma::OPEN)));
        return view('admin.rma', ['rows' => $rows, 'type' => $r->query('type'), 'status' => $status, 'counts' => $counts, 'search' => $s]);
    }

    public function adminShow(int $id)
    {
        $rma = DB::table('rma_requests')->find($id); abort_unless($rma, 404);
        $order = $rma->order_id ? DB::table('orders')->find($rma->order_id) : ($rma->order_number ? DB::table('orders')->where('order_number', $rma->order_number)->first() : null);
        $items = $order ? DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get() : collect();
        $refunds = $order ? DB::table('order_refunds')->where('order_id', $order->id)->orderByDesc('id')->get() : collect();
        $labels = $order && \Illuminate\Support\Facades\Schema::hasTable('shipping_labels') ? DB::table('shipping_labels')->where('order_id', $order->id)->orderByDesc('id')->get() : collect();
        $events = DB::table('rma_events as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->where('e.rma_id', $id)->orderByDesc('e.id')->get(['e.*', 'u.name as user_name']);
        $others = DB::table('rma_requests')->where('email', $rma->email)->where('id', '!=', $id)->orderByDesc('id')->limit(5)->get();
        return view('admin.rma-show', compact('rma', 'order', 'items', 'refunds', 'labels', 'events', 'others'));
    }

    /** A decision: move the request on (only to a status allowed from where it is), optionally e-mail the customer. */
    public function adminUpdate(Request $r, int $id)
    {
        $rma = DB::table('rma_requests')->find($id); abort_unless($rma, 404);
        $next = \App\Support\Rma::NEXT[$rma->status] ?? array_keys(\App\Support\Rma::STATUSES);
        $d = $r->validate([
            'status' => 'required|in:' . implode(',', $next),
            'message' => 'nullable|string|max:2000',
            'notify' => 'nullable|boolean',
            'resolution' => 'nullable|required_if:status,resolved|in:' . implode(',', array_keys(\App\Support\Rma::RESOLUTIONS)),
            'refund_amount' => 'nullable|numeric|min:0|max:100000',
        ], ['status.in' => 'That step is not available from "' . \App\Support\Rma::label($rma->status) . '".']);
        $upd = ['status' => $d['status'], 'updated_at' => now()];
        if (in_array($d['status'], ['approved', 'rejected'], true)) $upd['decided_at'] = now();
        if ($d['status'] === 'received') $upd['received_at'] = now();
        if (in_array($d['status'], ['resolved', 'rejected', 'withdrawn'], true)) $upd['closed_at'] = now();
        if ($d['status'] === 'new') $upd += ['closed_at' => null, 'resolution' => null];
        if ($d['status'] === 'resolved') $upd += ['resolution' => $d['resolution'], 'refund_amount' => $d['refund_amount'] ?? null];
        DB::table('rma_requests')->where('id', $id)->update($upd);
        $rma = DB::table('rma_requests')->find($id);

        $label = \App\Support\Rma::label($rma->status) . ($rma->status === 'resolved' ? ': ' . (\App\Support\Rma::RESOLUTIONS[$rma->resolution] ?? '') . ($rma->refund_amount !== null ? ' $' . number_format((float) $rma->refund_amount, 2) : '') : '');
        \App\Support\Rma::record($id, 'status', $label, $d['message'] ?? null, auth()->id());
        \App\Support\AdminLog::record('rma.status', "{$rma->ref}: {$label}", 'rma', $id);
        if ($rma->order_id) \App\Support\OrderEvents::record((int) $rma->order_id, 'rma', \App\Support\Rma::typeLabel($rma->type) . ' ' . strtolower($label) . ' · ' . $rma->ref);
        $mailed = !empty($d['notify']) && $rma->status !== 'new' && $rma->status !== 'withdrawn'
            ? \App\Support\Rma::notifyCustomer($rma, $rma->status, $d['message'] ?? null, auth()->id()) : null;
        return back()->with('admin_ok', 'Request ' . strtolower(\App\Support\Rma::label($rma->status)) . '.' . ($mailed === true ? ' Customer e-mailed.' : ($mailed === false ? ' The customer e-mail failed: see the history.' : '')));
    }

    public function adminNote(Request $r, int $id)
    {
        abort_unless(DB::table('rma_requests')->where('id', $id)->exists(), 404);
        $d = $r->validate(['body' => 'required|string|max:2000']);
        \App\Support\Rma::record($id, 'note', 'Internal note', $d['body'], auth()->id());
        return back()->with('admin_ok', 'Note added.');
    }

    /** The return shipment the customer sends back (carrier + tracking), shown on the request and its history. */
    public function adminTracking(Request $r, int $id)
    {
        abort_unless(DB::table('rma_requests')->where('id', $id)->exists(), 404);
        $d = $r->validate(['return_carrier' => 'nullable|string|max:40', 'return_tracking' => 'nullable|string|max:80']);
        DB::table('rma_requests')->where('id', $id)->update($d + ['updated_at' => now()]);
        \App\Support\Rma::record($id, 'tracking', 'Return tracking saved', trim(($d['return_carrier'] ?? '') . ' ' . ($d['return_tracking'] ?? '')) ?: null, auth()->id());
        return back()->with('admin_ok', 'Return tracking saved.');
    }
}
