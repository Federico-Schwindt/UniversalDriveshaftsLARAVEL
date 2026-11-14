<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Hosted "pay this order" page: /pay/{order}/{token}. Charges whatever balance the order has at the time it is opened. */
class PayLink
{
    public static function token(object $order): string
    {
        if ($order->pay_token) return $order->pay_token;
        $t = Str::random(40);
        DB::table('orders')->where('id', $order->id)->update(['pay_token' => $t]);
        $order->pay_token = $t;
        return $t;
    }

    public static function url(object $order): string
    {
        return rtrim(config('app.url'), '/') . '/pay/' . rawurlencode($order->order_number) . '/' . self::token($order);
    }

    public static function balance(object $order): float
    {
        return round(max(0, (float) $order->total - (float) $order->refund_total), 2);
    }

    public static function payable(object $order): bool
    {
        return in_array($order->payment_status, ['unpaid'], true) && !in_array($order->status, ['cancelled', 'failed'], true) && self::balance($order) > 0;
    }

    public static function stripeReady(): bool
    {
        return (bool) config('services.stripe.enabled') && config('services.stripe.secret') && config('services.stripe.key');
    }

    /**
     * The payment methods the shop offers, set explicitly rather than left to the Stripe
     * dashboard: cards (which is also how Apple Pay and Google Pay are offered in the Payment
     * Element) plus Affirm on orders inside Affirm's own $50–$30,000 range. Cash App and Klarna
     * are deliberately not offered.
     *
     * @return array<int,string>
     */
    public static function methodTypes(float $amount, string $country = 'US'): array
    {
        $types = ['card'];
        if (strtoupper($country ?: 'US') === 'US' && $amount >= 50 && $amount <= 30000) $types[] = 'affirm';
        return $types;
    }

    /**
     * What to send Stripe when creating a PaymentIntent. A payment-method configuration is the
     * authoritative switch (one place decides the offered methods, and Stripe hides the ones the
     * amount is ineligible for); the explicit type list is the fallback when none is configured.
     */
    public static function methodParams(float $amount, string $country = 'US'): array
    {
        if ($pmc = config('services.stripe.pm_config')) return ['payment_method_configuration' => $pmc];
        $out = [];
        foreach (self::methodTypes($amount, $country) as $i => $t) $out["payment_method_types[$i]"] = $t;
        return $out;
    }

    /** State of the order's stored PaymentIntent, so a customer who already paid (webhook pending) is never charged a second time. */
    public static function existingState(object $order): ?string
    {
        if (!self::stripeReady() || !$order->stripe_payment_intent) return null;
        $cur = Http::withToken(config('services.stripe.secret'))->get('https://api.stripe.com/v1/payment_intents/' . $order->stripe_payment_intent);
        if (!$cur->successful() || $cur->json('metadata.order_number') !== $order->order_number) return null;
        $st = $cur->json('status');
        if ($st === 'succeeded' && !self::markPaid($order, 'Card payment via Stripe (payment link)', 'stripe', (array) $cur->json())) return null;
        return in_array($st, ['succeeded', 'processing'], true) ? $st : null;
    }

