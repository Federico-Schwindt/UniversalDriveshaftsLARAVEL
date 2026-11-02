<?php

namespace App\Services\Admin;

use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use App\Services\ProductService;
use App\Support\AdminLog;
use App\Support\OrderEvents;
use Illuminate\Support\Facades\DB;

/** Creates and edits orders from the admin (phone / counter / wholesale orders). */
class OrderBuilder
{
    public function __construct(private CartService $cart, private ProductService $products, private CatalogService $catalog) {}

    /** Resolve a line sent by the order form into a cart-shaped item. */
    public function line(array $in): ?array
    {
        $qty = max(1, (int) ($in['qty'] ?? 1));
        $type = $in['type'] ?? 'product';
        if ($type === 'custom') {
            $name = trim((string) ($in['name'] ?? ''));
            if ($name === '') return null;
            return ['type' => 'custom', 'wp_product_id' => null, 'name' => $name, 'sku' => trim((string) ($in['sku'] ?? '')), 'image' => '',
                'price' => round((float) ($in['price'] ?? 0), 2), 'qty' => $qty, 'meta' => ['admin_line' => true, 'weight_lb' => (float) ($in['weight'] ?? 0)]];
        }
        if ($type === 'driveshaft') {
            $pn = DriveshaftFinder::pn((string) ($in['sku'] ?? ''));
            $p = DB::table('ds_parts')->where('part_number', $pn)->first();
            if (!$p) return null;
            $price = isset($in['price']) && $in['price'] !== '' ? (float) $in['price'] : (float) (DriveshaftFinder::customerPrice($p) ?? 0);
            return ['type' => 'driveshaft', 'wp_product_id' => null, 'name' => $p->description ?: ('Driveshaft ' . $pn), 'sku' => DriveshaftFinder::sku($pn),
                'image' => $p->main_image ? media_url($p->main_image, 160) : '', 'price' => round($price, 2), 'qty' => $qty,
                'meta' => ['part_number' => $pn, 'weight_lb' => (float) $p->weight, 'vehicle' => $in['vehicle'] ?? null]];
        }
        $pid = (int) ($in['id'] ?? 0);
        $p = $pid ? $this->products->byId($pid) : null;
        if (!$p) return null;
        $price = isset($in['price']) && $in['price'] !== '' ? (float) $in['price'] : (float) $p['price'];
        return ['type' => 'product', 'wp_product_id' => $pid, 'name' => $p['name'], 'sku' => $p['sku'], 'image' => $p['image'],
            'price' => round($price, 2), 'qty' => $qty, 'meta' => []];
    }

    public function lines(array $raw): array
    {
        $out = [];
        foreach ($raw as $l) { if ($i = $this->line($l)) $out[] = $i; }
        return $out;
    }

    /** Shipping choices for a set of lines and an address. Adds the "custom amount" and "no charge" options the admin always has. */
    public function shippingOptions(array $items, string $country, string $state, string $zip): array
    {
        $opts = [];
        try { $opts = $this->cart->shippingOptions(strtoupper($country ?: 'US'), strtoupper($state), $zip, $items); } catch (\Throwable $e) {}
        $opts[] = ['id' => 'manual', 'label' => 'Custom shipping amount', 'cost' => 0.0, 'manual' => true];
        $opts[] = ['id' => 'none', 'label' => 'No shipping charge', 'cost' => 0.0];
        return $opts;
    }

