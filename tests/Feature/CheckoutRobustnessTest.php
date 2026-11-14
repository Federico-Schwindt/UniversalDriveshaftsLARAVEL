<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use App\Support\OrderEvents;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checkout failure paths, all without Stripe (Http::fake): a failed or declined card payment never strands the
 * customer or duplicates the order, nothing "order placed" fires before the money, the charged total is the one
 * shown, /cart/add sells only published products, and the webhook is verified and acted on once.
 */
class CheckoutRobustnessTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int,\Illuminate\Http\Client\Request> */
    private array $stripeCalls = [];
    private bool $intentFails = false;
    /** Answers for the next GET payment_intents calls (default: not paid, another order). */
    private array $gets = [];
    private ?\Closure $onCancel = null;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.stripe.enabled' => true, 'services.stripe.secret' => 'sk_test_fake', 'services.stripe.key' => 'pk_test_fake',
            'services.stripe.pm_config' => null, 'services.stripe.webhook_secret' => 'whsec_test', 'services.brevo.key' => null]);
        Http::fake(function ($req) {
            if (!str_contains($req->url(), 'api.stripe.com')) return Http::response([], 200);
            $this->stripeCalls[] = $req;
            if (str_ends_with($req->url(), '/cancel')) return $this->onCancel ? ($this->onCancel)($req) : Http::response(['id' => 'pi_old', 'status' => 'canceled']);
            if ($req->method() === 'GET') return Http::response(array_shift($this->gets) ?? ['id' => 'pi_x', 'status' => 'requires_payment_method', 'metadata' => ['order_number' => 'nope']]);
            if ($this->intentFails) return Http::response(['error' => ['message' => 'boom']], 500);
            $id = 'pi_test' . count($this->stripeCalls);
            return Http::response(['id' => $id, 'client_secret' => $id . '_secret_x', 'status' => 'requires_payment_method']);
        });
    }

    private function fillCart(): float
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('in_stock', 1)->value('wp_product_id');
        $this->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk()->assertJsonPath('success', true);
        return app(CartService::class)->subtotal();
    }

    private function place(array $extra = [])
    {
        return $this->postJson('/checkout/place', $extra + ['email' => 'robust-' . uniqid() . '@example.com', 'phone' => '(813) 555-0142',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'billing_state' => 'FL',
            'shipping_method' => 'local_pickup', 'pickup_ack' => 1, 'agree_terms' => 1, 'payment_method' => 'card']);
    }

    private function coupon(): object
    {
        $code = 'ROBUST' . strtoupper(substr(uniqid(), -6));
        DB::table('coupons')->insert(['code' => $code, 'type' => 'percent', 'amount' => 5, 'free_shipping' => 0, 'used_count' => 0, 'per_customer_limit' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('coupons')->where('code', $code)->first();
    }

    public function test_failed_intent_marks_the_order_failed_and_a_retry_reuses_it(): void
    {
        $this->fillCart();
        $email = 'retry-' . uniqid() . '@example.com';
        $this->intentFails = true;
        $this->place(['email' => $email])->assertStatus(500);
        $first = DB::table('orders')->where('email', $email)->first();
        $this->assertSame('failed', $first->status);
        $this->assertGreaterThan(0, app(CartService::class)->count(), 'the cart survives a failed payment start');

        // the retry updates the same order (no orphan per attempt) and sends an idempotency key
        $this->intentFails = false;
        $res = $this->place(['email' => $email])->assertOk()->json('data');
        $this->assertNotEmpty($res['client_secret']);
        $this->assertSame(1, DB::table('orders')->where('order_number', $first->order_number)->count());
        $this->assertSame(1, DB::table('orders')->where('email', $email)->count());
        $this->assertSame('pending', DB::table('orders')->where('id', $first->id)->value('status'));
        $create = collect($this->stripeCalls)->last(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/payment_intents'));
        $this->assertStringStartsWith('checkout-' . $first->order_number, $create->header('Idempotency-Key')[0] ?? '');
        // an unpaid card order keeps the cart, so a declined card can be tried again
        $this->assertGreaterThan(0, app(CartService::class)->count());

        // declined again and placed once more: still one order, and the earlier intent is cancelled
        $this->place(['email' => $email])->assertOk();
        $this->assertSame(1, DB::table('orders')->where('email', $email)->count());
        $this->assertTrue(collect($this->stripeCalls)->contains(fn ($r) => str_ends_with($r->url(), '/cancel')));
    }

    public function test_coupon_sms_and_crm_wait_for_payment_on_card_orders(): void
    {
        \App\Support\Settings::set('notify.sms', true);   // texts are off on the site since 2026-11-14; this checks how they behave when on
        $this->fillCart();
        $c = $this->coupon();
        $this->post('/cart/coupon', ['code' => $c->code])->assertOk();
        $email = 'cpn-' . uniqid() . '@example.com';
        $this->place(['email' => $email, 'sms_opt_in' => 1])->assertOk();
        $o = DB::table('orders')->where('email', $email)->first();
        $this->assertSame($c->code, $o->coupon_code);
        $this->assertSame(0, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'), 'not counted before the money is in');
        $this->assertFalse(OrderEvents::has($o, 'notify:sms:placed'));

        // an unpaid card order holds the coupon for a while (its own retry keeps it), then lets it go
        [$ok] = app(\App\Services\CouponService::class)->validate($c->code, 100, null, $email);
        $this->assertFalse($ok, 'held by the checkout waiting for its card payment');
        [$ok] = app(\App\Services\CouponService::class)->validate($c->code, 100, null, $email, $o->id);
        $this->assertTrue($ok, 'the same checkout tried again keeps it');
        DB::table('orders')->where('id', $o->id)->update(['updated_at' => now()->subMinutes(\App\Services\CouponService::HOLD_MINUTES + 1)]);
        [$ok] = app(\App\Services\CouponService::class)->validate($c->code, 100, null, $email);
        $this->assertTrue($ok, 'an abandoned card checkout does not use up the per-customer limit');

        $pi = ['id' => $o->stripe_payment_intent, 'amount_received' => (int) round($o->total * 100), 'currency' => 'usd'];
        $this->assertTrue(PayLink::markPaid($o, 'test', 'stripe', $pi));
        $this->assertTrue(PayLink::markPaid($o, 'test', 'stripe', $pi));   // a second notification changes nothing
        $this->assertSame(1, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'));
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'paid')->count());
        $this->assertTrue(OrderEvents::has($o, 'notify:sms:placed'));
        [$ok] = app(\App\Services\CouponService::class)->validate($c->code, 100, null, $email);
        $this->assertFalse($ok, 'a paid order uses the coupon');

        // paid: the cart it came from is cleared on the next visit
        $this->getJson('/cart/mini')->assertJsonPath('data.count', 0);
    }

    public function test_pay_at_pickup_counts_the_coupon_and_clears_the_cart_right_away(): void
    {
        $this->actingAs($this->verifiedUser());
        $this->fillCart();
        $c = $this->coupon();
        $this->post('/cart/coupon', ['code' => $c->code])->assertOk();
        $this->place(['payment_method' => 'pay_at_pickup'])->assertOk()->assertJsonPath('data.client_secret', null);
        $this->assertSame(1, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'));
        $this->assertSame(0, app(CartService::class)->count());
    }

    private function verifiedUser(): User
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'pk-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['email_verified_at' => now()]);
        return $u->fresh();
    }

    /** Pay at pickup takes stock, texts and queues shop work before any money: a confirmed account, a couple of open orders. */
    public function test_pay_at_pickup_needs_a_confirmed_account_and_a_few_open_orders_at_most(): void
    {
        $this->fillCart();
        $email = 'pk-guest-' . uniqid() . '@example.com';
        $this->place(['email' => $email, 'payment_method' => 'pay_at_pickup'])->assertStatus(422);
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'pk-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $this->actingAs($u)->place(['email' => $email, 'payment_method' => 'pay_at_pickup'])->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('email', $email)->count(), 'no order from a guest or an unconfirmed account');

        // another account: a fresh session (AuthenticateSession would sign a switched user out)
        $this->flushSession();
        $this->actingAs($v = $this->verifiedUser());
        foreach (range(1, \App\Http\Controllers\ShopController::OPEN_PICKUP_ORDERS) as $n) {
            $this->fillCart();
            $this->place(['email' => $v->email, 'payment_method' => 'pay_at_pickup'])->assertOk();
        }
        $this->fillCart();
        $this->place(['email' => $v->email, 'payment_method' => 'pay_at_pickup'])->assertStatus(422);
        $this->assertSame(\App\Http\Controllers\ShopController::OPEN_PICKUP_ORDERS, DB::table('orders')->where('user_id', $v->id)->count());
    }

    /** An order nobody has paid for yet never texts a number abroad (SMS pumping). */
    public function test_an_unpaid_order_texts_only_a_domestic_number(): void
    {
        $this->assertTrue(\App\Services\SmsService::domestic('(813) 555-0142'));
        $this->assertTrue(\App\Services\SmsService::domestic('+1 787 555 0142'));   // Puerto Rico
        $this->assertFalse(\App\Services\SmsService::domestic('+1 876 555 0142'));  // Jamaica
        $this->assertFalse(\App\Services\SmsService::domestic('+44 20 7946 0958'));
        $this->actingAs($v = $this->verifiedUser());
        $this->fillCart();
        $num = $this->place(['email' => $v->email, 'phone' => '+44 20 7946 0958', 'sms_opt_in' => 1, 'payment_method' => 'pay_at_pickup'])->assertOk()->json('data.order_number');
        $o = DB::table('orders')->where('order_number', $num)->first();
        $this->assertFalse(OrderEvents::has($o, 'notify:sms:placed'));
        $this->assertTrue(OrderEvents::has($o, 'notify:crm'), 'the rest of the notices still ran');
    }

    /** A limited coupon can't be spread over several unpaid card orders and paid for later, nor won twice in a race. */
    public function test_the_last_use_of_a_coupon_goes_to_one_order(): void
    {
        $cs = app(\App\Services\CouponService::class);
        $c = $this->coupon();
        DB::table('coupons')->where('id', $c->id)->update(['usage_limit' => 1, 'per_customer_limit' => null]);
        $this->fillCart();
        $this->post('/cart/coupon', ['code' => $c->code])->assertOk();
        $email = 'last-' . uniqid() . '@example.com';
        $num = $this->place(['email' => $email])->assertOk()->json('data.order_number');
        $o = DB::table('orders')->where('order_number', $num)->first();
        $this->assertSame($c->code, $o->coupon_code);
        $this->assertSame(1, $cs->taken(DB::table('coupons')->find($c->id)));

        // anyone else is told it is used up while that card payment is pending
        [$ok, , $err] = $cs->validate($c->code, 100, null, 'someone-' . uniqid() . '@example.com');
        $this->assertFalse($ok);
        $this->assertSame('This coupon has reached its usage limit.', $err);
        $this->assertFalse($cs->claim(DB::table('coupons')->find($c->id), 'someone@example.com'));
        // the same checkout retried keeps it
        $this->place(['email' => $email])->assertOk();
        $this->assertSame($c->code, DB::table('orders')->where('id', $o->id)->value('coupon_code'));
        $this->assertTrue($cs->claim(DB::table('coupons')->find($c->id), $email, $o->id));
        // paid: counted once, still used up
        $pi = ['id' => 'pi_x', 'amount_received' => (int) round(DB::table('orders')->where('id', $o->id)->value('total') * 100), 'currency' => 'usd'];
        $this->assertTrue(PayLink::markPaid(DB::table('orders')->find($o->id), 'test', 'stripe', $pi));
        $this->assertSame(1, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'));
        $this->assertSame(1, $cs->taken(DB::table('coupons')->find($c->id)));
    }

    /** A retry after a declined card cancels the earlier payment first; if that payment went through, the order stays as paid. */
    public function test_a_retry_never_reprices_an_order_that_was_paid_meanwhile(): void
    {
        $this->fillCart();
        $email = 'race-' . uniqid() . '@example.com';
        $num = $this->place(['email' => $email])->assertOk()->json('data.order_number');
        $o = DB::table('orders')->where('order_number', $num)->first();
        $paid = ['id' => $o->stripe_payment_intent, 'status' => 'succeeded', 'amount_received' => (int) round($o->total * 100), 'currency' => 'usd', 'metadata' => ['order_number' => $num]];

        // the earlier payment succeeded after the first look: Stripe refuses to cancel it, and the order is marked paid
        $this->fillCart();   // a second line added meanwhile must not reach the paid order
        $this->gets = [['id' => $o->stripe_payment_intent, 'status' => 'requires_action', 'metadata' => ['order_number' => $num]], $paid, $paid];
        $this->onCancel = fn () => Http::response(['error' => ['code' => 'payment_intent_unexpected_state']], 400);
        $this->place(['email' => $email])->assertOk()->assertJsonPath('data.order_number', $num)->assertJsonPath('data.client_secret', null);
        $now = DB::table('orders')->find($o->id);
        $this->assertSame('paid', $now->payment_status);
        $this->assertEquals($o->total, $now->total);
        $this->assertSame(1, (int) DB::table('order_items')->where('order_id', $o->id)->sum('qty'));

        // the webhook lands while the retry is between its checks and its write: the paid row is left alone
        $this->fillCart();
        $num2 = $this->place(['email' => $email])->assertOk()->json('data.order_number');
        $o2 = DB::table('orders')->where('order_number', $num2)->first();
        $this->assertNotSame($num, $num2);
        $this->fillCart();
        $this->onCancel = function () use ($o2) {
            DB::table('orders')->where('id', $o2->id)->update(['payment_status' => 'paid', 'status' => 'processing']);
            return Http::response(['id' => $o2->stripe_payment_intent, 'status' => 'canceled']);
        };
        $this->place(['email' => $email])->assertOk()->assertJsonPath('data.order_number', $num2)->assertJsonPath('data.client_secret', null);
        $after = DB::table('orders')->find($o2->id);
        $this->assertSame('paid', $after->payment_status);
        $this->assertEquals($o2->total, $after->total);
        $this->assertSame(1, (int) DB::table('order_items')->where('order_id', $o2->id)->sum('qty'));
    }

    public function test_a_changed_total_is_refused_with_the_new_numbers(): void
    {
        $sub = $this->fillCart();
        $total = round($sub * 1.07 + 1e-9, 2);   // a pickup pays Pasco's 7% Florida tax
        $email = 'total-' . uniqid() . '@example.com';
        $this->place(['email' => $email, 'expected_total' => 1])->assertStatus(409)
            ->assertJsonPath('code', 'total_changed')->assertJsonPath('totals.total', $total);
        $this->assertSame(0, DB::table('orders')->where('email', $email)->count());
        $this->place(['email' => $email, 'expected_total' => (int) round($total * 100)])->assertOk();
    }

    public function test_cart_add_sells_only_published_products(): void
    {
        $draft = (int) DB::connection('wp')->table('wp_posts')->where('post_type', 'product')->whereIn('post_status', ['draft', 'private', 'trash'])->value('ID');
        $this->assertGreaterThan(0, $draft);
        $this->post('/cart/add', ['product_id' => $draft])->assertNotFound();
        $this->assertSame(0, app(CartService::class)->count());

        $this->fillCart();
        $line = array_values(app(CartService::class)->items())[0];
        $this->assertNotSame('', $line['sku']);
    }

    public function test_received_page_offers_pay_now_for_an_unpaid_card_order(): void
    {
        $this->fillCart();
        $email = 'recv-' . uniqid() . '@example.com';
        $num = $this->place(['email' => $email])->json('data.order_number');
        $this->get('/order-received/' . $num . '?email=' . urlencode($email))->assertOk()
            ->assertSee('Payment not completed')->assertSee('/pay/' . $num . '/', false)->assertDontSee('A confirmation is on its way');
    }

    public function test_webhook_accepts_any_v1_and_ignores_the_duplicate_charge_event(): void
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'stripe',
            'email' => 'wh@example.com', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US',
            'subtotal' => 40, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $o = DB::table('orders')->find($id);
        $send = function (string $type, array $obj, bool $goodSig = true) {
            $payload = json_encode(['type' => $type, 'data' => ['object' => $obj]]);
            $t = time();
            $sig = "t=$t,v1=" . hash_hmac('sha256', "$t.$payload", 'whsec_old_rolled') . ',v1=' . ($goodSig ? hash_hmac('sha256', "$t.$payload", 'whsec_test') : str_repeat('0', 64));
            return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);
        };
        $obj = ['id' => 'pi_wh1', 'amount' => 4000, 'amount_received' => 4000, 'currency' => 'usd', 'metadata' => ['order_number' => $o->order_number]];
        $send('payment_intent.succeeded', $obj, false)->assertStatus(400);
        // a charge with a PaymentIntent behind it is the same money: not acted on
        $send('charge.succeeded', ['id' => 'ch_1', 'payment_intent' => 'pi_wh1', 'amount_captured' => 4000, 'currency' => 'usd', 'metadata' => ['order_number' => $o->order_number]])->assertOk();
        $this->assertSame('unpaid', DB::table('orders')->where('id', $id)->value('payment_status'));
        $send('payment_intent.succeeded', $obj)->assertOk();
        $this->assertSame('paid', DB::table('orders')->where('id', $id)->value('payment_status'));
    }

    public function test_pay_confirm_rejects_a_non_intent_id(): void
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'stripe', 'pay_token' => str_repeat('a', 40),
            'email' => 'pc@example.com', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US',
            'subtotal' => 40, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $o = DB::table('orders')->find($id);
        $this->postJson('/pay/' . $o->order_number . '/' . $o->pay_token . '/confirm', ['payment_intent' => '../customers/cus_1'])->assertStatus(422);
        $this->assertCount(0, $this->stripeCalls);
    }

    public function test_saved_address_buttons_carry_parseable_json(): void
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'ds-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('dropship_addresses')->insert(['user_id' => $u->id, 'name' => 'Jo "Quote" Smith', 'kind' => 'dropship', 'company' => 'Acme & Co', 'street' => '1 Main St',
            'city' => 'Tampa', 'state' => 'FL', 'postcode' => '33602', 'country' => 'US', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u);
        $this->fillCart();
        $html = $this->get('/checkout')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-ds="([^"]+)"/', $html);
        preg_match('/data-ds="([^"]+)"/', $html, $m);
        $d = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);   // what the browser hands JSON.parse
        $this->assertSame('Acme & Co', $d['company'] ?? null);
        $this->assertArrayNotHasKey('user_id', $d);
    }
}
