<?php

namespace Tests\Feature;

use App\Http\Controllers\ShopController;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Owner decisions of 2026-11-01: free-shipping coupons in the continental U.S. only, stock follows sales, postal address on marketing mail. */
class OwnerDecisionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_free_shipping_coupons_only_ship_free_in_the_continental_us(): void
    {
        $this->assertTrue(ShopController::freeShipCountry('US', 'FL'));
        $this->assertTrue(ShopController::freeShipCountry('us', 'tx'));
        $this->assertFalse(ShopController::freeShipCountry('US', 'HI'));
        $this->assertFalse(ShopController::freeShipCountry('US', 'PR'));
        $this->assertFalse(ShopController::freeShipCountry('CA', 'ON'));
        $this->assertFalse(ShopController::freeShipCountry('MX', ''));
    }

    public function test_a_sale_takes_stock_off_once_and_a_cancel_puts_it_back_once(): void
    {
        $inv = DB::table('inventory')->where('source', 'manual')->where('stock', '>', 3)->whereNotNull('wp_product_id')->first();
        if (!$inv) $this->markTestSkipped('no counted stock');
        $id = DB::table('orders')->insertGetId(['order_number' => 'T-' . uniqid(), 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe', 'email' => 'alex@example.com',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'subtotal' => 10, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'wp_product_id' => $inv->wp_product_id, 'name' => 'x', 'sku' => $inv->sku, 'price' => 5, 'qty' => 2, 'line_total' => 10, 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $o = DB::table('orders')->find($id);
        $stock = fn () => (int) DB::table('inventory')->where('id', $inv->id)->value('stock');

        StockLedger::restore($o);                    // nothing was taken: nothing goes back
        $this->assertSame((int) $inv->stock, $stock());
        StockLedger::deduct($o);
        StockLedger::deduct($o);                     // once per order
        $this->assertSame((int) $inv->stock - 2, $stock());
        $this->assertTrue(DB::table('inventory_adjustments')->where('inventory_id', $inv->id)->where('delta', -2)->exists());
        StockLedger::restore($o, 'Refund');
        StockLedger::restore($o, 'Refund');          // a second partial refund doesn't add them again
        $this->assertSame((int) $inv->stock, $stock());
    }

    public function test_marketing_mail_carries_the_postal_address(): void
    {
        $html = view('emails.marketing.welcome', ['name' => 'Alex', 'unsubscribe' => 'https://x.test/u'])->render();
        $this->assertStringContainsString('11611 Prospect Drive', $html);
        $this->assertStringContainsString('Odessa, FL 33556', $html);
    }
}
