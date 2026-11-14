<?php

namespace App\Http\Controllers;

use App\Support\OrderEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /orders/{order_number}/status?e={email} — the customer's order timeline.
 * The order number alone is not enough: the e-mail used at checkout must
 * match (case-insensitive), the same rule the order-received page applies.
 * A signed URL (URL::signedRoute('orders.status', …)) is accepted too, and
 * a signed-in customer can open their own orders without the e-mail.
 */
class OrderStatusController extends Controller
{
    /** The order, if this request may see it: checkout e-mail, a signed URL, or the signed-in owner. */
    private function authorised(Request $r, string $order_number): object
    {
        $order = DB::table('orders')->where('order_number', $order_number)->first();
        abort_unless($order, 404);
        $email = (string) ($r->query('e') ?: $r->query('email'));
        $u = auth()->user();
        $allowed = ($email !== '' && strcasecmp($order->email, $email) === 0)
            || ($r->hasValidSignature(false))
            || ($u && (($order->user_id && (int) $order->user_id === (int) $u->id) || (!empty($u->email_verified_at) && strcasecmp((string) $order->email, (string) $u->email) === 0)));   // e-mail match only for a proven mailbox, like the account pages
        abort_unless($allowed, 404);
        return $order;
    }

    /** The customer's own invoice, as a PDF — same access rule as the status page. */
    public function invoice(Request $r, string $order_number)
    {
        $order = $this->authorised($r, $order_number);
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('invoice', compact('order', 'items'))->render());
        $pdf->setPaper('letter');
        $pdf->render();
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-' . $order->order_number . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function show(Request $r, string $order_number)
    {
        $order = $this->authorised($r, $order_number);
        // the carrier's scan history, refreshed at most every 30 minutes; a slow carrier never breaks the page
        if ($order->tracking_number && !OrderEvents::isPickup($order)) {
            try { $order = app(\App\Services\EasyPostTrackingService::class)->refreshOrder($order); } catch (\Throwable $e) { report($e); }
        }

        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        $events = OrderEvents::for($order);
        $byType = [];
        foreach ($events as $ev) { if (!isset($byType[$ev->type])) $byType[$ev->type] = $ev; }

        return view('order-status', [
            'rmas' => \App\Support\Rma::forOrder($order),
            'order' => $order,
            'items' => $items,
            'events' => $events->filter(fn ($e) => !str_starts_with($e->type, 'notify:'))->values(),
            'first' => $byType,
            'steps' => $this->steps($order, $byType),
            'isPickup' => OrderEvents::isPickup($order),
            'carrierUrl' => OrderEvents::carrierUrl($order->tracking_carrier, $order->tracking_number),
            'actions' => $this->actions($order),
        ]);
    }

    /**
     * What the customer can do from here. Links carry the order number and e-mail so the
     * forms open filled in. Cancelling is only offered before the order ships; returns and
     * exchanges only once it has (or for a completed pickup), per the return policy.
     */
    private function actions(object $order): array
    {
        $q = '?order=' . rawurlencode($order->order_number) . '&e=' . rawurlencode($order->email);
        $shipped = (bool) $order->tracking_number || (bool) $order->shipped_at || (bool) $order->delivered_at || $order->status === 'completed';
        $open = !in_array($order->status, ['cancelled', 'refunded', 'failed'], true);
        // returns and exchanges only inside the 14-day window (the form refuses after it too)
        $ends = \App\Support\Rma::windowEnds($order);
        $canReturn = $open && $shipped && (!$ends || now()->lte($ends));
        return [
            'invoice' => '/orders/' . rawurlencode($order->order_number) . '/invoice?e=' . rawurlencode($order->email),
            'return' => $canReturn ? '/return-request-form' . $q : null,
            'exchange' => $canReturn ? '/exchange-request-form' . $q : null,
            'cancel' => $open && !$shipped ? '/product-cancel-request-form' . $q : null,
            'damage' => $canReturn ? '/return-request-form' . $q . '&reason=' . rawurlencode('Damaged / defective') : null,
            'returnUntil' => $canReturn ? $ends : null,
            'returnClosed' => $open && $shipped && !$canReturn ? $ends : null,
        ];
    }

