<?php

namespace Tests\Feature;

use App\Mail\Branded;
use App\Services\CheckinBridge;
use App\Services\SmsService;
use App\Support\OrderEvents;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Custom builder orders become work orders on the shop's station through its signed integration API (uds-checkin
 * src/lib/integration.ts, tested there against Postgres). Here the station is a stand-in that checks the signature
 * the same way and answers with the same shapes.
 */
class CheckinBridgeTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'test-secret-0123456789-abcdefghijklmnop';
    /** @var array<string,array> job => work order */
    private array $wo = [];
    /** @var array<int,array> */
    private array $calls = [];
    private bool $down = false;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.checkin' => ['url' => 'https://station.test', 'secret' => self::SECRET, 'timeout' => 5]]);
        Http::fake(['station.test/*' => fn (Request $r) => $this->station($r), '*' => Http::response([], 200)]);
    }

    /** The station's side of the protocol: signature check, then create once / update / statuses. */
    private function station(Request $r)
    {
        if ($this->down) return Http::response(['error' => 'Service unavailable'], 503);
        $u = parse_url($r->url()); $path = $u['path'] . (isset($u['query']) ? '?' . $u['query'] : '');
        $ts = $r->header('X-UDS-Timestamp')[0] ?? '';
        $want = 'sha256=' . hash_hmac('sha256', "{$ts}.{$r->method()}.{$path}.{$r->body()}", self::SECRET);
        if (!hash_equals($want, $r->header('X-UDS-Signature')[0] ?? '') || abs(time() - (int) $ts) > 300) return Http::response(['error' => 'Bad signature'], 401);
        $body = json_decode($r->body(), true) ?: [];
        // like the station's schema: "custom" and "build" must be JSON objects (an empty PHP array would be [])
        $obj = json_decode($r->body());
        foreach (['custom', 'build'] as $k) if (isset($obj->entry) && property_exists($obj->entry, $k) && !is_object($obj->entry->{$k})) return Http::response(['error' => "entry.{$k}: expected an object"], 400);
        $this->calls[] = ['method' => $r->method(), 'path' => $u['path'], 'body' => $body];
        if ($r->method() === 'GET') {
            parse_str($u['query'] ?? '', $q);
            $jobs = array_filter(explode(',', $q['jobs'] ?? ''));
            return Http::response(['entries' => array_values(array_map(fn ($j) => ['job' => $j] + array_intersect_key($this->wo[$j], array_flip(['id', 'status', 'payment', 'cancelled', 'deleted'])), array_filter($jobs, fn ($j) => isset($this->wo[$j]))))]);
        }
        $job = rawurldecode(basename($u['path']));
        if ($r->method() === 'PUT') {
            if (isset($this->wo[$job])) return Http::response(['id' => $this->wo[$job]['id'], 'created' => false, 'deleted' => $this->wo[$job]['deleted']]);
            $this->wo[$job] = ['id' => 1000 + count($this->wo), 'status' => 'New', 'payment' => $body['entry']['payment'], 'cancelled' => false, 'deleted' => false, 'payload' => $body];
            return Http::response(['id' => $this->wo[$job]['id'], 'created' => true, 'deleted' => false], 201);
        }
        $e = &$this->wo[$job]; $changed = [];
        if (($body['payment'] ?? null) === 'Paid' && $e['payment'] !== 'Paid') { $e['payment'] = 'Paid'; $changed[] = 'payment'; }
        if (!empty($body['cancel']) && !$e['cancelled']) { $e['cancelled'] = true; $changed[] = 'notes'; if ($e['status'] !== 'Completed') { $e['status'] = 'On hold'; $changed[] = 'jobStatus'; } }
        if (!empty($body['complete']) && !$e['cancelled'] && $e['status'] !== 'Completed') { $e['status'] = 'Completed'; $changed[] = 'jobStatus'; }
        if (!empty($body['build']) && ($e['payload']['entry']['build'] ?? null) != $body['build']) { $e['payload']['entry']['build'] = $body['build']; $changed[] = 'build'; }
        return Http::response(['id' => $e['id'], 'changed' => $changed, 'status' => $e['status'], 'payment' => $e['payment']]);
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

    private function custom(array $cfg = [], array $price = []): array
    {
        $cfg += ['pieces' => 'one', 'build_mode' => 'custom', 'duty_key' => 'light_medium', 'duty_label' => 'Light / Medium Duty', 'series_key' => '1350', 'series_label' => '1350 Series',
            'material' => 'steel', 'price_tier' => 'certified', 'front_app' => 'slip_flange', 'front_app_label' => 'Slip Yoke with Flange Yoke', 'rear_app' => 'flange', 'rear_app_label' => 'Flange Yoke',
            'length' => 0, 'a1' => 52.25, 'b1' => 0, 'c1' => 4.5, 'flange_yoke' => ['kind' => 'none', 'product_id' => 0], 'trans_yoke' => ['kind' => 'none', 'product_id' => 0], 'notes' => 'Lowered 2 in.'];
        return ['type' => 'custom_build', 'name' => 'Custom Driveshaft — ' . $cfg['series_label'], 'price' => 900, 'meta' => json_encode(['config' => $cfg, 'summary' => 'x', 'price' => $price])];
    }

    /** A row of the build card: section title → label → value. */
    private function row(array $build, string $section, string $label): ?string
    {
        foreach ($build['sections'] as $s) if ($s['title'] === $section) foreach ($s['rows'] as [$l, $v]) if ($l === $label) return $v;
        return null;
    }

    private function calls(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c['method'] === $method));
    }

    public function test_a_committed_builder_order_becomes_one_work_order_through_the_signed_api(): void
    {
        $order = $this->order([$this->custom(), ['type' => 'product', 'name' => 'U-joint', 'sku' => 'U5-1310X', 'price' => 30]]);
        PayLink::placedNotices($order);
        $this->assertSame([], $this->calls, 'sent after the response, not during the checkout');
        app()->terminate();

        $this->assertCount(1, $this->calls('PUT'));
        [$call] = $this->calls('PUT');
        $this->assertSame('/api/integrations/web-orders/' . $order->order_number, $call['path']);
        $p = $call['body'];
        $this->assertSame($order->order_number, $p['order']);
        // no e-mail travels: the station keeps web customers silent
        $this->assertSame(['Alex', 'Morgan', '8135550142', 'Tampa', 'United States'], [$p['customer']['firstName'], $p['customer']['lastName'], $p['customer']['phone'], $p['customer']['city'], $p['customer']['country']]);
        $this->assertArrayNotHasKey('email', $p['customer']);
        $e = $p['entry'];
        $this->assertSame(['Custom Made Driveshaft', '1 Piece', 'Steel', 'One Piece', '52.25"', '', '4.5"', 'Paid'],
            [$e['laborRequest'], $e['driveshaftPieces'], $e['driveshaftMaterial'], $e['designerType'], $e['measurementA'], $e['measurementB'], $e['measurementC'], $e['payment']]);
        $this->assertEquals(900, $e['total']);
        // short notes, one fact per line (owner, 2026-11-11: "es difícil leer muchísimo texto en una sola columna")
        $lines = explode("\n", $e['notes']);
        $this->assertStringStartsWith('ONLINE ORDER ' . $order->order_number, $lines[0]);
        $this->assertContains('Slip Yoke with Flange Yoke → Flange Yoke', $lines);
        $this->assertContains('A1 52.25" · C1 4.5"', $lines);
        $this->assertContains('Customer notes: Lowered 2 in.', $lines);
        $this->assertLessThanOrEqual(10, count($lines));
        // the builder's drawing is the work order's product image
        $this->assertStringContainsString('/online-builder/drawing?f=slip_flange&r=flange&a1=52.25&c1=4.5', $e['productImage']);
        // everything else is the Online build card, grouped for the bench; no flat list of 30 fields any more
        $this->assertArrayNotHasKey('custom', $e);
        $b = $e['build'];
        $this->assertSame(['Shaft', 'Ends and yokes', 'Measurements', 'Customer notes', 'Order'], array_column($b['sections'], 'title'));
        $this->assertSame(['1350 Series', 'Steel', 'Slip Yoke with Flange Yoke', '52.25"', '4.5"'],
            [$this->row($b, 'Shaft', 'Series'), $this->row($b, 'Shaft', 'Tube material'), $this->row($b, 'Ends and yokes', 'Front end'), $this->row($b, 'Measurements', 'A1 · overall length'), $this->row($b, 'Measurements', 'C1 · u-joint center to center')]);
        $this->assertSame([$order->order_number, $order->email, 'Paid online', 'UPS Ground', '$900.00', '1 catalog part(s), packed by the store'],
            [$this->row($b, 'Order', 'Online order'), $this->row($b, 'Order', 'Customer e-mail'), $this->row($b, 'Order', 'Payment'), $this->row($b, 'Order', 'Delivery'), $this->row($b, 'Order', 'Shaft price'), $this->row($b, 'Order', 'Other items on the order')]);
        $this->assertStringContainsString('12 Test St, Tampa, FL 33601', $this->row($b, 'Order', 'Ship to'));
        $this->assertSame(['A1', 'C1', 'Series', 'Tube', 'Type', 'Qty'], array_column($b['highlights'], 'label'));
        $this->assertStringContainsString('shaft measurements', $b['note']);
        $this->assertStringContainsString('dsb_a1=52.25', $b['links']['build']);
        $this->assertStringNotContainsString('dsb_mph', $b['links']['build'], 'no empty speed values in the link');
        $this->assertStringContainsString('/online-builder/drawing?', $b['links']['drawing']);
        $this->assertCount(1, $p['parts'], 'only the shaft (no yoke or kit chosen here); the U-joint on the order is not the station\'s');
        $this->assertTrue(OrderEvents::has($order, 'checkin:sent'));
        $this->assertStringContainsString("Job {$order->order_number} = check-in #1000", DB::table('order_events')->where('order_id', $order->id)->where('type', 'checkin:sent')->value('detail'));

        // a retry (or a second trigger) never makes a second work order; it only brings the build card up to date
        $this->assertSame([$order->order_number => 1000], CheckinBridge::send((int) $order->id));
        $this->assertCount(1, $this->wo);
        $refresh = $this->calls('PATCH');
        $this->assertCount(1, $refresh);
        $this->assertSame(['order', 'build'], array_keys($refresh[0]['body']));
    }

    /** Owner, 2026-11-11: "usamos el checkin o workstation solo para custom driveshafts" — a pre-made shaft is not a work order. */
    public function test_two_custom_shafts_get_numbered_jobs_premade_stays_out_and_unpaid_pickup_is_flagged(): void
    {
        $premade = ['type' => 'premade_build', 'name' => 'Pre-Made 1310 Shaft', 'sku' => 'PM-1310', 'price' => 400, 'qty' => 2,
            'meta' => json_encode(['config' => ['pieces' => 'one', 'build_mode' => 'premade', 'premade_product_id' => 0, 'premade_a' => '48 1/2', 'premade_b' => '', 'premade_extras' => []]])];
        $order = $this->order([$this->custom(['pieces' => 'two']), $premade, array_merge($this->custom(), ['qty' => 2])],
            ['payment_status' => 'unpaid', 'payment_method' => 'pay_at_pickup', 'shipping_method' => \App\Services\CartService::PICKUP_ID]);
        $jobs = CheckinBridge::send((int) $order->id);

        $this->assertSame([$order->order_number . '-1', $order->order_number . '-2'], array_keys($jobs));
        [$a, $b] = [$this->wo[$order->order_number . '-1']['payload'], $this->wo[$order->order_number . '-2']['payload']];
        $this->assertSame(['2 Pieces', 'Two Piece', 'Unpaid', ''], [$a['entry']['driveshaftPieces'], $a['entry']['designerType'], $a['entry']['payment'], $a['entry']['productImage']], 'the builder draws one-piece shafts only');
        $this->assertEquals(1800, $b['entry']['total']);
        $card = $b['entry']['build'];
        $this->assertSame(['Local pickup', 'Pays at pickup', '2', '$945.00 (whole order)'], [$this->row($card, 'Order', 'Delivery'), $this->row($card, 'Order', 'Payment'), $this->row($card, 'Order', 'Quantity'), $this->row($card, 'Order', 'Collect at pickup')]);
        $this->assertNull($this->row($card, 'Order', 'Ship to'));
        $this->assertStringContainsString('shaft 2 of 2', $card['subtitle']);
        $this->assertSame(2, $b['parts'][0]['qty']);
        $this->assertCount(2, $this->calls('PUT'), 'the pre-made shaft never went');
    }

    /** The yokes and the kit the customer chose are the work order's parts (SKU to pull), and the lines add up to the shaft. */
    public function test_chosen_yokes_and_kit_are_a_pick_list_that_adds_up(): void
    {
        $w = DB::connection('wp');
        $pid = fn (int $cat) => (int) $w->table('wp_posts as p')->join('wp_term_relationships as r', 'r.object_id', '=', 'p.ID')->join('wp_term_taxonomy as t', 't.term_taxonomy_id', '=', 'r.term_taxonomy_id')
            ->where('t.term_id', $cat)->where('p.post_status', 'publish')->value('p.ID');
        [$yoke, $kit] = [$pid(\App\Services\DsbCatalog::FLANGE_CAT_ID), $pid(\App\Services\DsbCatalog::STRAP_CAT_ID)];
        $order = $this->order([$this->custom(['rear_app' => 'weld', 'front_app' => 'flange', 'front_yoke' => ['kind' => 'new', 'product_id' => $yoke], 'rear_yoke' => ['kind' => 'own', 'product_id' => 0],
            'hardware' => ['kind' => 'strap', 'product_id' => $kit]], ['front_addon' => 64.5, 'rear_addon' => 0, 'hardware_addon' => 21.25])]);
        CheckinBridge::send((int) $order->id);
        $parts = $this->wo[$order->order_number]['payload']['parts'];
        $this->assertCount(4, $parts);
        $this->assertSame(app(\App\Services\DsbCatalog::class)->productSku($yoke), $parts[1]['sku']);
        $this->assertStringStartsWith('Front yoke: ', $parts[1]['name']);
        $this->assertEquals([0, 21.25], [$parts[2]['price'], $parts[3]['price']]);
        $this->assertStringContainsString("customer's own", $parts[2]['name']);
        $this->assertEquals(900, round(array_sum(array_column($parts, 'price')), 2), 'shaft + yokes + kit = the price the customer paid');
    }

    public function test_nothing_goes_out_without_a_builder_shaft_or_without_the_station(): void
    {
        $plain = $this->order([['type' => 'product', 'name' => 'U-joint', 'sku' => 'U5-1310X', 'price' => 30]]);
        PayLink::placedNotices($plain);
        app()->terminate();
        $this->assertFalse(OrderEvents::has($plain, 'checkin:queued'));

        config(['services.checkin.secret' => null]);
        $built = $this->order([$this->custom()]);
        PayLink::placedNotices($built);
        app()->terminate();
        $this->assertFalse(OrderEvents::has($built, 'checkin:queued'));
        $this->assertSame([], $this->calls);
        $this->artisan('checkin:push')->expectsOutputToContain('not configured')->assertSuccessful();
    }

    public function test_requests_are_signed_and_a_wrong_secret_is_refused(): void
    {
        $order = $this->order([$this->custom()]);
        config(['services.checkin.secret' => str_repeat('x', 40)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');
        CheckinBridge::send((int) $order->id);
    }

    public function test_station_progress_reaches_the_customer_in_the_stores_words(): void
    {
        \App\Support\Settings::set('notify.sms', true);   // texts are off on the site since 2026-11-14; this checks them when on
        $sms = $this->spy(SmsService::class);
        $order = $this->order([$this->custom()], ['sms_opt_in' => 1]);
        $job = array_key_first(CheckinBridge::send((int) $order->id));
        $this->assertSame([0, 0], CheckinBridge::follow(), 'a new work order says nothing');

        $this->wo[$job]['status'] = 'Ongoing';
        $this->assertSame([1, 0], CheckinBridge::follow());
        $this->assertSame([0, 0], CheckinBridge::follow(), 'once');
        Mail::assertSent(Branded::class, fn (Branded $m) => $m->hasTo($order->email) && str_contains($m->subjectLine, 'We started building your driveshaft'));
        $sms->shouldHaveReceived('send')->once()->withArgs(fn ($to, $body) => str_contains($body, 'we started building your driveshaft') && !str_contains($body, 'pickup'));

        $this->wo[$job]['status'] = 'Completed';
        $this->assertSame([0, 1], CheckinBridge::follow());
        Mail::assertSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'Your driveshaft is built'));
        Mail::assertNotSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'ready for pickup'));
        $sms->shouldHaveReceived('send')->withArgs(fn ($to, $body) => str_contains($body, 'It ships soon'));
        $this->assertTrue(OrderEvents::has($order, 'build:done'));
        $this->assertSame([0, 0], CheckinBridge::follow());

        // a work order the shop deleted: the store says nothing more about it
        $other = $this->order([$this->custom()]);
        $job2 = array_key_first(CheckinBridge::send((int) $other->id));
        $this->wo[$job2] = ['status' => 'Completed', 'deleted' => true] + $this->wo[$job2];
        $this->assertSame([0, 0], CheckinBridge::follow());
    }

    public function test_a_pickup_build_finished_is_the_usual_ready_for_pickup_and_unfinished_multi_shaft_waits(): void
    {
        $this->spy(SmsService::class);
        $order = $this->order([$this->custom(), $this->custom(['pieces' => 'two'])], ['shipping_method' => \App\Services\CartService::PICKUP_ID]);
        [$one, $two] = array_keys(CheckinBridge::send((int) $order->id));

        $this->wo[$one]['status'] = 'Completed';
        $this->assertSame([1, 0], CheckinBridge::follow(), 'one of two shafts done: started, not finished');
        $this->wo[$two]['status'] = 'Completed';
        $this->assertSame([0, 1], CheckinBridge::follow());
        Mail::assertSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, "Order {$order->order_number} is ready for pickup"));
        Mail::assertNotSent(Branded::class, fn (Branded $m) => str_contains($m->subjectLine, 'Your driveshaft is built'));
        $this->assertTrue(OrderEvents::has($order, 'notify:email:ready'), 'the admin marking it completed later sends nothing twice');
    }

    /** After the send the work order follows the order: the store asks only when the station shows something to change. */
    public function test_the_work_order_follows_payment_cancellation_and_shipping(): void
    {
        $pickup = $this->order([$this->custom()], ['payment_status' => 'unpaid', 'payment_method' => 'pay_at_pickup', 'shipping_method' => \App\Services\CartService::PICKUP_ID]);
        $job = array_key_first(CheckinBridge::send((int) $pickup->id));
        $this->assertSame(0, CheckinBridge::sync(), 'nothing changed yet');
        $this->assertSame([], $this->calls('PATCH'));
        DB::table('orders')->where('id', $pickup->id)->update(['payment_status' => 'paid']);
        $this->assertSame(1, CheckinBridge::sync());
        $this->assertSame('Paid', $this->wo[$job]['payment']);
        $patches = count($this->calls('PATCH'));
        $this->assertSame(0, CheckinBridge::sync(), 'once');
        $this->assertCount($patches, $this->calls('PATCH'), 'no request when the station already shows it');

        $gone = $this->order([$this->custom()]);
        $job2 = array_key_first(CheckinBridge::send((int) $gone->id));
        $this->wo[$job2]['status'] = 'Ongoing';
        DB::table('orders')->where('id', $gone->id)->update(['status' => 'cancelled']);
        $this->assertSame(1, CheckinBridge::sync());
        $last = $this->calls('PATCH');
        $this->assertSame(['order' => $gone->order_number, 'cancel' => ['reason' => 'cancelled']], end($last)['body']);
        $this->assertSame([true, 'On hold'], [$this->wo[$job2]['cancelled'], $this->wo[$job2]['status']]);
        $this->assertSame(0, CheckinBridge::sync(), 'once');

        $sent = $this->order([$this->custom()]);
        $job3 = array_key_first(CheckinBridge::send((int) $sent->id));
        DB::table('orders')->where('id', $sent->id)->update(['shipped_at' => now(), 'tracking_carrier' => 'UPS', 'tracking_number' => '1Z999']);
        $this->assertSame(1, CheckinBridge::sync());
        $last = $this->calls('PATCH');
        $this->assertSame(['note' => 'Shipped from the online store (UPS 1Z999)'], end($last)['body']['complete']);
        $this->assertSame('Completed', $this->wo[$job3]['status']);
        $this->assertTrue(OrderEvents::has($sent, 'checkin:updated'));
    }

    public function test_a_failed_send_is_recorded_retried_and_the_shop_hears_once(): void
    {
        $order = $this->order([$this->custom()]);
        $this->down = true;
        PayLink::placedNotices($order);
        app()->terminate();
        $this->assertTrue(OrderEvents::has($order, 'checkin:failed'));
        $this->assertStringContainsString('HTTP 503', DB::table('order_events')->where('order_id', $order->id)->where('type', 'checkin:failed')->value('detail'));
        $this->assertFalse(OrderEvents::has($order, 'checkin:sent'));

        DB::table('order_events')->where('order_id', $order->id)->where('type', 'checkin:queued')->update(['created_at' => now()->subMinutes(45)]);
        $this->artisan('checkin:push')->expectsOutputToContain('is not on the station')->assertFailed();
        $this->artisan('checkin:push')->doesntExpectOutputToContain('is not on the station')->assertFailed();
        Mail::assertSent(Branded::class, fn (Branded $m) => $m->hasTo(config('uds.email')) && str_contains($m->subjectLine, "Work order missing on the shop station — order {$order->order_number}"));
        Mail::assertSent(Branded::class, 1);

        $this->down = false;
        $this->artisan('checkin:push')->expectsOutputToContain('1 of 1 order(s) sent')->assertSuccessful();
        $this->assertArrayHasKey($order->order_number, $this->wo);
    }

    /** The drawing on the work order is the builder's own: its dimensions only, with the customer's values. */
    public function test_the_build_drawing_endpoint(): void
    {
        $svg = $this->get('/online-builder/drawing?f=trans&r=flange&a1=52.25&c1=48')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->getContent();
        $this->assertStringContainsString('A1 = 52.25"', $svg);
        $this->assertStringContainsString('C1 = 48"', $svg);
        $this->assertStringNotContainsString('{W1}', $svg);
        $this->assertStringNotContainsString('FRONT', $svg);
        $this->assertMatchesRegularExpression('/<svg[^>]+width="\d+"[^>]+height="\d+"/', $svg);
        $this->assertStringContainsString('A1 = 30"', $this->get('/online-builder/drawing?f=trans&r=carrier_end&a1=30')->assertOk()->getContent());
        $this->get('/online-builder/drawing?f=nope&r=flange&a1=40')->assertNotFound();
        $this->get('/online-builder/drawing?f[]=x&r=flange')->assertNotFound();
    }
}
