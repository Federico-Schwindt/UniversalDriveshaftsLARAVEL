<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Owner, 2026-11-14: "pickup only for florida state please" — local pickup at the shop is offered to Florida
 * addresses only (CartService::pickupFor), at checkout, on the server when the order is placed, and in the assistant.
 */
class PickupFloridaOnlyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(fn () => Http::response([], 200));
    }

    private function fillCart(): void
    {
        $pid = (int) DB::table('catalog_live')->where('price', '>', 50)->where('in_stock', 1)->value('wp_product_id');
        $this->post('/cart/add', ['product_id' => $pid, 'quantity' => 1])->assertOk();
    }

    public function test_only_a_florida_address_may_pick_up(): void
    {
        $this->assertTrue(CartService::pickupFor('US', 'FL'));
        $this->assertTrue(CartService::pickupFor('US', ' fl'));
        $this->assertTrue(CartService::pickupFor('US', 'Florida'), 'an old address book kept the name');
        $this->assertTrue(CartService::pickupFor('', 'FL'));
        foreach ([['US', 'GA'], ['US', 'TX'], ['US', ''], ['CA', 'FL'], ['MX', '']] as [$cc, $st]) {
            $this->assertFalse(CartService::pickupFor($cc, $st), "$cc/$st");
        }
        $cart = app(CartService::class);
        $this->assertNotNull($cart->shippingCost(CartService::PICKUP_ID, 'US', 'FL'));
        $this->assertNull($cart->shippingCost(CartService::PICKUP_ID, 'US', 'GA'));
    }

    public function test_the_checkout_offers_pickup_to_florida_only_and_refuses_it_elsewhere(): void
    {
        $ids = fn ($state) => array_column($this->postJson('/checkout/shipping-options', ['country' => 'US', 'state' => $state, 'zip' => ''])->assertOk()->json('data'), 'id');
        $this->assertContains(CartService::PICKUP_ID, $ids('FL'));
        $this->assertNotContains(CartService::PICKUP_ID, $ids('GA'));
        $this->assertNotContains(CartService::PICKUP_ID, $ids(''));
        $this->assertStringNotContainsString('pickup-far', file_get_contents(resource_path('views/checkout.blade.php')), 'no "only if you come to Florida" warning: pickup is never offered outside it');

        // a pickup sent straight to the server from Georgia is turned down before anything is charged
        $this->fillCart();
        $this->postJson('/checkout/place', ['email' => 'pickup-' . uniqid() . '@example.com', 'phone' => '(404) 555-0142',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'billing_state' => 'GA',
            'shipping_method' => 'local_pickup', 'pickup_ack' => 1, 'agree_terms' => 1, 'payment_method' => 'card'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'The selected shipping method is not available for your address.');
    }

    public function test_the_assistant_offers_pickup_to_florida_customers_only(): void
    {
        $u = User::query()->create(['name' => 'Sam Lee', 'email' => 'bot-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['phone' => '(512) 555-0142', 'billing_state' => 'TX']);
        $u = $u->fresh();
        $this->actingAs($u);
        $this->fillCart();
        $bot = app(ChatbotService::class);
        (new \ReflectionProperty($bot, 'conv'))->setValue($bot, $bot->conversation($u));
        $tool = fn () => (new \ReflectionMethod($bot, 'runTool'))->invoke($bot, $u, 'prepare_order',
            ['address_id' => 'pickup', 'shipping_option' => 'local_pickup', 'payment' => 'pay_link', 'card_id' => '', 'po_number' => '', 'note' => ''])[0];

        $r = $tool();
        $this->assertFalse($r['ready']);
        $this->assertStringContainsString('Florida', $r['error']);

        DB::table('users')->where('id', $u->id)->update(['billing_state' => 'FL']);
        $u->refresh();
        $this->assertArrayNotHasKey('error', $tool());
    }
}
