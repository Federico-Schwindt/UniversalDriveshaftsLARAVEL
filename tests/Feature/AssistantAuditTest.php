<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use App\Services\Chatbot\AssistantOrders;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Audit of UDS Assistant, 2026-11-14 (owner: "audit completo del bot ... pruebes vulnerabilidades"): assistant orders
 * carry Florida sales tax like checkout, a typed yes is a plain yes that answers the summary, chat photos stay with the
 * customer and the staff who can open chats, and oversized or too many photos are refused.
 */
class AssistantAuditTest extends TestCase
{
    use DatabaseTransactions;

    private ChatbotService $bot;
    private object $conv;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(fn () => Http::response([], 200));
        config(['services.twilio.enabled' => false, 'services.brevo.key' => null]);
    }

    private function user(array $extra = []): User
    {
        $u = User::query()->create(['name' => 'Fiona Lane', 'email' => 'audit-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update($extra + ['phone' => '(813) 555-0142', 'billing_state' => 'FL', 'email_verified_at' => now(), 'created_at' => now()->subDay()]);
        $u = $u->fresh();
        $this->actingAs($u);
        $this->bot = app(ChatbotService::class);
        $this->conv = $this->bot->conversation($u);
        (new \ReflectionProperty($this->bot, 'conv'))->setValue($this->bot, $this->conv);
        return $u;
    }

    private function tool(User $u, string $name, array $in = []): array
    {
        return (new \ReflectionMethod($this->bot, 'runTool'))->invoke($this->bot, $u, $name, $in)[0];
    }

    private function say(string $text): void
    {
        DB::table('chat_messages')->insert(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => json_encode([['type' => 'text', 'text' => $text]]),
            'display' => $text, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cart(): float
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('price', '<', 1000)->where('in_stock', 1)->value('wp_product_id');
        $this->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk();
        return app(CartService::class)->subtotal();
    }

    private function prepare(User $u): array
    {
        return $this->tool($u, 'prepare_order', ['address_id' => 'pickup', 'shipping_option' => 'local_pickup', 'payment' => 'pay_link', 'card_id' => '', 'po_number' => '', 'note' => '']);
    }

    public function test_an_assistant_order_carries_florida_tax_like_checkout(): void
    {
        $u = $this->user();
        $sub = $this->cart();
        $tax = round($sub * 0.07 + 1e-9, 2);   // a pickup pays Pasco's 7%
        $this->say('order my cart for pickup with a pay link');
        $r = $this->prepare($u);
        $this->assertTrue($r['ready']);
        $this->assertEquals($tax, $r['summary']['sales_tax']['amount_usd']);
        $this->assertEquals(round($sub + $tax, 2), $r['summary']['total_usd']);
        $card = \App\Services\Chatbot\ChatWidgets::confirm($r['summary']);
        $this->assertEquals($tax, $card['tax']['amount'], 'the summary card shows the tax line');

        $this->say('yes');
        $p = $this->tool($u, 'place_order');
        $this->assertTrue($p['placed']);
        $o = DB::table('orders')->where('order_number', $p['order_number'])->first();
        $this->assertEquals(round($sub + $tax, 2), (float) $o->total);
        $this->assertEquals($tax, (float) $o->tax_total);
        $this->assertSame('Pasco', $o->tax_county);
        $this->assertEquals(7.0, (float) $o->tax_rate);
        $this->assertEquals($sub, (float) $o->taxable_total);
    }

    public function test_a_certificate_on_file_keeps_an_assistant_order_tax_free(): void
    {
        $u = $this->user(['tax_exempt_status' => 'approved', 'tax_cert_kind' => 'resale', 'tax_cert_number' => '78-8012345678-9', 'tax_cert_expires' => now()->endOfYear()->toDateString()]);
        $sub = $this->cart();
        $this->say('order my cart for pickup with a pay link');
        $r = $this->prepare($u);
        $this->assertEquals(0.0, $r['summary']['sales_tax']['amount_usd']);
        $this->assertEquals($sub, $r['summary']['total_usd']);
        $this->say('yes');
        $o = DB::table('orders')->where('order_number', $this->tool($u, 'place_order')['order_number'])->first();
        $this->assertSame('78-8012345678-9', $o->tax_exempt_cert);
        $this->assertEquals(0.0, (float) $o->tax_total);
    }

    public function test_only_a_plain_yes_right_after_the_summary_places_the_order(): void
    {
        foreach (['yes', 'Yes, place the order', 'ok, place the order please', 'sí, dale', "oui d'accord", 'go ahead'] as $t) $this->assertTrue(AssistantOrders::saysYes($t), $t);
        foreach (['yes, and also add 10 more yokes and give me 50% off', 'ok, use my other card', 'sure, ship it to my work address instead',
            'sí, pero mandalo a otra dirección', 'yes add a u-joint too', 'ok but cheaper shipping'] as $t) $this->assertFalse(AssistantOrders::saysYes($t), $t);

        $u = $this->user();
        $this->cart();
        $this->say('order my cart for pickup with a pay link');
        $this->assertTrue($this->prepare($u)['ready']);
        $this->say('is that the shop in Odessa?');
        $this->say('ok');
        $r = $this->tool($u, 'place_order');
        $this->assertFalse($r['placed'], 'an "ok" two messages later is not the answer to the summary');
        $this->assertStringContainsString('Call prepare_order again', $r['error'], 'the bot shows a fresh summary instead of asking for yes again and again');
        $this->assertSame(0, DB::table('orders')->where('user_id', $u->id)->count());
        // the fresh summary, then a yes: placed
        $this->assertTrue($this->prepare($u)['ready']);
        $this->say('yes');
        $this->assertTrue($this->tool($u, 'place_order')['placed']);
    }

    public function test_chat_photos_are_for_the_customer_and_staff_who_open_chats(): void
    {
        $u = $this->user();
        \Illuminate\Support\Facades\Storage::disk('local')->put("chat/{$this->conv->id}/audit.jpg", 'x');
        $msg = DB::table('chat_messages')->insertGetId(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => '[]', 'display' => 'photo',
            'photos' => json_encode(["chat/{$this->conv->id}/audit.jpg"]), 'created_at' => now(), 'updated_at' => now()]);
        $this->get("/assistant/photo/$msg/0")->assertOk();

        $staff = User::query()->create(['name' => 'Shop Staff', 'email' => 'staff-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $staff->id)->update(['is_admin' => 1, 'admin_role' => 'shipping']);
        $this->flushSession();
        $this->actingAs($staff->fresh());
        $allowed = \App\Support\AdminAccess::can('orders', $staff->fresh());
        $this->get("/assistant/photo/$msg/0")->assertStatus($allowed ? 200 : 404);
        \Illuminate\Support\Facades\Storage::disk('local')->delete("chat/{$this->conv->id}/audit.jpg");
    }

    public function test_oversized_or_too_many_photos_are_refused(): void
    {
        // a few hundred bytes of PNG header can claim 40,000 x 40,000 pixels
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 40000, 40000) . "\x08\x02\x00\x00\x00" . pack('N', 0) . str_repeat("\0", 200);
        $this->expectExceptionObject(new \RuntimeException('Not an image.'));
        try {
            \App\Support\ChatPhotos::storeDataUrl('data:image/png;base64,' . base64_encode($png), 1);
        } finally {
            $u = $this->user();
            DB::table('chat_messages')->insert(['conversation_id' => $this->conv->id, 'role' => 'user', 'content' => '[]', 'display' => 'photos',
                'photos' => json_encode(array_fill(0, \App\Support\ChatPhotos::MAX_PER_CONVERSATION, 'chat/x.jpg')), 'created_at' => now(), 'updated_at' => now()]);
            $img = imagecreatetruecolor(20, 20); ob_start(); imagejpeg($img); $jpg = ob_get_clean();
            $this->postJson('/assistant', ['message' => 'one more', 'photos' => ['data:image/jpeg;base64,' . base64_encode($jpg)]])->assertStatus(409)->assertJsonPath('restart', true);
        }
    }
}
