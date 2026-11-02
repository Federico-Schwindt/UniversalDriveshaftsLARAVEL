<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * On-hand stock (admin Inventory) follows the orders: a sale takes the parts off the shelf once the order is
 * paid or committed (Net terms, pay at pickup), and a cancellation or a refund with restock puts them back.
 * Each happens once per order (OrderEvents once-keys) and only for parts we count by hand ('manual' source);
 * PTI / supplier stock is not ours to count. The storefront's own stock meta is kept in step like an admin edit.
 */
class StockLedger
{
    public static function deduct(object $order): void
    {
        if (!OrderEvents::record($order, 'stock:deducted', 'Stock taken off the shelf', null, true)) return;
        self::apply($order, -1, 'Sold · ' . $order->order_number);
    }

    /** Back on the shelf; only when this order's sale was counted, and only once. */
    public static function restore(object $order, string $why = 'Cancelled'): void
    {
        if (!OrderEvents::has($order, 'stock:deducted')) return;
        if (!OrderEvents::record($order, 'stock:restored', 'Stock put back', $why, true)) return;
        self::apply($order, 1, $why . ' · ' . $order->order_number);
    }

    private static function apply(object $order, int $sign, string $reason): void
    {
        $lines = DB::table('order_items')->where('order_id', $order->id)->whereNotNull('wp_product_id')
            ->selectRaw('wp_product_id, SUM(qty) as qty')->groupBy('wp_product_id')->get();
        foreach ($lines as $l) {
            try {
                DB::transaction(function () use ($l, $sign, $reason) {
                    $row = DB::table('inventory')->where('wp_product_id', $l->wp_product_id)->where('source', 'manual')->lockForUpdate()->first();
                    if (!$row) return;
                    $after = max(0, (int) $row->stock + $sign * (int) $l->qty);
                    if ($after === (int) $row->stock) return;
                    DB::table('inventory')->where('id', $row->id)->update(['stock' => $after, 'updated_at' => now()]);
                    DB::table('inventory_adjustments')->insert(['inventory_id' => $row->id, 'delta' => $after - (int) $row->stock, 'before' => (int) $row->stock,
                        'after' => $after, 'reason' => mb_substr($reason, 0, 160), 'user_id' => auth()->id(), 'created_at' => now()]);
                    app(\App\Services\Admin\CatalogWriter::class)->setStock((int) $l->wp_product_id, $after);
                });
            } catch (\Throwable $e) {
                report($e);   // a stock hiccup never blocks a payment or a cancellation
            }
        }
    }
}
