<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use App\Services\Chatbot\AssistantOrders;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatWidgets;
use App\Support\ChatVoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UDS Assistant failure modes, without any real AI / voice / payment call: broken histories repaired before they
 * reach a provider, orders placed only on a real confirmation (and never twice), shop takeovers mid-turn,
 * ElevenLabs outages, and the small text rules (directives, measurements).
 */
class AssistantRobustnessTest extends TestCase
{
    use DatabaseTransactions;

    private ChatbotService $bot;
    private object $conv;

    private function user(array $extra = [], string $provider = 'claude'): User
    {
        $u = User::query()->create(['name' => 'Robin Vega', 'email' => 'bot-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update($extra + ['phone' => '(813) 555-0142', 'billing_state' => 'FL']);   // a Florida customer: pickup is Florida-only
        $u = $u->fresh();
        $this->actingAs($u);
        $this->bot = app(ChatbotService::class);
        $id = DB::table('chat_conversations')->insertGetId(['user_id' => $u->id, 'status' => 'open', 'provider' => $provider, 'mode' => 'bot', 'system_prompt' => 'test prompt', 'created_at' => now(), 'updated_at' => now()]);
        $this->conv = DB::table('chat_conversations')->find($id);
        (new \ReflectionProperty($this->bot, 'conv'))->setValue($this->bot, $this->conv);
        return $u;
    }

    private function say(string $text): int
    {
        return DB::table('chat_messages')->insertGetId(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => json_encode([['type' => 'text', 'text' => $text]]),
            'display' => $text, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function row(string $role, array $content, ?string $display = null): void
    {
        DB::table('chat_messages')->insert(['conversation_id' => $this->conv->id, 'role' => $role, 'content' => json_encode($content), 'display' => $display, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tool(User $u, string $name, array $in = []): array
    {
        return (new \ReflectionMethod($this->bot, 'runTool'))->invoke($this->bot, $u, $name, $in)[0];
    }

    private function history(): array
    {
        return (new \ReflectionMethod($this->bot, 'history'))->invoke($this->bot, DB::table('chat_conversations')->find($this->conv->id));
    }

    private function cart(User $u): void
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('in_stock', 1)->value('wp_product_id');
        $this->actingAs($u)->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk();
    }

    private function prepare(User $u): array
    {
        return $this->tool($u, 'prepare_order', ['address_id' => 'pickup', 'shipping_option' => 'local_pickup', 'payment' => 'pay_link', 'card_id' => '', 'po_number' => '', 'note' => '']);
    }

    /* ------------------------------------------------------------ history repair */

    public function test_history_answers_orphan_tool_calls_in_every_provider_format(): void
    {
        $this->user();
        // PHP died between the tool call and its result; then a shop reply landed between a call and its result
        $this->say('Find a 1310 u-joint');
        $this->row('assistant', [['type' => 'text', 'text' => 'Looking.'], ['type' => 'tool_use', 'id' => 't1', 'name' => 'search_parts', 'input' => ['query' => '1310']]]);
        $this->say('Hello?');
        $this->row('assistant', [['type' => 'tool_use', 'id' => 't2', 'name' => 'my_orders', 'input' => []]]);
        $this->row('assistant', [['type' => 'text', 'text' => 'Sam here from the shop.']], 'Sam here from the shop.');
        $this->row('user', [['type' => 'tool_result', 'tool_use_id' => 't2', 'content' => '{}']]);
        $this->say('Thanks');

        $h = $this->history();
        $this->assertSame(['user', 'assistant', 'user', 'assistant', 'user', 'assistant', 'user'], array_column($h, 'role'));
        $this->assertSame(['type' => 'tool_result', 'tool_use_id' => 't1', 'content' => 'Cancelled.', 'is_error' => true], $h[2]['content'][0]);
        $this->assertSame('Hello?', $h[2]['content'][1]['text']);
        $this->assertSame('t2', $h[4]['content'][0]['tool_use_id']);   // synthetic answer right after the call
        $this->assertSame('Thanks', $h[6]['content'][0]['text']);       // the late, orphaned result is gone
        foreach ($h as $i => $m) {
            foreach ($m['content'] as $b) if (($b['type'] ?? '') === 'tool_use') $this->assertContains($b['id'], array_column($h[$i + 1]['content'], 'tool_use_id'));
        }

        // Gemini: functionCall answered by name; an orphan functionResponse dropped
        $g = ChatbotService::sanitize([
            ['role' => 'user', 'parts' => [['text' => 'hi']]],
            ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'my_orders', 'args' => []]]]],
            ['role' => 'user', 'parts' => [['text' => 'still there?']]],
            ['role' => 'user', 'parts' => [['functionResponse' => ['name' => 'view_cart', 'response' => []]]]],
        ], 'gemini');
        $this->assertCount(3, $g);
        $this->assertSame('my_orders', $g[2]['parts'][0]['functionResponse']['name']);
        $this->assertSame('still there?', $g[2]['parts'][1]['text']);
        $tail = ChatbotService::sanitize([['role' => 'user', 'parts' => [['text' => 'hi']]], ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'a', 'args' => []]]]]], 'gemini');
        $this->assertSame(['user', 'model', 'user'], array_column($tail, 'role'));

        // OpenAI format (OpenRouter / Groq): a tool message right after each call, orphans dropped
        $o = ChatbotService::sanitize([
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'function' => ['name' => 'a']], ['id' => 'c2', 'function' => ['name' => 'b']]]],
            ['role' => 'tool', 'tool_call_id' => 'c2', 'content' => '{}'],
            ['role' => 'assistant', 'content' => 'Sam from the shop'],
            ['role' => 'tool', 'tool_call_id' => 'zz', 'content' => '{}'],
            ['role' => 'user', 'content' => 'ok'],
        ], 'openai');
        $this->assertSame(['user', 'assistant', 'tool', 'tool', 'assistant', 'user'], array_column($o, 'role'));
        $this->assertSame(['c2', 'c1'], [$o[2]['tool_call_id'], $o[3]['tool_call_id']]);
        $this->assertSame('Cancelled.', $o[3]['content']);
    }

    /* ------------------------------------------------------------ order confirmation */

    public function test_place_order_needs_a_real_confirmation(): void
    {
        Mail::fake();
        $u = $this->user();
        $this->cart($u);
        $this->say('Order my cart for pickup with a pay link');
        $r = $this->prepare($u);
        $this->assertTrue($r['ready']);
        $w = ChatWidgets::build([['name' => 'prepare_order', 'in' => [], 'result' => $r]], ['widgets' => [], 'choices' => []], 'order', 'Tap Yes.', fn () => null);
        $this->assertSame($r['summary_id'], $w[0]['nonce']);
        $this->assertGreaterThan(time(), $w[0]['expires_at']);

        // "Change something" is a new message, but not a yes
        AssistantOrders::confirmTap((int) $this->conv->id, null);
        $this->say('Change something');
        $this->assertFalse($this->tool($u, 'place_order')['placed']);
        $this->assertFalse(AssistantOrders::saysYes('yes, but wait'));
        $this->assertFalse(AssistantOrders::saysYes('no'));
        $this->assertTrue(AssistantOrders::saysYes('Sí, dale'));
        $this->assertTrue(AssistantOrders::saysYes("oui d'accord"));

        // prepared again and placed in the same response (parallel calls): refused, nothing ordered
        $this->say('yes');
        (new \ReflectionProperty($this->bot, 'roundTools'))->setValue($this->bot, ['prepare_order', 'place_order']);
        $this->assertStringContainsString('same reply', $this->tool($u, 'place_order')['error']);
        (new \ReflectionProperty($this->bot, 'roundTools'))->setValue($this->bot, []);
        // ... or in an earlier round of the same turn
        $r = $this->prepare($u);
        $this->assertFalse($this->tool($u, 'place_order')['placed']);
        $this->assertSame(0, DB::table('orders')->where('user_id', $u->id)->count());

        // the Yes button: its nonce matches the current draft, whatever the (translated) words
        $this->say('Passer la commande');   // the translated button label: not a typed yes, only the nonce can confirm it
        AssistantOrders::confirmTap((int) $this->conv->id, 'stale-nonce');
        $this->assertFalse($this->tool($u, 'place_order')['placed']);
        AssistantOrders::confirmTap((int) $this->conv->id, $r['summary_id']);
        $p = $this->tool($u, 'place_order');
        $this->assertTrue($p['placed'], json_encode($p));
        $this->assertSame(1, DB::table('orders')->where('user_id', $u->id)->count());

        // the same cart again within minutes: the model must ask first; a later message lets it through
        $this->cart($u);
        $this->say('order it again');
        $again = $this->prepare($u);
        $this->assertFalse($again['ready']);
        $this->assertStringContainsString($p['order_number'], $again['error']);
        $this->say('yes, I want a second one');
        $this->assertTrue($this->prepare($u)['ready']);
        $this->say('yes');
        $this->assertTrue($this->tool($u, 'place_order')['placed']);
        $this->assertSame(2, DB::table('orders')->where('user_id', $u->id)->count());
    }

    public function test_done_marker_is_written_before_payment_and_a_payment_crash_still_answers(): void
    {
        Mail::fake();
        $u = $this->user();
        $this->cart($u);
        $this->say('order it');
        $this->assertTrue($this->prepare($u)['ready']);
        $this->say('yes');
        $key = 'assistant_order:' . $this->conv->id . ':done';
        $box = new \stdClass();
        $box->seen = null;
        // the payment step blows up (first thing pay() does is empty the cart)
        app()->instance(CartService::class, new class ($key, $box) extends CartService {
            public function __construct(private string $k, private object $box) {}
            public function clear(): void { $this->box->seen = Cache::get($this->k); throw new \RuntimeException('network down'); }
        });
        $r = $this->tool($u, 'place_order');
        $seen = $box->seen;
        $this->assertNotNull($seen, ':done must exist before payment starts');
        $this->assertSame('pending_check', $seen['payment']);
        $this->assertTrue($r['placed']);
        $this->assertSame('pending_check', $r['payment']);
        $this->assertStringContainsString('/pay/', $r['pay_link']);
        $this->assertSame(1, DB::table('orders')->where('user_id', $u->id)->count());
        // a retry answers "already", it does not order again
        $this->assertTrue($this->tool($u, 'place_order')['already'] ?? false);
        $this->assertSame(1, DB::table('orders')->where('user_id', $u->id)->count());
    }

    /* ------------------------------------------------------------ takeover, display, ids */

    public function test_gemini_turn_stores_a_clean_answer_and_a_takeover_mid_turn_silences_the_bot(): void
    {
        config(['services.elevenlabs.key' => '', 'services.gemini.key' => 'test']);
        $u = $this->user([], 'gemini');
        // one fake for the whole test (a second Http::fake would only queue behind the first)
        $step = 'answer';
        Http::fake(function () use (&$step) {
            if ($step === 'answer') return Http::response(['candidates' => [['content' => ['parts' => [['text' => "Sure [[voice]] thing.\n[[choices:A|B]]\n[[text]]"]]]]]]);
            if ($step === 'takeover') {
                DB::table('chat_conversations')->where('id', $this->conv->id)->update(['mode' => 'human']);
                return Http::response(['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'my_orders', 'args' => []]]]]]]]);
            }
            return Http::response(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => []]]]]);
        });
        $out = $this->bot->turn($u, $this->conv, 'hello');
        $row = DB::table('chat_messages')->find($out['answer_id']);
        $this->assertSame('Sure thing.', $row->display);
        $this->assertStringContainsString('[[voice]]', $row->content);   // the model's own words stay verbatim
        $this->assertContains($out['answer_id'], $out['ids']);
        $this->assertContains($out['user_id'], $out['ids']);

        // the shop takes over while the model is answering: no bot answer, nothing half-written
        $before = DB::table('chat_messages')->where('conversation_id', $this->conv->id)->count();
        $step = 'takeover';
        $out = $this->bot->turn($u, $this->conv, 'where is my order');
        $this->assertTrue($out['human']);
        $this->assertSame($before + 1, DB::table('chat_messages')->where('conversation_id', $this->conv->id)->count());   // just the customer line
        $this->assertSame('user', DB::table('chat_messages')->where('conversation_id', $this->conv->id)->orderByDesc('id')->value('role'));

        // an empty answer that is not a safety block is our trouble, not an off-topic refusal
        DB::table('chat_conversations')->where('id', $this->conv->id)->update(['mode' => 'bot']);
        $step = 'empty';
        $this->assertStringContainsString('trouble answering', $this->bot->turn($u, DB::table('chat_conversations')->find($this->conv->id), 'hm')['text']);
    }

    public function test_admin_reply_waits_for_the_customers_send_lock(): void
    {
        $u = $this->user();
        $admin = User::query()->create(['name' => 'Sam Shop', 'email' => 'adm-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => 1]);
        // a bot turn in flight holds the lock for ~2 more seconds
        $this->assertTrue(cache()->lock(\App\Http\Controllers\ChatbotController::lockKey($u->id), 2)->get());
        $req = Request::create('/admin/chats/' . $this->conv->id . '/reply', 'POST', ['text' => 'Hi, Sam here']);
        $req->setUserResolver(fn () => $admin->fresh());
        $t = microtime(true);
        $res = app(\App\Http\Controllers\Admin\ChatsController::class)->reply($req, (int) $this->conv->id);
        $this->assertGreaterThanOrEqual(1.0, microtime(true) - $t, 'the reply did not wait for the lock');
        $this->assertTrue($res->getData(true)['ok']);
        $this->assertSame('human', DB::table('chat_conversations')->where('id', $this->conv->id)->value('mode'));
        $this->assertTrue(DB::table('chat_messages')->where('conversation_id', $this->conv->id)->where('display', 'Hi, Sam here')->exists());
    }

    /* ------------------------------------------------------------ voice and text rules */

    public function test_elevenlabs_outage_turns_voice_off(): void
    {
        config(['services.elevenlabs.key' => 'k']);
        Cache::forget(ChatVoice::DOWN_KEY);
        Cache::forget('elevenlabs:voice_refused:' . config('services.elevenlabs.voice'));
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->assertTrue(ChatVoice::enabled());

        $step = 'plan';
        Http::fake(function ($req) use (&$step) {
            return match ($step) {
                // the library voice needing a paid plan is not an outage
                'plan' => str_contains($req->url(), (string) config('services.elevenlabs.voice')) ? Http::response(['detail' => ['code' => 'paid_plan_required']], 402) : Http::response('ID3', 200, ['Content-Type' => 'audio/mpeg']),
                'quota' => Http::response(['detail' => ['status' => 'quota_exceeded']], 401),
                'silent' => Http::response(['text' => ''], 200),
                default => Http::response('busy', 429),
            };
        });
        $this->assertNotNull(ChatVoice::speak('Hello there.', 1));
        $this->assertTrue(ChatVoice::enabled());

        // out of credits / rate limited: voice is off, answers are text, the mic is hidden
        $step = 'quota';
        $this->assertNull(ChatVoice::speak('Hello there.', 1));
        $this->assertFalse(ChatVoice::enabled());
        $this->assertSame('text', ChatVoice::decide('Hi!', 'voice', true));
        $clip = 'chat-audio/1/x.webm';
        \Illuminate\Support\Facades\Storage::disk('local')->put($clip, str_repeat('x', 900));
        $this->assertNull(ChatVoice::transcribe($clip, $why));
        $this->assertSame('down', $why);

        Cache::forget(ChatVoice::DOWN_KEY);
        $step = 'silent';
        $this->assertNull(ChatVoice::transcribe($clip, $why));
        $this->assertSame('unclear', $why);
        $step = 'busy';
        ChatVoice::transcribe($clip, $why);
        $this->assertSame('down', $why);
        Cache::forget(ChatVoice::DOWN_KEY);
    }

    public function test_directive_and_text_edge_cases(): void
    {
        $D = fn (string $t) => ChatWidgets::directives($t)[0];
        $this->assertSame('Done, it is in your cart.', $D('Done, it is [[voice]] in your cart [[widget:orders]].'));
        $this->assertSame('Here it is.', $D('Here it is. [[voice]'));
        $this->assertSame('Here', $D('Here [[widget it is.'));   // a broken tag eats the rest of its line rather than leak
        $this->assertSame('Half', $D('Half [[vo'));

        $this->assertFalse(ChatVoice::speakableShort('Finish at /checkout when ready.'));
        $this->assertTrue(ChatVoice::speakableShort('Yes, 1/2 inch works.'));

        $this->user();
        $clean = new \ReflectionMethod($this->bot, 'clean');
        $this->assertSame('Tubes <50in long ship by UPS; > 50in go freight.', $clean->invoke($this->bot, 'Tubes <50in long ship by UPS; > 50in go freight.'));
        $this->assertSame('Hi there', $clean->invoke($this->bot, '<b>Hi</b> <script src="x">there'));
        $this->assertSame("Line one\nline two", $clean->invoke($this->bot, "Line one\nline two"));
    }
}
