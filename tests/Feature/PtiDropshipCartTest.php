<?php

namespace Tests\Feature;

use App\Services\PtiClient;
use App\Services\PtiDropship;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** PTI dropship without a person in the loop: the cart is emptied first, rebuilt when it drifts, tracking follows PTI. Never calls PTI. */
class PtiDropshipCartTest extends TestCase
{
    use DatabaseTransactions;

    /** A PTI account in memory: one cart, one dropship address book, one order. */
    private function fakePti(): PtiClient
    {
        return new class extends PtiClient {
            public array $cart = [];
            public int $address = 0;
            public array $calls = [];
            public string $tracking = '';
            public function configured(): bool { return true; }
            public function stock(string $part): array { return ['total' => 50, 'can_build' => 0, 'by_store' => []]; }
            public function cartItems(): array { return array_map(fn ($p, $q) => ['part_number' => $p, 'quantity' => $q, 'price' => '$10.00'], array_keys($this->cart), $this->cart); }
            public function call(string $method, string $path, array $data = []): array
            {
                $this->calls[] = "$method $path";
                return match ("$method $path") {
                    'PUT my/cart/item/' => ($this->cart[$data['part_number']] = ($this->cart[$data['part_number']] ?? 0) + (int) $data['quantity']) ? [] : [],
                    'DELETE my/cart/item/' => (function () use ($data) { unset($this->cart[$data['part_number']]); return []; })(),
                    'PUT my/dropship_addresses/confirm_dropship_address' => ['validated_address' => $data],
                    'PUT my/dropship_addresses/dropship_address' => ['id' => 777],
                    'POST my/cart/cart' => (function () use ($data) { if (isset($data['dropship_address_id'])) $this->address = (int) $data['dropship_address_id']; return []; })(),
                    'GET my/cart/shipping_rates' => [['shipping_method' => 'ups_ground', 'name' => 'UPS Ground', 'price' => 12.5]],
                    'GET my/cart/cart' => ['items' => $this->cartItems(), 'dropship_address_id' => $this->address, 'total_price' => '$20.00', 'shipping_method' => 'ups_ground', 'po' => $data['po'] ?? DB::table('orders')->orderByDesc('id')->value('order_number')],
                    'GET my/cim/payment_profiles' => ['payment_profiles' => ['123' => ['type' => 'visa', 'number' => '4242']]],
                    'PUT my/orders/order' => (function () { $this->cart = []; return ['order_id' => 'PTI-5001']; })(),
                    default => str_starts_with($path, 'my/orders/order/') ? ['order' => ['tracking_number' => $this->tracking]] : [],
                };
            }
        };
    }

    private function order(): object
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'UDS-TEST-' . uniqid(), 'status' => 'processing', 'payment_status' => 'paid', 'email' => 'alex.morgan@example.com',
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'total' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $part = DB::table('pti_parts')->whereIn('match', ['sku', 'sku_u', 'created'])->whereNotNull('wp_product_id')->first();
        if (!$part) $this->markTestSkipped('no linked PTI part in the catalog');
        DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'wp_product_id' => $part->wp_product_id, 'name' => 'Test part', 'sku' => $part->part_number,
            'price' => 50, 'qty' => 2, 'line_total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    private function ship(): array
    {
        return ['company' => 'Morgan Driveline', 'street' => '100 Main St', 'unit' => '', 'city' => 'Tampa', 'st' => 'FL', 'zip' => '33602', 'country' => 'US', 'phone' => '8135550142'];
    }

    public function test_cart_is_emptied_first_and_rebuilt_when_it_drifts_before_placing(): void
    {
        Mail::fake();
        $pti = $this->fakePti();
        $pti->cart = ['2726-255' => 1];   // left over in the cart (the case the owner hit)
        $ds = new PtiDropship($pti);
        $o = $this->order();
        $item = DB::table('order_items')->where('order_id', $o->id)->first();
        $res = $ds->prepare($o, [$item->id => 2], $this->ship(), false, 1, [$item->id => 1]);
        $this->assertTrue($res['ok'], json_encode($res));
        $this->assertSame([$item->sku => 2], $pti->cart);
        $this->assertTrue(DB::table('order_events')->where('order_id', $o->id)->where('type', 'pti_cart')->where('detail', 'like', '%2726-255%')->exists());

        // someone puts something else in PTI's cart before "Place order": it is put back, then ordered
        $pti->cart['9999-1'] = 3;
        $placed = $ds->place($res['dropship_id'], 'ups_ground', 'UPS Ground', 12.5, '100', '123', false);
        $this->assertTrue($placed['ok'], json_encode($placed));
        $this->assertSame('placed', DB::table('pti_dropships')->where('id', $res['dropship_id'])->value('status'));
    }

    public function test_tracking_follows_pti_including_a_new_label(): void
    {
        Mail::fake();
        $pti = $this->fakePti();
        $ds = new PtiDropship($pti);
        $o = $this->order();
        $id = DB::table('pti_dropships')->insertGetId(['order_id' => $o->id, 'status' => 'placed', 'pti_order_id' => 'PTI-5001', 'lines' => '[]', 'ship_to' => '{}', 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pti->tracking = '1Z999AA10123456784';
        $ds->syncTracking(DB::table('pti_dropships')->find($id), false);
        $this->assertSame('1Z999AA10123456784', DB::table('orders')->where('id', $o->id)->value('tracking_number'));
        // PTI re-labels the parcel: the order follows
        $pti->tracking = '1Z999AA10123456795';
        $ds->syncTracking(DB::table('pti_dropships')->find($id), false);
        $this->assertSame('1Z999AA10123456795', DB::table('orders')->where('id', $o->id)->value('tracking_number'));
        $this->assertSame('1Z999AA10123456795', DB::table('pti_dropships')->where('id', $id)->value('tracking_number'));
    }
}
