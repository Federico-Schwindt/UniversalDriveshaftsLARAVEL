<?php

namespace Tests\Feature;

use App\Mail\Branded;
use App\Services\CheckinBridge;
use App\Services\SmsService;
use App\Support\OrderEvents;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Online-builder orders become work orders on the shop's station (uds-checkin), here an in-memory stand-in. */
class CheckinBridgeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        config(['database.connections.checkin' => ['driver' => 'sqlite', 'url' => 'sqlite:///:memory:', 'prefix' => '']]);
        DB::purge('checkin');
        $ck = DB::connection('checkin');
        // the columns the station's own check-in form fills (src/db/schema.ts), with its defaults
        $ck->statement('CREATE TABLE locations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT)');
        $ck->statement("CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT DEFAULT '', last_name TEXT DEFAULT '', phone TEXT DEFAULT '', company_name TEXT DEFAULT '', company_phone TEXT DEFAULT '', email TEXT DEFAULT '', street TEXT DEFAULT '', address2 TEXT DEFAULT '', city TEXT DEFAULT '', state TEXT DEFAULT '', zip TEXT DEFAULT '', country TEXT DEFAULT 'United States', sms_consent BOOLEAN DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $ck->statement("CREATE TABLE entries (id INTEGER PRIMARY KEY AUTOINCREMENT, location_id INTEGER, customer_id INTEGER, notes TEXT DEFAULT '', labor_request TEXT DEFAULT '', driveshaft_pieces TEXT DEFAULT '', driveshaft_material TEXT DEFAULT '', designer_type TEXT DEFAULT '', measurement_a TEXT DEFAULT '', measurement_b TEXT DEFAULT '', measurement_c TEXT DEFAULT '', total NUMERIC, job_number TEXT DEFAULT '', job_status TEXT DEFAULT 'New', payment TEXT DEFAULT '', custom TEXT, created_by_user_id INTEGER, created_by_name TEXT DEFAULT '', deleted_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $ck->statement("CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, sku TEXT DEFAULT '', name TEXT DEFAULT '', qty INTEGER DEFAULT 1, price NUMERIC)");
        $ck->statement("CREATE TABLE status_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, from_status TEXT DEFAULT '', to_status TEXT DEFAULT '', note TEXT DEFAULT '', changed_by TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $ck->statement("CREATE TABLE activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT, entity TEXT DEFAULT '', entity_id INTEGER, summary TEXT DEFAULT '', details TEXT, actor TEXT DEFAULT '', ip TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $ck->table('locations')->insert([['name' => 'Odessa', 'slug' => 'odessa'], ['name' => 'Zephyrhills', 'slug' => 'zephyrhills']]);
    }

    private function order(array $lines, array $x = []): object
    {
        $id = DB::table('orders')->insertGetId($x + ['order_number' => 'UDS-T' . strtoupper(uniqid()), 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'buyer-' . uniqid() . '@example.test', 'phone' => '+1 (813) 555-0142', 'sms_opt_in' => 0, 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan',
            'billing_address_1' => '12 Test St', 'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33601', 'billing_country' => 'US',
            'shipping_label' => 'UPS Ground', 'subtotal' => 900, 'shipping_total' => 45, 'discount_total' => 0, 'tax_total' => 0, 'refund_total' => 0, 'total' => 945,
            'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as $l) {
            DB::table('order_items')->insert($l + ['order_id' => $id, 'wp_product_id' => null, 'sku' => null, 'qty' => 1, 'line_total' => $l['price'] * ($l['qty'] ?? 1), 'created_at' => now(), 'updated_at' => now()]);
        }
        return DB::table('orders')->find($id);
    }

    private function custom(array $cfg = []): array
    {
        $cfg += ['pieces' => 'one', 'build_mode' => 'custom', 'duty_key' => 'light_medium', 'duty_label' => 'Light / Medium Duty', 'series_key' => '1350', 'series_label' => '1350 Series',
            'material' => 'steel', 'price_tier' => 'certified', 'front_app' => 'slip_flange', 'front_app_label' => 'Slip Yoke with Flange Yoke', 'rear_app' => 'flange', 'rear_app_label' => 'Flange Yoke',
            'length' => 0, 'a1' => 52.25, 'b1' => 0, 'c1' => 4.5, 'flange_yoke' => ['kind' => 'none', 'product_id' => 0], 'trans_yoke' => ['kind' => 'none', 'product_id' => 0], 'notes' => 'Lowered 2 in.'];
        return ['type' => 'custom_build', 'name' => 'Custom Driveshaft — ' . $cfg['series_label'], 'price' => 900, 'meta' => json_encode(['config' => $cfg, 'summary' => 'x'])];
    }

    public function test_a_committed_builder_order_becomes_one_silent_work_order(): void
    {
        $order = $this->order([$this->custom(), ['type' => 'product', 'name' => 'U-joint', 'sku' => 'U5-1310X', 'price' => 30]]);
        PayLink::placedNotices($order);
        $this->assertSame(0, DB::connection('checkin')->table('entries')->count(), 'sent after the response, not during the checkout');
        app()->terminate();

        $ck = DB::connection('checkin');
        $e = $ck->table('entries')->sole();
        $this->assertSame($order->order_number, $e->job_number);
        $this->assertSame(['Custom Made Driveshaft', '1 Piece', 'Steel', 'One Piece', '52.25"', '', '4.5"', 'Paid', 'New', 'Online store', 1],
            [$e->labor_request, $e->driveshaft_pieces, $e->driveshaft_material, $e->designer_type, $e->measurement_a, $e->measurement_b, $e->measurement_c, $e->payment, $e->job_status, $e->created_by_name, (int) $e->location_id]);
        $this->assertEquals(900, $e->total);
        $this->assertStringContainsString('ONLINE ORDER ' . $order->order_number, $e->notes);
        $this->assertStringContainsString('Front end: Slip Yoke with Flange Yoke', $e->notes);
        $this->assertStringContainsString('Customer notes: Lowered 2 in.', $e->notes);
        $info = json_decode($e->custom, true);
        $this->assertSame([$order->order_number, $order->email, 'Paid online', 'UPS Ground', '$945.00'], [$info['Online order'], $info['Customer e-mail'], $info['Payment'], $info['Delivery'], $info['Order total']]);
        $this->assertStringContainsString('12 Test St, Tampa, FL 33601', $info['Ship to']);

        // the station never contacts a web customer: no e-mail, no SMS consent on its customer row
        $c = $ck->table('customers')->find($e->customer_id);
        $this->assertSame(['Alex', 'Morgan', '8135550142', '', 0], [$c->first_name, $c->last_name, $c->phone, $c->email, (int) $c->sms_consent]);
        $this->assertSame(1, $ck->table('parts')->where('entry_id', $e->id)->count(), 'only the builder line is a job');
        $this->assertSame('New', $ck->table('status_history')->where('entry_id', $e->id)->value('to_status'));
        $this->assertStringContainsString("(Job {$order->order_number})", $ck->table('activity_log')->where('entity_id', $e->id)->value('summary'));
        $this->assertTrue(OrderEvents::has($order, 'checkin:sent'));

        // a retry (or a second trigger) never makes a second work order
        $this->assertSame([$order->order_number => (int) $e->id], CheckinBridge::send((int) $order->id));
        $this->assertSame(1, $ck->table('entries')->count());
    }

    public function test_two_shafts_get_numbered_jobs_and_unpaid_pickup_is_flagged(): void
    {
        $premade = ['type' => 'premade_build', 'name' => 'Pre-Made 1310 Shaft', 'sku' => 'PM-1310', 'price' => 400, 'qty' => 2,
            'meta' => json_encode(['config' => ['pieces' => 'one', 'build_mode' => 'premade', 'premade_product_id' => 0, 'premade_a' => '48 1/2', 'premade_b' => '', 'premade_extras' => []]])];
        $order = $this->order([$this->custom(['pieces' => 'two']), $premade], ['payment_status' => 'unpaid', 'payment_method' => 'pay_at_pickup', 'shipping_method' => \App\Services\CartService::PICKUP_ID]);
        $jobs = CheckinBridge::send((int) $order->id);

        $this->assertSame([$order->order_number . '-1', $order->order_number . '-2'], array_keys($jobs));
        $ck = DB::connection('checkin');
        [$a, $b] = [$ck->table('entries')->find($jobs[$order->order_number . '-1']), $ck->table('entries')->find($jobs[$order->order_number . '-2'])];
        $this->assertSame(['2 Pieces', 'Two Piece', 'Unpaid'], [$a->driveshaft_pieces, $a->designer_type, $a->payment]);
        $this->assertSame(['New Driveshaft', '48 1/2"', '800.00'], [$b->labor_request, $b->measurement_a, number_format((float) $b->total, 2, '.', '')]);
        $info = json_decode($b->custom, true);
        $this->assertSame(['Local pickup', 'Pays at pickup', '2'], [$info['Delivery'], $info['Payment'], $info['Quantity']]);
        $this->assertArrayNotHasKey('Ship to', $info);
        $this->assertSame(2, $ck->table('parts')->where('entry_id', $b->id)->value('qty'));
        $this->assertSame(1, $ck->table('customers')->count(), 'one customer row for the order');
    }

    public function test_walk_in_customer_is_not_borrowed_but_a_returning_web_customer_is(): void
    {
        $ck = DB::connection('checkin');
        $walkIn = $ck->table('customers')->insertGetId(['first_name' => 'Alex', 'last_name' => 'Morgan', 'phone' => '8135550142', 'email' => 'alex@example.test', 'sms_consent' => 1]);
        $first = CheckinBridge::send((int) $this->order([$this->custom()])->id);
        $c1 = $ck->table('entries')->find(reset($first))->customer_id;
        $this->assertNotEquals($walkIn, $c1);

        $again = CheckinBridge::send((int) $this->order([$this->custom()], ['billing_city' => 'Odessa'])->id);
        $c2 = $ck->table('entries')->find(reset($again))->customer_id;
        $this->assertEquals($c1, $c2);
        $this->assertSame('Odessa', $ck->table('customers')->where('id', $c2)->value('city'));
        $this->assertSame(2, $ck->table('customers')->count());
    }

    public function test_nothing_goes_out_without_a_builder_shaft_or_without_the_station(): void
    {
        $plain = $this->order([['type' => 'product', 'name' => 'U-joint', 'sku' => 'U5-1310X', 'price' => 30]]);
        PayLink::placedNotices($plain);
        app()->terminate();
        $this->assertFalse(OrderEvents::has($plain, 'checkin:queued'));

        config(['database.connections.checkin.url' => null]);
        $built = $this->order([$this->custom()]);
        PayLink::placedNotices($built);
        app()->terminate();
        $this->assertFalse(OrderEvents::has($built, 'checkin:queued'));
        $this->assertSame(0, DB::connection('checkin')->table('entries')->count());
        $this->artisan('checkin:push')->expectsOutputToContain('not configured')->assertSuccessful();
    }

    public function test_station_progress_reaches_the_customer_in_the_stores_words(): void
    {
        Mail::fake();
        $sms = $this->spy(SmsService::class);
        $ck = DB::connection('checkin');
        $order = $this->order([$this->custom()], ['sms_opt_in' => 1]);
        $job = array_key_first(CheckinBridge::send((int) $order->id));
        $this->assertSame([0, 0], CheckinBridge::follow(), 'a new work order says nothing');

        $ck->table('entries')->where('job_number', $job)->update(['job_status' => 'Ongoing']);
        $this->assertSame([1, 0], CheckinBridge::follow());
        $this->assertSame([0, 0], CheckinBridge::follow(), 'once');
        Mail::assertSent(Branded::class, fn (Branded $m) => $m->hasTo($order->email) && str_contains($m->subjectLine, 'We started building your driveshaft'));
        $sms->shouldHaveReceived('send')->once()->withArgs(fn ($to, $body) => str_contains($body, 'we started building your driveshaft') && !str_contains($body, 'pickup'));

        $ck->table('entries')->where('job_number', $job)->update(['job_status' => 'Completed']);
        $this->assertSame([0, 1], CheckinBridge::follow());
        Mail::assertSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'Your driveshaft is built'));
        Mail::assertNotSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'ready for pickup'));
        $sms->shouldHaveReceived('send')->withArgs(fn ($to, $body) => str_contains($body, 'It ships soon'));
        $this->assertTrue(OrderEvents::has($order, 'build:done'));
        $this->assertSame([0, 0], CheckinBridge::follow());
    }

    public function test_a_pickup_build_finished_is_the_usual_ready_for_pickup_and_unfinished_multi_shaft_waits(): void
    {
        Mail::fake();
        $this->spy(SmsService::class);
        $ck = DB::connection('checkin');
        $order = $this->order([$this->custom(), $this->custom(['pieces' => 'two'])], ['shipping_method' => \App\Services\CartService::PICKUP_ID]);
        [$one, $two] = array_keys(CheckinBridge::send((int) $order->id));

        $ck->table('entries')->where('job_number', $one)->update(['job_status' => 'Completed']);
        $this->assertSame([1, 0], CheckinBridge::follow(), 'one of two shafts done: started, not finished');
        $ck->table('entries')->where('job_number', $two)->update(['job_status' => 'Completed']);
        $this->assertSame([0, 1], CheckinBridge::follow());
        Mail::assertSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, "Order {$order->order_number} is ready for pickup"));
        Mail::assertNotSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'Your driveshaft is built'));
        $this->assertTrue(OrderEvents::has($order, 'notify:email:ready'), 'the admin marking it completed later sends nothing twice');
    }

    public function test_a_failed_send_is_recorded_and_retried_by_the_command(): void
    {
        $order = $this->order([$this->custom()]);
        DB::connection('checkin')->statement('ALTER TABLE entries RENAME TO entries_away');
        PayLink::placedNotices($order);
        app()->terminate();
        $this->assertTrue(OrderEvents::has($order, 'checkin:failed'));
        $this->assertFalse(OrderEvents::has($order, 'checkin:sent'));

        DB::connection('checkin')->statement('ALTER TABLE entries_away RENAME TO entries');
        DB::table('order_events')->where('order_id', $order->id)->where('type', 'checkin:queued')->update(['created_at' => now()->subMinutes(10)]);
        $this->artisan('checkin:push')->expectsOutputToContain('1 of 1 order(s) sent')->assertSuccessful();
        $this->assertSame($order->order_number, DB::connection('checkin')->table('entries')->value('job_number'));
    }
}
