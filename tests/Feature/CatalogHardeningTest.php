<?php

namespace Tests\Feature;

use App\Services\CartService;
use App\Services\DriveshaftFinder;
use App\Services\DsbPricing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Catalog / search / builder hardening (review Nov 2026): tampered builder keys, array params, $0 parts, wrong-group URLs. */
class CatalogHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function build(array $over = []): array
    {
        return $over + [
            'build_mode' => 'custom', 'pieces' => 'one', 'duty_key' => 'heavy', 'subcat_key' => 'hd_spl', 'series_key' => 'spl250',
            'series_label' => 'SPL 250', 'price_tier' => 'new', 'material' => 'steel', 'front_app' => 'flange', 'rear_app' => 'flange', 'a1' => 50, 'c1' => 40,
        ];
    }

    public function test_builder_prices_spl250_from_the_tree(): void
    {
        $p = app(DsbPricing::class)->compute($this->build());
        $this->assertSame(2150.0, (float) $p['final']);
        $this->assertSame(1750.0, (float) app(DsbPricing::class)->compute($this->build(['price_tier' => 'certified']))['final']);
        // aluminum is light/medium only: a heavy series cannot be switched to the aluminum table
        $this->assertSame(2150.0, (float) app(DsbPricing::class)->compute($this->build(['material' => 'aluminum']))['final']);
    }

    public function test_builder_never_falls_back_to_the_base_price(): void
    {
        $pricing = app(DsbPricing::class);
        foreach ([['series_key' => 'SPL250'], ['series_key' => 'zzz'], ['duty_key' => 'nope'], ['subcat_key' => 'hd_gwb'], ['duty_key' => 'light_medium']] as $bad) {
            $p = $pricing->compute($this->build($bad));
            $this->assertSame(0.0, (float) $p['final'], json_encode($bad));
            $this->assertNotEquals(DsbPricing::BASE_PRICE, (float) $p['final']);
        }
    }

    public function test_builder_submit_rejects_tampered_keys_and_uses_server_labels(): void
    {
        foreach ([['series_key' => 'SPL250'], ['duty_key' => 'unknown'], ['front_app' => 'rocket'], ['rear_app' => 'slip_flange'], ['series_key' => ['x']]] as $bad) {
            $this->post('/dsb/submit', $this->build($bad))->assertStatus(422);
        }
        $this->post('/dsb/quote', $this->build(['series_key' => 'SPL250']))->assertStatus(422);

        $r = $this->post('/dsb/submit', $this->build(['series_label' => '<b>Cheap</b>', 'duty_label' => 'x', 'front_app' => 'flange']));
        $r->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(2150.0, $r->json('data.price.final'));
        $line = collect(app(CartService::class)->items())->last();
        $this->assertSame(2150.0, (float) $line['price']);
        $this->assertStringContainsString('SPL 250', $line['name']);
        $this->assertStringNotContainsString('Cheap', json_encode($line));
    }

    public function test_array_query_params_never_500(): void
    {
        foreach ([
            '/search?q[]=x', '/api/search?q[]=x', '/shop?q[]=x',
            '/shop/driveshaft-components/u-joints?q[]=x', '/shop/driveshaft-components/u-joints?sort[]=x', '/shop/driveshaft-components/u-joints?series[]=x',
            '/shop/complete-driveshafts/by-vehicle?q[]=x', '/shop/complete-driveshafts/by-vehicle?make[]=x',
            '/api/catalog/driveshafts/search?q[]=x', '/api/catalog/driveshafts/options?make[]=x', '/api/catalog/driveshafts/step?make[]=x',
        ] as $url) {
            $s = $this->get($url)->status();
            $this->assertTrue(in_array($s, [200, 301, 302], true), "$url -> $s");
        }
    }

    public function test_unknown_filter_values_redirect_to_the_clean_url(): void
    {
        $this->get('/shop/driveshaft-components/u-joints?series=zzz')->assertRedirect('/shop/driveshaft-components/u-joints');
        $this->get('/shop/driveshaft-components/u-joints?sort=evil&series=1310')->assertRedirect('/shop/driveshaft-components/u-joints?series=1310');
        $this->get('/shop/driveshaft-components/u-joints?series=1310')->assertOk();
    }

    public function test_zero_priced_part_is_refused(): void
    {
        $id = (int) DB::connection('wp')->table('wp_posts as p')->join('wp_postmeta as m', 'm.post_id', '=', 'p.ID')
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('m.meta_key', '_price')
            ->whereIn('m.meta_value', ['0', '0.00'])->value('p.ID');
        if (!$id) $this->markTestSkipped('no $0 product in the catalog copy');
        $hasRegular = (float) DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_regular_price')->value('meta_value') > 0;
        if ($hasRegular) $this->markTestSkipped('the $0 product has a regular price fallback');
        $before = app(CartService::class)->count();
        $this->post('/shop/catalog/add', ['id' => $id, 'qty' => 1])->assertStatus(422)->assertJsonPath('success', false);
        $sku = DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_sku')->value('meta_value');
        $this->post('/quick-order/add', ['lines' => json_encode([['sku' => $sku, 'qty' => 1]])])->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame($before, app(CartService::class)->count());
        // the product page offers a quote, not an add button, and no $0 Offer
        $page = $this->get('/parts/' . rawurlencode($sku));
        if ($page->status() === 200) {
            $page->assertDontSee('id="pdp-add"', false)->assertDontSee('"price":"0.00"', false);
        }
    }

    public function test_category_under_the_wrong_group_redirects(): void
    {
        $this->get('/shop/small-parts/u-joints')->assertStatus(301)->assertRedirect('/shop/driveshaft-components/u-joints');
        $this->get('/shop/small-parts/u-joints?series=1310')->assertRedirect('/shop/driveshaft-components/u-joints?series=1310');
        $this->get('/shop/driveshaft-components/no-such-category')->assertNotFound();
        // Custom Cut Tubing became Driveshaft Tubing (2026-11): the old URLs keep working through a 301
        $this->get('/shop/custom-cut-tubing')->assertStatus(301)->assertRedirect('/shop/driveshaft-tubing/tubing');
        $this->get('/shop/custom-cut-tubing/tubing')->assertStatus(301)->assertRedirect('/shop/driveshaft-tubing/tubing');
        $this->get('/shop/driveshaft-tubing')->assertStatus(301)->assertRedirect('/shop/driveshaft-tubing/tubing');
    }

    public function test_non_numeric_ids_404(): void
    {
        $this->get('/shop/quick-view/abc')->assertNotFound();
        $this->get('/shop/alternates/abc')->assertNotFound();
    }

    public function test_finder_search_excludes_inactive_parts(): void
    {
        $pn = DB::table('ds_parts')->where('is_active', 0)->whereNotNull('description')->value('part_number');
        if (!$pn) $this->markTestSkipped('no inactive finder part');
        $hits = array_column(app(DriveshaftFinder::class)->search($pn), 'part_number');
        $this->assertNotContains($pn, $hits);
    }

    public function test_designer_svg_refuses_out_of_order_sections(): void
    {
        $this->get('/driveshaft-designer/svg/ZZ+YY')->assertNotFound();
        $this->assertFalse(\App\Http\Controllers\DesignerController::validSections('A+B+C+D'));
        $this->assertSame('<a>x</a>', \App\Http\Controllers\DesignerController::sanitize("<a href='http://evil'>x</a>"));
    }
}
