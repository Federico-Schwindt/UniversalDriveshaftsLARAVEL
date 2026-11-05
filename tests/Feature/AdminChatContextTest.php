<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The admin chat page: the customer's whereabouts and trail, and replies that carry photos, parts and tracking. */
class AdminChatContextTest extends TestCase
{
    use DatabaseTransactions;

    /** AuthenticateSession logs out when the user changes mid-test, so switching users starts a fresh session. */
    private function as(User $u): static
    {
        if (auth()->id() !== $u->id) $this->flushSession();
        return $this->actingAs($u);
    }

    private function customer(): User
    {
        return User::query()->create(['name' => 'Alex Morgan', 'email' => 'chat-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
    }

    private function conv(User $u): int
    {
        return DB::table('chat_conversations')->insertGetId(['user_id' => $u->id, 'status' => 'open', 'provider' => 'claude', 'mode' => 'bot', 'system_prompt' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_customer_pages_errors_and_heartbeat_reach_the_admin_panel(): void
    {
        $u = $this->customer();
        $this->as($u)->get('/shop')->assertOk();
        $this->as($u)->get('/parts/does-not-exist-' . uniqid());
        $this->as($u)->postJson('/assistant/ping', ['url' => '/cart', 'title' => 'Cart · Universal Driveshafts'])->assertOk()->assertJson(['unread' => 0]);
        $this->as($u)->postJson('/assistant/client-log', ['message' => 'TypeError: x is undefined', 'source' => 'https://dev.udshafts.com/js/cart.js', 'line' => 12, 'url' => '/cart'])->assertNoContent();
        $kinds = DB::table('customer_activity')->where('user_id', $u->id)->pluck('kind')->all();
        $this->assertContains('page', $kinds);
        $this->assertContains('error', $kinds);
        $this->assertContains('client_error', $kinds);

        $id = $this->conv($u);
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $ctx = $this->as($admin)->getJson("/admin/chats/$id/context")->assertOk()->json();
        $this->assertTrue($ctx['presence']['online']);
        $this->assertSame('/cart', $ctx['presence']['url']);
        $this->assertGreaterThanOrEqual(2, $ctx['errors']);
        $this->assertStringContainsString('cart.js:12', collect($ctx['activity'])->firstWhere('kind', 'client_error')['detail']);
        // staff browsing is never logged as a customer
        $this->as($admin)->get('/shop');
        $this->assertFalse(DB::table('customer_activity')->where('user_id', $admin->id)->exists());
        $this->as($admin)->get("/admin/chats/$id")->assertOk()->assertSee('Right now')->assertSee('Activity');
    }

    public function test_reply_carries_a_photo_a_part_and_tracking(): void
    {
        $u = $this->customer();
        $id = $this->conv($u);
        $order = DB::table('orders')->insertGetId(['order_number' => 'UDS-CHAT-' . uniqid(), 'user_id' => $u->id, 'email' => $u->email, 'status' => 'completed', 'payment_status' => 'paid',
            'total' => 50, 'subtotal' => 50, 'tracking_number' => '1Z999AA10123456784', 'tracking_carrier' => 'UPS', 'shipped_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $sku = app(\App\Services\ProductService::class)->search('u-joint', 1)[0]['sku'] ?? null;
        if (!$sku) $this->markTestSkipped('catalog empty');
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $this->as($admin)->getJson("/admin/chats/$id/products?q=" . urlencode($sku))->assertOk()->assertJsonFragment(['sku' => $sku]);

        $img = imagecreatetruecolor(40, 30); ob_start(); imagepng($img); $png = 'data:image/png;base64,' . base64_encode(ob_get_clean());
        $res = $this->as($admin)->postJson("/admin/chats/$id/reply", ['text' => 'Here is the part and your tracking.', 'photos' => [$png], 'skus' => [$sku], 'tracking_order' => $order])->assertOk()->json();
        $m = collect($res['messages'])->firstWhere('role', 'agent');
        $this->assertCount(1, $m['photos']);
        $this->assertSame($sku, $m['cards'][0]['sku']);
        $this->assertSame('tracking', $m['widgets'][0]['type']);
        $this->assertSame('1Z999AA10123456784', $m['widgets'][0]['tracking_number']);
        // the customer sees the same message (and its photo) in their widget
        $poll = $this->as($u)->getJson('/assistant/poll?after=0')->assertOk()->json();
        $this->assertSame($sku, collect($poll['messages'])->firstWhere('role', 'agent')['cards'][0]['sku']);
        $this->as($u)->get($m['photos'][0])->assertOk();

        // an order of someone else cannot be pushed into this chat
        $other = DB::table('orders')->insertGetId(['order_number' => 'UDS-OTHER-' . uniqid(), 'email' => 'someone.else@example.com', 'status' => 'completed', 'payment_status' => 'paid', 'total' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->as($admin)->postJson("/admin/chats/$id/reply", ['tracking_order' => $other])->assertStatus(422);
        Storage::disk('local')->deleteDirectory('chat/' . $id);
    }
}
