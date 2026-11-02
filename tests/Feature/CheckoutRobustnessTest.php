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

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.stripe.enabled' => true, 'services.stripe.secret' => 'sk_test_fake', 'services.stripe.key' => 'pk_test_fake',
            'services.stripe.pm_config' => null, 'services.stripe.webhook_secret' => 'whsec_test', 'services.brevo.key' => null]);
        Http::fake(function ($req) {
            if (!str_contains($req->url(), 'api.stripe.com')) return Http::response([], 200);
            $this->stripeCalls[] = $req;
            if (str_ends_with($req->url(), '/cancel')) return Http::response(['id' => 'pi_old', 'status' => 'canceled']);
            if ($req->method() === 'GET') return Http::response(['id' => 'pi_x', 'status' => 'requires_payment_method', 'metadata' => ['order_number' => 'nope']]);
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
        $this->fillCart();
        $c = $this->coupon();
        $this->post('/cart/coupon', ['code' => $c->code])->assertOk();
        $email = 'cpn-' . uniqid() . '@example.com';
        $this->place(['email' => $email, 'sms_opt_in' => 1])->assertOk();
        $o = DB::table('orders')->where('email', $email)->first();
        $this->assertSame($c->code, $o->coupon_code);
        $this->assertSame(0, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'), 'not counted before the money is in');
        $this->assertFalse(OrderEvents::has($o, 'notify:sms:placed'));

        // an unpaid card order does not use up the per-customer limit
        [$ok] = app(\App\Services\CouponService::class)->validate($c->code, 100, null, $email);
        $this->assertTrue($ok);

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
        $this->fillCart();
        $c = $this->coupon();
        $this->post('/cart/coupon', ['code' => $c->code])->assertOk();
        $this->place(['payment_method' => 'pay_at_pickup'])->assertOk()->assertJsonPath('data.client_secret', null);
        $this->assertSame(1, (int) DB::table('coupons')->where('id', $c->id)->value('used_count'));
        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_a_changed_total_is_refused_with_the_new_numbers(): void
    {
        $sub = $this->fillCart();
        $email = 'total-' . uniqid() . '@example.com';
        $this->place(['email' => $email, 'expected_total' => 1])->assertStatus(409)
            ->assertJsonPath('code', 'total_changed')->assertJsonPath('totals.total', $sub);
        $this->assertSame(0, DB::table('orders')->where('email', $email)->count());
        $this->place(['email' => $email, 'expected_total' => (int) round($sub * 100)])->assertOk();
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
