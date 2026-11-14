<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\B2bAccounts;
use App\Services\CartService;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UDS Assistant's account tools, run directly (no AI call): orders placed for the customer only after they
 * confirm, account / business answers, applications, alerts, cart edits. Plus avatars and saved-card checkout.
 */
class AssistantToolsTest extends TestCase
{
    use DatabaseTransactions;

    private ChatbotService $bot;
    private object $conv;

    private function user(array $extra = []): User
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'bot-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update($extra + ['phone' => '(813) 555-0142', 'billing_state' => 'FL']);   // a Florida customer: pickup is Florida-only
        if (($extra['wholesale_status'] ?? '') === 'approved') B2bAccounts::evaluate($u->id, false);
        $u = $u->fresh();
        $this->actingAs($u);
        $this->bot = app(ChatbotService::class);
        $this->conv = $this->bot->conversation($u);
        (new \ReflectionProperty($this->bot, 'conv'))->setValue($this->bot, $this->conv);
        return $u;
    }

    /** A message typed by the customer (what place_order waits for). */
    private function say(string $text): void
    {
        DB::table('chat_messages')->insert(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => json_encode([['type' => 'text', 'text' => $text]]),
            'display' => $text, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tool(User $u, string $name, array $in = []): array
    {
        $m = new \ReflectionMethod($this->bot, 'runTool');
        return $m->invoke($this->bot, $u, $name, $in)[0];
    }

    private function cart(User $u, int $qty = 2): float
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('in_stock', 1)->value('wp_product_id');
        $this->actingAs($u)->post('/cart/add', ['product_id' => $pid, 'quantity' => $qty])->assertOk();
        return app(CartService::class)->subtotal();
    }

    private function prepare(User $u, string $payment, array $extra = []): array
    {
        return $this->tool($u, 'prepare_order', $extra + ['address_id' => 'pickup', 'shipping_option' => 'local_pickup', 'payment' => $payment, 'card_id' => '', 'po_number' => '', 'note' => '']);
    }

    public function test_terms_order_is_placed_only_after_the_customer_confirms(): void
    {
        Mail::fake();
        $u = $this->user(['wholesale_status' => 'approved', 'net_terms' => 1]);
        $sub = $this->cart($u);
        $this->say('Order what is in my cart, pickup, on terms');

        $r = $this->tool($u, 'prepare_order', ['address_id' => 'pickup', 'shipping_option' => '', 'payment' => 'ask', 'card_id' => '', 'po_number' => '', 'note' => '']);
        $this->assertFalse($r['ready']);
        $this->assertSame('local_pickup', $r['choose_shipping'][0]['shipping_option']);
        $r = $this->prepare($u, 'ask');
        $this->assertContains('net_terms', $r['choose_payment']);
        $this->assertContains('pay_link', $r['choose_payment']);

        $r = $this->prepare($u, 'net_terms', ['po_number' => 'PO-55']);
        $this->assertTrue($r['ready']);
        $this->assertEquals(round($sub - round($sub * 0.05, 2), 2), $r['summary']['total_usd']);

        // same reply as the summary: refused
        $r = $this->tool($u, 'place_order');
        $this->assertFalse($r['placed']);
        $this->assertSame(0, DB::table('orders')->where('user_id', $u->id)->count());

        $this->say('yes');
        $r = $this->tool($u, 'place_order');
        $this->assertTrue($r['placed']);
        $this->assertSame('invoice', $r['payment']);
        $o = DB::table('orders')->where('order_number', $r['order_number'])->first();
        $this->assertSame('net_terms', $o->payment_method);
        $this->assertSame('assistant', $o->source);
        $this->assertSame('PO-55', $o->po_number);
        $this->assertSame('processing', $o->status);
        $this->assertSame(0, app(CartService::class)->count());
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($u->email));

        // a repeated call does not order twice
        $again = $this->tool($u, 'place_order');
        $this->assertTrue($again['already'] ?? false);
        $this->assertSame(1, DB::table('orders')->where('user_id', $u->id)->count());
    }

    public function test_retail_pay_link_order_and_cart_change_needs_a_new_summary(): void
    {
        Mail::fake();
        $u = $this->user();
        $this->cart($u);
        $this->say('order it');
        $r = $this->prepare($u, 'net_terms');
        $this->assertFalse($r['ready']);
        $this->assertNotContains('net_terms', $r['choose_payment']);
        $this->assertTrue($this->prepare($u, 'pay_link')['ready']);

        $this->cart($u, 1);   // the cart moved after the summary
        $this->say('yes');
        $r = $this->tool($u, 'place_order');
        $this->assertFalse($r['placed']);
        $this->assertStringContainsString('cart changed', $r['error']);

        $this->assertTrue($this->prepare($u, 'pay_link')['ready']);
        $this->say('yes please');
        $r = $this->tool($u, 'place_order');
        $this->assertTrue($r['placed']);
        $this->assertSame('awaiting_payment', $r['payment']);
        $this->assertStringContainsString('/pay/' . $r['order_number'], $r['pay_link']);
        $o = DB::table('orders')->where('order_number', $r['order_number'])->first();
        $this->assertSame('unpaid', $o->payment_status);
        $this->assertSame('pending', $o->status);
    }

    public function test_account_answers_application_and_stock_alert(): void
    {
        Mail::fake();
        $u = $this->user();
        $a = $this->tool($u, 'my_account');
        $this->assertSame('none', $a['business_account']['status']);
        $this->assertArrayHasKey('how_to_apply', $a['business_account']);

        $r = $this->tool($u, 'apply_for_business_account', ['business_name' => 'Morgan Fleet Repair', 'business_type' => 'repair shop', 'tax_id' => '', 'volume' => '$3,000', 'notes' => '']);
        $this->assertTrue($r['filed']);
        $this->assertSame('pending', DB::table('users')->where('id', $u->id)->value('wholesale_status'));
        $this->assertTrue(DB::table('leads')->where('email', $u->email)->where('source', 'wholesale-application')->exists());
        $this->assertFalse($this->tool($u->fresh(), 'apply_for_business_account', ['business_name' => 'X', 'business_type' => '', 'tax_id' => '', 'volume' => '', 'notes' => ''])['filed']);

        $b = $this->user(['wholesale_status' => 'approved', 'net_terms' => 1]);
        $acc = $this->tool($b, 'my_account')['business_account'];
        $this->assertSame('Bronze 5%', $acc['tier']);
        $this->assertTrue($acc['net_terms']['available']);

        $sku = DB::table('catalog_live')->where('in_stock', 0)->where(fn ($q) => $q->whereNull('pti_in_stock')->orWhere('pti_in_stock', 0))->whereNotNull('sku')->value('sku');
        if ($sku) {
            $r = $this->tool($b, 'notify_when_in_stock', ['sku' => $sku]);
            $this->assertTrue($r['subscribed'] ?? false, json_encode($r));
            $this->assertTrue(DB::table('stock_alerts')->where('email', strtolower($b->email))->exists());
        }
    }

    public function test_reorder_and_update_cart(): void
    {
        $u = $this->user();
        $p = DB::table('catalog_live')->where('price', '>', 20)->where('in_stock', 1)->whereNotNull('sku')->first();
        $oid = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'user_id' => $u->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => $u->email, 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'subtotal' => 10, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 10,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $oid, 'type' => 'product', 'wp_product_id' => $p->wp_product_id, 'name' => 'x', 'sku' => $p->sku, 'price' => 1, 'qty' => 3, 'line_total' => 3, 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $number = DB::table('orders')->where('id', $oid)->value('order_number');

        $r = $this->tool($u, 'reorder', ['order_number' => $number]);
        $this->assertSame([['sku' => $p->sku, 'qty' => 3]], $r['added']);
        $this->assertSame(3, app(CartService::class)->count());
        $r = $this->tool($u, 'update_cart', ['sku' => $p->sku, 'qty' => 0]);
        $this->assertTrue($r['removed']);
        $this->assertSame(0, app(CartService::class)->count());
        $this->assertFalse($this->tool($u, 'reorder', ['order_number' => 'UDS-NOPE'])['found']);
    }

    public function test_profile_picture_and_saved_card_checkout(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/account/settings')->assertOk()->assertSee('Profile picture')->assertSee('Saved cards')->assertSee('/avatar/notionists/', false);
        $this->actingAs($u)->post('/account/avatar', ['avatar_style' => 'pixel-art', 'avatar_seed' => 'abc123'])->assertRedirect();
        $this->assertSame(['pixel-art', 'abc123'], array_values((array) DB::table('users')->where('id', $u->id)->first(['avatar_style', 'avatar_seed'])));
        $this->actingAs($u)->post('/account/avatar', ['avatar_style' => 'evil', 'avatar_seed' => '../x'])->assertRedirect();
        $this->assertSame('pixel-art', DB::table('users')->where('id', $u->id)->value('avatar_style'));
        $this->actingAs($u->fresh())->getJson('/assistant')->assertOk()->assertJsonFragment(['avatar' => '/avatar/pixel-art/abc123']);

        $this->cart($u);
        $this->actingAs($u)->get('/checkout')->assertOk()->assertSee("Don't save my card for future purchases", false);
        // a card id that is not on this account is refused before any payment is created
        $this->actingAs($u)->postJson('/checkout/place', ['email' => $u->email, 'phone' => '(813) 555-0142', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan',
            'billing_country' => 'US', 'billing_state' => 'FL', 'shipping_method' => 'local_pickup', 'pickup_ack' => 1, 'agree_terms' => 1, 'payment_method' => 'card', 'saved_card' => 'pm_notmine'])
            ->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->where('user_id', $u->id)->count());
        $this->get('/terms')->assertSee('securely save your card details')->assertSee('saved by default');

        // cards are saved unless the customer opts out; the opt-out is flagged so the card is detached after payment
        $p = \App\Support\StripeCards::intentParams($u, true);
        $this->assertSame('off_session', $p['payment_method_options[card][setup_future_usage]']);
        $this->assertArrayNotHasKey('setup_future_usage', $p);   // Affirm can't be saved, so only the card option asks
        $this->assertSame('1', \App\Support\StripeCards::intentParams($u, false)['metadata[dont_save_card]']);
        $this->assertArrayNotHasKey('payment_method_options[card][setup_future_usage]', \App\Support\StripeCards::intentParams($u, true, 'pm_x'));
    }

    public function test_voice_message_is_transcribed_and_answers_can_be_listened_to(): void
    {
        config(['services.elevenlabs.key' => 'test-key']);
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Cache::forget('elevenlabs:voice_refused:' . config('services.elevenlabs.voice'));
        $sent = [];
        \Illuminate\Support\Facades\Http::fake(function ($req) use (&$sent) {
            $sent[] = $req->url();
            if (str_contains($req->url(), 'speech-to-text')) return \Illuminate\Support\Facades\Http::response(['text' => ' Do you have a 1310   u-joint? ']);
            // the library voice is refused on a free plan: the premade fallback speaks instead
            if (str_contains($req->url(), config('services.elevenlabs.voice'))) return \Illuminate\Support\Facades\Http::response(['detail' => ['code' => 'paid_plan_required']], 402);
            return \Illuminate\Support\Facades\Http::response('ID3fakeaudio', 200, ['Content-Type' => 'audio/mpeg']);
        });
        $u = $this->user();

        $clip = \App\Support\ChatVoice::storeDataUrl('data:audio/webm;codecs=opus;base64,' . base64_encode(str_repeat('x', 2000)), (int) $this->conv->id);
        $this->assertStringEndsWith('.webm', $clip);
        $this->assertSame('Do you have a 1310 u-joint?', \App\Support\ChatVoice::transcribe($clip));
        try { \App\Support\ChatVoice::storeDataUrl('data:text/html;base64,PHNjcmlwdD4=', 1); $this->fail('accepted a non-audio upload'); } catch (\RuntimeException $e) {}

        // what the AI reads is marked as spoken; the customer sees just the words and can replay the recording
        $this->bot->voicePath = $clip;
        (new \ReflectionMethod($this->bot, 'append'))->invoke($this->bot, $this->conv, 'user', (new \ReflectionMethod($this->bot, 'userContent'))->invoke($this->bot, $this->conv, 'Do you have a 1310 u-joint?'), 'Do you have a 1310 u-joint?');
        $this->bot->voicePath = null;
        $mine = DB::table('chat_messages')->where('conversation_id', $this->conv->id)->where('role', 'user')->orderByDesc('id')->first();
        $this->assertStringContainsString('[Voice message, transcribed]', $mine->content);
        $this->assertSame($clip, $mine->audio);
        $this->actingAs($u)->get('/assistant/audio/' . $mine->id)->assertOk()->assertHeader('Content-Type', 'audio/webm');

        // an answer is spoken the first time someone presses Listen, then kept
        $ans = (new \ReflectionMethod($this->bot, 'append'))->invoke($this->bot, $this->conv, 'assistant', [['type' => 'text', 'text' => 'Yes: **5-1310XU** is $11.50. https://x.test/p']], 'Yes: **5-1310XU** is $11.50. https://x.test/p');
        $this->actingAs($u)->get('/assistant/audio/' . $ans)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertNotNull(DB::table('chat_messages')->where('id', $ans)->value('audio'));
        $this->assertTrue(collect($sent)->contains(fn ($x) => str_contains($x, config('services.elevenlabs.fallback_voice'))));
        $calls = count($sent);
        $this->actingAs($u)->get('/assistant/audio/' . $ans)->assertOk();
        $this->assertSame($calls, count($sent));
        $this->assertSame('Yes: 5-1310XU is $11.50.', \App\Support\ChatVoice::speakable('Yes: **5-1310XU** is $11.50. https://x.test/p'));

        // somebody else's chat audio is not served
        $other = User::query()->create(['name' => 'Other', 'email' => 'o-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $this->flushSession();   // AuthenticateSession: a session can't switch users mid-test
        $this->flushSession();   // a new account in the same session: AuthenticateSession would sign out on the first one's password hash
        $this->actingAs($other)->get('/assistant/audio/' . $mine->id)->assertNotFound();
        $this->actingAs($other)->get('/assistant/audio/' . $ans)->assertNotFound();
    }

    public function test_reply_format_directives_and_voice_rules(): void
    {
        [$t, $hint, $tags] = \App\Services\Chatbot\ChatWidgets::directives("It's out for delivery today.\n[[widget:tracking:UDS-1]] [[choices:Thanks|Change address]]\n[[voice]]");
        $this->assertSame("It's out for delivery today.", $t);
        $this->assertSame('voice', $hint);
        $this->assertSame([['tracking', 'UDS-1']], $tags['widgets']);
        $this->assertSame(['Thanks', 'Change address'], $tags['choices']);
        $this->assertSame('Half', \App\Services\Chatbot\ChatWidgets::directives('Half [[vo')[0]);

        config(['services.elevenlabs.key' => 'k']);
        $V = \App\Support\ChatVoice::class;
        $this->assertSame('voice', $V::decide('Hi Alex! What can I find for you today?', 'voice', false));
        $this->assertSame('text', $V::decide('Hi Alex!', 'text', true));
        $this->assertSame('voice', $V::decide('Sure, done.', null, true));
        $this->assertSame('text', $V::decide('Sure, done.', null, false));
        // things to read or copy are never only spoken, whatever the model asked for
        $this->assertSame('text', $V::decide('Pay here: https://x.test/pay/1', 'voice', true));
        $this->assertSame('text', $V::decide("Options:\n- 5-1310XU \$11.50\n- 5-297XU \$10.70", 'voice', true));
        $this->assertSame('text', $V::decide('5-1310XU, 5-297XU and 5-760XU fit.', 'voice', true));
        $this->assertSame('text', $V::decide('Tracking 1Z999AA10123456784 on UPS.', 'voice', true));
        $this->assertSame('text', $V::decide(str_repeat('word ', 80), 'voice', true));
        $this->assertSame('text', $V::decide('Hi!', 'voice', true, 'off'));
        $this->assertSame('text', $V::decide('Hi!', 'voice', false, 'spoken'));
        $this->assertSame('voice', $V::decide('Hi!', 'voice', true, 'spoken'));
        config(['services.elevenlabs.key' => '']);
        $this->assertSame('text', $V::decide('Hi!', 'voice', true));
    }

    public function test_order_widgets_come_from_the_customers_own_orders(): void
    {
        $u = $this->user();
        $mk = function (array $x) use ($u) {
            $id = DB::table('orders')->insertGetId($x + ['order_number' => 'T-' . uniqid(), 'user_id' => $u->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
                'email' => $u->email, 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'subtotal' => 40, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 40,
                'created_at' => now()->subDays(3), 'updated_at' => now()]);
            DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'wp_product_id' => 1, 'name' => 'U-joint', 'sku' => '5-1310XU', 'price' => 20, 'qty' => 2, 'line_total' => 40, 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()]);
            return DB::table('orders')->find($id);
        };
        $a = $mk(['paid_at' => now()->subDays(3)]);
        $b = $mk(['paid_at' => now()->subDays(2), 'shipped_at' => now()->subDay(), 'tracking_number' => '1Z999AA10123456784', 'tracking_carrier' => 'UPS', 'tracking_est' => now()->addDay(),
            'tracking_events' => json_encode([['status' => 'out_for_delivery', 'message' => 'Out For Delivery', 'place' => 'Tampa, FL', 'at' => now()->toIso8601String()], ['status' => 'in_transit', 'message' => 'Departed', 'place' => 'Atlanta, GA', 'at' => now()->subHours(9)->toIso8601String()]])]);
        $foreign = DB::table('orders')->insertGetId(['order_number' => 'T-X' . uniqid(), 'user_id' => 999999, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe', 'email' => 'x@example.com',
            'billing_first_name' => 'X', 'billing_last_name' => 'Y', 'billing_country' => 'US', 'subtotal' => 1, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $fnum = DB::table('orders')->where('id', $foreign)->value('order_number');
        $own = fn ($n) => $this->bot->ownOrder($u, (string) $n);
        $W = \App\Services\Chatbot\ChatWidgets::class;

        // "where is my order?" with two orders: a list to tap
        $tools = [['name' => 'my_orders', 'in' => [], 'result' => $this->tool($u, 'my_orders')]];
        $w = $W::build($tools, ['widgets' => [], 'choices' => []], 'where is my order?', 'You have two orders — which one?', $own);
        $this->assertSame('orders', $w[0]['type']);
        $this->assertCount(2, $w[0]['orders']);
        $this->assertSame('2 × 5-1310XU', $w[0]['orders'][0]['items']);

        // tracking: the steps, carrier, estimate and latest scans, from the database
        $tools = [['name' => 'order_details', 'in' => [], 'result' => $this->tool($u, 'order_details', ['order_number' => $b->order_number])]];
        $w = $W::build($tools, ['widgets' => [], 'choices' => []], '¿dónde está mi pedido?', 'Está en reparto.', $own);
        $this->assertSame('tracking', $w[0]['type']);
        $this->assertSame(['Placed', 'Paid', 'Shipped', 'Out for delivery'], array_column(array_filter($w[0]['steps'], fn ($s) => $s['done']), 'label'));
        $this->assertSame('1Z999AA10123456784', $w[0]['tracking_number']);
        $this->assertSame('Tampa, FL', $w[0]['scans'][0]['place']);
        $this->assertNotNull($w[0]['eta']);
        $this->assertNull($w[0]['pay_link']);

        // a directive for someone else's order draws nothing
        $this->assertSame([], $W::build([], ['widgets' => [['tracking', $fnum]], 'choices' => []], 'track ' . $fnum, 'ok', $own));
        $this->assertSame('tracking', $W::build([], ['widgets' => [['tracking', $a->order_number]], 'choices' => []], 'status', 'ok', $own)[0]['type']);

        // order summary → confirm card; shipping choices → buttons
        $c = $W::build([['name' => 'prepare_order', 'in' => [], 'result' => ['ready' => true, 'summary' => ['items' => [['sku' => '5-1310XU', 'name' => 'U-joint', 'qty' => 2, 'price_usd' => 10]], 'subtotal_usd' => 20, 'business_discount' => null,
            'shipping' => ['method' => 'Local pickup', 'cost_usd' => 0], 'ship_to' => 'Pickup', 'payment' => 'Payment link', 'po_number' => null, 'total_usd' => 20]]]], ['widgets' => [], 'choices' => ['x']], 'order it', 'Check and tap Yes.', $own);
        $this->assertSame(['confirm'], array_column($c, 'type'));
        $this->assertSame(20.0, $c[0]['items'][0]['line']);
        $ch = $W::build([['name' => 'prepare_order', 'in' => [], 'result' => ['ready' => false, 'choose_shipping' => [['shipping_option' => 'a', 'label' => 'UPS Ground', 'cost_usd' => 12.5]]]]], ['widgets' => [], 'choices' => []], 'ship it', 'Which?', $own);
        $this->assertSame(['UPS Ground · $12.50'], $ch[0]['options']);
    }

    public function test_chat_history_shows_the_last_15_days_only(): void
    {
        $u = $this->user();
        $this->say('Need a 1310 u-joint');
        $old = DB::table('chat_conversations')->insertGetId(['user_id' => $u->id, 'status' => 'closed', 'provider' => 'claude', 'mode' => 'bot', 'system_prompt' => 'x', 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
        DB::table('chat_messages')->insert(['conversation_id' => $old, 'role' => 'user', 'content' => '[]', 'display' => 'old question', 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
        $recent = DB::table('chat_conversations')->insertGetId(['user_id' => $u->id, 'status' => 'closed', 'provider' => 'claude', 'mode' => 'bot', 'system_prompt' => 'x', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);
        DB::table('chat_messages')->insert(['conversation_id' => $recent, 'role' => 'user', 'content' => '[]', 'display' => 'Where is UDS-1?', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);

        $r = $this->actingAs($u)->getJson('/assistant/history')->assertOk()->json('chats');
        $this->assertSame(['Need a 1310 u-joint', 'Where is UDS-1?'], array_column($r, 'title'));
        $this->assertTrue($r[0]['open']);
        $this->actingAs($u)->getJson('/assistant/history/' . $recent)->assertOk()->assertJsonFragment(['text' => 'Where is UDS-1?']);
        $this->actingAs($u)->getJson('/assistant/history/' . $old)->assertNotFound();
        $other = User::query()->create(['name' => 'Other', 'email' => 'o-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $this->flushSession();
        $this->flushSession();   // see above: switching accounts mid-session
        $this->actingAs($other)->getJson('/assistant/history/' . $recent)->assertNotFound();
    }
}
