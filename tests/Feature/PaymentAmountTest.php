<?php

namespace Tests\Feature;

use App\Support\PayLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** An order is only marked paid when the money Stripe took covers its balance, in US dollars. */
class PaymentAmountTest extends TestCase
{
    use DatabaseTransactions;

    private function order(float $total = 120.50): object
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'stripe',
            'email' => 'alex@example.com', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US',
            'subtotal' => $total, 'shipping_total' => 0, 'discount_total' => 0, 'total' => $total, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_short_or_foreign_currency_payments_do_not_mark_the_order_paid(): void
    {
        Mail::fake();
        $o = $this->order();
        $this->assertFalse(PayLink::markPaid($o, 'test', 'stripe', ['id' => 'pi_short', 'amount_received' => 100, 'currency' => 'usd']));
        $this->assertSame('unpaid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
        $this->assertTrue(DB::table('order_events')->where('order_id', $o->id)->where('type', 'payment_short')->exists());

        $this->assertFalse(PayLink::markPaid($o, 'test', 'stripe', ['id' => 'pi_eur', 'amount_received' => 12050, 'currency' => 'eur']));
        $this->assertSame('unpaid', DB::table('orders')->where('id', $o->id)->value('payment_status'));

        $this->assertTrue(PayLink::markPaid($o, 'test', 'stripe', ['id' => 'pi_ok', 'amount_received' => 12050, 'currency' => 'usd']));
        $this->assertSame('paid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
        // a charge object (charge.succeeded) is read by amount_captured
        $c = $this->order(10);
        $this->assertFalse(PayLink::covers($c, ['amount' => 1000, 'amount_captured' => 500, 'currency' => 'usd']));
        $this->assertTrue(PayLink::covers($c, ['amount' => 1000, 'amount_captured' => 1000, 'currency' => 'usd']));
    }

    public function test_webhook_checks_the_amount(): void
    {
        Mail::fake();
        config(['services.stripe.enabled' => true, 'services.stripe.webhook_secret' => 'whsec_test']);
        $send = function (object $o, int $cents) {
            $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_' . uniqid(), 'object' => 'payment_intent', 'amount' => $cents, 'amount_received' => $cents, 'currency' => 'usd', 'metadata' => ['order_number' => $o->order_number]]]]);
            $t = time();
            return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=" . hash_hmac('sha256', "$t.$payload", 'whsec_test'), 'CONTENT_TYPE' => 'application/json'], $payload);
        };
        $o = $this->order(50);
        $send($o, 2000)->assertOk();
        $this->assertSame('unpaid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
        $send($o, 5000)->assertOk();
        $this->assertSame('paid', DB::table('orders')->where('id', $o->id)->value('payment_status'));
    }
}
