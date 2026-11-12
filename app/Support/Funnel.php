<?php

namespace App\Support;

use App\Http\Controllers\FeedController;

/**
 * The shopping funnel as GA4 ecommerce events (and their Meta pixel twins, partials/analytics udsTrack()): list seen,
 * product seen, added, cart, checkout, shipping, payment, purchase. Until 2026-11-12 only the purchase was sent, so
 * nobody could see where buyers dropped off (owner: "quiero vender mucho más").
 */
class Funnel
{
    /** A GA4 item. A catalog product goes by the id Merchant Center knows it by (as the purchase does), anything else by its SKU. */
    public static function item(?int $wpId, string $sku, string $name, float $price, int $qty = 1, string $category = '', string $type = 'product'): array
    {
        return array_filter([
            'item_id' => $wpId && $type === 'product' ? FeedController::offerId($wpId) : $sku,
            'item_name' => $name, 'sku' => $sku, 'price' => round($price, 2), 'quantity' => max(1, $qty), 'item_category' => $category,
        ], fn ($v) => $v !== '' && $v !== null);
    }

    /** Cart lines (CartService::items()) as GA4 items. */
    public static function lines(array $lines): array
    {
        return array_values(array_map(fn ($i) => self::item(isset($i['wp_product_id']) ? (int) $i['wp_product_id'] : null, (string) ($i['sku'] ?? ''),
            (string) ($i['name'] ?? ''), (float) ($i['price'] ?? 0), (int) ($i['qty'] ?? 1), '', (string) ($i['type'] ?? 'product')), $lines));
    }

    /** ['value' => total, 'items' => [...]]: the payload of a cart-wide event (view_cart, begin_checkout). */
    public static function cart(array $lines): array
    {
        $items = self::lines($lines);
        return ['value' => round(array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items)), 2), 'items' => $items];
    }
}
