<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use App\Support\SalesTax;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owner, 2026-11-13: "make the website charge Florida tax automatically, only to Florida customers, and skip it for
 * shops that send us their resale certificate". 6% + the delivery county's surtax (DR-15DSS 2026), pickups at the
 * shop's county (Pasco, 7%), nothing outside Florida, nothing for an approved certificate.
 */
class FloridaSalesTaxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(fn () => Http::response([], 200));
        config(['services.stripe.enabled' => false, 'services.stripe.secret' => null, 'services.brevo.key' => null]);
        Settings::set('tax.enabled', true);
        Settings::set('tax.shipping', false);
    }

    protected function tearDown(): void
    {
        Settings::set('tax.shipping', false);   // empties the in-memory copy, so the next test reads the rolled-back table
        parent::tearDown();
    }

    public function test_zip_codes_land_in_their_county_and_rates_follow_the_2026_table(): void
    {
        $this->assertSame('Pasco', SalesTax::county('34655'));
        $this->assertSame('Hillsborough', SalesTax::county('33602-1234'));
        $this->assertSame('Miami-Dade', SalesTax::county('33101'));
        $this->assertSame('Hillsborough', SalesTax::county('33601'), 'a P.O. box ZIP takes its neighbours\' county');
        $this->assertNull(SalesTax::county('90210'));
        $this->assertCount(67, SalesTax::data()['surtax']);
        $this->assertSame(1.0, SalesTax::surtax('Pasco'));
        $this->assertSame(1.5, SalesTax::surtax('Hillsborough'));
        $this->assertSame(0.0, SalesTax::surtax('Collier'));
        $this->assertSame(2.0, SalesTax::surtax('Hamilton'));
    }

    public function test_only_florida_deliveries_and_pickups_are_taxed(): void
    {
        $this->assertSame(7.0, SalesTax::profile('US', 'CA', '90210', true)['rate'], 'a pickup pays the shop\'s county wherever the buyer lives');
        $this->assertSame('Pasco', SalesTax::profile('US', 'CA', '90210', true)['county']);
        $this->assertSame(7.5, SalesTax::profile('US', 'FL', '33602', false)['rate']);
        $this->assertNull(SalesTax::profile('US', 'CA', '90210', false));
        $this->assertNull(SalesTax::profile('MX', '', '33602', false));
        $this->assertSame(6.0, SalesTax::profile('US', 'FL', '', false)['rate'], 'a Florida address without a known ZIP still pays the state rate');
        Settings::set('tax.enabled', false);
        $this->assertNull(SalesTax::profile('US', 'FL', '33602', false));
    }

    public function test_the_amount_follows_the_discount_and_the_5000_surtax_cap(): void
    {
        $p = SalesTax::profile('US', 'FL', '34655', false);
        $this->assertSame(12.6, SalesTax::amount($p, [['price' => 100, 'qty' => 2]], 20, 35));
        // the county surtax counts the first $5,000 of each piece: 6% of 6,000 + 1% of 5,000
        $this->assertSame(410.0, SalesTax::amount($p, [['price' => 6000, 'qty' => 1]]));
        $this->assertSame(0.0, SalesTax::amount(null, [['price' => 100, 'qty' => 1]]));
        Settings::set('tax.shipping', true);
        $this->assertSame(15.05, SalesTax::amount(SalesTax::profile('US', 'FL', '34655', false), [['price' => 100, 'qty' => 2]], 20, 35));
    }

    public function test_an_approved_certificate_makes_the_account_exempt_until_it_expires(): void
    {
        $u = (object) ['tax_exempt_status' => 'approved', 'tax_cert_expires' => now()->addMonth()->toDateString()];
        $this->assertTrue(SalesTax::profile('US', 'FL', '33602', false, $u)['exempt']);
        $this->assertSame(0.0, SalesTax::amount(SalesTax::profile('US', 'FL', '33602', false, $u), [['price' => 100, 'qty' => 1]]));
        $u->tax_cert_expires = now()->subDay()->toDateString();
        $this->assertFalse(SalesTax::profile('US', 'FL', '33602', false, $u)['exempt']);
        $u->tax_exempt_status = 'pending'; $u->tax_cert_expires = null;
        $this->assertFalse(SalesTax::profile('US', 'FL', '33602', false, $u)['exempt']);
    }

    private function fillCart(): float
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('price', '<', 1000)->where('in_stock', 1)->value('wp_product_id');
        $this->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk()->assertJsonPath('success', true);
        return app(CartService::class)->subtotal();
    }

    private function place(array $extra = [])
    {
        return $this->postJson('/checkout/place', $extra + ['email' => 'tax-' . uniqid() . '@example.com', 'phone' => '(813) 555-0142',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'billing_state' => 'FL',   // pickup is Florida-only
            'shipping_method' => 'local_pickup', 'pickup_ack' => 1, 'agree_terms' => 1, 'payment_method' => 'card']);
    }

    public function test_a_pickup_order_carries_pasco_tax_in_its_total(): void
    {
        $sub = $this->fillCart();
        $email = 'tax-' . uniqid() . '@example.com';
        $tax = round($sub * 0.07 + 1e-9, 2);
        // the page's total without tax is refused with the taxed numbers
        $this->place(['email' => $email, 'expected_total' => (int) round($sub * 100)])->assertStatus(409)->assertJsonPath('totals.tax', $tax);
        $this->place(['email' => $email, 'expected_total' => (int) round(($sub + $tax) * 100)])->assertOk();
        $o = DB::table('orders')->where('email', $email)->first();
        $this->assertEquals($tax, (float) $o->tax_total);
        $this->assertEquals(round($sub + $tax, 2), (float) $o->total);
        $this->assertSame('Pasco', $o->tax_county);
        $this->assertEquals(7.0, (float) $o->tax_rate);
        $this->assertEquals($sub, (float) $o->taxable_total);
        $this->assertNull($o->tax_exempt_cert);
    }

    public function test_an_exempt_account_pays_no_tax_and_the_order_keeps_its_certificate(): void
    {
        $u = User::query()->create(['name' => 'Shop Owner', 'email' => 'resale-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['tax_exempt_status' => 'approved', 'tax_cert_kind' => 'resale', 'tax_cert_number' => '78-8012345678-9', 'tax_cert_expires' => now()->endOfYear()->toDateString()]);
        $this->actingAs($u->fresh());
        $sub = $this->fillCart();
        $this->place(['email' => $u->email, 'expected_total' => (int) round($sub * 100)])->assertOk();
        $o = DB::table('orders')->where('email', $u->email)->first();
        $this->assertEquals(0, (float) $o->tax_total);
        $this->assertSame('78-8012345678-9', $o->tax_exempt_cert);
        $this->assertSame('Pasco', $o->tax_county);
    }

    public function test_the_checkout_learns_how_a_delivery_is_taxed_with_its_rates(): void
    {
        $this->fillCart();
        $this->postJson('/checkout/shipping-options', ['country' => 'US', 'state' => 'FL', 'zip' => '33602'])->assertOk()
            ->assertJsonPath('tax.county', 'Hillsborough')->assertJsonPath('tax.rate', 7.5);
        $this->postJson('/checkout/shipping-options', ['country' => 'US', 'state' => 'TX', 'zip' => '75001'])->assertOk()->assertJsonPath('tax', null);
        $this->get('/checkout')->assertOk()->assertSee('id="tax-row"', false);
    }

    public function test_a_customer_sends_a_certificate_for_review(): void
    {
        Storage::fake('local');
        $u = User::query()->create(['name' => 'Shop Owner', 'email' => 'cert-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        // the overview lists every account section, the certificate upload among them
        $this->actingAs($u)->get('/account')->assertOk()->assertSee('href="/account/settings#tax"', false)->assertSee('Sales tax certificate')
            ->assertSee('href="/account/buy-again"', false)->assertSee('href="/rma"', false);
        $this->actingAs($u)->get('/account/settings')->assertOk()->assertSee('Upload sales certificate')->assertSee('All account sections');
        $this->actingAs($u)->post('/account/tax-certificate', ['tax_cert_kind' => 'resale', 'tax_cert_number' => '78-8012345678-9',
            'tax_cert_file' => UploadedFile::fake()->create('dr13.pdf', 120, 'application/pdf')])->assertRedirect();
        $row = DB::table('users')->find($u->id);
        $this->assertSame('pending', $row->tax_exempt_status);
        Storage::disk('local')->assertExists($row->tax_cert_file);
        // pending is not exempt: the shop checks the certificate first
        $this->assertFalse(SalesTax::profile('US', 'FL', '33602', false, $row)['exempt']);
        $this->actingAs($u->fresh())->get('/account/settings')->assertOk()->assertSee('Certificate under review');
    }

    public function test_a_manager_approves_the_certificate_and_staff_only_see_it(): void
    {
        $u = User::query()->create(['name' => 'Shop Owner', 'email' => 'cert-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['tax_exempt_status' => 'pending', 'tax_cert_number' => '78-8012345678-9']);
        $staff = function (string $role) {
            $a = User::query()->create(['name' => 'Staff ' . $role, 'email' => 'adm-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
            DB::table('users')->where('id', $a->id)->update(['is_admin' => 1, 'admin_role' => $role, 'email_verified_at' => now()]);
            return $a->fresh();
        };
        $form = ['tax_exempt_status' => 'approved', 'tax_cert_kind' => 'resale', 'tax_cert_number' => '78-8012345678-9', 'tax_cert_expires' => now()->endOfYear()->toDateString()];
        $clerk = $staff('staff');
        $this->actingAs($clerk)->get('/admin/tax-exemptions')->assertOk()->assertSee('78-8012345678-9')->assertSee('Collected by month');
        $this->actingAs($clerk)->get('/admin/customers/' . $u->id)->assertOk()->assertSee('a manager approves exemptions');
        $this->actingAs($clerk)->post('/admin/customers/' . $u->id . '/tax', $form)->assertForbidden();
        $this->flushSession();
        $this->actingAs($staff('manager'))->post('/admin/customers/' . $u->id . '/tax', $form)->assertRedirect();
        $row = DB::table('users')->find($u->id);
        $this->assertSame('approved', $row->tax_exempt_status);
        $this->assertNotNull($row->tax_cert_reviewed_at);
        $this->assertTrue(SalesTax::profile('US', 'FL', '33602', false, $row)['exempt']);
    }
}
