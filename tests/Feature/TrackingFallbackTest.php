<?php

namespace Tests\Feature;

use App\Services\EasyPostTrackingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** When EasyPost can't answer (e.g. out of funds) tracking comes from Shippo, and deliveries still get recorded. */
class TrackingFallbackTest extends TestCase
{
    use DatabaseTransactions;

    private function fake(string $deliveredAt): void
    {
        config(['services.easypost.enabled' => true, 'services.easypost.key' => 'ep', 'services.shippo.token' => 'sh']);
        Cache::forget('easypost:paused');
        foreach (['', ':lock'] as $sfx) Cache::forget('shippo:trk:1Z97Y4100309739640' . $sfx);
        Http::fake([
            'api.easypost.com/*' => Http::response(['error' => ['message' => 'Insufficient funds.']], 402),
            'api.goshippo.com/tracks/ups/*' => Http::response(['carrier' => 'ups', 'eta' => null,
                'tracking_status' => ['status' => 'DELIVERED', 'status_details' => 'Delivered', 'status_date' => $deliveredAt],
                'tracking_history' => [
                    ['status' => 'TRANSIT', 'status_details' => 'Out For Delivery Today', 'substatus' => ['code' => 'out_for_delivery'], 'status_date' => '2026-10-30T12:00:00Z', 'location' => ['city' => 'CLARKSVILLE', 'state' => 'IN', 'country' => 'US']],
                    ['status' => 'DELIVERED', 'status_details' => 'Delivered', 'status_date' => $deliveredAt, 'location' => ['city' => 'Corydon', 'state' => 'IN', 'country' => 'US']],
                ]]),
        ]);
    }

    private function order(): object
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'stripe', 'email' => 'alex@example.com',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'shipping_method' => 'ups_ground', 'subtotal' => 10, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 10,
            'tracking_number' => '1Z97Y4100309739640', 'tracking_carrier' => 'UPS', 'shipped_at' => '2026-10-28 10:00:00', 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_easypost_first_then_shippo_when_easypost_is_out_of_funds(): void
    {
        $this->fake('2026-10-30T17:42:00Z');
        $t = app(EasyPostTrackingService::class);
        $st = $t->status('1Z97Y4100309739640', 'UPSDAP');
        $this->assertTrue($st['delivered']);
        $this->assertSame('out_for_delivery', $st['events'][1]['status']);
        $this->assertSame('Clarksville, IN', $st['events'][1]['place']);
        $this->assertTrue(Cache::has('easypost:paused'));
        Http::assertSentCount(2);                    // EasyPost (402), then Shippo
        $this->assertTrue($t->status('1Z97Y4100309739640', 'UPSDAP')['delivered']);
        Http::assertSentCount(2);                    // paused EasyPost + Shippo answer reused for 3 h: no new calls
    }

    public function test_easypost_answer_is_used_without_shippo(): void
    {
        config(['services.easypost.enabled' => true, 'services.easypost.key' => 'ep', 'services.shippo.token' => 'sh']);
        Cache::forget('easypost:paused');
        Http::fake(['api.easypost.com/*' => Http::response(['status' => 'in_transit', 'carrier' => 'UPS', 'tracking_details' => [['status' => 'in_transit', 'message' => 'On the way', 'datetime' => '2026-10-30T10:00:00Z', 'tracking_location' => ['city' => 'Atlanta', 'state' => 'GA']]]])]);
        $this->assertSame('In transit', app(EasyPostTrackingService::class)->status('1ZEP0000000000001', 'UPSDAP')['status']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'goshippo'));
    }

    public function test_orders_track_marks_delivered_at_the_scan_time(): void
    {
        Mail::fake();
        $this->fake('2026-10-30T17:42:00Z');
        $o = $this->order();
        $this->artisan('orders:track', ['--order' => $o->order_number])->assertSuccessful();
        $this->assertSame('2026-10-30 17:42:00', DB::table('orders')->where('id', $o->id)->value('delivered_at'));
        Cache::forget('easypost:paused');
    }

    public function test_a_delivery_scan_older_than_the_shipment_is_left_for_staff(): void
    {
        Mail::fake();
        $this->fake('2026-09-04T17:42:00Z');   // a delivery from before the order shipped
        $o2 = $this->order();
        $this->artisan('orders:track', ['--order' => $o2->order_number])->assertSuccessful();
        $this->assertNull(DB::table('orders')->where('id', $o2->id)->value('delivered_at'));
        $this->assertTrue(DB::table('order_events')->where('order_id', $o2->id)->where('type', 'tracking:mismatch')->exists());
        Cache::forget('easypost:paused');
    }
}
