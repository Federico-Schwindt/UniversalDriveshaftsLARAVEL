<?php

namespace Tests\Feature;

use App\Services\PackingService;
use App\Support\ShaftWeight;
use Tests\TestCase;

/**
 * Owner, 2026-11-11: a 55" 1350 aluminum shaft from the online builder paid $12.63 UPS Ground ("no tiene sentido").
 * A builder shaft is no product, so it shipped as the 15 lb, 48 x 8 x 8 placeholder whatever its length; it is now
 * weighed and boxed from its own build.
 */
class BuilderShippingWeightTest extends TestCase
{
    private function cfg(array $over = []): array
    {
        return $over + [
            'series_key' => '1350', 'material' => 'aluminum', 'a1' => 55.0, 'tube_od' => '4"',
            'front_app' => 'trans', 'rear_app' => 'flange',
            'front_yoke' => ['kind' => 'none', 'product_id' => 0], 'rear_yoke' => ['kind' => 'new', 'product_id' => 0], 'hardware' => ['kind' => 'none', 'product_id' => 0],
        ];
    }

    public function test_the_box_is_as_long_as_the_shaft_and_the_weight_counts_tube_and_ends(): void
    {
        $e = ShaftWeight::estimate($this->cfg());
        $this->assertSame([59.0, 7.5, 7.5], $e['dims']);
        $this->assertGreaterThanOrEqual(25, $e['weight']);
        $this->assertLessThanOrEqual(32, $e['weight']);
        // steel weighs more than aluminum, a longer shaft more than a shorter one, a heavier series more than a lighter one
        $this->assertGreaterThan($e['weight'], ShaftWeight::estimate($this->cfg(['material' => 'steel', 'tube_od' => '3.5"']))['weight']);
        $this->assertGreaterThan($e['weight'], ShaftWeight::estimate($this->cfg(['a1' => 80.0]))['weight']);
        $this->assertGreaterThan($e['weight'], ShaftWeight::estimate($this->cfg(['series_key' => '1550']))['weight']);
        $this->assertSame(84.0, ShaftWeight::estimate($this->cfg(['a1' => 80.0]))['dims'][0]);
        // the critical-speed check's tube wins over the label when both are there
        $thin = ShaftWeight::estimate($this->cfg(['material' => 'steel', 'speed_check' => ['tube_od' => 3.0, 'wall' => 0.065]]));
        $thick = ShaftWeight::estimate($this->cfg(['material' => 'steel', 'speed_check' => ['tube_od' => 3.0, 'wall' => 0.12]]));
        $this->assertGreaterThan($thin['weight'], $thick['weight']);
        $this->assertNull(ShaftWeight::estimate($this->cfg(['a1' => 0])));
    }

    public function test_the_checkout_quotes_a_builder_shaft_from_its_build_not_the_placeholder(): void
    {
        $pack = app(PackingService::class)->packageSummary([['type' => 'custom_build', 'wp_product_id' => null, 'qty' => 2, 'price' => 919.51, 'meta' => ['config' => $this->cfg()]]]);
        $this->assertCount(2, $pack['boxes']);
        $this->assertSame([59.0, 7.5, 7.5], $pack['boxes'][0]['dims']);
        $this->assertGreaterThan(15, $pack['boxes'][0]['weight']);
        $this->assertEquals($pack['boxes'][0]['weight'] * 2, $pack['weight_lb']);
        // a line that names its own weight (admin order lines, the assistant) keeps it
        $own = app(PackingService::class)->packageSummary([['type' => 'custom_build', 'qty' => 1, 'price' => 1, 'meta' => ['weight_lb' => 40, 'config' => $this->cfg()]]]);
        $this->assertEquals(40, $own['boxes'][0]['weight']);
        // no build (an old line without config) still ships, as before
        $bare = app(PackingService::class)->packageSummary([['type' => 'custom_build', 'qty' => 1, 'price' => 1, 'meta' => []]]);
        $this->assertEquals(15, $bare['boxes'][0]['weight']);
    }
}
