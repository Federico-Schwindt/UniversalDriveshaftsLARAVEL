<?php

namespace Tests\Feature;

use App\Mail\Branded;
use App\Models\User;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Owner, 2026-11-14: "2FA por mail para entrar al admin". (The rest of the suite runs with ADMIN_2FA=false.) */
class AdminTwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['uds.admin_2fa' => true]);
    }

    private function admin(): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff Two', 'email' => 'twofa-' . uniqid() . '@example.test', 'password' => bcrypt('pw-' . uniqid()), 'is_admin' => 1, 'admin_role' => 'staff',
            'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        return User::findOrFail($id);
    }

    private function code(User $u): string
    {
        $code = null;
        Mail::assertSent(Branded::class, function ($m) use ($u, &$code) {
            if (!$m->hasTo($u->email) || !preg_match('/code: (\d{6})/', $m->subjectLine, $x)) return false;
            $code = $x[1];
            return true;
        });
        return $code;
    }

    public function test_the_admin_needs_the_e_mailed_code(): void
    {
        $u = $this->admin();
        $this->actingAs($u)->get('/admin/orders')->assertRedirect('/admin/verify');
        $this->postJson('/admin/orders/1/labels/rates', [])->assertForbidden();
        $this->get('/admin/verify')->assertOk()->assertSee('Admin sign-in code')->assertSee('Trust this computer');
        $this->get('/admin/verify')->assertOk();
        Mail::assertSent(Branded::class, 1);   // a reload does not send a second code
        $code = $this->code($u);

        $this->post('/admin/verify', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->get('/admin/orders')->assertRedirect('/admin/verify');
        $res = $this->post('/admin/verify', ['code' => $code, 'trust' => 1])->assertRedirect(url('/admin/orders'));
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === AdminTwoFactor::COOKIE);
        $this->assertNotNull($cookie);
        $this->get('/admin/orders')->assertOk();

        // a new session on this computer: the trusted cookie lets the admin in; without it, a new code
        $this->flushSession();
        $this->withUnencryptedCookie(AdminTwoFactor::COOKIE, $cookie->getValue())->get('/admin/orders')->assertOk();
        $this->flushSession();
        $this->withUnencryptedCookie(AdminTwoFactor::COOKIE, 'x')->get('/admin/orders')->assertRedirect('/admin/verify');
        // a password change voids the trusted computer
        $this->flushSession();
        DB::table('users')->where('id', $u->id)->update(['password' => bcrypt('changed-' . uniqid())]);
        $this->actingAs($u->fresh())->withUnencryptedCookie(AdminTwoFactor::COOKIE, $cookie->getValue())->get('/admin/orders')->assertRedirect('/admin/verify');
    }

    public function test_customers_never_reach_the_code_page(): void
    {
        $u = User::query()->create(['name' => 'Cust Two', 'email' => 'c2-' . uniqid() . '@example.test', 'password' => bcrypt('x')]);
        $this->actingAs($u)->get('/admin/verify')->assertForbidden();
        Mail::assertNothingSent();
    }
}
