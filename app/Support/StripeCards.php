<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cards a customer chose to save at checkout. The card itself lives in Stripe (attached to a Stripe Customer
 * with consent for off-session use); we only keep the Customer id on the user and show brand / last 4.
 */
class StripeCards
{
    private static function api()
    {
        return Http::asForm()->withToken((string) config('services.stripe.secret'))->timeout(20);
    }

    public static function ready(): bool
    {
        return PayLink::stripeReady();
    }

    /** The user's Stripe Customer, created the first time a card is saved. */
    public static function customerId(object $user, bool $create = true): ?string
    {
        if (!empty($user->stripe_customer_id)) return $user->stripe_customer_id;
        if (!$create || !self::ready()) return null;
        $r = self::api()->post('https://api.stripe.com/v1/customers', [
            'email' => $user->email, 'name' => $user->name ?: null, 'phone' => $user->phone ?: null,
            'metadata[user_id]' => $user->id,
        ]);
        if (!$r->successful()) { Log::warning('stripe customer: ' . $r->body()); return null; }
        DB::table('users')->where('id', $user->id)->update(['stripe_customer_id' => $r->json('id')]);
        $user->stripe_customer_id = $r->json('id');
        return $user->stripe_customer_id;
    }

    /** Saved cards: [{id, brand, last4, exp, label}], newest first. No Customer yet = none, without calling Stripe. */
    public static function list(object $user): array
    {
        $cus = self::customerId($user, false);
        if (!$cus || !self::ready()) return [];
        return Cache::remember('stripe_cards:' . $cus, 300, function () use ($cus) {
            $r = Http::withToken((string) config('services.stripe.secret'))->timeout(20)
                ->get("https://api.stripe.com/v1/customers/$cus/payment_methods", ['type' => 'card', 'limit' => 10]);
            if (!$r->successful()) return [];
            return array_map(fn ($pm) => [
                'id' => $pm['id'],
                'brand' => ucfirst((string) ($pm['card']['display_brand'] ?? $pm['card']['brand'] ?? 'card')),
                'last4' => (string) ($pm['card']['last4'] ?? ''),
                'exp' => sprintf('%02d/%02d', (int) ($pm['card']['exp_month'] ?? 0), ((int) ($pm['card']['exp_year'] ?? 0)) % 100),
                'wallet' => $pm['card']['wallet']['type'] ?? null,
                'label' => ucfirst(str_replace('_', ' ', (string) ($pm['card']['display_brand'] ?? $pm['card']['brand'] ?? 'Card'))) . ' •••• ' . ($pm['card']['last4'] ?? ''),
            ], $r->json('data') ?? []);
        });
    }

    public static function forget(object $user): void
    {
        if (!empty($user->stripe_customer_id)) Cache::forget('stripe_cards:' . $user->stripe_customer_id);
    }

    /** One of this user's saved cards, or null (never trust a payment method id from the browser or the model). */
    public static function find(object $user, string $pmId): ?array
    {
        foreach (self::list($user) as $c) if ($c['id'] === $pmId) return $c;
        return null;
    }

    public static function remove(object $user, string $pmId): bool
    {
        if (!self::find($user, $pmId)) return false;
        $ok = self::api()->post("https://api.stripe.com/v1/payment_methods/$pmId/detach")->successful();
        self::forget($user);
        return $ok;
    }

    /**
     * PaymentIntent fields that tie a checkout payment to the account: always the Customer when one exists,
     * and the consent to reuse a new card off-session. Cards are saved by default (Terms §6); only the card
     * option carries it so Affirm and other methods that can't be saved stay available. A customer who ticked
     * "Don't save my card" is flagged in the metadata and the card is detached once the payment succeeds.
     */
    public static function intentParams(?object $user, bool $save, ?string $pmId = null): array
    {
        if (!$user) return [];
        // the signed-in Payment Element always asks for card reuse, so the intent must match even if the
        // Stripe customer can't be created right now (the card then simply isn't attached to anyone)
        $cus = self::customerId($user, true);
        $p = $cus ? ['customer' => $cus] : [];
        if ($pmId) $p['payment_method'] = $pmId;
        else {
            $p['payment_method_options[card][setup_future_usage]'] = 'off_session';
            if (!$save) $p['metadata[dont_save_card]'] = '1';
        }
        return $p;
    }

    /** Detach the card of a succeeded payment whose customer asked not to keep it. Safe to call twice. */
    public static function dropIfUnwanted(string $piId): void
    {
        if ($piId === '' || !config('services.stripe.secret')) return;
        try {
            $pi = self::api()->get("https://api.stripe.com/v1/payment_intents/$piId");
            if (!$pi->successful() || $pi->json('metadata.dont_save_card') !== '1' || $pi->json('status') !== 'succeeded') return;
            $pm = (string) $pi->json('payment_method');
            if ($pm !== '' && Cache::add("stripe_drop:$pm", 1, 86400)) {
                self::api()->post("https://api.stripe.com/v1/payment_methods/$pm/detach");
                if ($cus = $pi->json('customer')) Cache::forget('stripe_cards:' . $cus);
            }
        } catch (\Throwable $e) {
            \Log::warning("drop unwanted card $piId: " . $e->getMessage());
        }
    }

    /**
     * Charge a saved card for an order without the customer present (orders placed for them by the assistant
     * after they confirmed). Returns [status, message]: 'paid' | 'processing' (Stripe still settling; the
     * webhook marks it paid) | 'action' (bank wants the customer, send the pay link) | 'failed'.
     */
    public static function chargeOrder(object $order, object $user, string $pmId): array
    {
        if (!self::find($user, $pmId)) return ['failed', 'That card is not saved on this account.'];
        $r = self::api()->withHeaders(['Idempotency-Key' => 'assistant-' . $order->order_number])->post('https://api.stripe.com/v1/payment_intents', [
            'amount' => (int) round(PayLink::balance($order) * 100), 'currency' => 'usd',
            'customer' => $user->stripe_customer_id, 'payment_method' => $pmId,
            'off_session' => 'true', 'confirm' => 'true',
            'description' => 'Order ' . $order->order_number, 'receipt_email' => $order->email,
            'metadata[order_number]' => $order->order_number, 'metadata[source]' => 'assistant',
        ]);
        $pi = $r->json('id') ?? $r->json('error.payment_intent.id');
        if ($pi) DB::table('orders')->where('id', $order->id)->update(['stripe_payment_intent' => $pi]);
        if ($r->successful() && $r->json('status') === 'succeeded') {
            if (PayLink::markPaid(DB::table('orders')->find($order->id), 'Saved card charged (order placed through UDS Assistant)', 'stripe', (array) $r->json())) return ['paid', 'Paid with the saved card.'];
            return ['failed', 'The charge did not cover the order total; the shop will follow up.'];
        }
        // not paid yet: the webhook marks the order paid when Stripe finishes
        if ($r->successful() && $r->json('status') === 'processing') return ['processing', 'The card payment is processing; the order is confirmed as soon as it clears.'];
        $code = (string) $r->json('error.code');
        if ($code === 'authentication_required' || $r->json('status') === 'requires_action') return ['action', 'The bank wants the customer to confirm this payment.'];
        Log::warning('assistant card charge ' . $order->order_number . ': ' . $code . ' ' . $r->json('error.message'));
        return ['failed', (string) ($r->json('error.message') ?: 'The card was declined.')];
    }
}
