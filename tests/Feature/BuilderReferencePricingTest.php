<?php

namespace Tests\Feature;

use App\Services\AmPricing;
use App\Services\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reference pricing from the supplier table (storage/app/builder/am-prices.json): 85 % of the supplier's variant (AmPricing::MARKDOWN)
 * price, unknown combinations refused, the length question answered from the measurement, the sync guard rail.
 * The table is replaced by an in-memory one so the tests never touch the real file.
 */
class BuilderReferencePricingTest extends TestCase
{
    use DatabaseTransactions;

    private array $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $lenOpt = ['name' => 'Is your driveshaft 88" or over (seal to flange face)?', 'values' => ['87.75" or Less', '88" - 93.50"']];
        $this->table = ['source' => 'https://supplier.test', 'markdown' => 0.85, 'synced_at' => '2026-11-10 00:00:00', 'products' => [
            '3s1310-l' => ['handle' => '3s1310-l', 'title' => 'Slip yoke shaft 3 in steel 1310', 'families' => ['L'], 'series' => '1310', 'material' => 'steel', 'od' => 3.0, 'max_len' => 56, 'available' => true,
                'options' => [['name' => 'Rear U-Joint Hardware', 'values' => ['Driveshaft Only', 'Driveshaft & 1310 Strap Kit (GM)']], ['name' => 'Driveshaft Build Options', 'values' => ['No Change', 'I will send in my slip yoke.']]],
                'variants' => [
                    '1' => ['id' => '1', 'sku' => '3S1310-L', 'options' => ['Driveshaft Only', 'No Change'], 'am_price' => 511.92, 'our_price' => 409.54, 'available' => true],
                    '2' => ['id' => '2', 'sku' => '3S1310-L_1-0024', 'options' => ['Driveshaft & 1310 Strap Kit (GM)', 'No Change'], 'am_price' => 522.67, 'our_price' => 418.14, 'available' => true],
                    '3' => ['id' => '3', 'sku' => '3S1310-L(used_slip)', 'options' => ['Driveshaft Only', 'I will send in my slip yoke.'], 'am_price' => 449.30, 'our_price' => 359.44, 'available' => true],
                ]],
            '35s1310-l' => ['handle' => '35s1310-l', 'title' => 'Slip yoke shaft 3.5 in steel 1310', 'families' => ['L'], 'series' => '1310', 'material' => 'steel', 'od' => 3.5, 'max_len' => 61, 'available' => true,
                'options' => [['name' => 'Rear U-Joint Hardware', 'values' => ['Driveshaft Only']], ['name' => 'Driveshaft Build Options', 'values' => ['No Change']]],
                'variants' => ['4' => ['id' => '4', 'sku' => '35S1310-L', 'options' => ['Driveshaft Only', 'No Change'], 'am_price' => 568.63, 'our_price' => 454.90, 'available' => true]]],
            '5a1555-b' => ['handle' => '5a1555-b', 'title' => '5 in aluminum 1480', 'families' => ['B'], 'series' => '1480', 'material' => 'aluminum', 'od' => 5.0, 'max_len' => 93.5, 'available' => true,
                'options' => [['name' => 'Rear U-Joint Hardware', 'values' => ['Driveshaft Only']], ['name' => 'Driveshaft Build Options', 'values' => ['No Change', 'I will send in my slip yoke.']], $lenOpt],
                'variants' => [
                    '5' => ['id' => '5', 'sku' => '5A-U', 'options' => ['Driveshaft Only', 'No Change', '87.75" or Less'], 'am_price' => 1220.43, 'our_price' => 976.34, 'available' => true],
                    '6' => ['id' => '6', 'sku' => '5A-U(used_slip)+88', 'options' => ['Driveshaft Only', 'I will send in my slip yoke.', '88" - 93.50"'], 'am_price' => 1386.03, 'our_price' => 1108.82, 'available' => true],
                    '7' => ['id' => '7', 'sku' => '5A-U(used_slip)', 'options' => ['Driveshaft Only', 'I will send in my slip yoke.', '87.75" or Less'], 'am_price' => 1093.53, 'our_price' => 874.82, 'available' => true],
                ]],
        ]];
        $fake = new class($this->table) extends AmPricing {
            public function __construct(public array $t) {}
            public function table(): array { return $this->t; }
            public function save(array $table): void { $this->t = $table; }
        };
        app()->instance(AmPricing::class, $fake);
        app()->forgetInstance(\App\Services\DsbPricing::class);
        app()->bind(\App\Services\DsbPricing::class, fn () => new \App\Services\DsbPricing(app(\App\Services\DsbCatalog::class), $fake));
    }

    private function build(array $over = []): array
    {
        return $over + ['pieces' => 'one', 'family' => 'L', 'duty_key' => 'light_medium', 'series_key' => '1310', 'material' => 'steel', 'front_app' => 'trans', 'rear_app' => 'weld', 'a1' => 50, 'c1' => 40, 'front_yoke_kind' => 'none', 'rear_yoke_kind' => 'none', 'hardware_kind' => 'none'];
    }

    public function test_price_is_eighty_five_percent_of_the_supplier_variant(): void
    {
        $this->assertSame(435.13, AmPricing::ours(511.92));
        $r = $this->post('/dsb/quote', $this->build())->assertOk();
        $this->assertSame(435.13, $r->json('data.final')); $this->assertSame('reference', $r->json('data.breakdown.pricing')); $this->assertSame('3S1310-L', $r->json('data.breakdown.variant.sku'));
        $r = $this->post('/dsb/quote', $this->build(['am_options' => json_encode(['Rear U-Joint Hardware' => 'Driveshaft & 1310 Strap Kit (GM)'])]))->assertOk();
        $this->assertSame(444.27, $r->json('data.final'));
        $r = $this->post('/dsb/quote', $this->build(['am_options' => json_encode(['Driveshaft Build Options' => 'I will send in my slip yoke.'])]))->assertOk();
        $this->assertSame(381.91, $r->json('data.final')); $this->assertStringContainsString('own', $r->json('data.breakdown.front_label'));
        // a longer shaft moves to the next tube; a rear flange yoke from our shop is added on top
        $r = $this->post('/dsb/quote', $this->build(['a1' => 58]))->assertOk();
        $this->assertSame(483.34, $r->json('data.final')); $this->assertSame('3.5"', $r->json('data.breakdown.tube_od'));
        $r = $this->post('/dsb/quote', $this->build(['rear_app' => 'flange', 'rear_yoke_kind' => 'used']))->assertOk();
        $this->assertSame(435.13 + 45, $r->json('data.final'));
    }

    public function test_unknown_combinations_are_refused_not_guessed(): void
    {
        // an option value the product does not have, a length no tube reaches, a family the table does not cover (our engine prices it)
        $r = $this->post('/dsb/quote', $this->build(['am_options' => json_encode(['Rear U-Joint Hardware' => 'Gold plated'])]))->assertOk();
        $this->assertSame(0, (int) $r->json('data.final')); $this->assertSame('variant', $r->json('data.breakdown.error'));
        $this->post('/dsb/submit', $this->build(['am_options' => json_encode(['Rear U-Joint Hardware' => 'Gold plated'])]))->assertStatus(422);
        $this->post('/dsb/submit', $this->build(['a1' => 70]))->assertStatus(422);
        $r = $this->post('/dsb/quote', $this->build(['family' => 'A', 'series_key' => '1330']))->assertOk();
        $this->assertNull($r->json('data.breakdown.pricing')); $this->assertGreaterThan(0, $r->json('data.final'));
    }

    public function test_length_question_follows_the_measured_length(): void
    {
        $b = ['family' => 'B', 'series_key' => '1480', 'material' => 'aluminum', 'am_options' => json_encode(['Driveshaft Build Options' => 'I will send in my slip yoke.'])];
        $r = $this->post('/dsb/quote', $this->build($b + ['a1' => 80]))->assertOk();
        $this->assertSame('87.75" or Less', $r->json('data.breakdown.options.Is your driveshaft 88" or over (seal to flange face)?')); $this->assertSame(929.50, $r->json('data.final'));
        $r = $this->post('/dsb/quote', $this->build($b + ['a1' => 90]))->assertOk();
        $this->assertSame('88" - 93.50"', $r->json('data.breakdown.options.Is your driveshaft 88" or over (seal to flange face)?')); $this->assertSame(1178.13, $r->json('data.final'));
        // the measurement answers the length question: a short-band answer sent for a 90 in shaft is ignored and the
        // long-band variant is priced; 88+ with a new slip yoke has no variant; over 93.5 nothing fits
        $r = $this->post('/dsb/submit', $this->build($b + ['a1' => 90, 'am_options' => json_encode(['Driveshaft Build Options' => 'I will send in my slip yoke.', 'Is your driveshaft 88" or over (seal to flange face)?' => '87.75" or Less'])]))->assertOk();
        $this->assertSame(1178.13, $r->json('data.price.final'));
        // (the override goes first: with `$b + [...]` the union would keep $b's own am_options)
        $this->assertSame('variant', $this->post('/dsb/quote', $this->build(['a1' => 90, 'am_options' => json_encode(['Driveshaft Build Options' => 'No Change'])] + $b))->json('data.breakdown.error'));
        $this->post('/dsb/submit', $this->build($b + ['a1' => 95]))->assertStatus(422);
        $this->assertSame('88 - 93.50 Inches', AmPricing::lengthAnswer(['values' => ['87.75 Inch or Less', '88 - 93.50 Inches']], 88));
        $this->assertNull(AmPricing::lengthAnswer(['values' => ['87.75 Inch or Less', '88 - 93.50 Inches']], 94));
    }

    /** security review 2026-11-11: the variant price covers the front end and hardware, so a posted yoke or kit never reaches the build sheet */
    public function test_supplier_priced_build_drops_a_front_yoke_or_kit_it_did_not_price(): void
    {
        $yoke = (int) \DB::table('catalog_live')->join('wp_term_relationships', 'object_id', '=', 'wp_product_id')
            ->join('wp_term_taxonomy', 'wp_term_taxonomy.term_taxonomy_id', '=', 'wp_term_relationships.term_taxonomy_id')
            ->join('wp_terms', 'wp_terms.term_id', '=', 'wp_term_taxonomy.term_id')->where('wp_terms.slug', 'transmission-yokes')->where('price', '>', 0)->value('wp_product_id');
        $kit = (int) \DB::table('catalog_live')->join('wp_term_relationships', 'object_id', '=', 'wp_product_id')
            ->join('wp_term_taxonomy', 'wp_term_taxonomy.term_taxonomy_id', '=', 'wp_term_relationships.term_taxonomy_id')
            ->where('wp_term_taxonomy.term_id', \App\Services\DsbCatalog::STRAP_CAT_ID)->where('price', '>', 0)->value('wp_product_id');
        $r = $this->post('/dsb/submit', $this->build(['front_yoke_kind' => 'new', 'front_yoke_product_id' => $yoke, 'hardware_kind' => 'strap', 'hardware_product_id' => $kit]))->assertOk();
        $this->assertSame(435.13, $r->json('data.price.final'));
        $cfg = collect(app(CartService::class)->items())->last()['meta']['config'];
        $this->assertSame('none', $cfg['front_yoke']['kind']);
        $this->assertSame(['kind' => 'none', 'product_id' => 0], $cfg['hardware']);
        $this->assertSame('trans', $cfg['front_app']);
        $rows = collect(\App\Support\BuildConfig::rows($cfg))->pluck('value', 'label');
        $this->assertFalse($rows->has('Front yoke'));
        $this->assertFalse($rows->has('Rear u-joint hardware'));
    }

    public function test_cart_line_carries_the_reference_product_and_options(): void
    {
        $r = $this->post('/dsb/submit', $this->build(['am_options' => json_encode(['Rear U-Joint Hardware' => 'Driveshaft & 1310 Strap Kit (GM)'])]))->assertOk();
        $this->assertSame(444.27, $r->json('data.price.final'));
        $line = collect(app(CartService::class)->items())->last();
        $this->assertSame('3S1310-L_1-0024', $line['meta']['price']['breakdown']['variant']['sku']);
        $this->assertSame('Driveshaft & 1310 Strap Kit (GM)', $line['meta']['config']['am_options']['Rear U-Joint Hardware']);
        // the shop sees what was picked, in our words, and a supplier build is all new parts
        $this->assertSame('new', $line['meta']['config']['price_tier']);
        $rows = collect(\App\Support\BuildConfig::rows($line['meta']['config']))->pluck('value', 'label');
        $this->assertSame('Driveshaft & 1310 Strap Kit (GM)', $rows['U-joint hardware kit']);
        $this->assertSame('Standard build, new slip yoke included', $rows['Slip yoke']);
        $this->assertSame('Brand new', $rows['Component grade']);
        $this->assertSame([], \App\Http\Controllers\DsbController::buildOptionRows(['Title' => 'Default Title']));
    }

    public function test_sync_guard_rail_and_missing_variants(): void
    {
        Http::fake(['supplier.test/products/3s1310-l.js' => Http::response([
            'title' => 'Slip yoke shaft 3 in steel 1310', 'options' => [['name' => 'Rear U-Joint Hardware', 'values' => ['Driveshaft Only']], ['name' => 'Driveshaft Build Options', 'values' => ['No Change']]],
            'variants' => [
                ['id' => 1, 'sku' => '3S1310-L', 'option1' => 'Driveshaft Only', 'option2' => 'No Change', 'option3' => null, 'price' => 52000, 'available' => true],       // +1.6 %: applied
                ['id' => 2, 'sku' => '3S1310-L_1-0024', 'option1' => 'Driveshaft & 1310 Strap Kit (GM)', 'option2' => 'No Change', 'option3' => null, 'price' => 99000, 'available' => true], // +89 %: skipped
            ],
        ]), 'supplier.test/products/35s1310-l.js' => Http::response('', 404), 'supplier.test/products/5a1555-b.js' => Http::response(['title' => 'x', 'options' => [], 'variants' => []])]);
        $this->artisan('builder:sync-prices')->assertSuccessful();
        $t = app(AmPricing::class)->table();
        $this->assertSame(520.0, $t['products']['3s1310-l']['variants']['1']['am_price']); $this->assertSame(442.0, $t['products']['3s1310-l']['variants']['1']['our_price']);
        $this->assertSame(522.67, $t['products']['3s1310-l']['variants']['2']['am_price']); // guard rail: unchanged
        $this->assertFalse($t['products']['3s1310-l']['variants']['3']['available']);          // gone from the feed: kept, unavailable
        $this->assertFalse($t['products']['35s1310-l']['available']);                           // 404: product unavailable, never deleted
        $this->assertArrayHasKey('5', $t['products']['5a1555-b']['variants']);
        $this->assertNotNull($t['synced_at']);
    }

    public function test_product_pages_are_gone(): void
    {
        // removed before launch (owner, 2026-11-10): old links open the builder, nothing is in the sitemap
        $this->get('/online-builder/p/3s1310-l')->assertRedirect('/online-builder');
        $this->assertFileDoesNotExist(app_path('Seo/Sources/BuilderProducts.php'));
    }
}
