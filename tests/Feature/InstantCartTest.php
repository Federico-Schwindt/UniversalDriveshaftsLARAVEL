<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Owner, 2026-11-12: the slide-in cart stayed blank for seconds on a phone. Every page now carries the cart, so the
 * panel opens with its lines, and the lines show the list-size picture instead of the product page's 1200 px one.
 */
class InstantCartTest extends TestCase
{
    private function line(): array
    {
        return ['key' => 'k1', 'type' => 'custom_build', 'wp_product_id' => null, 'name' => 'Test </script> shaft', 'sku' => 'ZZ-1',
            'image' => '/media/1200/abcdefgh.webp?v=1', 'price' => 10.5, 'qty' => 2, 'meta' => ['config' => ['a1' => 40, 'note' => 'private build data']]];
    }

    public function test_every_page_carries_the_cart_for_the_panel(): void
    {
        $html = $this->withSession(['uds_cart' => ['k1' => $this->line()]])->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/window\.UCART_DATA = \{"count":2,"subtotal":21,"items":\[\{"key":"k1","sku":"ZZ-1"/', $html);
        $this->assertStringContainsString('\/media\/320\/abcdefgh.webp?v=1', $html);
        // a name can never close the script, and a line's build data stays out of the page
        $this->assertStringNotContainsString('Test </script>', $html);
        $this->assertStringNotContainsString('private build data', $html);
        $this->flushSession();
        $this->get('/')->assertSee('window.UCART_DATA = null', false);
    }

    public function test_the_panel_endpoint_answers_with_the_same_lines(): void
    {
        $d = $this->withSession(['uds_cart' => ['k1' => $this->line()]])->getJson('/cart/mini')->assertOk()->json('data');
        $this->assertSame(['key' => 'k1', 'sku' => 'ZZ-1', 'name' => 'Test </script> shaft', 'image' => '/media/320/abcdefgh.webp?v=1', 'price' => 10.5, 'qty' => 2], $d['items'][0]);
        $this->assertSame(2, $d['count']);
        $this->assertArrayHasKey('free_base', $d);
    }
}
