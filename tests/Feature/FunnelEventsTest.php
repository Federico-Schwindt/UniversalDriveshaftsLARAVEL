<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use Tests\TestCase;

/**
 * Owner, 2026-11-12: "quiero vender mucho más". Only the purchase reached GA4 and Meta, so nobody could see where buyers
 * dropped off; every step of the funnel is sent now, with the ids Merchant Center knows the products by.
 */
class FunnelEventsTest extends TestCase
{
    private function line(int $id, string $sku): array
    {
        return ['key' => 'k1', 'type' => 'product', 'wp_product_id' => $id, 'name' => 'Test part', 'sku' => $sku, 'image' => '', 'price' => 12.5, 'qty' => 2, 'meta' => []];
    }

    public function test_every_step_of_the_funnel_is_on_its_page(): void
    {
        $c = app(CatalogService::class);
        $id = (int) $c->searchIds('3104-29')[0];   // an end yoke with a price and a product page
        $p = $c->productById($id);
        // the function the events go through exists on every page, and sends nothing without the tags
        $this->get('/')->assertSee('window.udsTrack = function', false);
        $this->get('/parts/' . rawurlencode($p['sku']))->assertOk()->assertSee('udsTrack("view_item"', false)->assertSee('"item_id":"gla_' . $id . '"', false);
        $this->get('/search?q=1310')->assertSee('udsTrack("search"', false)->assertSee('"search_term":"1310"', false);
        $cart = ['k1' => $this->line($id, $p['sku'])];
        $this->withSession(['uds_cart' => $cart])->get('/cart')->assertSee('udsTrack("view_cart"', false)->assertSee('"value":25', false);
        $this->withSession(['uds_cart' => $cart])->get('/checkout')->assertSee("udsTrack('begin_checkout', cart)", false)->assertSee('"item_id":"gla_' . $id . '"', false);
        // the cart panel's lines carry the same id, so add to cart and remove match the purchase
        $this->withSession(['uds_cart' => $cart])->getJson('/cart/mini')->assertJsonPath('data.items.0.item_id', 'gla_' . $id);
    }
}
