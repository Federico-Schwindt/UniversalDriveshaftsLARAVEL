<?php

namespace Tests\Feature;

use App\Services\DsbCatalog;
use App\Support\YokeMakes;
use Tests\TestCase;

/**
 * Owner, 2026-11-11: when a new flange yoke is chosen, ask the vehicle make first and filter the yokes by it
 * ("ford, toyota, etc… pero debe ser muy robusto"); no driveshaft photo above the review drawing; a start over button.
 */
class BuilderVehicleMakeTest extends TestCase
{
    public function test_a_make_counts_only_when_the_text_says_the_part_is_for_it(): void
    {
        $this->assertSame(['jeep'], YokeMakes::detect('Fits Jeep Commander/ Grand Cherokee at differential'));
        $this->assertSame(['ford'], YokeMakes::detect('Proprietary to Ford. Use 4102-1709'));
        $this->assertSame(['dodge'], YokeMakes::detect('Does not fit Demon/Demon170 but fits most other Charger/Challengers/300'));
        $this->assertSame(['gm', 'dodge', 'bmw'], YokeMakes::detect('108mm CVJ conversion, multiple apps: GM CTS-V, Mopar Charger/Challenger/300/etc. BMW'));
        $this->assertSame(['mercedes'], YokeMakes::detect('Sprinter thru 2006'));
        // another part's make, an engine in a boat, a UTV, a "not for"
        $this->assertSame([], YokeMakes::detect('Use 3102-50 for GM Astro Van AWD and Oldsmobile'));
        $this->assertSame([], YokeMakes::detect('Bolts to flywheel in boat applications with GM 6.0L engines'));
        $this->assertSame([], YokeMakes::detect('Fits up to early 90s Polaris Ranger'));
        $this->assertSame([], YokeMakes::detect('Not for Toyota'));
        $this->assertSame([], YokeMakes::detect('Flange Yokes – 1350 Series – Bolt Circle Dia 3.750"'));
    }

    public function test_only_the_parts_own_data_is_read_never_the_description(): void
    {
        $byId = YokeMakes::forCategory(DsbCatalog::FLANGE_CAT_ID);
        $id = fn (string $sku) => (int) \DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', $sku)->value('post_id');
        // its description says pilots like it are "standard on many Volkswagen, BMW, and Mercedes differentials"; PTI's note says Subaru
        $this->assertSame(['subaru'], $byId[$id('1202-29')] ?? null);
        // "Fits Jeep Commander/ Grand Cherokee at differential" is PTI's note only
        $this->assertSame(['jeep'], $byId[$id('3102-216')] ?? null);
        // "This 1350 series Ford flange fits the GM full size vans" (PTI's sales story): GM, the Ford is the flange's style
        $this->assertSame(['gm'], $byId[$id('3502-280')] ?? null);
        $this->assertSame([], YokeMakes::detect('This 1350 series Ford flange fits'));
        // N2-2-1050's own note points GM Astro buyers at 3102-50; GM comes from its same part in PTI (2102-50, "This Oldsmobile flange")
        $this->assertSame([], YokeMakes::detect('Use 3102-50 for GM Astro Van AWD and Oldsmobile Bravada'));
        $this->assertSame(['gm'], $byId[$id('N2-2-1050U')] ?? null);
    }

