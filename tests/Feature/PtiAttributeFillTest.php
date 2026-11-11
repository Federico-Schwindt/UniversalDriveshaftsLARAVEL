<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use App\Services\PtiAttributeFill;
use App\Support\PartGlossary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PTI's measurements copied onto our same parts (owner, 2026-11-11: "si el sku es el mismo pero el nuestro termina en U
 * entonces es lo mismo… copia todos los atributos bien completos") without reshaping a category table.
 */
class PtiAttributeFillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_part_means_the_same_number_or_ours_plus_u(): void
    {
        $this->assertTrue(PtiAttributeFill::samePartNumber('5-1330X', '5-1330X'));
        $this->assertTrue(PtiAttributeFill::samePartNumber('5-1330XU', '5-1330X'));
        $this->assertTrue(PtiAttributeFill::samePartNumber('3R 2-21', '3R2-21'));
        $this->assertFalse(PtiAttributeFill::samePartNumber('5-1330XUU', '5-1330X'));
        $this->assertFalse(PtiAttributeFill::samePartNumber('N5-1330X', '5-1330X'));
        $this->assertFalse(PtiAttributeFill::samePartNumber('', '5-1330X'));
    }

    public function test_values_are_stored_in_our_words(): void
    {
        $this->assertSame('21.5°', PtiAttributeFill::clean('joint_angle', '21.500'));
        $this->assertSame('23/24 Slip Yokes', PtiAttributeFill::clean('use_with', '23/24 PTI Slip Yokes'));
        $this->assertSame('2716-497, 2715-285, 2714-612', PtiAttributeFill::clean('boot', "2716-497,\t2715-285, 2714-612"));
        $this->assertSame('INCLUDED', PtiAttributeFill::clean('gasket', 'Y'));
        $this->assertSame('Not Sold Separately', PtiAttributeFill::clean('gasket', 'Not Sold Sepretley'));
        $this->assertSame('1330F, 3R, 7290', PtiAttributeFill::clean('series', '1330F/3R/7290'));
        $this->assertSame('Y', PtiAttributeFill::clean('lube', 'Yes'));
        $this->assertSame('0.512', PtiAttributeFill::clean('stud_bore_diam', '.512'));
        $this->assertSame('', PtiAttributeFill::clean('hex', '0.000'));
        $this->assertSame('', PtiAttributeFill::clean('gasket', 'N/A'));
    }

    public function test_a_part_whose_measurements_differ_is_left_alone(): void
    {
        ['changes' => $changes, 'skipped' => $skipped] = app(PtiAttributeFill::class)->plan();
        // 5-330X: our second cap width 5.531, PTI's 5.844, so it may not be the same joint
        $this->assertContains('5-330X', array_column($skipped, 'sku'));
        foreach ($changes as $c) {
            $this->assertNotContains($c['sku'], array_column($skipped, 'sku'));
            // values we have are never replaced (a blank / 0 placeholder is not a value); only a kit's series list may widen
            $blank = !$c['old'] || preg_match('/^(0+(\.0+)?|N\/A)$/i', implode(' ', $c['old']));
            $this->assertTrue($blank || ($c['tax'] === 'pa_series' && in_array($c['type'], ['small:strap-kits', 'small:ubolt-kits'], true)), "{$c['sku']} {$c['tax']}");
            // our "U" parts keep their own lube
            if ($c['match'] === 'sku_u') $this->assertNotContains($c['tax'], ['pa_lube', 'pa_greasable']);
        }
    }

    public function test_category_tables_keep_their_columns(): void
    {
        $cat = app(CatalogService::class);
        foreach (config('catalog_columns') as $slug => $pinned) {
            $cols = array_column($cat->categorySchema($slug)['columns'], 'key');
            $dims = array_values(array_diff($cols, ['pa_series', 'pa_brand', 'pa_style', 'pa_notes']));
            // the table's own columns come first, in their order (the "how to measure" letters follow them)
            $this->assertSame(array_slice($pinned, 0, count(array_intersect($pinned, $dims))), array_slice($dims, 0, count(array_intersect($pinned, $dims))), $slug);
            $this->assertSame([], array_values(array_intersect($cols, CatalogService::DETAIL_ATTRS)), "$slug shows a detail-only attribute as a column");
        }
    }

    public function test_copied_details_read_well_on_the_product_page(): void
    {
        $p = app(CatalogService::class)->productBySku('10-2-29');                  // Spicer 1000 flange yoke, linked to PTI by SKU
        $this->assertSame('M', $p['specs']['Pilot Type'] ?? null);
        $this->assertSame('LD', $p['specs']['Class'] ?? null);
        $this->assertSame('Light duty', PartGlossary::specValue('Class', 'LD'));
        $this->assertSame('No', PartGlossary::specValue('Vented', 'No'));
        $this->assertSame('Not sold separately', PartGlossary::specValue('U Bolt No', 'N.S.S.'));
        $this->assertSame('OEM manufacturer', PartGlossary::specLabel('Oem Manufacturer'));
        $this->assertSame('U-bolt no.', PartGlossary::specLabel('U Bolt No'));
        $this->get('/parts/10-2-29')->assertOk()->assertSee('Swing diameter')->assertSee('Light duty');
    }

    /** owner, 2026-11-11: "Cl To washer no aparece en el drawing" */
    public function test_copied_measurements_have_a_callout_on_the_yoke_drawings(): void
    {
        $trans = \App\Support\Measure\Diagram::for('transmission-yokes', ['pa_hub-dia', 'pa_cl-to-washer', 'pa_counter-bore-depth', 'pa_hub-length']);
        $this->assertSame(['pa_hub-dia' => 'A', 'pa_cl-to-washer' => 'B', 'pa_counter-bore-depth' => 'C', 'pa_hub-length' => 'D'], $trans['codes']);
        $this->assertArrayHasKey('pa_cl-to-washer', \App\Support\Measure\Diagram::for('slip-yoke', ['pa_cl-to-washer'])['codes']);
        $this->get('/parts/1203-18')->assertOk()->assertSee('data-dim="pa_cl-to-washer"', false);
        // "agrégalas… añade más vistas de ser necesario pero muy profesional": end to radius, hole to hole, and a
        // max. joint angle view under every yoke family that lists one (double cardan heads drawn as such)
        $this->assertArrayHasKey('pa_end-to-radius', \App\Support\Measure\Diagram::for('stub-midship-shaft', ['pa_end-to-radius'])['codes']);
        $this->assertArrayHasKey('pa_end-to-radius', \App\Support\Measure\Diagram::for('yoke-shafts', ['pa_end-to-radius'])['codes']);
        $this->assertArrayHasKey('pa_hole-to-hole', \App\Support\Measure\Diagram::for('tri-quad-prongs', ['pa_hole-to-hole'])['codes']);
        foreach (['flange-yokes', 'weld-yoke', 'ball-stud-yokes', 'slip-yoke', 'transmission-yokes', 'yoke-shafts', 'h-yokes', 'cv-double-joints-heads'] as $cat) {
            $with = \App\Support\Measure\Diagram::for($cat, ['pa_series', 'pa_joint-angle'], null, '', ['pa_joint-angle' => '26°']);
            $this->assertArrayHasKey('pa_joint-angle', $with['codes'], $cat);
            $this->assertStringContainsString('Max. joint angle', $with['svg'], $cat);
            // no angle on the part: no extra view, the drawing keeps its size
            $without = \App\Support\Measure\Diagram::for($cat, ['pa_series']);
            $this->assertStringContainsString('viewBox="0 0 480 270"', $without['svg'], $cat);
        }
        $this->get('/parts/3190-181')->assertOk()->assertSee('data-dim="pa_joint-angle"', false);
    }
}