    /** Create (or re-use / re-price) the order's PaymentIntent for the current balance. Returns [client_secret, intent_id] or null. */
    public static function intent(object $order): ?array
    {
        if (!self::stripeReady()) return null;
        $amount = (int) round(self::balance($order) * 100);
        $api = fn () => Http::asForm()->withToken(config('services.stripe.secret'));
        if ($order->stripe_payment_intent) {
            $cur = $api()->get('https://api.stripe.com/v1/payment_intents/' . $order->stripe_payment_intent);
            if ($cur->successful() && in_array($cur->json('status'), ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                if ((int) $cur->json('amount') !== $amount) {
                    $upd = $api()->post('https://api.stripe.com/v1/payment_intents/' . $order->stripe_payment_intent, ['amount' => $amount]);
                    if ($upd->successful()) return [$upd->json('client_secret'), $upd->json('id')];
                } else return [$cur->json('client_secret'), $cur->json('id')];
            }
        }
        $resp = $api()->post('https://api.stripe.com/v1/payment_intents', [
            'amount' => $amount, 'currency' => 'usd', 'description' => 'Order ' . $order->order_number, 'receipt_email' => $order->email,
            'metadata[order_number]' => $order->order_number,
        ] + self::methodParams(self::balance($order), (string) ($order->billing_country ?: 'US')));
        if (!$resp->successful()) return null;
        DB::table('orders')->where('id', $order->id)->update(['stripe_payment_intent' => $resp->json('id')]);
        return [$resp->json('client_secret'), $resp->json('id')];
    }

    /** Mark an order paid (card via Stripe or any manual method) — used by the webhook, the pay page and the admin. */
    /**
     * Mark an order paid (once). $intent is the Stripe PaymentIntent (or Charge) that paid it: the money Stripe
     * actually received has to cover the balance, in US dollars, or the order stays unpaid and the shop sees why
     * on its timeline. Returns true when the order is (now) paid.
     */
    public static function markPaid(object $order, string $detail = 'Card payment via Stripe', ?string $method = null, ?array $intent = null): bool
    {
        $fresh = DB::table('orders')->find($order->id);
        if (!$fresh) return false;
        if ($fresh->payment_status === 'paid') return true;
        if ($intent !== null && !self::covers($fresh, $intent)) {
            $got = self::receivedCents($intent);
            OrderEvents::record($fresh, 'payment_short', 'Payment does not match the order',
                sprintf('Stripe %s: %s %.2f received, balance $%.2f. Not marked paid.', $intent['id'] ?? '', strtoupper((string) ($intent['currency'] ?? '?')), $got / 100, self::balance($fresh)), true);
            \Illuminate\Support\Facades\Log::warning("payment short for {$fresh->order_number}: " . ($intent['id'] ?? '') . " received {$got} cents");
            return false;
        }
        // the webhook, the pay page and the checkout return can all arrive at once: only the request whose
        // update actually flips the row goes on to record, e-mail and notify
        $changed = DB::table('orders')->where('id', $fresh->id)
            ->where(fn ($q) => $q->where('payment_status', '!=', 'paid')->orWhereNull('payment_status'))
            ->update(array_filter([
                'payment_status' => 'paid', 'paid_at' => now(), 'payment_method' => $method,
                // a checkout whose first attempt failed was marked failed; the money is in now, so it is a live order
                'status' => in_array($fresh->status, ['pending', 'failed'], true) ? 'processing' : $fresh->status, 'updated_at' => now(),
            ], fn ($v) => $v !== null));
        if (!$changed) return true;
        OrderEvents::record($fresh, 'paid', 'Payment confirmed', $detail, true);
        if (in_array($fresh->status, ['pending', 'failed'], true)) OrderEvents::record($fresh, 'processing', 'In production / packing', null, true);
        // a card order's coupon, "order received" text and CRM sync wait for the money (see ShopController::placeOrder)
        if (OrderEvents::has($fresh, 'notify:placed:deferred')) self::placedNotices(DB::table('orders')->find($fresh->id) ?: $fresh);
        \Illuminate\Support\Facades\Cache::forget('adm_nav_counts'); \Illuminate\Support\Facades\Cache::forget('adm_order_tabs');
        // card orders are confirmed by e-mail only now that the money is in (no-op if it already went out)
        \App\Services\OrderMailer::confirmOnce($fresh);
        // a paid order can lift a B2B account to the next tier right away
        if ($fresh->user_id) { try { \App\Services\B2bAccounts::evaluate((int) $fresh->user_id); } catch (\Throwable $e) { report($e); } }
        return true;
    }

    /**
     * What goes out once an order is committed: the coupon counted, the "order received" text (only with the
     * customer's checkout consent), the CRM sync and the work order for a builder shaft. Invoice / pickup / manual orders run it when placed, card
     * orders when paid. Each step is keyed on the order timeline so it can never run twice.
     */
    public static function placedNotices(object $order): void
    {
        if ($order->coupon_code && OrderEvents::record($order, 'notify:coupon:used', 'Coupon counted', $order->coupon_code, true)) {
            try { app(\App\Services\CouponService::class)->markUsed($order->coupon_code); } catch (\Throwable $e) { report($e); }
        }
        // an order not paid yet (pay at pickup, invoice) texts only a U.S. / Canadian number: no free texts abroad
        $smsOk = $order->payment_status === 'paid' || \App\Services\SmsService::domestic((string) $order->phone);
        if ($order->sms_opt_in && $order->phone && $smsOk && \App\Services\SmsService::on() && OrderEvents::record($order, 'notify:sms:placed', 'Confirmation text sent', $order->phone, true)) {
            $statusUrl = OrderEvents::statusUrl($order);
            $sms = OrderEvents::isPickup($order)
                ? "Universal Driveshafts: order {$order->order_number} received for LOCAL PICKUP in Florida, total \${$order->total}. We'll text you when it's ready. Pickup " . config('uds.pickup.hours') . "; other times call (813) 524-6491 first. Status: {$statusUrl}"
                : "Universal Driveshafts: order {$order->order_number} received — total \${$order->total}. We'll text you when it ships. Status: {$statusUrl} (813) 524-6491";
            try { app(\App\Services\SmsService::class)->send($order->phone, $sms); } catch (\Throwable $e) {}
        }
        if (OrderEvents::record($order, 'notify:crm', 'Customer synced to Brevo', null, true)) {
            try { app(\App\Services\BrevoService::class)->syncOrder($order); } catch (\Throwable $e) {}
        }
        // the parts are sold: take them off the on-hand count (once)
        StockLedger::deduct($order);
        // a builder shaft becomes a work order on the shop's station (after the response, once)
        try { \App\Services\CheckinBridge::queue($order); } catch (\Throwable $e) { report($e); }
    }

    /** Money a succeeded PaymentIntent / Charge actually took, in cents. */
    public static function receivedCents(array $intent): int
    {
        if (isset($intent['amount_received'])) return (int) $intent['amount_received'];       // PaymentIntent
        if (isset($intent['amount_captured'])) return (int) $intent['amount_captured'];       // Charge
        return (int) ($intent['amount'] ?? 0);
    }

    /** True when the payment is in USD and covers the order's balance (a cent of rounding allowed). */
    public static function covers(object $order, array $intent): bool
    {
        return strtolower((string) ($intent['currency'] ?? '')) === 'usd'
            && self::receivedCents($intent) >= (int) round(self::balance($order) * 100) - 1;
    }

    /** E-mail (and optionally text) the customer the link. Returns what was sent. */
    public static function send(object $order, bool $sms = false, ?string $note = null): array
    {
        if (OrderEvents::imported($order)) return [];   // old-store history: the link can still be copied from the admin page
        $url = self::url($order);
        $bal = number_format(self::balance($order), 2);
        $name = trim($order->billing_first_name ?: 'there');
        $sent = [];
        try {
            Mailer::send($order->email, "Payment link for order {$order->order_number} — \${$bal}", 'emails.order.pay-link', [
                'order' => $order, 'items' => \App\Services\OrderMailer::items(DB::table('order_items')->where('order_id', $order->id)->get()), 'balance' => self::balance($order), 'payUrl' => $url, 'note' => $note,
                'hero' => ['kicker' => 'Payment request', 'title' => "Hi {$name}, your order is ready to be paid.", 'text' => 'Pay in under a minute with any major card — your parts start moving as soon as it clears.'], 'preheader' => "\${$bal} due on order {$order->order_number}",
            ], ['replyTo' => [config('uds.email')]]); $sent[] = 'email';
        } catch (\Throwable $e) { report($e); }
        if ($sms && $order->phone && \App\Services\SmsService::on()) {
            try { if (app(\App\Services\SmsService::class)->send($order->phone, "Universal Driveshafts: order {$order->order_number} — \${$bal} due. Pay online: {$url}")) $sent[] = 'sms'; } catch (\Throwable $e) {}
        }
        DB::table('orders')->where('id', $order->id)->update(['pay_link_sent_at' => now()]);
        OrderEvents::record($order, 'notify:paylink', 'Payment link sent', implode(' + ', $sent) . ' · $' . $bal);
        return $sent;
    }
}
