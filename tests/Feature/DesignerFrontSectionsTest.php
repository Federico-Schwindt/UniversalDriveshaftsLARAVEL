<?php

namespace Tests\Feature;

use App\Http\Controllers\DesignerController;
use Tests\TestCase;

/** Owner, 2026-11-14: "from driveshaft designer remove from the front section 10Q 9Q 13 16 24 27 28 30 47Q 41 43 44 48 on one piece". */
class DesignerFrontSectionsTest extends TestCase
{
    public function test_one_piece_front_hides_the_removed_sections(): void
    {
        $one = $this->get('/driveshaft-designer/1')->assertOk()->viewData('options');
        foreach (DesignerController::HIDDEN_FRONT_ONE_PIECE as $code) $this->assertNotContains($code, $one, $code);
        $this->assertContains('01', $one);
        $this->assertContains('10', $one, '10 stays; only 10Q goes');
        // two piece keeps them
        $this->assertContains('10Q', $this->get('/driveshaft-designer/2')->assertOk()->viewData('options'));
    }
}
