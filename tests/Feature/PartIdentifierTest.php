<?php

namespace Tests\Feature;

use App\Support\PartIdentifier;
use Tests\TestCase;

/** A foreign u-joint number plus two caliper readings must land on a series we sell, in inches or mm. */
class PartIdentifierTest extends TestCase
{
    public function test_measurements_in_inches_or_mm_find_the_series(): void
    {
        $this->assertSame('1310', PartIdentifier::matchUJoint(1.062, 3.219)['match']);
        $this->assertSame('1330', PartIdentifier::matchUJoint(1.062, 3.62)['match']);
        $this->assertSame('1350', PartIdentifier::matchUJoint(PartIdentifier::inches('30.2 mm'), PartIdentifier::inches('92 mm'))['match']);
        $this->assertSame('1410', PartIdentifier::matchUJoint(PartIdentifier::inches('1 3/16'), PartIdentifier::inches('4.19'))['match']);
        $this->assertSame('3R/S44', PartIdentifier::matchUJoint(1.125, 2.556)['match']);
    }

    public function test_cap_alone_lists_the_series_that_share_it_and_asks_for_the_width(): void
    {
        $m = PartIdentifier::matchUJoint(1.062, null);
        $this->assertNull($m['match']);
        $this->assertSame(['1210', '1310', '1330'], array_column($m['candidates'], 'series'));
        $this->assertStringContainsString('width', $m['note']);
    }

    public function test_impossible_cap_asks_to_remeasure(): void
    {
        $m = PartIdentifier::matchUJoint(2.5, 3.0);
        $this->assertNull($m['match']);
        $this->assertSame([], $m['candidates']);
        $this->assertStringContainsString('re-measure', $m['note']);
    }

    public function test_foreign_numbers_are_recognised_and_stripped(): void
    {
        $this->assertSame('u-joint', PartIdentifier::numberHint('04371-AR202-TO')['part_type']);
        $this->assertSame('u-joint', PartIdentifier::numberHint('GUT-21')['part_type']);
        $this->assertSame('slip yoke', PartIdentifier::numberHint('2-3-6061KX')['part_type']);
        $this->assertNull(PartIdentifier::numberHint('ZZ'));
        $this->assertContains('04371-AR202', PartIdentifier::numberVariants('04371-AR202-TO'));
        $this->assertContains('5-153X', PartIdentifier::numberVariants('5-153XU'));
    }

    public function test_inches_parser(): void
    {
        $this->assertSame(1.062, PartIdentifier::inches('1.062'));
        $this->assertSame(1.063, PartIdentifier::inches('27mm'));
        $this->assertSame(1.063, PartIdentifier::inches('1 1/16'));
        $this->assertNull(PartIdentifier::inches(''));
    }
}
