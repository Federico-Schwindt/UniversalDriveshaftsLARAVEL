<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Every signed-in page and the checkout (with something in the cart) must render: no 500s behind the login. */
class SignedInSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGES = ['/account', '/account/orders', '/account/addresses', '/account/addresses?edit=new&kind=dropship', '/account/addresses?billing=1',
        '/account/designs', '/account/requests', '/account/settings', '/account/buy-again', '/cart'];

    public function test_account_pages_render(): void
    {
        Mail::fake();
        $u = User::query()->where('is_admin', 0)->firstOrFail();
        foreach (self::PAGES as $p) $this->actingAs($u)->get($p)->assertOk();
        // the wishlist was removed (owner, 2026-11): old links land on the catalog
        $this->actingAs($u)->get('/wishlist')->assertRedirect('/shop');
        if ($o = \DB::table('orders')->where('user_id', $u->id)->first()) $this->actingAs($u)->get('/account/orders/' . $o->order_number)->assertOk();
    }

    /** /wholesale for every account state: none, pending, approved (2026-11-11: a 500 for approved accounts only). */
    public function test_wholesale_page_renders_for_each_account_state(): void
    {
        $u = User::query()->where('is_admin', 0)->firstOrFail();
        foreach (['none', 'pending', 'approved'] as $state) {
            $u->forceFill(['wholesale_status' => $state])->save();
            $res = $this->actingAs($u->fresh())->get('/wholesale')->assertOk();
            if ($state === 'approved') $res->assertSee('wholesale account')->assertSee('% trade discount', false);
        }
    }

    public function test_checkout_renders_with_a_cart(): void
    {
        Mail::fake();
        $u = User::query()->where('is_admin', 0)->firstOrFail();
        $pid = (int) \DB::table('catalog_live')->where('price', '>', 0)->where('in_stock', 1)->value('wp_product_id');
        $this->actingAs($u)->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk();
        $this->actingAs($u)->get('/checkout')->assertOk()->assertSee('co-form', false);
    }
}
