<?php

namespace Tests\Feature;

use App\Http\Controllers\MediaController;
use App\Models\User;
use App\Services\CouponService;
use App\Services\PtiDropship;
use App\Services\RecoveryMailer;
use App\Support\PayLink;
use App\Support\Rma;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security review 2026-11-14 (b277443, applied on the server first): one cache key per picture, the anonymous newsletter
 * and quote forms can't be turned on a stranger's inbox, a cancel needs a real order, track-order takes the order number,
 * PTI dropships go where the rate was quoted, and a lapsed coupon hold is re-checked before a pay link charges.
 */
class SecurityFixesNov14Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
    }

    private function order(array $x = []): object
    {
        $id = DB::table('orders')->insertGetId($x + ['order_number' => 'UDS-SEC-' . strtoupper(Str::random(6)), 'status' => 'pending', 'source' => 'web', 'payment_status' => 'unpaid',
            'payment_method' => 'stripe', 'email' => 'sec-' . uniqid() . '@example.test', 'billing_first_name' => 'Ana', 'billing_last_name' => 'Ruiz', 'billing_address_1' => '100 Main St',
            'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33602', 'billing_country' => 'US', 'ship_to_different' => 0, 'shipping_method' => 'upsdap-ground',
            'subtotal' => 100, 'shipping_total' => 10, 'discount_total' => 0, 'total' => 110, 'pay_token' => Str::random(32), 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_media_answers_only_the_key_the_site_mints(): void
    {
        $src = '/img/makes/acura.png';
        $key = rtrim(strtr(base64_encode($src), '+/', '-_'), '=');
        $this->assertStringContainsString("/media/320/$key.webp", MediaController::url($src, 320));
        // the same bytes under another spelling: spare low bits in the last character
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $loose = substr($key, 0, -1) . $alpha[strpos($alpha, substr($key, -1)) ^ 1];
        $this->assertSame($src, base64_decode(strtr($loose, '-_', '+/'), true));
        $padded = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        foreach ([$loose, $padded('/img/makes//acura.png'), $padded('/img/makes/./acura.png'), $padded('/img/makes/acura.png?x=1'), $padded('/img/makes\\acura.png')] as $k) {
            $this->assertSame([null, null, false], MediaController::ensure(320, $k), $k);
            $this->get("/media/320/$k.webp")->assertNotFound();
        }
    }

    public function test_the_anonymous_newsletter_form_cannot_lift_an_unsubscribe_or_mail_a_typed_name(): void
    {
        config(['services.brevo.enabled' => true, 'services.brevo.key' => 'test-key']);
        \App\Support\Settings::set('brevo.list_newsletter', 9);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);
        $welcomes = fn ($to) => Http::recorded(fn ($r) => str_contains($r->url(), '/smtp/email') && ($r['to'][0]['email'] ?? '') === $to)->count();

        // an address that unsubscribed stays unsubscribed and gets no welcome
        $gone = 'nl-gone-' . uniqid() . '@example.test';
        DB::table('marketing_suppressions')->insert(['email' => $gone, 'channel' => 'newsletter', 'created_at' => now()]);
        $this->postJson('/newsletter', ['email' => $gone, 'name' => 'Visit evil.example now'])->assertOk();
        $this->assertTrue(DB::table('marketing_suppressions')->where('email', $gone)->exists());
        $this->assertSame(0, $welcomes($gone));

        // a new address gets one welcome a day, without the typed name
        $new = 'nl-new-' . uniqid() . '@example.test';
        $this->postJson('/newsletter', ['email' => $new, 'name' => 'Visit evil.example now'])->assertOk();
        $this->postJson('/newsletter', ['email' => $new, 'name' => 'Visit evil.example now'])->assertOk();
        $this->assertSame(1, $welcomes($new));
        $mail = Http::recorded(fn ($r) => str_contains($r->url(), '/smtp/email') && ($r['to'][0]['email'] ?? '') === $new)->first()[0];
        $this->assertStringNotContainsString('evil.example', $mail['htmlContent']);

        // the signed-in, verified owner of the address may opt back in
        $u = User::query()->create(['name' => 'Nora Lane', 'email' => $gone, 'password' => bcrypt(Str::random(12))]);
        DB::table('users')->where('id', $u->id)->update(['email_verified_at' => now()]);
        $this->actingAs($u->fresh())->postJson('/newsletter', ['email' => $gone])->assertOk();
        $this->assertFalse(DB::table('marketing_suppressions')->where('email', $gone)->exists());
    }

    public function test_quote_acknowledgements_leave_out_typed_text_and_stop_after_three_a_day(): void
    {
        $q = ['email' => 'quote-' . uniqid() . '@example.test', 'phone' => null, 'vehicle' => 'Visit evil.example', 'note' => 'call evil.example', 'part_number' => 'SEC-1',
            'sku' => 'SEC-1', 'description' => null, 'fits' => null, 'url' => null, 'ip' => '127.0.0.1'];
        $html = view('emails.recovery.quote-customer', ['q' => $q, 'title' => 'We have your quote request', 'preheader' => ''])->render();
        $this->assertStringNotContainsString('evil.example', $html);
        $mailer = app(RecoveryMailer::class);
        foreach ([true, true, true, false] as $i => $customer) {
            $sent = $mailer->quote($q);
            $this->assertTrue($sent['shop'], "shop copy $i");
            $this->assertSame($customer, $sent['customer'], "customer copy $i");
        }
    }

    public function test_a_cancel_request_needs_a_real_order(): void
    {
        $this->assertNotNull(Rma::refusal('cancel', null, 'UDS-NOPE-1'));
        $this->assertNotNull(Rma::refusal('cancel', null));
        $this->assertNull(Rma::refusal('cancel', $this->order()));
    }

    public function test_track_order_takes_the_order_number_not_the_row_id(): void
    {
        $o = $this->order();
        $this->post('/track-order', ['orderid' => (string) $o->id, 'order_email' => $o->email])->assertSessionHas('track_notice');
        $this->post('/track-order', ['orderid' => $o->order_number, 'order_email' => $o->email])->assertSessionMissing('track_notice')->assertRedirect();
    }

    public function test_a_pti_dropship_goes_to_the_address_the_rate_was_quoted_for(): void
    {
        $o = $this->order(['shipping_first_name' => 'Someone', 'shipping_last_name' => 'Else', 'shipping_address_1' => '9 Elsewhere Rd', 'shipping_city' => 'Miami',
            'shipping_state' => 'FL', 'shipping_postcode' => '33101', 'shipping_country' => 'US']);
        $to = app(PtiDropship::class)->shipTo($o);
        $this->assertSame('100 Main St', $to['street']);
        $this->assertSame('Tampa', $to['city']);
        $this->assertStringContainsString('Ana Ruiz', $to['company']);
        $o->ship_to_different = 1;
        $this->assertSame('9 Elsewhere Rd', app(PtiDropship::class)->shipTo($o)['street']);
    }

    public function test_a_lapsed_coupon_hold_is_checked_again_before_paying(): void
    {
        $code = 'SEC' . strtoupper(Str::random(6));
        $cid = DB::table('coupons')->insertGetId(['code' => $code, 'type' => 'percent', 'amount' => 10, 'usage_limit' => 1, 'used_count' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $coupons = app(CouponService::class);
        $late = $this->order(['coupon_code' => $code, 'updated_at' => now()->subHours(3)]);
        $this->assertFalse($coupons->stillHolds($late), 'the code was used up while this order sat unpaid');
        $this->assertTrue($coupons->stillHolds($this->order(['coupon_code' => $code])), 'inside its 60-minute hold');
        $this->assertTrue($coupons->stillHolds($this->order(['updated_at' => now()->subHours(3)])), 'no coupon');
        $this->assertTrue($coupons->stillHolds($this->order(['coupon_code' => $code, 'payment_method' => 'invoice', 'updated_at' => now()->subHours(3)])), 'committed when placed');
        $this->postJson("/pay/{$late->order_number}/{$late->pay_token}/intent")->assertStatus(409)->assertJsonPath('error', fn ($e) => str_contains($e, 'coupon'));

        // the order still inside its hold counts too: one use left after it
        DB::table('coupons')->where('id', $cid)->update(['usage_limit' => 2, 'used_count' => 0]);
        $this->assertTrue($coupons->stillHolds($late), 'room left on the code');

        // money that arrives anyway (straight through the checkout intent) is taken, and the shop is told
        DB::table('coupons')->where('id', $cid)->update(['used_count' => 1]);
        $this->assertTrue(PayLink::markPaid($late, 'Card payment via Stripe (test)', 'stripe'));
        $this->assertSame('paid', DB::table('orders')->where('id', $late->id)->value('payment_status'));
        $this->assertTrue(DB::table('order_events')->where('order_id', $late->id)->where('type', 'coupon_over_limit')->exists());
    }
}
