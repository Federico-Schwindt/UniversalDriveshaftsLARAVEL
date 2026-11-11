<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use App\Services\CompleteJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
