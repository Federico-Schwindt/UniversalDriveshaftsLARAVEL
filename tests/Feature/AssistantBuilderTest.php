<?php

namespace Tests\Feature;

use App\Http\Controllers\DsbController;
use App\Models\User;
use App\Services\CartService;
use App\Services\Chatbot\AssistantBuilder;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatWidgets;
use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UDS Assistant builds a custom driveshaft in the chat (owner, 2026-11-11): the builder's questions in the builder's
 * order, the builder's rules, the builder's price, and the same cart line the builder page makes. Tools run directly.
 */
class AssistantBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private ChatbotService $bot;
    private object $conv;
    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /** Builds the supplier table does not price (our own engine), like BuilderFlowTest. */
    private function partsPricedOnly(): void
    {
        $none = new class extends \App\Services\AmPricing { public function __construct() {} public function table(): array { return ['products' => [], 'markdown' => self::MARKDOWN]; } };
        app()->instance(\App\Services\AmPricing::class, $none);
        app()->forgetInstance(DsbPricing::class);
        app()->bind(DsbPricing::class, fn () => new DsbPricing(app(DsbCatalog::class), $none));
    }

    private function chat(): void
    {
        $this->u = User::query()->create(['name' => 'Sam Rivera', 'email' => 'build-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $this->actingAs($this->u);
        $this->bot = app(ChatbotService::class);
        $this->conv = $this->bot->conversation($this->u);
        (new \ReflectionProperty($this->bot, 'conv'))->setValue($this->bot, $this->conv);
    }

    private function say(string $text): void
    {
        DB::table('chat_messages')->insert(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => json_encode([['type' => 'text', 'text' => $text]]),
            'display' => $text, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function build(array $in): array
    {
        $m = new \ReflectionMethod($this->bot, 'runTool');
        return $m->invoke($this->bot, $this->u, 'driveshaft_builder', $in + ['action' => 'show', 'choice' => '', 'a1' => 0, 'c1' => 0, 'mph' => 0, 'axle_ratio' => 0, 'tire_in' => 0, 'text' => '', 'quantity' => 1])[0];
    }

    /** The option indices from the tree's start to a family leaf (breadth first, so the test follows the tree as it is). */
    private function pathTo(string $leaf): array
    {
        $nodes = DsbController::flow()['nodes'];
        $queue = [['start', []]]; $seen = [];
        while ($queue) {
            [$id, $path] = array_shift($queue);
            if (isset($seen[$id]) || !isset($nodes[$id])) continue;
            $seen[$id] = true;
            foreach ($nodes[$id]['options'] as $i => $o) {
                if (($o['leaf'] ?? null) === $leaf) return array_merge($path, [$i]);
                if (!empty($o['next'])) $queue[] = [$o['next'], array_merge($path, [$i])];
            }
        }
        $this->fail("no path to $leaf");
    }

    private function walkTree(string $leaf): array
    {
        $r = $this->build(['action' => 'start']);
        $this->assertSame('tree', $r['step']);
        foreach ($this->pathTo($leaf) as $i) $r = $this->build(['action' => 'answer', 'choice' => (string) ($i + 1)]);
        return $r;
    }

    public function test_a_guided_build_is_the_same_cart_line_as_the_builder_page(): void
    {
        $this->partsPricedOnly();
        $this->chat();
        $this->say('I need a driveshaft for my truck, GM 4L60E');
        $r = $this->walkTree('L');
        $this->assertSame('series', $r['step']);
        $this->assertContains('1350', array_column($r['options'], 'id'));
        $this->assertStringContainsString('Step 2 of 5', $r['stage']);

        // an option that is not offered is refused, nothing changes
        $r = $this->build(['action' => 'answer', 'choice' => 'SPL250']);
        $this->assertArrayHasKey('problem', $r); $this->assertSame('series', $r['step']);

        $r = $this->build(['action' => 'answer', 'choice' => '1350']);
        if ($r['step'] === 'material') $r = $this->build(['action' => 'answer', 'choice' => 'Steel']);   // the label works too
        $this->assertSame('rear', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'flange']);
        $this->assertSame('rear_yoke', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'own']);
        $this->assertSame('measure', $r['step']);
        $this->assertArrayHasKey('c1', $r['ask']);

        // the builder's measurement rules
        $r = $this->build(['action' => 'measure', 'a1' => 52.25, 'c1' => 0]);
        $this->assertSame('Please enter C1 (u-joint center to u-joint center).', $r['problem']);
        $r = $this->build(['action' => 'measure', 'a1' => 52.25, 'c1' => 60]);
        $this->assertSame('C1 cannot be longer than A1. Please check both measurements.', $r['problem']);
        $r = $this->build(['action' => 'measure', 'a1' => 70, 'c1' => 66]);
        $this->assertStringContainsString('aluminum', $r['problem']);             // steel stops at 65 in, aluminum reaches it
        $r = $this->build(['action' => 'measure', 'a1' => 140, 'c1' => 130]);
        $this->assertStringContainsString('two-piece', $r['problem']);
        $r = $this->build(['action' => 'measure', 'a1' => 52.25, 'c1' => 48]);
        $this->assertSame('speed', $r['step']);
        $this->assertSame('2.75"', $r['tube_od']);

        $r = $this->build(['action' => 'speed', 'mph' => 85, 'axle_ratio' => 3.73, 'tire_in' => 31]);
        $this->assertSame('front_yoke', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'own', 'text' => '4L60E']);
        $this->assertSame('review', $r['step']);
        $this->assertTrue($r['ready']);
        $this->assertStringContainsString('At 85 mph', $r['build']['speed_check']);
        $this->assertSame(['certified', 'new'], array_column($r['component_choice'], 'id'));

        // the price is the builder's own quote for the same answers
        $engine = app(AssistantBuilder::class)->load((int) $this->conv->id);
        $payload = $engine->payload();
        $quote = $this->post('/dsb/quote', $payload)->assertOk()->json('data.final');
        $this->assertEquals($quote, $r['price_usd']);
        $this->assertEquals($quote, $r['build']['price_usd']);

        // not in the same message the price was shown in: the customer says yes first
        $r = $this->build(['action' => 'add_to_cart']);
        $this->assertFalse($r['added']);
        $this->assertSame(0, app(CartService::class)->count());
        $this->say('yes, add it');
        $r = $this->build(['action' => 'add_to_cart', 'text' => 'lifted 2 in']);
        $this->assertTrue($r['added']);
        $this->assertSame(1, $this->bot->cartCount);
        $chatLine = collect(app(CartService::class)->items())->last();

        // the same answers through the builder page's submit make the same line
        app(CartService::class)->clear();
        $this->post('/dsb/submit', ['notes' => 'lifted 2 in'] + $payload)->assertOk();
        $pageLine = collect(app(CartService::class)->items())->last();
        foreach (['type', 'name', 'price', 'image', 'qty'] as $k) $this->assertEquals($pageLine[$k], $chatLine[$k], $k);
        $this->assertEquals($pageLine['meta']['config'], $chatLine['meta']['config']);
        $this->assertEquals($pageLine['meta']['price'], $chatLine['meta']['price']);
        $this->assertSame('4L60E', $chatLine['meta']['config']['front_yoke']['model']);
        $this->assertSame('own', $chatLine['meta']['config']['rear_yoke']['kind']);
    }

    public function test_the_builder_link_opens_the_same_build_and_back_and_go_to_change_it(): void
    {
        $this->partsPricedOnly();
        $this->chat();
        $this->say('build me a shaft');
        $this->walkTree('L');
        $this->build(['action' => 'answer', 'choice' => '1350']);
        $r = $this->build(['action' => 'show']);
        if ($r['step'] === 'material') $this->build(['action' => 'answer', 'choice' => 'steel']);
        $this->build(['action' => 'answer', 'choice' => 'weld']);
        $r = $this->build(['action' => 'show']);
        $this->assertSame('hardware', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'strap']);
        $this->assertSame('hardware', $r['step']);                       // now the kit list
        $kit = $r['options'][0];
        $this->assertStringStartsWith('p', $kit['id']);
        $this->assertGreaterThan(0, $kit['price_usd']);
        $r = $this->build(['action' => 'answer', 'choice' => $kit['id']]);
        $this->assertSame('measure', $r['step']);
        $r = $this->build(['action' => 'measure', 'a1' => 50, 'c1' => 46]);
        $r = $this->build(['action' => 'speed']);                           // all zeros: skipped
        $this->assertSame('front_yoke', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'own']);
        $this->assertTrue($r['ready']);

        parse_str(parse_url($r['builder_link'], PHP_URL_QUERY), $q);
        $this->assertSame(implode('.', $this->pathTo('L')), $q['dsb_p']);
        $this->assertSame(['L', '1350', 'weld', 'strap', '50', '46', 's', 'review'], [$q['dsb_fam'], $q['dsb_series'], $q['dsb_rear'], $q['dsb_hw'], $q['dsb_a1'], $q['dsb_c1'], $q['dsb_seen'], $q['dsb_step']]);
        $this->assertSame(substr($kit['id'], 1), $q['dsb_hwid']);

        // change the series: the kit (series-specific) has to be picked again, the rest stays
        $r = $this->build(['action' => 'go_to', 'choice' => 'series']);
        $this->assertSame('series', $r['step']);
        $other = collect($r['options'])->first(fn ($o) => $o['id'] !== '1350' && !str_contains($o['id'], '|'))['id'];
        $r = $this->build(['action' => 'answer', 'choice' => $other]);
        $this->assertArrayNotHasKey('problem', $r);
        $this->assertSame('hardware', $r['step']);
        $r = $this->build(['action' => 'back']);
        $this->assertSame('rear', $r['step']);
        $this->assertSame('50 in', collect($r['answers'])->firstWhere('q', 'A1')['a']);
    }

    public function test_a_supplier_priced_build_asks_its_options_and_orders_them_in_our_words(): void
    {
        $this->chat();
        $this->say('one-piece shaft for my truck');
        $r = $this->walkTree('PL');
        $r = $this->build(['action' => 'answer', 'choice' => '1310']);
        if ($r['step'] === 'material') $r = $this->build(['action' => 'answer', 'choice' => 'steel']);
        $r = $this->build(['action' => 'answer', 'choice' => 'flange']);
        $r = $this->build(['action' => 'answer', 'choice' => 'own']);
        $r = $this->build(['action' => 'measure', 'a1' => 52.25, 'c1' => 48]);
        $r = $this->build(['action' => 'speed']);
        $this->assertSame('options', $r['step']);
        $this->assertNotContains('Default Title', array_column($r['options'], 'label'));
        while ($r['step'] === 'options') $r = $this->build(['action' => 'answer', 'choice' => $r['options'][0]['id']]);
        $this->assertTrue($r['ready']);
        $this->assertArrayNotHasKey('component_choice', $r);                // supplier builds are all new components
        $payload = app(AssistantBuilder::class)->load((int) $this->conv->id)->payload();
        $this->assertEquals($this->post('/dsb/quote', $payload)->json('data.final'), $r['price_usd']);
        $this->say('yes please');
        $r = $this->build(['action' => 'add_to_cart', 'quantity' => 2]);
        $this->assertTrue($r['added']);
        $line = collect(app(CartService::class)->items())->last();
        $this->assertSame(2, $line['qty']);
        $this->assertSame('new', $line['meta']['config']['price_tier']);
        $this->assertNotEmpty($line['meta']['config']['build_options']);
    }

    /** A supplier combination that is sold out is never offered (the page used to dead-end on it), and the price is that variant's. */
    public function test_sold_out_supplier_combinations_are_not_offered(): void
    {
        $table = ['markdown' => 0.85, 'products' => ['t-1310-pl' => [
            'handle' => 't-1310-pl', 'title' => 'Test 1310', 'families' => ['PL'], 'series' => '1310', 'material' => 'steel', 'od' => 3, 'max_len' => 60, 'available' => true,
            'options' => [['name' => 'Rear U-Joint Hardware', 'values' => ['Driveshaft Only', 'Driveshaft & 1310 U-Bolt Kit']], ['name' => 'Driveshaft Build Options', 'values' => ['No Change', 'I will send in my slip yoke.']]],
            'variants' => [
                1 => ['id' => 1, 'sku' => 'a', 'options' => ['Driveshaft Only', 'No Change'], 'am_price' => 600, 'available' => false],
                2 => ['id' => 2, 'sku' => 'b', 'options' => ['Driveshaft Only', 'I will send in my slip yoke.'], 'am_price' => 500, 'available' => true],
                3 => ['id' => 3, 'sku' => 'c', 'options' => ['Driveshaft & 1310 U-Bolt Kit', 'No Change'], 'am_price' => 620, 'available' => true],
            ],
        ]]];
        $am = new class($table) extends \App\Services\AmPricing { public function __construct(private array $t) {} public function table(): array { return $this->t; } };
        app()->instance(\App\Services\AmPricing::class, $am);
        app()->forgetInstance(DsbPricing::class);
        app()->bind(DsbPricing::class, fn () => new DsbPricing(app(DsbCatalog::class), $am));
        $this->chat();
        $this->say('shaft please');
        $this->walkTree('PL');
        $r = $this->build(['action' => 'answer', 'choice' => '1310']);
        if ($r['step'] === 'material') $r = $this->build(['action' => 'answer', 'choice' => 'steel']);
        $this->build(['action' => 'answer', 'choice' => 'flange']);
        $this->build(['action' => 'answer', 'choice' => 'own']);
        $this->build(['action' => 'measure', 'a1' => 50, 'c1' => 46]);
        $r = $this->build(['action' => 'speed']);
        $this->assertSame('options', $r['step']);
        $r = $this->build(['action' => 'answer', 'choice' => 'No hardware kit']);
        $this->assertSame(['I will send in my slip yoke.'], array_column($r['options'], 'value'));   // "No Change" is sold out with it
        $r = $this->build(['action' => 'answer', 'choice' => 'Standard build, new slip yoke included']);
        $this->assertArrayHasKey('problem', $r);
        $r = $this->build(['action' => 'answer', 'choice' => $r['options'][0]['id']]);
        $this->assertTrue($r['ready']);
        $this->assertEquals(425.0, $r['price_usd']);   // 500 × 0.85
    }

    /** A suggested answer (the material a family suggests) is pre-selected but still asked, like the page shows it. */
    public function test_a_suggested_material_is_still_asked(): void
    {
        $this->chat();
        $this->say('Duramax one-piece');
        $this->walkTree('CV_DMAX');
        $sug = DsbController::flow()['families']['CV_DMAX']['suggest'] ?? [];
        $r = $this->build(['action' => 'answer', 'choice' => (string) ($sug['series'] ?? '1480')]);
        $this->assertSame('material', $r['step']);
        if (!empty($sug['material'])) $this->assertStringContainsString('Typical for this vehicle', collect($r['options'])->firstWhere('id', $sug['material'])['note']);
        $r = $this->build(['action' => 'answer', 'choice' => 'steel']);
        $this->assertNotSame('material', $r['step']);
        $this->assertSame('steel', app(AssistantBuilder::class)->load((int) $this->conv->id)->state()['material']);
    }

    /** /dsb/yokes for a series with no yokes listed answered 500, then a broken cached object (the empty filter list is {} in the JSON). */
    public function test_yoke_lookup_for_a_series_without_yokes(): void
    {
        // twice: the second answer comes from the cache, which only restores arrays
        foreach ([1, 2] as $i) $this->assertStringContainsString('"filters":{}', $this->get('/dsb/yokes?type=flange&series=68735&page=1&per_page=1&filters=%7B%7D')->assertOk()->assertJsonPath('data.total', 0)->getContent());
    }

    public function test_widgets_tool_schema_and_a_fresh_start(): void
    {
        $this->partsPricedOnly();
        $this->chat();
        $this->say('hi');
        $first = $this->build(['action' => 'show']);
        $w = ChatWidgets::build([['name' => 'driveshaft_builder', 'in' => [], 'result' => $first]], ['widgets' => [], 'choices' => ['A', 'B']], 'hi', 'Which one?', fn () => null);
        $this->assertSame(['build_choices'], array_column($w, 'type'));      // the build's buttons, not the generic chips
        $this->assertCount(count($first['options']), $w[0]['options']);
        $this->assertSame(ChatWidgets::builder(['added' => true, 'title' => 'Custom 1350 series steel driveshaft, transmission yoke to flange yoke, 52.25 in', 'price_each_usd' => 500, 'qty' => 1])['type'], 'build_added');
        $this->assertSame('build_link', ChatWidgets::builder(['step' => 'measure', 'stage' => 'Step 4 of 5 · Length · Measurements', 'builder_link' => '/online-builder'])['type']);

        // strict schema: every property required (Claude), and the same tool for Gemini / OpenAI-style providers
        $tools = collect((new \ReflectionMethod($this->bot, 'tools'))->invoke($this->bot))->keyBy('name');
        $t = $tools['driveshaft_builder'];
        $this->assertEqualsCanonicalizing(array_keys((array) $t['input_schema']['properties']), $t['input_schema']['required']);
        $gem = collect((new \ReflectionMethod($this->bot, 'geminiTools'))->invoke($this->bot))->keyBy('name');
        $this->assertSame('number', $gem['driveshaft_builder']['parameters']['properties']['a1']['type']);

        // start throws the old build away
        $this->build(['action' => 'answer', 'choice' => '1']);
        $r = $this->build(['action' => 'start']);
        $this->assertSame([], $r['answers']);
        $this->assertSame('tree', $r['step']);
    }
}
