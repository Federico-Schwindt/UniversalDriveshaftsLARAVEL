<?php

namespace Tests\Feature;

use App\Services\CartService;
use App\Support\Arrival;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner, 2026-11-12: "Carrito: mostrar el costo de envío con el código postal que ya conocemos". /api/cart-ship answers
 * the cheapest carrier option for the session's cart. It never makes a rate up (no rate = the checkout explains), and
 * it only reads the session: a slow carrier answer must not write an old cart back.
 */
class CartShippingTest extends TestCase
{
    private const LINE = ['key' => 'k1', 'type' => 'product', 'wp_product_id' => 1, 'name' => '1350 U-Joint', 'sku' => '5-1350XU', 'price' => 42.0, 'qty' => 2];
    private const GROUND = ['id' => 'ups_ground', 'label' => 'UPS Ground · 3 business days', 'cost' => 18.4, 'days' => 3];
    private const AIR = ['id' => 'ups_2nd_day_air', 'label' => 'UPS 2nd Day Air · 2 business days', 'cost' => 44.1, 'days' => 2];
    private const PICKUP = ['id' => CartService::PICKUP_ID, 'label' => 'Pick up at our shop', 'cost' => 0.0];

    /** Every question put to the carrier: [country, state, zip, items]. */
    private array $asked = [];

    /** The carrier answers these options, whatever is asked. */
    private function carrier(array $opts): void
    {
        Http::fake();   // nothing here reaches a real carrier
        $this->partialMock(CartService::class, fn ($m) => $m->shouldReceive('shippingOptions')->andReturnUsing(function (...$a) use ($opts) {
            $this->asked[] = $a;
            return $opts;
        }));
    }

    private function ship(string $zip, array $line = self::LINE)
    {
        return $this->withSession(['uds_cart' => ['k1' => $line]])->getJson('/api/cart-ship?zip=' . $zip)->assertOk();
    }

    public function test_without_a_zip_or_a_cart_the_carrier_is_not_asked(): void
    {
        $this->carrier([self::GROUND]);
        $this->getJson('/api/cart-ship')->assertOk()->assertJsonPath('success', false)->assertJsonPath('need_zip', true);
        $this->getJson('/api/cart-ship?zip=336')->assertOk()->assertJsonPath('need_zip', true);
        $this->getJson('/api/cart-ship?zip=33607')->assertOk()->assertJsonPath('success', false)->assertJsonPath('empty', true);
        $this->assertSame([], $this->asked);
    }

    public function test_the_cheapest_carrier_option_is_the_estimate_and_pickup_never_is(): void
    {
        $this->carrier([self::PICKUP, self::AIR, self::GROUND]);
        $res = $this->ship('33607-1234');
        $res->assertJson(['success' => true, 'zip' => '33607', 'cost' => 18.4, 'free' => false, 'more' => true, 'parts_free' => false,
            'service' => 'UPS Ground', 'freight' => false, 'built' => false, 'iso' => Arrival::dateAfter(3)->toDateString()]);
        $this->assertNotEmpty($res->json('date'));
        $this->assertSame(['US', '', '33607'], array_slice($this->asked[0], 0, 3));
        $this->assertSame('5-1350XU', $this->asked[0][3][0]['sku'], 'the session cart is what gets rated');

        // the same cart to the same ZIP is answered from the cache; another quantity is a new question
        $this->ship('33607');
        $this->assertCount(1, $this->asked);
        $this->ship('33607', ['qty' => 3] + self::LINE);
        $this->assertCount(2, $this->asked);
    }

    public function test_the_lookup_only_reads_the_session(): void
    {
        $this->carrier([self::GROUND]);
        $res = $this->ship('33607');
        $this->assertNull(collect($res->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session')), 'no session cookie is set');
        $route = app('router')->getRoutes()->match(Request::create('/api/cart-ship'));
        $this->assertContains(StartSession::class, $route->excludedMiddleware(), 'without the session middleware nothing is saved when the request ends');
    }

    public function test_no_carrier_rate_is_never_made_up_or_remembered(): void
    {
        $this->carrier([self::PICKUP]);
        foreach ([1, 2] as $n) {
            $this->ship('33607')->assertJsonPath('success', false)->assertJsonMissingPath('cost')->assertJsonMissingPath('need_zip');
            $this->assertCount($n, $this->asked, 'the carrier is asked again the next time');
        }
    }

    public function test_free_shipping_shows_as_free_with_the_ground_service_and_date(): void
    {
        $this->carrier([['id' => 'free_shipping', 'label' => 'Free shipping', 'cost' => 0.0], self::GROUND, self::AIR, self::PICKUP]);
        $this->ship('33607')->assertJson(['success' => true, 'cost' => 0, 'free' => true, 'parts_free' => false, 'service' => 'UPS Ground',
            'iso' => Arrival::dateAfter(3)->toDateString()]);
    }

    public function test_parts_free_with_rated_tube_is_a_price_not_free_shipping(): void
    {
        $this->carrier([['id' => 'free_ups_ground', 'label' => 'Free shipping on parts + UPS Ground for 1 tube', 'cost' => 31.5, 'days' => 4], self::PICKUP]);
        $this->ship('33607')->assertJson(['success' => true, 'cost' => 31.5, 'free' => false, 'parts_free' => true, 'service' => 'UPS Ground', 'more' => false]);
    }

    public function test_a_driveshaft_built_to_order_gets_a_price_and_no_date(): void
    {
        $this->carrier([self::GROUND]);
        $build = ['type' => 'custom_build', 'wp_product_id' => null, 'sku' => 'UDS-CUSTOM', 'name' => 'Custom driveshaft', 'price' => 640.0, 'qty' => 1, 'meta' => ['config' => ['a1' => 52.5]]] + self::LINE;
        $this->ship('33607', $build)->assertJson(['success' => true, 'cost' => 18.4, 'built' => true, 'date' => null, 'iso' => null]);
    }

    public function test_freight_and_the_states_outside_the_lower_48_are_told_apart(): void
    {
        $this->carrier([['id' => 'warp-ltl-fedex', 'label' => 'LTL freight · FedEx Freight', 'cost' => 212.0, 'days' => 5]]);
        $this->ship('99501')->assertJson(['success' => true, 'freight' => true, 'cost' => 212.0]);
        $this->ship('00901');
        $this->ship('96813');
        $this->assertSame(['AK', 'PR', 'HI'], array_column($this->asked, 1), 'those never ship free: the state goes to the rating');
    }
}
