<?php

namespace Tests\Feature;

use App\Mail\Branded;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Registration by e-mail code, e-mail change, address book.
 * Run against the MySQL dev database (the migrations are MySQL-only); every test rolls back:
 *   DB_CONNECTION=mysql DB_DATABASE=uds_app_dev php artisan test --filter=AccountOtpTest
 */
class AccountOtpTest extends TestCase
{
    use DatabaseTransactions;

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

    private function form(array $over = []): array
    {
        return $over + ['name' => 'Otp Tester', 'email' => 'otp-test@udshafts.test', 'phone' => '', 'password' => 'Secret!2026', 'password_confirmation' => 'Secret!2026', '_ts' => encrypt(time() - 10), 'website' => ''];
    }

    public function test_registration_waits_for_the_code(): void
    {
        Mail::fake();
        $this->post('/register', $this->form())->assertRedirect('/register/verify');
        $this->assertDatabaseMissing('users', ['email' => 'otp-test@udshafts.test']);   // nothing exists before the code
        $this->get('/register/verify')->assertOk()->assertSee('o•••t@udshafts.test', false);

        $code = $this->lastCode('otp-test@udshafts.test');
        $this->post('/register/verify', ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('users', ['email' => 'otp-test@udshafts.test']);

        $this->post('/register/verify', ['code' => $code])->assertRedirect('/account');
        $u = User::where('email', 'otp-test@udshafts.test')->first();
        $this->assertNotNull($u);
        $this->assertNotNull($u->email_verified_at);
        $this->assertAuthenticatedAs($u);
    }

    public function test_bots_are_turned_away(): void
    {
        Mail::fake();
        $this->post('/register', $this->form(['website' => 'http://spam.example']))->assertSessionHasErrors('email');
        $this->post('/register', $this->form(['_ts' => encrypt(time())]))->assertSessionHasErrors('email');   // sent in under 3 s
        $this->post('/register', $this->form(['_ts' => 'forged']))->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_code_burns_after_five_wrong_tries(): void
    {
        Mail::fake();
        $this->post('/register', $this->form());
        $code = $this->lastCode('otp-test@udshafts.test');
        $wrong = $code === '123456' ? '654321' : '123456';
        for ($i = 0; $i < 5; $i++) $this->post('/register/verify', ['code' => $wrong]);
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');   // the right code no longer works
        $this->assertDatabaseMissing('users', ['email' => 'otp-test@udshafts.test']);
    }

    public function test_email_change_needs_the_new_mailbox(): void
    {
        Mail::fake();
        $u = User::create(['name' => 'Change Me', 'email' => 'old-otp@udshafts.test', 'password' => bcrypt('Secret!2026')]);
        DB::table('users')->where('id', $u->id)->update(['email_verified_at' => now()]);
        $this->actingAs($u);

        $this->post('/account/email', ['new_email' => 'new-otp@udshafts.test', 'current_password' => 'nope'])->assertSessionHasErrorsIn('email', 'current_password');
        $this->post('/account/email', ['new_email' => 'new-otp@udshafts.test', 'current_password' => 'Secret!2026'])->assertRedirect('/account/email/verify');
        $this->assertDatabaseHas('users', ['id' => $u->id, 'email' => 'old-otp@udshafts.test']);   // unchanged until confirmed

        $this->post('/account/email/verify', ['code' => $this->lastCode('new-otp@udshafts.test')])->assertRedirect('/account/settings');
        $this->assertDatabaseHas('users', ['id' => $u->id, 'email' => 'new-otp@udshafts.test']);
        Mail::assertSent(Branded::class, fn (Branded $m) => $m->hasTo('old-otp@udshafts.test') && str_contains($m->subjectLine, 'changed'));
    }

    public function test_address_book_and_profile(): void
    {
        $u = User::create(['name' => 'Addr Owner', 'email' => 'addr-otp@udshafts.test', 'password' => bcrypt('Secret!2026')]);
        $other = User::create(['name' => 'Someone Else', 'email' => 'addr-other@udshafts.test', 'password' => bcrypt('Secret!2026')]);
        $this->actingAs($u);
        $a = ['name' => 'Addr Owner', 'street' => '19122 Weymouth Drive', 'city' => "Land O' Lakes", 'state' => 'FL', 'postcode' => '34638', 'country' => 'US'];

        $this->post('/account/addresses', $a)->assertRedirect('/account/addresses');
        $first = DB::table('dropship_addresses')->where('user_id', $u->id)->first();
        $this->assertTrue((bool) $first->is_default);   // the first address becomes the default

        $this->post('/account/addresses', ['street' => 'PO Box 12', 'label' => 'Box'] + $a)->assertSessionHasErrors('street');
        $this->post('/account/addresses', ['street' => '11611 Prospect Drive', 'company' => 'UDS', 'is_default' => 1] + $a);
        $this->assertSame(1, DB::table('dropship_addresses')->where('user_id', $u->id)->where('is_default', true)->count());

        // cannot touch somebody else's address
        $theirs = DB::table('dropship_addresses')->insertGetId(['user_id' => $other->id, 'company' => 'X', 'street' => '1 Main', 'city' => 'Tampa', 'postcode' => '33601', 'country' => 'US', 'created_at' => now(), 'updated_at' => now()]);
        $this->post("/account/addresses/$theirs/delete")->assertNotFound();
        $this->post('/account/addresses', ['id' => $theirs] + $a)->assertNotFound();

        $this->post('/account/billing', ['billing_address_1' => '1 Billing Way', 'billing_city' => 'Tampa', 'billing_postcode' => '33601', 'billing_country' => 'us'])->assertRedirect('/account/addresses');
        $this->assertDatabaseHas('users', ['id' => $u->id, 'billing_city' => 'Tampa', 'billing_country' => 'US']);

        $this->post('/account/update', ['name' => 'Addr Owner', 'company' => 'Owner Co', 'phone' => '+1 813 555 0100', 'locale' => 'es']);
        $this->assertDatabaseHas('users', ['id' => $u->id, 'company' => 'Owner Co', 'locale' => 'es']);

        foreach (['/account', '/account/orders', '/account/addresses', '/account/addresses?edit=new', '/account/settings', '/account/designs', '/account/requests', '/account/buy-again'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/account/dropship')->assertRedirect('/account/addresses');
    }
}
