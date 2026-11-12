<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use App\Services\CompleteJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Complete the job" / "Fits this part" (owner, 2026-11-11: "el fitment debe ser perfecto") and the delivery-date
 * lookups (background calls that must never touch the session: an Add to cart made while one ran was lost).
 */
class CartSuggestionsTest extends TestCase
{
    use DatabaseTransactions;

    private function line(string $sku): array
    {
        $p = app(CatalogService::class)->productBySku($sku);
        $this->assertNotNull($p, "$sku is in the catalog");
        return ['type' => 'product', 'wp_product_id' => (int) $p['id'], 'sku' => $sku, 'qty' => 1, 'price' => (float) $p['price']];
    }

    public function test_a_weld_yoke_is_offered_only_tube_of_its_exact_size_and_material(): void
    {
        $r = app(CompleteJob::class)->forCart([$this->line('2-28-437')]);   // 1310 weld yoke for 3.000 × .083 steel tube
        $this->assertNotEmpty($r['items']);
        $this->assertTrue($r['build']);
        $c = app(CatalogService::class);
        foreach ($r['items'] as $s) {
            $p = $c->productBySku($s['sku']);
            $this->assertSame('tubing', $c->primaryCategory((int) $p['id'])['slug'], "{$s['sku']} is tube, not a series guess");
            $this->assertEqualsWithDelta(3.0, (float) $p['specs']['Tube Dia'], 0.001);
            $this->assertMatchesRegularExpression('/^(0?\.083"?|83)$/', trim((string) $p['specs']['Tube Wall']));
            $this->assertSame('STEEL', strtoupper((string) $p['specs']['Material']));
        }
    }

    public function test_series_alone_never_produces_a_suggestion(): void
    {
        // a 1350 transmission yoke and a 1350 u-joint: no strap kit (snap rings hold a joint in a slip yoke), no guesses
        $r = app(CompleteJob::class)->forCart([$this->line('3503-326'), $this->line('5-1350XU')]);
        $this->assertSame([], $r['items']);
        $this->assertFalse($r['build']);
        // a u-joint's product page lists no "fits" from its series either
        $id = (int) app(CatalogService::class)->productBySku('5-1330XU')['id'];
        $this->assertSame([], app(CompleteJob::class)->forProduct($id)['fits']);
        foreach (app(CompleteJob::class)->forProduct($id)['also'] as $r) {
            $this->assertStringNotContainsString('1350', (string) ($r['series'] ?? ''), 'another series is not offered with a 1330 joint');
        }
    }

    /** Owner, 2026-11-12: "sugerir piezas relacionadas". The cart's second list is what others ordered with these parts. */
    public function test_also_bought_in_the_cart_is_buyable_and_never_a_substitute_or_a_repeat(): void
    {
        $c = app(CatalogService::class);
        $job = app(CompleteJob::class);
        $own = 0; $like = 0;
        foreach (['u-joints', 'weld-yoke', 'flange-yokes'] as $cat) {
            foreach ($c->products($cat, [], 1, 20, 'popular')['items'] as $p) {
                $r = $job->forCart([['type' => 'product', 'wp_product_id' => (int) $p['id'], 'sku' => $p['sku'], 'qty' => 1, 'price' => (float) $p['price']]]);
                $ids = array_column($r['also'], 'id');
                $this->assertLessThanOrEqual(4, count($ids));
                $this->assertLessThanOrEqual(6, count($r['items']) + count($ids), 'both lists share the six places');
                $this->assertSame($ids, array_values(array_unique($ids)));
                $this->assertNotContains((int) $p['id'], $ids, "{$p['sku']} is already in the cart");
                $this->assertSame([], array_intersect($ids, array_column($r['items'], 'id')), 'a proven fit is not listed twice');
                foreach ($r['also'] as $a) {
                    $this->assertTrue($a['in_stock'], "{$a['sku']} can ship now");
                    $this->assertGreaterThan(0, $a['price']);
                    $this->assertNotSame($cat, $c->primaryCategory($a['id'])['slug'], "{$a['sku']} is another part of the same kind as {$p['sku']}: a substitute");
                    $this->assertStringStartsWith('Often ordered with ', $a['reason'], 'what others ordered, never a fitment claim');
                    str_contains($a['reason'], $p['sku']) ? $own++ : $like++;
                }
            }
        }
        $this->assertGreaterThan(0, $own, 'parts with order history offer what was ordered with them');
        $this->assertGreaterThan(0, $like, 'parts without it offer what was ordered with parts of their kind and series');
    }

    public function test_like_parts_history_counts_shared_orders_and_leaves_the_parts_themselves_out(): void
    {
        [$a, $b, $washer, $strap, $gone] = [990000001, 990000002, 990000003, 990000004, 990000005];   // ids no real order has
        $order = function (array $ids, string $status = 'completed') {
            $id = DB::table('orders')->insertGetId(['order_number' => 'UDS-ALSO-' . uniqid(), 'email' => 'also-' . uniqid() . '@example.com', 'billing_first_name' => 'Alex',
                'status' => $status, 'payment_status' => 'paid', 'source' => 'web', 'total' => 20, 'subtotal' => 20, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($ids as $pid) {
                DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'wp_product_id' => $pid, 'name' => 'Test part', 'sku' => "T-$pid", 'price' => 10, 'qty' => 1,
                    'line_total' => 10, 'created_at' => now(), 'updated_at' => now()]);
            }
        };
        $order([$a, $washer]);
        $order([$b, $washer]);
        $order([$a, $strap]);
        $order([$a, $b]);                     // the like parts together: neither is offered for the other
        $order([$a, $gone], 'cancelled');     // a cancelled order is no history
        Cache::forget('also_bought_map');
        $this->assertSame([$washer => 2, $strap => 1], app(CatalogService::class)->alsoBoughtCounts([$a, $b]));
        $this->assertSame([], app(CatalogService::class)->alsoBoughtCounts([990000009]));
    }

    public function test_delivery_lookups_never_start_a_session(): void
    {
        foreach (['/api/arrival', '/api/zip-near?lat=10&lng=10', '/geo'] as $url) {
            $res = $this->get($url)->assertOk();
            $this->assertNull(collect($res->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session')), "$url sets no session cookie");
        }
        $this->get('/api/arrival')->assertJsonPath('need_zip', true);
        $this->get('/api/zip-near?lat=10&lng=10')->assertJsonPath('outside', true);
    }
}
