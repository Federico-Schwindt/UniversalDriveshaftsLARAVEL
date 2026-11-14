<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ShipStationLabelService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Owner, 2026-11-14: staff can buy labels through ShipStation too, picked in Settings or per label, and Auto tries EasyPost
 * first and pays from the ShipStation balance when EasyPost has none. Both APIs are faked: nothing is bought.
 */
class ShipStationLabelsTest extends TestCase
{
    use DatabaseTransactions;

    private array $ssSent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Cache::forget('shipstation_carriers'); Cache::forget('ep:carriers');
        config(['services.shipstation.api_key' => 'ss-key', 'services.shipstation.api_secret' => 'ss-secret',
            'services.easypost.enabled' => true, 'services.easypost.key' => 'EZAK_live', 'services.easypost.test_key' => 'EZTK_test']);
        Http::preventStrayRequests();
        Http::fake([
            'ssapi.shipstation.com/carriers' => Http::response([['name' => 'UPS by ShipStation', 'code' => 'ups_walleted', 'balance' => 250.0, 'requiresFundedAccount' => true]]),
            'ssapi.shipstation.com/shipments/getrates' => Http::response([['serviceName' => 'UPS® Ground', 'serviceCode' => 'ups_ground', 'shipmentCost' => 11.5, 'otherCost' => 0.5],
                ['serviceName' => 'UPS Next Day Air®', 'serviceCode' => 'ups_next_day_air', 'shipmentCost' => 61.2, 'otherCost' => 0]]),
            'ssapi.shipstation.com/shipments/createlabel' => Http::response(['shipmentId' => 777001, 'shipmentCost' => 11.5, 'insuranceCost' => 0, 'trackingNumber' => '1ZSHIPSTATION01', 'labelData' => base64_encode('%PDF-1.4 test')]),
            'ssapi.shipstation.com/shipments/voidlabel' => Http::response(['approved' => true, 'message' => 'Label voided successfully']),
            'api.easypost.com/v2/carrier_accounts' => Http::response([['id' => 'ca_ups', 'type' => 'UpsDapAccount', 'readable' => 'UPS']]),
            'api.easypost.com/v2/shipments' => Http::response(['id' => 'shp_1', 'rates' => [['id' => 'rate_1', 'service' => 'Ground', 'carrier' => 'UPSDAP', 'carrier_account_id' => 'ca_ups', 'rate' => '10.40']]]),
            'api.easypost.com/v2/shipments/shp_1/buy' => Http::response(['error' => ['code' => 'PAYMENT.INSUFFICIENT_FUNDS', 'message' => 'Insufficient funds: your EasyPost balance is too low.']], 402),
        ]);
    }

    private function staff(): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Packer Test', 'email' => 'packer-' . uniqid() . '@example.test', 'password' => bcrypt('x'), 'is_admin' => 1, 'admin_role' => 'staff',
            'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        return User::findOrFail($id);
    }

    private function order(): object
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'UDS-SS-' . strtoupper(Str::random(6)), 'status' => 'processing', 'source' => 'web', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'buyer-' . uniqid() . '@example.test', 'phone' => '(813) 555-0142', 'billing_first_name' => 'Ana', 'billing_last_name' => 'Ruiz', 'billing_address_1' => '100 Main St',
            'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33602', 'billing_country' => 'US', 'ship_to_different' => 0, 'shipping_method' => 'upsdap-ground',
            'subtotal' => 100, 'shipping_total' => 12, 'discount_total' => 0, 'total' => 112, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    private function box(array $x = []): array
    {
        return $x + ['kind' => 'outbound', 'weight_lb' => 4, 'length_in' => 12, 'width_in' => 6, 'height_in' => 6, 'confirmation' => 'none', 'notify' => 0];
    }

    public function test_staff_buy_a_label_through_shipstation_when_it_is_chosen(): void
    {
        $o = $this->order();
        $this->actingAs($this->staff());
        $r = $this->postJson("/admin/orders/{$o->id}/labels/rates", $this->box(['mode' => 'shipstation']))->assertOk();
        $this->assertSame('shipstation', $r->json('provider'));
        $this->assertSame(['ups_ground', 'ups_next_day_air'], array_column($r->json('rates'), 'serviceCode'));
        $this->assertEquals(12.0, $r->json('rates.0.cost'), 'other costs (fuel) are part of the price');

        $this->post("/admin/orders/{$o->id}/labels", $this->box(['mode' => 'shipstation', 'provider' => 'shipstation', 'carrier_code' => 'ups_walleted', 'service_code' => 'ups_ground', 'service_name' => 'Ground']))
            ->assertRedirect()->assertSessionHas('admin_ok', fn ($m) => str_contains($m, 'through ShipStation'));
        $label = DB::table('shipping_labels')->where('order_id', $o->id)->first();
        $this->assertSame('shipstation', $label->provider);
        $this->assertSame('1ZSHIPSTATION01', $label->tracking_number);
        $this->assertSame('777001', $label->provider_shipment_id);
        $this->assertTrue(Storage::disk('local')->exists($label->pdf_path));
        $this->assertSame('UPS', DB::table('orders')->where('id', $o->id)->value('tracking_carrier'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/shipments/createlabel') && $req['testLabel'] === true && $req['shipTo']['postalCode'] === '33602'
            && $req['shipFrom']['postalCode'] === '33556' && $req['weight'] == ['value' => 64, 'units' => 'ounces']);

        $this->post("/admin/labels/{$label->id}/void")->assertRedirect()->assertSessionHas('admin_ok');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/shipments/voidlabel') && $req['shipmentId'] === 777001);
        $this->assertNotNull(DB::table('shipping_labels')->where('id', $label->id)->value('voided_at'));
    }

    public function test_auto_pays_from_shipstation_when_easypost_has_no_balance(): void
    {
        Settings::set('labels.provider', 'auto');
        $o = $this->order();
        $this->actingAs($this->staff());
        $r = $this->postJson("/admin/orders/{$o->id}/labels/rates", $this->box())->assertOk();
        $this->assertSame('easypost', $r->json('provider'), 'Auto prices with EasyPost first');

        $this->post("/admin/orders/{$o->id}/labels", $this->box(['provider' => 'easypost', 'carrier_code' => 'ups', 'service_code' => 'Ground']))
            ->assertRedirect()->assertSessionHas('admin_ok', fn ($m) => str_contains($m, 'EasyPost had no balance'));
        $label = DB::table('shipping_labels')->where('order_id', $o->id)->first();
        $this->assertSame('shipstation', $label->provider);
        $this->assertSame('ups_ground', $label->service_code, 'the same carrier and service the packer picked');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/shipments/createlabel') && $req['serviceCode'] === 'ups_ground');
    }

    public function test_easypost_only_does_not_fall_back(): void
    {
        Settings::set('labels.provider', 'easypost');
        $o = $this->order();
        $this->actingAs($this->staff());
        $this->post("/admin/orders/{$o->id}/labels", $this->box(['provider' => 'easypost', 'carrier_code' => 'ups', 'service_code' => 'Ground']))
            ->assertRedirect()->assertSessionHas('admin_err', fn ($m) => str_contains($m, 'Insufficient funds'));
        $this->assertSame(0, DB::table('shipping_labels')->where('order_id', $o->id)->count());
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/shipments/createlabel'));
    }

    public function test_services_match_across_the_two_accounts(): void
    {
        $this->assertSame('usps', ShipStationLabelService::family('stamps_com'));
        $this->assertSame('usps', ShipStationLabelService::family('usps'), '"usps" is not UPS');
        $this->assertSame('ups', ShipStationLabelService::family('ups_walleted'));
        foreach ([['ups', 'Ground', 'UPS® Ground'], ['ups', 'Next Day Air', 'UPS Next Day Air®'], ['usps', 'Ground Advantage', 'USPS Ground Advantage'],
            ['usps', 'Priority', 'USPS Priority Mail'], ['usps', 'Express', 'USPS Priority Mail Express'], ['fedex', 'Ground Home Delivery', 'FedEx Home Delivery®'], ['fedex', '2 Day', 'FedEx 2Day®']] as [$f, $ep, $ss]) {
            $this->assertSame(ShipStationLabelService::serviceKey($f, $ss), ShipStationLabelService::serviceKey($f, $ep), "$ep = $ss");
        }
        $this->assertNotSame(ShipStationLabelService::serviceKey('ups', 'Ground'), ShipStationLabelService::serviceKey('ups', 'UPS Ground Saver®'));
    }
}
