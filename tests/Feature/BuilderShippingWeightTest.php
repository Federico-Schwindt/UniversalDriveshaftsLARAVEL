<?php

namespace Tests\Feature;

use App\Services\PackingService;
use App\Support\ShaftWeight;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner, 2026-11-11: a 55" 1350 aluminum shaft from the online builder paid $12.63 UPS Ground ("no tiene sentido").
 * A builder shaft is no product, so it shipped as the 15 lb, 48 x 8 x 8 placeholder whatever its length; it is now
 * weighed and boxed from its own build.
 */
class BuilderShippingWeightTest extends TestCase
{
    use DatabaseTransactions;

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

    /** A finder shaft PTI gave no weight or carton: owner, 2026-11-11 ("puedes arreglarlo?"). */
    private function part(string $pn, string $desc, ?float $w, ?array $box): void
    {
        DB::table('ds_parts')->updateOrInsert(['part_number' => $pn], ['description' => $desc, 'weight' => $w, 'box_length' => $box[0] ?? null, 'box_width' => $box[1] ?? null,
            'box_height' => $box[2] ?? null, 'supplier' => 'PTI', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_finder_shaft_without_weight_or_carton_is_estimated_from_shafts_like_it(): void
    {
        DB::table('ds_fitments')->whereIn('part_number', ['ZZ-1', 'ZZ-2', 'ZZ-3', 'ZZ-4'])->delete();
        foreach ([['ZZ-P1', 40.0], ['ZZ-P2', 44.0], ['ZZ-P3', 46.0]] as [$pn, $w]) $this->part($pn, 'ZZTEST FLEET 1PC', $w, [59.13, 7.25, 7.38]);
        $this->part('ZZ-P4', 'ZZTEST FLEET 1PC', 20.0, [31.25, 5.25, 5.5]);
        Cache::forget('ds_ship_peers_v1');
        // no weight: the median of the same kind of shaft in the same carton
        $this->part('ZZ-1', 'ZZTEST FLEET 1PC', 0.0, [59.13, 7.25, 7.38]);
        $e = ShaftWeight::finderPart('ZZ-1');
        $this->assertSame([44.0, ['weight']], [$e['weight'], $e['estimated']]);
        // no carton: the carton the same kind of shaft of about this weight ships in
        $this->part('ZZ-2', 'ZZTEST FLEET 1PC', 42.0, null);
        $this->assertSame([59.13, 7.38, 7.25], ShaftWeight::finderPart('ZZ-2')['dims']);
        $this->part('ZZ-3', 'ZZTEST FLEET 1PC', 21.0, null);
        $this->assertSame(31.25, ShaftWeight::finderPart('ZZ-3')['dims'][0]);
        // no carton but a weld-to-weld length: the smallest PTI carton that holds it
        $this->part('ZZ-4', 'ZZTEST OTHER', 30.0, null);
        DB::table('ds_fitments')->insert(['part_number' => 'ZZ-4', 'make' => 'Test', 'year' => 2000, 'model' => 'Test', 'fkey' => 'zz-4-test', 'weld_to_weld' => '55.5']);
        $this->assertSame(71.38, ShaftWeight::finderPart('ZZ-4')['dims'][0]);
        // a boat shaft names its length
        $this->part('ZZ-5', '1350 Boat Shaft, 8.188 flange to end', 12.6, null);
        $this->assertSame([15.0, 6.0, 6.0], ShaftWeight::finderPart('ZZ-5')['dims']);
        // and the checkout uses it instead of the 15 lb, 48 x 8 x 8 placeholder
        $pack = app(PackingService::class)->packageSummary([['type' => 'driveshaft', 'sku' => 'UZZ-1', 'qty' => 1, 'price' => 500, 'meta' => ['part_number' => 'ZZ-1']]]);
        $this->assertEquals(44, $pack['boxes'][0]['weight']);
        $this->assertSame(59.13, $pack['boxes'][0]['dims'][0]);
        // PTI's own figures always win
        $this->assertSame([40.0, []], [ShaftWeight::finderPart('ZZ-P1')['weight'], ShaftWeight::finderPart('ZZ-P1')['estimated']]);
        Cache::forget('ds_ship_peers_v1');
    }
}
