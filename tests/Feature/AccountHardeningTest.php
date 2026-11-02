<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountController;
use App\Mail\Branded;
use App\Models\User;
use App\Support\EmailOtp;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Account review fixes (wholesale re-apply, locale, OTP limits, avatar disk use, RMA routes, order paging,
 * password change sign-out, Google-only re-auth). MySQL dev database, every test rolls back:
 *   APP_CONFIG_CACHE=… DB_CONNECTION=mysql DB_DATABASE=uds_app_dev MAIL_MAILER=array php artisan test --filter=AccountHardeningTest
 */
class AccountHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $over = []): User
    {
        $u = User::create(['name' => 'Harden Tester', 'email' => $over['email'] ?? 'harden-' . uniqid() . '@udshafts.test', 'password' => array_key_exists('password', $over) ? $over['password'] : bcrypt('Secret!2026')]);
        DB::table('users')->where('id', $u->id)->update(array_diff_key($over, array_flip(['email', 'password'])) + ['email_verified_at' => now()]);
        return $u->fresh();
    }

    private function lastCode(string $to): string
    {
        $code = null;
        Mail::assertSent(Branded::class, function (Branded $m) use ($to, &$code) {
            if (!$m->hasTo($to) || !preg_match('/(\d{6})$/', $m->subjectLine, $mm)) return false;
            $code = $mm[1];
            return true;
        });
        return $code;
    }

    /* H1 */
    public function test_wholesale_application_refuses_approved_and_pending_accounts(): void
    {
        Mail::fake();
        $approved = $this->user(['wholesale_status' => 'approved', 'b2b_tier' => 'gold']);
        $leads = DB::table('leads')->where('source', 'wholesale-application')->count();
        $this->actingAs($approved)->post('/wholesale', ['business_name' => 'Shop'])->assertRedirect('/wholesale')->assertSessionHas('acct_err');
        $this->assertSame('approved', DB::table('users')->where('id', $approved->id)->value('wholesale_status'));   // approval kept
        $this->assertSame(AccountController::WHOLESALE_APPROVED, AccountController::submitWholesale($approved, ['business_name' => 'Shop'], null));   // chatbot path too

        $fresh = $this->user();
        $this->flushSession();   // another account: AuthenticateSession would sign out a session holding the first one's hash
        $this->actingAs($fresh)->post('/wholesale', ['business_name' => 'New Shop'])->assertSessionHas('acct_ok');
        $this->assertSame('pending', DB::table('users')->where('id', $fresh->id)->value('wholesale_status'));
        $this->actingAs($fresh)->post('/wholesale', ['business_name' => 'New Shop'])->assertSessionHas('acct_err');   // no second lead
        $this->assertSame($leads + 1, DB::table('leads')->where('source', 'wholesale-application')->count());
    }

    /* H4 */
    public function test_french_is_a_valid_setting_and_sign_in_keeps_it(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/account/update', ['name' => 'Harden Tester', 'locale' => 'fr'])->assertSessionHasNoErrors();
        $this->assertSame('fr', DB::table('users')->where('id', $u->id)->value('locale'));
        $this->actingAs($u->fresh())->get('/account/settings')->assertOk()->assertSee('<option value="fr" selected', false);

        auth()->logout();
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret!2026', 'remember' => '1'])->assertRedirect();
        $this->assertSame('fr', DB::table('users')->where('id', $u->id)->value('locale'));   // an English visit doesn't overwrite it

        $blank = $this->user(['locale' => null]);
        auth()->logout();
        $this->post('/login', ['email' => $blank->email, 'password' => 'Secret!2026']);
        $this->assertSame('en', DB::table('users')->where('id', $blank->id)->value('locale'));   // empty: seeded from the site language
    }

    /* M5 */
    public function test_wrong_codes_do_not_extend_the_code_lifetime(): void
    {
        Mail::fake();
        $e = 'otp-exp-' . uniqid() . '@udshafts.test';
        $this->assertNull(EmailOtp::send('verify', $e));
        $code = $this->lastCode($e);
        $this->travel(9)->minutes();
        $this->assertNotNull(EmailOtp::check('verify', $e, $code === '000000' ? '111111' : '000000'));
        $this->travel(2)->minutes();   // 11 minutes after sending: expired, whatever the wrong try did
        $this->assertSame(__('That code has expired. Ask for a new one.'), EmailOtp::check('verify', $e, $code));
    }

    public function test_daily_cap_per_address_and_purpose(): void
    {
        Mail::fake();
        $e = 'otp-day-' . uniqid() . '@udshafts.test';
        for ($i = 0; $i < EmailOtp::MAX_PER_DAY; $i++) {
            $this->assertNull(EmailOtp::send('verify', $e), "send #$i");
            $this->travel(61)->minutes();   // past the cooldown and the hourly cap
        }
        $this->assertNotNull(EmailOtp::send('verify', $e));
        $this->assertNull(EmailOtp::send('register', $e));   // another purpose has its own count
    }

    public function test_three_burned_codes_lock_the_address_for_a_day(): void
    {
        Mail::fake();
        $e = 'otp-lock-' . uniqid() . '@udshafts.test';
        for ($b = 0; $b < EmailOtp::MAX_BURNED; $b++) {
            $this->assertNull(EmailOtp::send('verify', $e));
            $code = $this->lastCode($e);
            for ($i = 0; $i < EmailOtp::MAX_TRIES; $i++) EmailOtp::check('verify', $e, $code === '000000' ? '111111' : '000000');
            $this->travel(1)->minutes();
        }
        $this->assertNotNull($err = EmailOtp::send('verify', $e));
        $this->assertStringContainsString('24', $err);
        $this->travel(25)->hours();
        $this->assertNull(EmailOtp::send('verify', $e));
    }

    /* M6 */
    public function test_avatar_only_persists_account_seeds(): void
    {
        Http::fake(['api.dicebear.com/*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', 200)]);
        $random = 'zz' . strtolower(bin2hex(random_bytes(6)));
        $file = storage_path('app/avatars/thumbs/' . $random . '.svg');
        $this->get('/avatar/thumbs/' . $random)->assertOk()->assertHeader('Cache-Control', 'max-age=600, private');
        $this->assertFileDoesNotExist($file);

        $mine = 'zz' . strtolower(bin2hex(random_bytes(6)));
        $this->user(['avatar_style' => 'thumbs', 'avatar_seed' => $mine]);
        $kept = storage_path('app/avatars/thumbs/' . $mine . '.svg');
        try {
            $this->get('/avatar/thumbs/' . $mine)->assertOk();
            $this->assertFileExists($kept);
        } finally {
            @unlink($kept);
        }
    }

    /* L4 */
    public function test_rma_routes_follow_the_controller_types(): void
    {
        $this->get('/rma/warranty')->assertRedirect('/return-policy#warranty');
        $this->get('/rma/cancel')->assertOk();
        $this->get('/rma/exchange')->assertOk();
        $this->get('/rma/bogus')->assertNotFound();
    }

    /* M4 */
    public function test_orders_page_paginates_and_searches_in_sql_within_the_owner_scope(): void
    {
        $u = $this->user();
        $order = fn (array $x) => DB::table('orders')->insertGetId($x + ['order_number' => 'TH-' . uniqid(), 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'billing_first_name' => 'A', 'billing_last_name' => 'B', 'billing_country' => 'US', 'subtotal' => 10, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 10, 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < 27; $i++) $order(['user_id' => $u->id, 'email' => $u->email]);
        $hit = $order(['user_id' => null, 'email' => strtoupper($u->email), 'order_number' => 'TH-HIT-' . uniqid()]);   // verified e-mail match
        DB::table('order_items')->insert(['order_id' => $hit, 'type' => 'product', 'wp_product_id' => 1, 'name' => 'Yoke', 'sku' => 'ZQ9-1310', 'price' => 1, 'qty' => 1, 'line_total' => 1, 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $other = $order(['user_id' => null, 'email' => 'someone-else@udshafts.test', 'order_number' => 'TH-OTHER-' . uniqid()]);
        DB::table('order_items')->insert(['order_id' => $other, 'type' => 'product', 'wp_product_id' => 1, 'name' => 'Yoke', 'sku' => 'ZQ9-1310', 'price' => 1, 'qty' => 1, 'line_total' => 1, 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($u);
        $p1 = $this->get('/account/orders')->assertOk();
        $this->assertSame(25, $p1->viewData('orders')->count());
        $this->assertSame(28, $p1->viewData('orders')->total());
        $this->assertSame(3, $this->get('/account/orders?page=2')->viewData('orders')->count());

        $s = $this->get('/account/orders?q=zq91310')->assertOk();   // normalised SKU, order beyond the first page of an unpaged list
        $this->assertSame([$hit], $s->viewData('orders')->pluck('id')->all());
        $this->assertSame([], $this->get('/account/orders?q=TH-OTHER')->viewData('orders')->pluck('id')->all());

        DB::table('users')->where('id', $u->id)->update(['email_verified_at' => null]);
        $this->assertSame(0, $this->actingAs($u->fresh())->get('/account/orders?q=zq9')->viewData('orders')->total());   // unproven mailbox: no e-mail match
    }

    /* H3 + M2 */
    public function test_password_change_signs_out_other_devices_and_keeps_this_one(): void
    {
        Event::fake([OtherDeviceLogout::class]);
        $u = $this->user();
        DB::table('users')->where('id', $u->id)->update(['remember_token' => 'old-token']);
        $u = $u->fresh();
        $this->actingAs($u);

        $this->post('/account/password', ['current_password' => 'wrong', 'password' => 'NewSecret!27', 'password_confirmation' => 'NewSecret!27'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->assertSame([], session('errors')->getBag('default')->all());   // not under the e-mail form

        $this->post('/account/password', ['current_password' => 'Secret!2026', 'password' => 'NewSecret!27', 'password_confirmation' => 'NewSecret!27'])
            ->assertSessionHas('acct_ok');
        Event::assertDispatched(OtherDeviceLogout::class);
        $row = DB::table('users')->where('id', $u->id)->first();
        $this->assertNotSame('old-token', $row->remember_token);
        $this->assertTrue(\Hash::check('NewSecret!27', $row->password));
        $this->get('/account/settings')->assertOk();   // this session is still signed in
    }

    public function test_email_change_rotates_the_remember_token(): void
    {
        Mail::fake();
        $u = $this->user();
        DB::table('users')->where('id', $u->id)->update(['remember_token' => 'old-token']);
        $this->actingAs($u->fresh());
        $new = 'moved-' . uniqid() . '@udshafts.test';
        $this->post('/account/email', ['new_email' => $new, 'current_password' => 'Secret!2026'])->assertRedirect('/account/email/verify');
        $this->post('/account/email/verify', ['code' => $this->lastCode($new)])->assertRedirect('/account/settings');
        $this->assertNotSame('old-token', DB::table('users')->where('id', $u->id)->value('remember_token'));
    }

    /* M1 */
    public function test_google_only_account_needs_a_code_to_the_current_mailbox(): void
    {
        Mail::fake();
        $u = $this->user(['password' => null, 'google_id' => 'g-' . uniqid()]);
        $this->actingAs($u);
        $new = 'g-moved-' . uniqid() . '@udshafts.test';
        $this->get('/account/settings')->assertOk()->assertSee('name="reauth_code"', false)->assertSee('action="/account/reauth/send"', false);
        $this->post('/account/email', ['new_email' => $new])->assertSessionHasErrorsIn('email', 'reauth_code');
        $this->post('/account/password', ['password' => 'NewSecret!27', 'password_confirmation' => 'NewSecret!27'])->assertSessionHasErrorsIn('password', 'reauth_code');
        $this->assertNull(DB::table('users')->where('id', $u->id)->value('password'));

        $this->post('/account/reauth/send', ['for' => 'email'])->assertSessionHas('reauth_sent', 'email');
        $this->post('/account/email', ['new_email' => $new, 'reauth_code' => $this->lastCode($u->email)])->assertRedirect('/account/email/verify');
        // confirmed once: setting a first password in the next minutes needs no second code
        $this->post('/account/password', ['password' => 'NewSecret!27', 'password_confirmation' => 'NewSecret!27'])->assertSessionHas('acct_ok');
    }

    /* M3 */
    public function test_registration_returns_to_the_intended_page(): void
    {
        Mail::fake();
        $e = 'intended-' . uniqid() . '@udshafts.test';
        $this->get('/register?redirect=/wishlist')->assertOk();
        $this->post('/register', ['name' => 'Back Again', 'email' => $e, 'phone' => '', 'password' => 'Secret!2026', 'password_confirmation' => 'Secret!2026', '_ts' => encrypt(time() - 10), 'website' => '']);
        $this->get('/register')->assertSee('value="Back Again"', false);   // "Use a different e-mail" keeps the name
        $this->post('/register/verify', ['code' => $this->lastCode($e)])->assertRedirect('/wishlist');
    }
}
