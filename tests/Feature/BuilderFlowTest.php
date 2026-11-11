<?php

namespace Tests\Feature;

use App\Http\Controllers\DsbController;
use App\Services\CartService;
use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use App\Support\BuildConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Online builder rebuild (Nov 2026): the question tree, the end options (new / good used / reuse mine), the
 * u-joint hardware, the length limit and what reaches the cart line.
 */
class BuilderFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        // these cases cover the parts-priced path (builds the supplier does not sell), so the supplier table is empty here;
        // supplier-priced builds are BuilderReferencePricingTest's job
        $none = new class extends \App\Services\AmPricing { public function __construct() {} public function table(): array { return ['products' => [], 'markdown' => self::MARKDOWN]; } };
        app()->instance(\App\Services\AmPricing::class, $none);
        app()->forgetInstance(DsbPricing::class);
        app()->bind(DsbPricing::class, fn () => new DsbPricing(app(DsbCatalog::class), $none));
    }

    private function build(array $over = []): array
    {
        return $over + [
            'pieces' => 'one', 'family' => 'L', 'answers' => json_encode([['q' => 'Transmission make', 'a' => 'GM'], ['q' => 'Transmission model', 'a' => '4L60E 27 spline']]),
            'duty_key' => 'light_medium', 'series_key' => '1350', 'material' => 'steel', 'price_tier' => 'certified',
            'front_app' => 'trans', 'rear_app' => 'flange', 'a1' => 52.25, 'c1' => 40,
            'front_yoke_kind' => 'own', 'rear_yoke_kind' => 'own', 'hardware_kind' => 'none', 'front_spline' => '27',
        ];
    }

    /** A published product of a category, for the "new part" cases. */
    private function firstOf(int $cat): int
    {
        return (int) \DB::connection('wp')->table('wp_posts as p')->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->whereColumn('tr.object_id', 'p.ID')->where('tt.taxonomy', 'product_cat')->where('tt.term_id', $cat))
            ->join('wp_postmeta as m', fn ($j) => $j->on('m.post_id', '=', 'p.ID')->where('m.meta_key', '_price'))->where('m.meta_value', '>', 0)->orderBy('p.ID')->value('p.ID');
    }

    public function test_tree_file_is_consistent(): void
    {
        $flow = DsbController::flow();
        $this->assertNotEmpty($flow['nodes']['start']['options']);
        $settings = app(DsbPricing::class)->settings();
        foreach ($flow['nodes'] as $id => $node) {
            foreach ($node['options'] as $o) {
                if (isset($o['next'])) $this->assertTrue(isset($flow['nodes'][$o['next']]) || $o['next'] === 'heavy', "$id -> {$o['next']}");
                if (isset($o['leaf'])) $this->assertArrayHasKey($o['leaf'], $flow['families'], "$id -> leaf {$o['leaf']}");
            }
        }
        foreach ($flow['families'] as $k => $f) {
            $this->assertArrayHasKey($f['front_app'], $settings['front_apps'], "family $k front_app");
            foreach ($f['series'] as $s) $this->assertArrayHasKey($s, $settings['tree']['light_medium']['series'], "family $k series $s");
        }
    }

    public function test_own_yoke_costs_nothing_and_used_yoke_uses_the_series_map(): void
    {
        $p = app(DsbPricing::class);
        $own = $p->compute($this->build(['front_yoke' => ['kind' => 'own'], 'rear_yoke' => ['kind' => 'own']]));
        $used = $p->compute($this->build(['front_yoke' => ['kind' => 'used'], 'rear_yoke' => ['kind' => 'used']]));
        $this->assertSame(0.0, $own['front_addon']); $this->assertSame(0.0, $own['rear_addon']);
        $this->assertSame(45.0, $used['front_addon']); $this->assertSame(45.0, $used['rear_addon']);
        $this->assertSame($own['final'] + 90, $used['final']);
        $this->assertSame('2.75"', $own['breakdown']['tube_od']);
        $this->assertSame(65.0, $own['breakdown']['max_length']);
    }

    public function test_new_yoke_and_hardware_are_priced_from_the_product(): void
    {
        $cat = app(DsbCatalog::class);
        $yoke = $this->firstOf(DsbCatalog::TRANS_CAT_ID); $kit = $this->firstOf(DsbCatalog::STRAP_CAT_ID);
        $r = $this->post('/dsb/quote', $this->build(['front_yoke_kind' => 'new', 'front_yoke_product_id' => $yoke, 'rear_app' => 'weld', 'hardware_kind' => 'strap', 'hardware_product_id' => $kit]));
        $r->assertOk();
        $this->assertEquals($cat->price($yoke), $r->json('data.front_addon'));
        $this->assertEquals($cat->price($kit), $r->json('data.hardware_addon'));
        $this->assertEquals(370 + $cat->price($yoke) + $cat->price($kit), $r->json('data.final'));
        $this->assertSame(0, (int) $r->json('data.rear_addon'));
    }

    public function test_unknown_or_wrong_category_ids_are_refused(): void
    {
        $flange = $this->firstOf(DsbCatalog::FLANGE_CAT_ID);
        // a flange yoke on a transmission (slip) end, a kit id that is a yoke, a yoke id that does not exist
        $this->post('/dsb/quote', $this->build(['front_yoke_kind' => 'new', 'front_yoke_product_id' => $flange]))->assertStatus(422);
        $this->post('/dsb/quote', $this->build(['rear_app' => 'weld', 'hardware_kind' => 'strap', 'hardware_product_id' => $flange]))->assertStatus(422);
        $this->post('/dsb/quote', $this->build(['rear_yoke_kind' => 'new', 'rear_yoke_product_id' => 999999999]))->assertStatus(422);
        // a used yoke where the map prices it at 0 (1760) is not offered
        $this->post('/dsb/quote', $this->build(['duty_key' => 'heavy', 'subcat_key' => 'hd_1610_1810', 'series_key' => '1760', 'front_app' => 'flange', 'front_yoke_kind' => 'used']))->assertStatus(422);
        // a weld-yoke end carries no yoke choice; hardware only on a weld rear
        $r = $this->post('/dsb/quote', $this->build(['front_app' => 'weld', 'front_yoke_kind' => 'used', 'hardware_kind' => 'strap', 'hardware_product_id' => 15439]));
        $r->assertOk(); $this->assertSame(0, (int) $r->json('data.front_addon')); $this->assertSame(0, (int) $r->json('data.hardware_addon'));
    }

    public function test_c1_is_required_and_not_longer_than_a1(): void
    {
        $this->post('/dsb/submit', $this->build(['c1' => 0]))->assertStatus(422)->assertJsonFragment(['Please enter C1 (u-joint center to u-joint center).']);
        $this->post('/dsb/submit', $this->build(['c1' => 60]))->assertStatus(422)->assertJsonFragment(['C1 cannot be longer than A1. Please check both measurements.']);
        $this->post('/dsb/submit', $this->build(['c1' => 52.25]))->assertOk();
        // u-joint at both ends with the slip joint in the shaft: C1 is A1, so it is not asked
        $this->post('/dsb/submit', $this->build(['family' => 'UJ', 'front_app' => 'slip_no_flange', 'rear_app' => 'weld', 'c1' => 0, 'front_yoke_kind' => 'none']))->assertOk();
    }

    public function test_length_over_the_material_limit_is_refused_at_submit(): void
    {
        $r = $this->post('/dsb/submit', $this->build(['a1' => 70]));
        $r->assertStatus(422);
        $this->assertStringContainsString('65', $r->json('errors.0'));
        $this->post('/dsb/submit', $this->build(['a1' => 70, 'material' => 'aluminum']))->assertOk();
        $this->post('/dsb/submit', $this->build(['a1' => 0]))->assertStatus(422);
    }

    public function test_every_answer_reaches_the_cart_line_and_the_order_rows(): void
    {
        $kit = $this->firstOf(DsbCatalog::STRAP_CAT_ID);
        $r = $this->post('/dsb/submit', $this->build(['rear_app' => 'weld', 'hardware_kind' => 'strap', 'hardware_product_id' => $kit, 'front_trans_model' => '4L60E', 'series_variant' => 'x', 'notes' => '<b>lifted 4 in</b>', 'quantity' => 2]));
        $r->assertOk()->assertJsonPath('success', true);
        $this->assertStringStartsWith('Custom 1350 series steel driveshaft, transmission yoke to weld yoke (open u-joint), 52.25 in', $r->json('data.title'));
        $line = collect(app(CartService::class)->items())->last();
        $this->assertSame('custom_build', $line['type']); $this->assertSame(2, $line['qty']);
        $cfg = $line['meta']['config'];
        $this->assertSame('L', $cfg['family']); $this->assertSame('4L60E 27 spline', $cfg['answers'][1]['a']);
        $this->assertSame('', $cfg['series_variant']); // not a variant the tree offers for 1350
        $this->assertSame('own', $cfg['front_yoke']['kind']); $this->assertSame('27', $cfg['front_yoke']['spline']); $this->assertSame('4L60E', $cfg['front_yoke']['model']);
        $this->assertSame('strap', $cfg['hardware']['kind']); $this->assertSame($kit, $cfg['hardware']['product_id']);
        $this->assertSame('2.75"', $cfg['tube_od']); $this->assertSame('lifted 4 in', $cfg['notes']);
        $rows = collect(BuildConfig::rows($cfg))->mapWithKeys(fn ($r) => [$r['label'] => $r['value']]);
        $this->assertSame('GM', $rows['Transmission make']); $this->assertSame('4L60E 27 spline', $rows['Transmission model']);
        $this->assertStringContainsString('27 splines, 4L60E', $rows['Front yoke']); $this->assertStringStartsWith("Customer's own yoke", $rows['Front yoke']);
        $this->assertStringStartsWith('Strap kit:', $rows['Rear u-joint hardware']);
        $this->assertSame('52.25"', $rows['Measurement A1']); $this->assertSame('2.75"', $rows['Tube OD']);
        $this->assertSame('Two-piece', collect(BuildConfig::rows(['pieces' => 'two']))->firstWhere('label', 'Configuration')['value']);
    }

    public function test_yoke_catalog_filters_by_spline_count_and_lists_hardware_for_a_series(): void
    {
        $r = $this->get('/dsb/yokes?type=trans&series=1350&spline=27&per_page=5')->assertOk();
        $this->assertGreaterThan(0, $r->json('data.total'));
        foreach ($r->json('data.products') as $p) $this->assertMatchesRegularExpression('/(^|\/)27([ \/Xx]|$)/', $p['attributes']['splines-count-dia'] ?? '');
        $this->assertContains(27, $r->json('data.spline_counts'));
        $h = $this->get('/dsb/yokes?type=strap&series=1350')->assertOk();
        $this->assertTrue($h->json('data.matched_series'));
        foreach ($h->json('data.products') as $p) $this->assertStringContainsString('1350', $p['attributes']['series'] ?? '');
        $this->get('/dsb/yokes?type=rocket')->assertStatus(400);
        $this->get('/dsb/premades')->assertStatus(404);
    }

    public function test_critical_speed_uses_the_standard_tube_formula(): void
    {
        $sp = DsbPricing::speedCheck('3"', 'steel', 58, ['mph' => 85, 'ratio' => 3.73, 'tire' => 31]);
        $id = 3 - 2 * 0.083;
        $this->assertSame((int) round(4705000 * sqrt(9 + $id * $id) / (58 * 58)), $sp['critical_rpm']);
        $this->assertSame((int) round($sp['critical_rpm'] * 0.75), $sp['safe_rpm']);
        $this->assertSame((int) round(85 * 3.73 * 336 / 31), $sp['shaft_rpm']);
        $this->assertSame('ok', $sp['status']);
        $this->assertSame('bad', DsbPricing::speedCheck('2"', 'steel', 64, ['mph' => 120, 'ratio' => 4.56, 'tire' => 28])['status']);
        $this->assertNull(DsbPricing::speedCheck('', 'steel', 58, [])); // no tube yet
        // the inputs travel with the quote and the order line; implausible numbers are dropped
        $r = $this->post('/dsb/quote', $this->build(['speed_mph' => 85, 'axle_ratio' => 3.73, 'tire_in' => 31]))->assertOk();
        $this->assertSame('ok', $r->json('data.breakdown.speed.status'));
        $r = $this->post('/dsb/submit', $this->build(['speed_mph' => 9999, 'axle_ratio' => 3.73, 'tire_in' => 31]))->assertOk();
        $line = collect(app(CartService::class)->items())->last();
        $this->assertNull($line['meta']['config']['speed_check']['status']);
        $this->assertStringContainsString('critical', collect(BuildConfig::rows($line['meta']['config']))->firstWhere('label', 'Critical speed check')['value']);
    }

    public function test_builder_page_renders_the_tree(): void
    {
        $this->get('/online-builder')->assertOk()->assertSee('What does the shaft connect to', false)->assertSee('"families"', false)->assertDontSee('dsb-premade', false);
        // the two-piece builder was removed (owner, 2026-11-10): its URL opens the one-piece builder
        $this->get('/online-builder/two-piece')->assertRedirect('/online-builder');
    }
}
