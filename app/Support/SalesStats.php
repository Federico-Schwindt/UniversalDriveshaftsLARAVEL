<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One definition of sales for the dashboard, Reports and Monthly sales — the one WooCommerce Analytics uses, so the
 * numbers can be checked against the old store (owner, 2026-11-04):
 *
 *  - Net sales = order total less shipping and tax (products and fees after coupons), less refunds of products.
 *    Shipping and tax are reported on their own; "total sales" = net + shipping + tax.
 *  - A refund comes off on the day it was made (order_refunds.created_at), not on the order's day.
 *  - Days and months are Eastern time, the store's clock.
 *  - Which orders count: imported ones by their WooCommerce status (not pending / failed / cancelled, WooCommerce's
 *    defaults); orders placed here when paid (incl. later refunded) or on Net terms, and never cancelled / failed.
 * Verified 2026-11-04: Nov 2025 – Oct 2026 match WooCommerce's revenue report to the cent.
 */
class SalesStats
{
    public const TZ = 'America/New_York';
    public const WOO_EXCLUDED = ['pending', 'failed', 'cancelled', 'checkout-draft', 'trash'];

    /** Constrain an orders query (columns prefixed with $p, e.g. "o.") to orders that count as sales. */
    public static function counted($q, string $p = '')
    {
        return $q->where(fn ($s) => $s
            ->where(fn ($w) => $w->where($p . 'source', 'woocommerce')->whereNotIn($p . 'wc_status', self::WOO_EXCLUDED))
            ->orWhere(fn ($w) => $w->where(fn ($x) => $x->whereNull($p . 'source')->orWhere($p . 'source', '!=', 'woocommerce'))
                ->whereNotIn($p . 'status', ['cancelled', 'failed'])
                ->where(fn ($x) => $x->whereIn($p . 'payment_status', ['paid', 'partially_refunded', 'refunded'])->orWhere($p . 'payment_method', 'net_terms'))));
    }

    /** UTC bounds of an Eastern date range (whole days). */
    public static function bounds(Carbon $from, Carbon $to): array
    {
        return [Carbon::parse($from->toDateString(), self::TZ)->startOfDay()->utc(), Carbon::parse($to->toDateString(), self::TZ)->endOfDay()->utc()];
    }

    /**
     * Per Eastern day in the range: net, orders, shipping, tax, refunds, discounts.
     * @return array<string, array{net:float,orders:int,shipping:float,tax:float,refunds:float,discounts:float}>
     */
    public static function daily(Carbon $from, Carbon $to): array
    {
        [$a, $b] = self::bounds($from, $to);
        $out = [];
        $blank = ['net' => 0.0, 'orders' => 0, 'shipping' => 0.0, 'tax' => 0.0, 'refunds' => 0.0, 'discounts' => 0.0];
        $key = fn (string $at) => Carbon::parse($at, 'UTC')->timezone(self::TZ)->toDateString();
        foreach (self::counted(DB::table('orders'))->whereBetween('created_at', [$a, $b])->get(['created_at', 'total', 'shipping_total', 'tax_total', 'discount_total']) as $o) {
            $k = $key($o->created_at);
            $out[$k] ??= $blank;
            $out[$k]['net'] += (float) $o->total - (float) $o->shipping_total - (float) $o->tax_total;
            $out[$k]['orders']++; $out[$k]['shipping'] += (float) $o->shipping_total; $out[$k]['tax'] += (float) $o->tax_total; $out[$k]['discounts'] += (float) $o->discount_total;
        }
        foreach (self::refundRows($a, $b) as $r) {
            $k = $key($r->created_at);
            $out[$k] ??= $blank;
            $out[$k]['net'] -= (float) $r->amount - (float) $r->shipping - (float) $r->tax;
            $out[$k]['refunds'] += (float) $r->amount; $out[$k]['shipping'] -= (float) $r->shipping; $out[$k]['tax'] -= (float) $r->tax;
        }
        ksort($out);
        return $out;
    }

    /** Refunds made in [$a, $b] (UTC) on orders that count. */
    public static function refundRows(Carbon $a, Carbon $b)
    {
        return self::counted(DB::table('order_refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id'), 'o.')
            ->whereBetween('r.created_at', [$a, $b])->get(['r.order_id', 'r.amount', 'r.shipping', 'r.tax', 'r.created_at', 'o.email', 'o.user_id', 'o.source']);
    }

    /** Totals of daily(). */
    public static function totals(Carbon $from, Carbon $to): array
    {
        $t = ['net' => 0.0, 'orders' => 0, 'shipping' => 0.0, 'tax' => 0.0, 'refunds' => 0.0, 'discounts' => 0.0];
        foreach (self::daily($from, $to) as $d) foreach ($t as $k => $_) $t[$k] += $d[$k];
        return $t;
    }

    /** Per-order net for breakdowns (top customers, states…): refunds against the order itself. */
    public const ORDER_NET = '(total - shipping_total - tax_total - COALESCE(refund_total, 0))';
}
