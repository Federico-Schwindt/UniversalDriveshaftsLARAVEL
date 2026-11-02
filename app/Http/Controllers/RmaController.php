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


    /** Store a return / exchange / cancellation request and e-mail the customer and the shop. Used by the form and the assistant. */
    public static function createRequest(array $d, ?string $ip = null): string
    {
        $d = array_intersect_key($d, array_flip(['type', 'order_number', 'name', 'email', 'phone', 'product', 'reason', 'details']));
        $ref = 'RMA-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        DB::table('rma_requests')->insert($d + [
            'ref' => $ref, 'status' => 'new', 'ip' => $ip,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $d += ['order_number' => null, 'phone' => null, 'product' => null, 'reason' => null, 'details' => null];
        $label = ucfirst($d['type']);
        try {
            // the customer's copy goes out in the language they used on the site; the shop's stays English
            $cLabel = __(['return' => 'Return request', 'exchange' => 'Exchange request', 'cancel' => 'Cancellation request'][$d['type']]);
            \App\Support\Mailer::simple($d['email'], __(':label received — :ref', ['label' => $cLabel, 'ref' => $ref]), ['kicker' => app()->getLocale() === 'en' ? $label : $cLabel, 'title' => __('We got your request, :name.', ['name' => $d['name']]), 'intro' => __('Reference :ref. Our team reviews every request personally and will get back to you shortly — usually within one business day.', ['ref' => $ref]), 'kv' => [__('Reference') => $ref, __('Order') => $d['order_number'] ?: null, __('Product') => $d['product'] ?: null, __('Reason') => $d['reason'] ? __($d['reason']) : null], 'after' => [__('Please do not ship anything back until you receive the RMA instructions.')]], ['replyTo' => [config('uds.email')]]);
            \App\Support\Mailer::simple(config('uds.email'), "{$label} request — {$ref}", ['kicker' => 'Returns', 'title' => "New {$label} request {$ref}", 'kv' => ['Name' => $d['name'], 'E-mail' => $d['email'], 'Phone' => $d['phone'] ?: null, 'Order' => $d['order_number'] ?: null, 'Product' => $d['product'] ?: null, 'Reason' => $d['reason'] ?: null, 'Details' => $d['details'] ?: null], 'button' => ['Open in admin', config('app.url') . '/admin/rma'], 'signoff' => false], ['replyTo' => [$d['email'], $d['name']]]);
        } catch (\Throwable $e) {}
        return $ref;
    }

    public static function reasons(): array
    {
        return self::REASONS;
    }

    // ---- admin ----
    public function adminList(Request $r)
    {
        $q = DB::table('rma_requests');
        if ($t = $r->query('type')) $q->where('type', $t);
        if ($s = $r->query('status')) $q->where('status', $s);
        $rows = $q->orderByDesc('id')->paginate(30)->withQueryString();
        return view('admin.rma', ['rows' => $rows, 'type' => $r->query('type'), 'status' => $r->query('status')]);
    }

    public function adminUpdate(Request $r, int $id)
    {
        $r->validate(['status' => 'required|in:new,approved,rejected,completed']);
        DB::table('rma_requests')->where('id', $id)->update(['status' => $r->input('status'), 'updated_at' => now()]);
        return back()->with('admin_ok', 'Request updated.');
    }
}