    /**
     * The timeline, computed from order fields first (they are the source of
     * truth) with event timestamps filling in the "when". Each step:
     * ['key','title','sub','state' => done|current|todo|skipped, 'at' => ?Carbon]
     */
    private function steps(object $order, array $ev): array
    {
        $pickup = OrderEvents::isPickup($order);
        $cancelled = $order->status === 'cancelled';
        $paid = $order->payment_status === 'paid';
        $payAtPickup = $order->payment_method === 'pay_at_pickup';
        $netTerms = $order->payment_method === 'net_terms';
        $shipped = (bool) $order->tracking_number || (bool) $order->shipped_at;
        $delivered = (bool) $order->delivered_at;
        $completed = $order->status === 'completed';
        $inProd = in_array($order->status, ['processing', 'completed'], true) || $shipped;
        $at = fn (string $t) => isset($ev[$t]) ? \Carbon\Carbon::parse($ev[$t]->created_at) : null;

        $steps = [];
        $steps[] = ['key' => 'placed', 'title' => __('Order placed'), 'state' => 'done',
            'at' => $at('placed') ?: \Carbon\Carbon::parse($order->created_at),
            'sub' => __('Order number :n issued and the build sheet sent to the shop.', ['n' => $order->order_number])];

        if ($payAtPickup && !$paid) {
            $steps[] = ['key' => 'paid', 'title' => __('Payment at pickup'), 'state' => 'todo', 'at' => null,
                'sub' => __('Due at the counter when you collect: $:amount. Bring your order number.', ['amount' => number_format((float) $order->total, 2)])];
        } elseif ($netTerms && !$paid) {
            // business account on terms: the invoice is due later and the build starts right away
            $due = $order->terms_due_at ? \Carbon\Carbon::parse($order->terms_due_at)->translatedFormat('F j, Y') : null;
            $steps[] = ['key' => 'paid', 'title' => __('Invoice on Net :d terms', ['d' => \App\Services\B2bAccounts::termsDays()]), 'state' => 'done',
                'at' => $at('placed') ?: \Carbon\Carbon::parse($order->created_at),
                'sub' => $due ? __('Invoice :n is due :date. Production does not wait for payment.', ['n' => $order->order_number, 'date' => $due])
                    : __('Invoice :n will be e-mailed with the due date. Production does not wait for payment.', ['n' => $order->order_number])];
        } else {
            $steps[] = ['key' => 'paid', 'title' => $paid ? __('Payment confirmed') : __('Payment pending'), 'state' => $paid ? 'done' : 'current',
                'at' => $paid ? $at('paid') : null,
                'sub' => $paid ? __($ev['paid']->detail ?? 'Thank you, payment received.')
                    : ($order->payment_method === 'stripe' ? __('Card payment is being confirmed.') : __('We will contact you to complete payment; the build starts once it is in.'))];
        }

        $prodTitle = $pickup ? __('Being prepared') : __('In production / packing');
        $steps[] = ['key' => 'processing', 'title' => $prodTitle,
            'state' => $inProd ? 'done' : ($paid || $payAtPickup || $netTerms ? 'current' : 'todo'),
            'at' => $inProd ? ($at('processing') ?: ($at('paid') ?: null)) : null,
            'sub' => $inProd
                ? __('Stock parts are pulled; custom shafts are cut, welded and balanced.')
                : __('Most stock parts ship the same day; custom and new driveshafts take up to 5 business days to process.')];

        if ($pickup) {
            $steps[] = ['key' => 'ready', 'title' => __('Ready for pickup'), 'state' => $completed ? 'done' : ($inProd ? 'current' : 'todo'),
                'at' => $completed ? ($at('completed') ?: null) : null,
                'sub' => $completed ? __('Your parts are at the counter.') : __('We will e-mail and text you the moment your parts are ready at the counter.')];
        } else {
            $steps[] = ['key' => 'shipped', 'title' => __('Shipped'), 'state' => $shipped ? 'done' : ($inProd ? 'current' : 'todo'),
                'at' => $shipped ? ($at('shipped') ?: ($order->shipped_at ? \Carbon\Carbon::parse($order->shipped_at) : null)) : null,
                'sub' => $shipped
                    ? trim(($order->tracking_carrier ?: __('Carrier')) . ($order->tracking_service ? ' · ' . trim(preg_replace(['/^(UPS|FedEx|USPS|DHL)\s+/i', '/Groundsaver Greater Than\s*1lb|Groundsaver Less Than Or Equal To\s*1lb/i'], ['', 'Ground Saver'], $order->tracking_service)) : ''))
                    : ($order->sms_opt_in && \App\Services\SmsService::on() ? __('You will get the tracking number by e-mail and text the moment it leaves the shop.') : __('You will get the tracking number by e-mail the moment it leaves the shop.'))];
            $steps[] = ['key' => 'delivered', 'title' => __('Delivered'), 'state' => $delivered ? 'done' : ($shipped ? 'current' : 'todo'),
                'at' => $delivered ? ($at('delivered') ?: \Carbon\Carbon::parse($order->delivered_at)) : null,
                'sub' => $delivered ? __('Check the parts against your order; call within 48 hours if anything is wrong.')
                    : ($shipped ? ($order->last_tracking_status ? __('Latest carrier status: :s', ['s' => __($order->last_tracking_status)]) : __('Follow the carrier link above for the live scan history.')) : '')];
        }

        if ($cancelled) {
            foreach ($steps as &$s) { if ($s['state'] !== 'done') $s['state'] = 'skipped'; }
            unset($s);
            $steps[] = ['key' => 'cancelled', 'title' => __('Order cancelled'), 'state' => 'cancelled', 'at' => $at('cancelled'),
                'sub' => __('This order was cancelled. Call (813) 524-6491 if that is unexpected.')];
        }
        return $steps;
    }
}
