<?php

namespace Tests\Feature;

use App\Http\Controllers\ShipStationController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Owner, 2026-11-14: orders go to ShipStation too (balance left there, used before EasyPost). ShipStation's Custom
 * Store pulls the orders to ship and reports the labels bought there (App\Http\Controllers\ShipStationController).
 */
class ShipStationStoreTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/shipstation/custom-store';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(fn () => Http::response([], 200));
        config(['services.shipstation.store_user' => 'ss-test', 'services.shipstation.store_password' => 'pw-' . str_repeat('x', 20), 'services.shipstation.since' => '2026-11-13 21:13:00']);
    }

    private function auth(): array
    {
        return ['PHP_AUTH_USER' => 'ss-test', 'PHP_AUTH_PW' => 'pw-' . str_repeat('x', 20)];
    }

    private function order(array $over = []): object
    {
        $n = 'UDS-SS-' . strtoupper(substr(uniqid(), -6)) . random_int(10, 99);
        $id = DB::table('orders')->insertGetId($over + [
            'order_number' => $n, 'status' => 'processing', 'source' => 'web', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'ss-' . uniqid() . '@example.com', 'phone' => '+1 813-555-0142',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_address_1' => '100 Main St', 'billing_city' => 'Tampa',
            'billing_state' => 'FL', 'billing_postcode' => '33602', 'billing_country' => 'US', 'ship_to_different' => 0,
            'shipping_method' => 'upsdap-ground', 'shipping_label' => 'UPS Ground', 'subtotal' => 34.42, 'shipping_total' => 12.63,
            'discount_total' => 0, 'tax_total' => 2.41, 'total' => 49.46, 'created_at' => '2026-11-14 00:37:48', 'updated_at' => '2026-11-14 00:41:05',
        ]);
        DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'name' => 'Transmission Yoke 1350', 'sku' => '3-3-4281X', 'price' => 34.42, 'qty' => 1, 'line_total' => 34.42, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    private function export(string $from = '11/13/2026 21:00', string $to = '11/14/2026 23:59')
    {
        return $this->withServerVariables($this->auth())->get(self::URL . '?action=export&start_date=' . urlencode($from) . '&end_date=' . urlencode($to) . '&page=1');
    }

    public function test_it_needs_the_store_login(): void
    {
        $this->get(self::URL . '?action=export')->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->withServerVariables(['PHP_AUTH_USER' => 'ss-test', 'PHP_AUTH_PW' => 'wrong'])->get(self::URL . '?action=export')->assertStatus(401);
        config(['services.shipstation.store_password' => '']);
        $this->withServerVariables($this->auth())->get(self::URL . '?action=export')->assertStatus(503);
    }

    public function test_only_orders_that_ship_from_this_site_are_exported(): void
    {
        $paid = $this->order();
        $terms = $this->order(['payment_status' => 'unpaid', 'payment_method' => 'net_terms', 'ship_to_different' => 1,
            'shipping_first_name' => 'Dock', 'shipping_last_name' => 'Two', 'shipping_address_1' => '9 Port Rd', 'shipping_city' => 'Houston',
            'shipping_state' => 'TX', 'shipping_postcode' => '77002', 'shipping_country' => 'US', 'po_number' => 'PO-77']);
        $pickup = $this->order(['shipping_method' => 'local_pickup']);
        $old = $this->order(['source' => 'woocommerce']);
        $unpaid = $this->order(['payment_status' => 'unpaid']);
        $before = $this->order(['created_at' => '2026-11-12 10:00:00']);

        $xml = simplexml_load_string($this->export()->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent());
        $numbers = array_map('strval', iterator_to_array($xml->xpath('//Order/OrderNumber'), false));
        $this->assertContains($paid->order_number, $numbers);
        $this->assertContains($terms->order_number, $numbers);
        foreach ([$pickup, $old, $unpaid, $before] as $o) $this->assertNotContains($o->order_number, $numbers);

        $t = $xml->xpath("//Order[OrderNumber='{$terms->order_number}']")[0];
        $this->assertSame('paid', (string) $t->OrderStatus);
        $this->assertSame('Houston', (string) $t->Customer->ShipTo->City, 'the ship-to address, not the billing one');
        $this->assertSame('PO-77', (string) $t->CustomField1);
        $this->assertSame('11/14/2026 00:37', (string) $t->OrderDate);
        $this->assertSame('3-3-4281X', (string) $t->Items->Item->SKU);
        $this->assertNotNull(DB::table('orders')->where('id', $paid->id)->value('shipstation_pushed_at'));
    }

    public function test_an_exported_order_keeps_coming_with_its_new_status_and_the_window_is_respected(): void
    {
        $o = $this->order();
        $this->export()->assertOk();
        DB::table('orders')->where('id', $o->id)->update(['status' => 'cancelled', 'payment_status' => 'refunded', 'updated_at' => '2026-11-14 02:00:00']);
        $xml = simplexml_load_string($this->export()->getContent());
        $this->assertSame('cancelled', (string) $xml->xpath("//Order[OrderNumber='{$o->order_number}']")[0]->OrderStatus);
        // changed outside the asked window: not in this answer
        $xml = simplexml_load_string($this->export('11/14/2026 03:00', '11/14/2026 04:00')->getContent());
        $this->assertEmpty($xml->xpath("//Order[OrderNumber='{$o->order_number}']"));
        $this->withServerVariables($this->auth())->get(self::URL . '?action=export')->assertStatus(400);
    }

    public function test_a_label_bought_in_shipstation_ships_the_order_and_tells_the_customer_once(): void
    {
        $o = $this->order();
        $body = "<?xml version=\"1.0\" encoding=\"utf-8\"?><ShipNotice><OrderNumber>{$o->order_number}</OrderNumber><OrderID>{$o->id}</OrderID><Carrier>UPS</Carrier><Service>UPS Ground</Service><TrackingNumber>1Z999AA10123456784</TrackingNumber></ShipNotice>";
        $url = self::URL . '?action=shipnotify&order_number=' . urlencode($o->order_number) . '&carrier=ups_walleted&service=UPS%20Ground&tracking_number=1Z999AA10123456784';
        $this->withServerVariables($this->auth())->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/xml'], $body)->assertOk();
        $row = DB::table('orders')->find($o->id);
        $this->assertSame('1Z999AA10123456784', $row->tracking_number);
        $this->assertSame('UPS', $row->tracking_carrier);
        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->shipped_at);
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'notify:email:shipped')->count());
        // ShipStation repeats the notice: no second e-mail
        $this->withServerVariables($this->auth())->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/xml'], $body)->assertOk();
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'notify:email:shipped')->count());
        $this->assertSame('shipped', ShipStationController::status(DB::table('orders')->find($o->id)));
        $this->withServerVariables($this->auth())->post(self::URL . '?action=shipnotify&order_number=NOPE&carrier=UPS&tracking_number=1Z1')->assertStatus(404);
    }

    public function test_carrier_names_become_the_ones_tracking_links_know(): void
    {
        $this->assertSame('USPS', ShipStationController::carrier('stamps_com'));
        $this->assertSame('USPS', ShipStationController::carrier('USPS'));
        $this->assertSame('UPS', ShipStationController::carrier('ups_walleted'));
        $this->assertSame('FedEx', ShipStationController::carrier('fedex'));
        $this->assertSame('DHL', ShipStationController::carrier('dhl_express_worldwide'));
    }
}