    /** Create the order. Returns the order row. */
    public function create(array $d, array $items, ?object $actor = null): object
    {
        $subtotal = CartService::subtotalOf($items);
        $discount = round((float) ($d['discount_total'] ?? 0), 2);
        $tax = round((float) ($d['tax_total'] ?? 0), 2);
        $shipCost = round((float) ($d['shipping_total'] ?? 0), 2);
        $shipId = ($d['shipping_method'] ?? '') ?: 'manual';
        $shipLabel = ($d['shipping_label'] ?? '') ?: ($shipId === 'none' ? 'No shipping charge' : ($shipId === CartService::PICKUP_ID ? config('uds.pickup.label') : 'Shipping'));
        $total = round(max(0, $subtotal - $discount) + $tax + $shipCost, 2);
        $paid = ($d['payment_status'] ?? 'unpaid') === 'paid';
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => CartService::newOrderNumber(),
            'user_id' => $d['user_id'] ?: null,
            'status' => $d['status'] ?? 'pending',
            'source' => $d['source'] ?? 'admin',
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'payment_method' => $d['payment_method'] ?? 'manual',
            'paid_at' => $paid ? now() : null,
            // Net-terms invoices carry a due date like checkout's, so they show on the Invoices tab and in B2B credit
            'terms_due_at' => ($d['payment_method'] ?? '') === 'net_terms' && !$paid ? now('America/New_York')->addDays(\App\Services\B2bAccounts::termsDays())->toDateString() : null,
            'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'sms_opt_in' => !empty($d['sms_opt_in']),
            'billing_first_name' => $d['billing_first_name'], 'billing_last_name' => $d['billing_last_name'] ?? '',
            'billing_company' => $d['billing_company'] ?? null, 'billing_address_1' => $d['billing_address_1'] ?? null, 'billing_address_2' => $d['billing_address_2'] ?? null,
            'billing_city' => $d['billing_city'] ?? null, 'billing_state' => $d['billing_state'] ?? null, 'billing_postcode' => $d['billing_postcode'] ?? null,
            'billing_country' => strtoupper($d['billing_country'] ?? 'US'),
            'ship_to_different' => !empty($d['ship_to_different']),
            'shipping_first_name' => $d['shipping_first_name'] ?? null, 'shipping_last_name' => $d['shipping_last_name'] ?? null,
            'shipping_address_1' => $d['shipping_address_1'] ?? null, 'shipping_address_2' => $d['shipping_address_2'] ?? null,
            'shipping_city' => $d['shipping_city'] ?? null, 'shipping_state' => $d['shipping_state'] ?? null, 'shipping_postcode' => $d['shipping_postcode'] ?? null,
            'shipping_country' => !empty($d['ship_to_different']) ? strtoupper($d['shipping_country'] ?? 'US') : null,
            'shipping_method' => $shipId, 'shipping_label' => $shipLabel,
            'subtotal' => $subtotal, 'shipping_total' => $shipCost, 'discount_total' => $discount, 'tax_total' => $tax,
            'coupon_code' => $d['coupon_code'] ?? null, 'total' => $total,
            'customer_note' => $d['customer_note'] ?? null, 'po_number' => $d['po_number'] ?? null,
            'signature_required' => !empty($d['signature_required']), 'ship_instructions' => $d['ship_instructions'] ?? null,
            'tags' => $d['tags'] ?? null, 'ip' => request()?->ip(), 'created_by' => $actor?->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($items as $i) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'type' => $i['type'], 'wp_product_id' => $i['wp_product_id'], 'name' => $i['name'], 'sku' => $i['sku'],
                'price' => $i['price'], 'qty' => $i['qty'], 'line_total' => round($i['price'] * $i['qty'], 2),
                'meta' => json_encode($i['meta'] ?? []), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $order = DB::table('orders')->find($orderId);
        OrderEvents::record($order, 'placed', 'Order created by ' . ($actor?->name ?: 'admin'), $shipLabel, true);
        if ($paid) OrderEvents::record($order, 'paid', 'Payment recorded', ucfirst(str_replace('_', ' ', $order->payment_method)), true);
        if (!empty($d['internal_note'])) DB::table('order_notes')->insert(['order_id' => $orderId, 'user_id' => $actor?->id, 'kind' => 'internal', 'body' => $d['internal_note'], 'created_at' => now()]);
        AdminLog::record('order.create', 'Created order ' . $order->order_number, 'order', $orderId, ['total' => $total, 'items' => count($items)]);
        try { app(\App\Services\BrevoService::class)->syncOrder($order); } catch (\Throwable $e) {}
        return $order;
    }

    /** Recompute subtotal/total from the stored lines (after an item edit). */
    public function recalc(int $orderId): object
    {
        $o = DB::table('orders')->find($orderId);
        $sub = (float) DB::table('order_items')->where('order_id', $orderId)->sum('line_total');
        $total = round(max(0, $sub - (float) $o->discount_total) + (float) $o->tax_total + (float) $o->shipping_total, 2);
        DB::table('orders')->where('id', $orderId)->update(['subtotal' => round($sub, 2), 'total' => $total, 'updated_at' => now()]);
        return DB::table('orders')->find($orderId);
    }
}
