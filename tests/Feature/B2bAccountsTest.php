<?php

namespace Tests\Feature;

use App\Mail\Branded;
use App\Models\User;
use App\Services\B2bAccounts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Business accounts: tier discount at checkout, Net terms orders, monthly tier up / down with e-mails. */
class B2bAccountsTest extends TestCase
{
    use DatabaseTransactions;

    private function account(array $extra = []): User
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'b2b-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update($extra + ['wholesale_status' => 'approved', 'net_terms' => 1, 'phone' => '(813) 555-0142']);   // terms are off by default: the shop turns them on
        B2bAccounts::evaluate($u->id, false);
        return $u->fresh();
    }

    private function order(User $u, float $subtotal, string $when, array $extra = []): void
    {
        DB::table('orders')->insert($extra + ['order_number' => 'T-' . uniqid(), 'user_id' => $u->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => $u->email, 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'subtotal' => $subtotal, 'shipping_total' => 0, 'discount_total' => 0,
            'total' => $subtotal, 'created_at' => $when, 'updated_at' => $when]);
    }

    private function cart(User $u): float
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('in_stock', 1)->value('wp_product_id');
        $this->actingAs($u)->post('/cart/add', ['product_id' => $pid, 'quantity' => 2])->assertOk();
        return app(\App\Services\CartService::class)->subtotal();
    }

    private function place(User $u, array $extra = [])
    {
        return $this->actingAs($u)->postJson('/checkout/place', $extra + ['email' => $u->email, 'phone' => '(813) 555-0142', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan',
            'billing_country' => 'US', 'billing_state' => 'FL', 'shipping_method' => 'local_pickup', 'pickup_ack' => 1, 'agree_terms' => 1]);
    }

    public function test_new_account_is_bronze_and_gets_five_percent_at_checkout_on_terms(): void
    {
        Mail::fake();
        $u = $this->account();
        $this->assertSame('bronze', $u->b2b_tier);
        $sub = $this->cart($u);
        $this->actingAs($u)->get('/checkout')->assertOk()->assertSee('Invoice, Net 20')->assertSee('Bronze 5% account');
        $res = $this->place($u, ['payment_method' => 'net_terms', 'po_number' => 'PO-7731'])->assertOk()->json('data');
        $o = DB::table('orders')->where('order_number', $res['order_number'])->first();
        $this->assertSame('net_terms', $o->payment_method);
        $this->assertSame('processing', $o->status);
        $this->assertSame('PO-7731', $o->po_number);
        $this->assertEquals(round($sub * 0.05, 2), (float) $o->tier_discount);
        // a pickup at the shop pays Pasco's 7% Florida tax on the discounted price (App\Support\SalesTax)
        $net = round($sub - $sub * 0.05, 2);
        $this->assertEquals(round($net * 1.07 + 1e-9, 2), (float) $o->total);
        $this->assertEquals(round($net * 0.07 + 1e-9, 2), (float) $o->tax_total);
        $this->assertSame(now('America/New_York')->addDays(20)->toDateString(), $o->terms_due_at);
        $this->assertNull($res['client_secret']);
        Mail::assertSent(Branded::class, fn ($m) => $m->hasTo($u->email));   // invoice orders are confirmed right away
        $this->actingAs($u)->get('/account/business')->assertOk()->assertSee($o->order_number)->assertSee('PO-7731');
    }

    public function test_retail_customer_cannot_use_terms_or_get_a_tier(): void
    {
        $u = $this->account(['wholesale_status' => 'none']);
        $this->cart($u);
        $this->place($u, ['payment_method' => 'net_terms'])->assertStatus(422);
        $this->actingAs($u)->get('/account/business')->assertOk()->assertSee('Apply for a business account');
    }

    public function test_overdue_invoice_or_credit_limit_blocks_terms(): void
    {
        $u = $this->account(['credit_limit' => 100]);
        $this->cart($u);
        $this->place($u, ['payment_method' => 'net_terms'])->assertStatus(422)->assertJsonFragment(['errors' => [__('This order is over the credit available on your account. Pay by card, or call (813) 524-6491 to raise your limit.')]]);
        $v = $this->account();
        $this->order($v, 300, now()->subDays(30), ['payment_method' => 'net_terms', 'payment_status' => 'unpaid', 'terms_due_at' => now()->subDays(5)->toDateString()]);
        [$ok, , $overdue, , $why] = B2bAccounts::terms($v->fresh(), 10);
        $this->assertFalse($ok); $this->assertSame(1, $overdue); $this->assertSame('overdue', $why);
    }

    public function test_tier_goes_up_mid_month_holds_next_month_then_drops_with_emails(): void
    {
        Mail::fake();
        $this->travelTo(now('America/New_York')->setDate(2026, 10, 10)->setTime(12, 0));
        $u = $this->account();
        $this->order($u, 6000, now());
        $this->assertSame('silver', B2bAccounts::evaluate($u->id));
        Mail::assertSent(Branded::class, fn ($m) => $m->hasTo($u->email) && str_contains($m->subjectLine, 'Silver'));
        $this->order($u, 1500, now());
        $this->assertSame('gold', B2bAccounts::evaluate($u->id));
        // November: October's $7,500 keeps Gold all month even with no purchases
        $this->travelTo(now('America/New_York')->setDate(2026, 11, 1)->setTime(0, 15));
        $this->assertSame('gold', B2bAccounts::evaluate($u->id));
        // December 1st: November was $5,600 → Silver, and the customer is told
        $this->order($u, 5600, now('America/New_York')->setDate(2026, 11, 20));
        $this->travelTo(now('America/New_York')->setDate(2026, 12, 1)->setTime(0, 15));
        Mail::fake();
        $this->artisan('b2b:tiers')->assertExitCode(0);
        $this->assertSame('silver', $u->fresh()->b2b_tier);
        Mail::assertSent(Branded::class, fn ($m) => $m->hasTo($u->email) && str_contains($m->subjectLine, 'Silver'));
        // January 1st with nothing in December → Bronze
        $this->travelTo(now('America/New_York')->setDate(2027, 1, 1)->setTime(0, 15));
        $this->assertSame('bronze', B2bAccounts::evaluate($u->id));
        $this->assertSame(4, DB::table('b2b_tier_log')->where('user_id', $u->id)->where('from_tier', '!=', null)->count());
    }

    public function test_terms_reminders_go_once(): void
    {
        Mail::fake();
        $u = $this->account();
        $this->order($u, 400, now(), ['payment_method' => 'net_terms', 'payment_status' => 'unpaid', 'terms_due_at' => now('America/New_York')->addDays(3)->toDateString()]);
        $this->order($u, 200, now()->subDays(25), ['payment_method' => 'net_terms', 'payment_status' => 'unpaid', 'terms_due_at' => now('America/New_York')->subDay()->toDateString()]);
        $this->assertSame(2, B2bAccounts::remind());
        $this->assertSame(0, B2bAccounts::remind());
    }

    public function test_cutoff_setting_drives_the_ship_date(): void
    {
        \App\Support\Settings::set('shipping.cutoff', '3:30pm');
        $this->assertSame([15, 30], \App\Services\CartService::cutoff());
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 15:00', 'America/New_York'));   // Wednesday
        $this->assertTrue(\App\Services\CartService::shipDate()->isSameDay(now('America/New_York')));
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 15:45', 'America/New_York'));
        $this->assertNull(\App\Services\CartService::cutoffToday());
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Cache::forget('settings_all');   // the settings cache is not rolled back with the transaction
        parent::tearDown();
    }
}