    /** Action Machine's vehicle tags count only when the part number, the series and the bolt circle agree. */
    public function test_supplier_vehicle_tags_need_the_same_number_series_and_bolt_circle(): void
    {
        $file = storage_path('app/' . \App\Console\Commands\BuilderSyncAmYokes::FILE);
        $this->assertFileExists($file);
        $src = YokeMakes::sources(DsbCatalog::FLANGE_CAT_ID);
        $am = collect(json_decode(file_get_contents($file), true)['items'])->filter(fn ($i) => $i['makes']);
        $w = \DB::connection('wp');
        $n = fn ($s) => strtoupper(preg_replace('/[\s\-]+/', '', (string) $s));
        $checked = 0;
        foreach ($src as $pid => $makes) {
            foreach ($makes as $make => $from) {
                if ($from !== ['am']) continue;
                $sku = $n($w->table('wp_postmeta')->where('post_id', $pid)->where('meta_key', '_sku')->value('meta_value'));
                $series = $w->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
                    ->where('tr.object_id', $pid)->where('tt.taxonomy', 'pa_series')->value('t.name');
                $hit = $am->first(fn ($i) => in_array($make, $i['makes'], true) && array_intersect(array_map($n, $i['numbers']), [$sku, preg_replace('/U$/', '', $sku)])
                    && str_contains(strtoupper((string) $series), strtoupper($i['series'])));
                $this->assertNotNull($hit, "$sku $make");
                $checked++;
            }
        }
        $this->assertGreaterThan(0, $checked);
    }

    public function test_the_yoke_list_filters_by_make_and_counts_match(): void
    {
        $all = $this->getJson('/dsb/yokes?type=flange&series=1350&per_page=48&filters={}')->assertOk()->json('data');
        $this->assertSame($all['total'], $all['series_total']);
        $this->assertNotEmpty($all['makes']);
        $byId = YokeMakes::forCategory(DsbCatalog::FLANGE_CAT_ID);
        foreach ($all['makes'] as $m) {
            $this->assertSame(YokeMakes::label($m['key']), $m['label']);
            $one = $this->getJson('/dsb/yokes?type=flange&series=1350&per_page=48&filters={}&make=' . $m['key'])->json('data');
            $this->assertSame($m['count'], $one['total'], $m['key']);
            $this->assertSame($all['series_total'], $one['series_total'], 'the "see all" count stays the series total');
            foreach ($one['products'] as $p) $this->assertContains($m['key'], $byId[$p['id']] ?? [], $p['sku']);
        }
        // "all", an unknown make or a hand-made value change nothing
        foreach (['all', 'tesla', '<x>'] as $v) $this->assertSame($all['total'], $this->getJson('/dsb/yokes?type=flange&series=1350&filters={}&make=' . urlencode($v))->json('data.total'));
        // a saved build restores its yoke by id whatever make is asked
        $pid = $all['products'][0]['id'];
        $this->assertSame($pid, $this->getJson("/dsb/yokes?type=flange&id=$pid&make=" . collect($all['makes'])->first(fn ($m) => !in_array($m['key'], $byId[$pid] ?? [], true))['key'])->json('data.products.0.id'));
        // slip yokes keep their spline filter and carry no makes
        $this->assertSame([], $this->getJson('/dsb/yokes?type=trans&series=1350&filters={}')->json('data.makes'));
    }

    public function test_the_builder_page_has_the_make_steps_start_over_and_no_review_photo(): void
    {
        $html = $this->get('/online-builder')->assertOk()->getContent();
        $this->assertStringContainsString('data-step="rear_brand"', $html);
        $this->assertStringContainsString('data-step="front_brand"', $html);
        $this->assertStringContainsString('data-restart', $html);
        $this->assertStringNotContainsString('dsb-review-photo', $html);
        $js = file_get_contents(public_path('js/dsb.js'));
        $this->assertStringNotContainsString('photo of a shaft we build', $js);
        $this->assertStringContainsString("s.push('rear_brand')", $js);
        $this->assertStringContainsString("s.push('front_brand')", $js);
        // owner, 2026-11-11: "i cant continue if i put a coma" — a number input drops "52,5"; text + decimal keyboard, comma read as a point
        foreach (['dsb-inp-a1', 'dsb-inp-c1', 'dsb-speed-ratio', 'dsb-speed-tire'] as $f) $this->assertMatchesRegularExpression('/<input type="text"[^>]*id="' . $f . '"[^>]*inputmode="decimal"/', $html, $f);
        $this->assertStringContainsString('state[f] = decimal(e.target.value)', $js);
        $this->assertStringContainsString('state.speed[k] = decimal(e.target.value)', $js);
    }
}
