<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ProductsController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Admin role gates and the money / catalog guards added in the Nov 2026 admin review. */
class AdminHardeningTest extends TestCase
{
    use DatabaseTransactions;

    // the catalog lives in wp_* tables on a second connection: roll those back too
    protected $connectionsToTransact = ['mysql', 'wp'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();   // never reach Stripe / EasyPost / Warp / PTI from a test
        Http::fake(['api.easypost.com/v2/carrier_accounts*' => Http::response([], 200)]);   // read-only list the order page shows
    }

    private array $admins = [];

    /** AuthenticateSession logs out when the user changes mid-test, so switching users starts a fresh session. */
    private function as(User $u): static
    {
        if (auth()->id() !== $u->id) $this->flushSession();
        return $this->actingAs($u);
    }

    private function admin(string $role): User
    {
        return $this->admins[$role] ??= $this->makeAdmin($role);
    }

    private function makeAdmin(string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => ucfirst($role) . ' Test', 'email' => $role . '-' . uniqid() . '@example.test', 'password' => bcrypt('x'),
            'is_admin' => 1, 'admin_role' => $role, 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        return User::findOrFail($id);
    }

    private function customer(array $x = []): object
    {
        $id = DB::table('users')->insertGetId($x + ['name' => 'Cust Test', 'email' => 'cust-' . uniqid() . '@example.test', 'password' => bcrypt('x'), 'is_admin' => 0,
            'wholesale_status' => 'none', 'wholesale_discount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('users')->find($id);
    }

    private function order(array $x = []): object
    {
        $id = DB::table('orders')->insertGetId($x + ['order_number' => 'T-' . uniqid(), 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'buyer-' . uniqid() . '@example.test', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US',
            'subtotal' => 100, 'shipping_total' => 0, 'discount_total' => 0, 'tax_total' => 0, 'refund_total' => 0, 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_staff_cannot_change_wholesale_pricing_but_manager_can(): void
    {
        $c = $this->customer();
        $base = ['name' => $c->name, 'email' => $c->email];
        // profile-only save works for staff
        $this->as($this->admin('staff'))->post("/admin/customers/{$c->id}", $base + ['phone' => '813-555-0100'])->assertRedirect();
        $this->assertSame('813-555-0100', DB::table('users')->where('id', $c->id)->value('phone'));
        // a discount / approval from staff is refused and nothing changes
        $this->as($this->admin('staff'))->post("/admin/customers/{$c->id}", $base + ['wholesale_status' => 'approved', 'wholesale_discount' => 90, 'net_terms' => 1, 'credit_limit' => 50000])->assertForbidden();
        $row = DB::table('users')->find($c->id);
        $this->assertSame('none', $row->wholesale_status);
        $this->assertEquals(0, (float) $row->wholesale_discount);
        // staff never see the inputs
        $this->as($this->admin('staff'))->get("/admin/customers/{$c->id}")->assertOk()->assertDontSee('name="wholesale_discount"', false);
        $this->as($this->admin('manager'))->get("/admin/customers/{$c->id}")->assertOk()->assertSee('name="wholesale_discount"', false);
        // a manager may
        $this->as($this->admin('manager'))->post("/admin/customers/{$c->id}", $base + ['wholesale_status' => 'pending', 'wholesale_discount' => 5, 'credit_limit' => ''])->assertRedirect();
        $this->assertEquals(5, (float) DB::table('users')->where('id', $c->id)->value('wholesale_discount'));
    }

    public function test_email_change_moves_orders_and_notes(): void
    {
        $c = $this->customer();
        $o = $this->order(['email' => $c->email, 'user_id' => $c->id]);
        DB::table('customer_notes')->insert(['email' => $c->email, 'author_id' => null, 'body' => 'note', 'created_at' => now()]);
        $new = 'moved-' . uniqid() . '@example.test';
        // staff can reset a password, so an e-mail change by staff would hand them the account (2026-11-14 review)
        $this->as($this->admin('staff'))->post("/admin/customers/{$c->id}", ['name' => $c->name, 'email' => $new])->assertForbidden();
        $this->assertSame($c->email, DB::table('users')->where('id', $c->id)->value('email'));
        $this->as($this->admin('manager'))->post("/admin/customers/{$c->id}", ['name' => $c->name, 'email' => $new])->assertRedirect();
        $this->assertSame($new, DB::table('orders')->where('id', $o->id)->value('email'));
        $this->assertSame(1, DB::table('customer_notes')->where('email', $new)->count());
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($c->email));   // the old address hears about it
    }

    public function test_staff_blocked_from_money_export_and_catalog_write_routes(): void
    {
        $staff = $this->admin('staff');
        $o = $this->order(['payment_method' => 'manual']);
        $this->as($staff)->post("/admin/orders/{$o->id}/refund", ['amount' => 10])->assertForbidden();
        $this->as($staff)->get('/admin/orders?export=csv')->assertForbidden();
        $this->as($staff)->get('/admin/customers?export=csv')->assertForbidden();
        $this->as($staff)->get('/admin/products?export=csv')->assertForbidden();
        $this->as($staff)->post('/admin/products/bulk', ['ids' => '1', 'bulk' => 'delete'])->assertForbidden();
        $this->as($staff)->post('/admin/products/import', [])->assertForbidden();
        $this->as($staff)->post('/admin/products/999999999/delete')->assertForbidden();
        $this->as($staff)->post("/admin/orders/{$o->id}/freight", [])->assertForbidden();
        $this->as($staff)->post('/admin/pti-dropship/999999999/place', [])->assertForbidden();
        // staff still work orders
        $this->as($staff)->get("/admin/orders/{$o->id}")->assertOk()->assertDontSee('data-modal="refund"', false);

        $owner = $this->admin('owner');
        $this->as($owner)->get('/admin/orders?export=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->as($owner)->get('/admin/customers?export=csv')->assertOk();
        $this->as($owner)->post("/admin/orders/{$o->id}/refund", ['amount' => 10])->assertRedirect();
        $this->assertEquals(10, (float) DB::table('orders')->where('id', $o->id)->value('refund_total'));
        // legacy is_admin with no role is still the owner
        $legacy = $this->makeAdmin('owner'); DB::table('users')->where('id', $legacy->id)->update(['admin_role' => null]);
        $this->as($legacy->fresh())->get('/admin/orders?export=csv')->assertOk();
    }

    public function test_net_terms_survive_a_status_save(): void
    {
        $o = $this->order(['payment_method' => 'net_terms', 'payment_status' => 'unpaid']);
        $this->as($this->admin('staff'))->post("/admin/orders/{$o->id}", ['status' => 'completed', 'payment_status' => 'unpaid', 'payment_method' => 'net_terms'])->assertRedirect()->assertSessionHasNoErrors();
        $row = DB::table('orders')->find($o->id);
        $this->assertSame('net_terms', $row->payment_method);
        $this->assertSame('completed', $row->status);
        // an unknown legacy method is shown and kept as-is
        $o2 = $this->order(['payment_method' => 'paypal_legacy']);
        $this->as($this->admin('staff'))->get("/admin/orders/{$o2->id}")->assertOk()->assertSee('value="paypal_legacy" selected', false);
        $this->as($this->admin('staff'))->post("/admin/orders/{$o2->id}", ['status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'paypal_legacy'])->assertSessionHasNoErrors();
        $this->assertSame('paypal_legacy', DB::table('orders')->where('id', $o2->id)->value('payment_method'));
        // refund states cannot be picked by hand
        $this->as($this->admin('owner'))->post("/admin/orders/{$o->id}", ['payment_status' => 'refunded'])->assertSessionHasErrors('payment_status');
    }

    public function test_bulk_price_rejects_zero(): void
    {
        $owner = $this->admin('owner');
        foreach ([['set', 0], ['set', ''], ['pct', -100], ['set', 'abc']] as [$mode, $v]) {
            $this->as($owner)->post('/admin/products/bulk', ['ids' => '1,2', 'bulk' => 'price', 'mode' => $mode, 'value' => $v])->assertSessionHasErrors('value');
        }
    }

    public function test_import_parses_money_and_rejects_zero_prices(): void
    {
        $this->assertSame('1250.00', ProductsController::csvMoney('$1,250.00'));
        $this->assertSame('12.50', ProductsController::csvMoney(' $12.50 '));
        $this->assertNull(ProductsController::csvMoney('call'));

        $sku = 'ZZTEST-' . strtoupper(substr(uniqid(), -6));
        $csv = "\xEF\xBB\xBFSKU,Name,Regular_Price,Status,extra\n{$sku},Hardening test part,\"\$12.50\",draft,x,overflow\n{$sku}-0,Zero part,\$0.00,draft\n{$sku}-N,Bad part,call us,draft\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);
        $res = $this->as($this->admin('owner'))->post('/admin/products/import', ['file' => $file]);
        $res->assertRedirect();
        $err = (string) session('admin_err');
        $this->assertStringContainsString("{$sku}-0", $err);
        $this->assertStringContainsString("{$sku}-N", $err);
        $pid = DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', $sku)->value('post_id');
        $this->assertNotNull($pid, 'the $12.50 row (with a BOM header and an extra column) is created');
        $this->assertEquals(12.5, (float) DB::connection('wp')->table('wp_postmeta')->where('post_id', $pid)->where('meta_key', '_regular_price')->value('meta_value'));
        $this->assertFalse(DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', "{$sku}-0")->exists());

        $noSku = UploadedFile::fake()->createWithContent('bad.csv', "Name,Price\nx,1\n");
        $this->as($this->admin('owner'))->post('/admin/products/import', ['file' => $noSku])->assertSessionHas('admin_err');
    }

    public function test_refund_sends_idempotency_key_and_adds_atomically(): void
    {
        config(['services.stripe.secret' => 'sk_test_hardening']);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 're_test', 'status' => 'succeeded'], 200)]);
        $o = $this->order(['stripe_payment_intent' => 'pi_test_123', 'refund_total' => 20]);
        $this->as($this->admin('owner'))->post("/admin/orders/{$o->id}/refund", ['amount' => 30])->assertRedirect()->assertSessionMissing('admin_err')->assertSessionHas('admin_ok');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.stripe.com/v1/refunds')
            && $req->hasHeader('Idempotency-Key', "refund-{$o->id}-" . sha1('30|20.00')) && $req['amount'] == 3000);
        $row = DB::table('orders')->find($o->id);
        $this->assertEquals(50, (float) $row->refund_total);
        $this->assertSame('partially_refunded', $row->payment_status);
        // more than what is left is refused, and Stripe is not called again
        $this->as($this->admin('owner'))->post("/admin/orders/{$o->id}/refund", ['amount' => 60])->assertSessionHas('admin_err');
        Http::assertSentCount(1);
    }

    public function test_voiding_the_shipping_label_reverts_the_order(): void
    {
        config(['services.easypost.enabled' => true, 'services.easypost.key' => 'EZTK_test']);
        $o = $this->order(['status' => 'completed', 'tracking_number' => '1ZTEST123', 'tracking_carrier' => 'UPS', 'tracking_service' => 'Ground', 'shipped_at' => now()]);
        $lid = DB::table('shipping_labels')->insertGetId(['order_id' => $o->id, 'kind' => 'outbound', 'provider' => 'easypost', 'carrier_code' => 'ups', 'service_code' => 'Ground',
            'tracking_number' => '1ZTEST123', 'cost' => 12.5, 'is_test' => 1, 'provider_shipment_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->as($this->admin('staff'))->post("/admin/labels/{$lid}/void")->assertRedirect()->assertSessionHas('admin_ok');
        $row = DB::table('orders')->find($o->id);
        $this->assertNull($row->tracking_number);
        $this->assertNull($row->shipped_at);
        $this->assertSame('processing', $row->status);
        $this->assertTrue(DB::table('order_events')->where('order_id', $o->id)->where('type', 'unshipped')->exists());
        $this->assertNotNull(DB::table('shipping_labels')->where('id', $lid)->value('voided_at'));
    }

    public function test_bulk_cancel_skips_paid_orders(): void
    {
        $paid = $this->order(['status' => 'processing', 'payment_status' => 'paid']);
        $unpaid = $this->order(['status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'payment_link']);
        $this->as($this->admin('staff'))->post('/admin/orders/bulk', ['ids' => "{$paid->id},{$unpaid->id}", 'bulk' => 'cancelled'])->assertSessionHas('admin_err');
        $this->assertSame('processing', DB::table('orders')->where('id', $paid->id)->value('status'));
        $this->assertSame('cancelled', DB::table('orders')->where('id', $unpaid->id)->value('status'));
        $this->as($this->admin('staff'))->post('/admin/orders/bulk', ['ids' => "{$paid->id}", 'bulk' => ''])->assertSessionHas('admin_err');
    }

    public function test_edited_admin_pages_render_for_owner_and_staff(): void
    {
        $o = $this->order(['payment_status' => 'partially_refunded', 'refund_total' => 10]);
        $pid = (int) DB::table('catalog_live')->value('wp_product_id');
        $cat = DB::connection('wp')->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'product_cat')->value('t.slug');
        $c = $this->customer(['wholesale_status' => 'approved', 'wholesale_discount' => 3]);
        foreach (['owner', 'staff'] as $role) {
            foreach (['/admin/products', "/admin/products/$pid", '/admin/products/new', '/admin/customers', '/admin/customers/new', "/admin/customers/{$c->id}", '/admin/inventory',
                '/admin/orders', '/admin/orders/new', "/admin/orders/{$o->id}", "/admin/categories/$cat"] as $url) {
                $this->as($this->admin($role))->get($url)->assertOk();
            }
        }
        $this->as($this->admin('owner'))->get("/admin/orders/{$o->id}")->assertSee('Partially refunded')->assertDontSee('<option value="refunded"', false);
        $this->as($this->admin('staff'))->get('/admin/products')->assertDontSee('Import CSV')->assertDontSee('name="bulk"', false);
        $this->as($this->admin('owner'))->get('/admin/products')->assertSee('Import CSV')->assertSee('Choose action…');
    }

    public function test_reports_survive_a_bad_custom_range(): void
    {
        $this->as($this->admin('owner'))->get('/admin/reports?range=custom&from=nonsense&to=2026-13-45')->assertOk();
        $this->as($this->admin('owner'))->get('/admin/')->assertOk();
    }
}
