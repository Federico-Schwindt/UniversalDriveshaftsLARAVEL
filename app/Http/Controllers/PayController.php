<?php

namespace App\Http\Controllers;

use App\Support\OrderEvents;
use App\Support\PayLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Public hosted payment page for an order (link sent by the admin or printed on unpaid confirmations). */
class PayController extends Controller
{
    private function order(string $number, string $token): object
    {
        $o = DB::table('orders')->where('order_number', $number)->first();
        abort_unless($o && $o->pay_token && hash_equals($o->pay_token, $token), 404);
        return $o;
    }

    public function show(string $number, string $token)
    {
        $order = $this->order($number, $token);
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        return view('pay', [
            'order' => $order, 'items' => $items, 'balance' => PayLink::balance($order), 'payable' => PayLink::payable($order),
            'stripeKey' => PayLink::stripeReady() ? config('services.stripe.key') : '', 'statusUrl' => OrderEvents::statusUrl($order, false),
        ]);
    }

    public function intent(string $number, string $token)
    {
        $order = $this->order($number, $token);
        if (!PayLink::payable($order)) return response()->json(['error' => 'This order has no balance due.'], 422);
        $state = PayLink::existingState($order);
        if ($state === 'succeeded') return response()->json(['paid' => true, 'redirect' => OrderEvents::statusUrl($order, false) . '?paid=1']);
        if ($state === 'processing') return response()->json(['error' => 'A payment for this order is already being processed. Please check back in a few minutes — you have not been charged twice.'], 409);
        $pi = PayLink::intent($order);
        if (!$pi) return response()->json(['error' => 'Online payment is not available right now. Please call (813) 524-6491.'], 503);
        return response()->json(['client_secret' => $pi[0], 'amount' => PayLink::balance($order)]);
    }

    /** After Stripe confirms on the client, verify server-side and mark paid without waiting for the webhook. */
    public function confirm(Request $r, string $number, string $token)
    {
        $order = $this->order($number, $token);
        // only an intent id goes into the Stripe URL (no path tricks); the metadata check below ties it to this order
        $piId = (string) ($r->input('payment_intent') ?: $order->stripe_payment_intent);
        if (!preg_match('/^pi_[A-Za-z0-9]+$/', $piId) || !PayLink::stripeReady()) return response()->json(['ok' => false], 422);
        $resp = Http::withToken(config('services.stripe.secret'))->get('https://api.stripe.com/v1/payment_intents/' . $piId);
        if ($resp->successful() && $resp->json('status') === 'succeeded' && ($resp->json('metadata.order_number') === $order->order_number)) {
            if (PayLink::markPaid($order, 'Card payment via Stripe (payment link)', 'stripe', (array) $resp->json())) {
                return response()->json(['ok' => true, 'redirect' => OrderEvents::statusUrl($order, false) . '?paid=1']);
            }
            return response()->json(['ok' => false, 'status' => 'amount_mismatch', 'error' => 'That payment does not cover this order. Please call (813) 524-6491.']);
        }
        return response()->json(['ok' => false, 'status' => $resp->json('status')]);
    }
}
