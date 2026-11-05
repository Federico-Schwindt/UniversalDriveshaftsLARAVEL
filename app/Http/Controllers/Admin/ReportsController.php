<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /** Orders that count as sales: see App\Support\SalesStats (the WooCommerce Analytics definition). */
    public static function sold($q, string $p = '')
    {
        return \App\Support\SalesStats::counted($q, $p);
    }

    /** Net sales of one order row for breakdowns: total less shipping, tax and refunds. Period totals use SalesStats (refunds on their own day). */
    public const NET = \App\Support\SalesStats::ORDER_NET;

    public function index(Request $r)
    {
        $preset = $r->query('range', '30d');
        // a hand-typed custom range must be two real dates; anything else falls back to the last 30 days
        if ($preset === 'custom') {
            $v = \Illuminate\Support\Facades\Validator::make($r->query(), ['from' => 'required|date|after:2000-01-01', 'to' => 'required|date|after_or_equal:from|before:2100-01-01']);
            if ($v->fails()) { $preset = '30d'; session()->now('admin_err', 'That custom date range is not valid — showing the last 30 days.'); }
        }
        $today = Carbon::today(\App\Support\SalesStats::TZ);
        [$from, $to] = match ($preset) {
            '7d' => [$today->copy()->subDays(6), $today->copy()], '90d' => [$today->copy()->subDays(89), $today->copy()],
            'mtd' => [$today->copy()->startOfMonth(), $today->copy()], 'lastm' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'ytd' => [$today->copy()->startOfYear(), $today->copy()], '12m' => [$today->copy()->subMonths(12)->startOfMonth(), $today->copy()],
            'custom' => [Carbon::parse($r->query('from', $today->copy()->subDays(29))), Carbon::parse($r->query('to', $today->copy()))],
            default => [$today->copy()->subDays(29), $today->copy()],
        };
        // the range is in Eastern days (the store's clock), like WooCommerce's reports
        $days = (int) round(abs($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()))) + 1;
        [$fromUtc, $toEnd] = \App\Support\SalesStats::bounds($from, $to);
        $base = fn () => self::sold(DB::table('orders')->whereBetween('created_at', [$fromUtc, $toEnd]));
        $prevFrom = (clone $from)->subDays($days); $prevTo = (clone $from)->subDay();
        $net = self::NET;
        $daily = \App\Support\SalesStats::daily($from, $to);
        $t = \App\Support\SalesStats::totals($from, $to);
        $pt = \App\Support\SalesStats::totals($prevFrom, $prevTo);
        $sum = (object) ['n' => $t['orders'], 't' => $t['net'], 'sh' => $t['shipping'], 'd' => $t['discounts'], 'tx' => $t['tax'], 'rf' => $t['refunds']];
        $psum = (object) ['n' => $pt['orders'], 't' => $pt['net']];
        $items = fn () => self::sold(DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereBetween('o.created_at', [$fromUtc, $toEnd]), 'o.');
        $units = (int) $items()->sum('i.qty');
        $customers = $base()->distinct()->count('email');
        $newCust = $base()->whereNotExists(fn ($e) => $e->selectRaw('1')->from('orders as p')->whereColumn('p.email', 'orders.email')->whereColumn('p.id', '<', 'orders.id'))->distinct()->count('email');
        $k = ['sales' => (float) $sum->t, 'sales_prev' => (float) $psum->t, 'orders' => (int) $sum->n, 'orders_prev' => (int) $psum->n, 'aov' => $sum->n ? $sum->t / $sum->n : 0, 'aov_prev' => $psum->n ? $psum->t / $psum->n : 0,
            'units' => $units, 'shipping' => (float) $sum->sh, 'discounts' => (float) $sum->d, 'tax' => (float) $sum->tx, 'refunds' => (float) $sum->rf, 'customers' => $customers, 'new_customers' => $newCust, 'paid' => (float) $base()->where('payment_status', 'paid')->sum('total'),
            'unpaid_open' => (float) DB::table('orders')->whereBetween('created_at', [$fromUtc, $toEnd])->whereNotIn('status', ['cancelled', 'failed'])->where('payment_status', 'unpaid')->where('payment_method', '!=', 'net_terms')->sum('total')];

        // series (Eastern days; a refund on the day it was made)
        $labels = []; $sales = []; $orders = [];
        if ($days <= 92) { for ($d = clone $from; $d <= $to; $d->addDay()) { $k2 = $d->toDateString(); $labels[] = $d->format($days > 31 ? 'M j' : 'D j'); $sales[] = round((float) ($daily[$k2]['net'] ?? 0), 2); $orders[] = (int) ($daily[$k2]['orders'] ?? 0); } }
        else { $m = (clone $from)->startOfMonth(); while ($m <= $to) { $s = 0; $c = 0; foreach ($daily as $d => $v) if (str_starts_with($d, $m->format('Y-m'))) { $s += $v['net']; $c += $v['orders']; } $labels[] = $m->format('M Y'); $sales[] = round($s, 2); $orders[] = $c; $m->addMonth(); } }

        $topProducts = $items()
            ->selectRaw('i.sku, i.name, i.wp_product_id, SUM(i.qty) q, SUM(i.line_total) t, COUNT(DISTINCT o.id) n')->groupBy('i.sku', 'i.name', 'i.wp_product_id')->orderByDesc('t')->limit(15)->get();
        $byCat = $items()->leftJoin('inventory as inv', 'inv.wp_product_id', '=', 'i.wp_product_id')
            ->selectRaw("COALESCE(inv.category, CASE WHEN i.type IN ('driveshaft','custom_build','premade_build') THEN 'complete-driveshafts' WHEN i.sku LIKE 'U%' THEN 'complete-driveshafts' ELSE 'other' END) cat, SUM(i.qty) q, SUM(i.line_total) t")->groupBy('cat')->orderByDesc('t')->get();
        $topCustomers = $base()->selectRaw('email, MAX(CONCAT(billing_first_name," ",billing_last_name)) name, MAX(billing_company) company, COUNT(*) n, SUM(' . $net . ') t')->groupBy('email')->orderByDesc('t')->limit(10)->get();
        $byState = $base()->selectRaw('COALESCE(NULLIF(shipping_state,""), billing_state) st, COALESCE(NULLIF(shipping_country,""), billing_country) co, COUNT(*) n, SUM(' . $net . ') t')->groupBy('st', 'co')->orderByDesc('t')->limit(12)->get();
        $byPay = $base()->selectRaw("payment_method m, COUNT(*) n, SUM($net) t")->groupBy('m')->orderByDesc('t')->get();
        $byShip = $base()->selectRaw('shipping_label l, COUNT(*) n, SUM(shipping_total) t')->groupBy('l')->orderByDesc('n')->limit(8)->get();
        $bySource = $base()->selectRaw("source s, COUNT(*) n, SUM($net) t")->groupBy('s')->orderByDesc('t')->get();
        $coupons = $base()->whereNotNull('coupon_code')->selectRaw('coupon_code c, COUNT(*) n, SUM(discount_total) d, SUM(' . $net . ') t')->groupBy('c')->orderByDesc('n')->limit(8)->get();
        $carts = ['abandoned' => DB::table('abandoned_carts')->whereBetween('created_at', [$fromUtc, $toEnd])->count(), 'recovered' => DB::table('abandoned_carts')->whereBetween('recovered_at', [$fromUtc, $toEnd])->count(), 'recovered_value' => (float) DB::table('abandoned_carts')->whereBetween('recovered_at', [$fromUtc, $toEnd])->sum('subtotal')];
        $leads = DB::table('leads')->whereBetween('created_at', [$fromUtc, $toEnd])->selectRaw('source, COUNT(*) n')->groupBy('source')->orderByDesc('n')->get();
        $thumbs = app(\App\Services\CatalogService::class)->thumbsFor($topProducts->pluck('wp_product_id')->filter()->all());
        foreach ($topProducts as $p) $p->image = $thumbs[(int) $p->wp_product_id] ?? '';

        if ($r->query('export') === 'csv') {
            abort_unless(adm_can('exports'), 403, 'Your admin role cannot export reports.');
            $out = fopen('php://temp', 'w+'); \App\Support\Csv::put($out, ['Period', 'Sales', 'Orders']);
            foreach ($labels as $i => $l) \App\Support\Csv::put($out, [$l, $sales[$i], $orders[$i]]);
            \App\Support\Csv::put($out, []); \App\Support\Csv::put($out, ['SKU', 'Product', 'Units', 'Revenue', 'Orders']);
            foreach ($topProducts as $p) \App\Support\Csv::put($out, [$p->sku, $p->name, $p->q, $p->t, $p->n]);
            rewind($out); return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="report-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv"']);
        }
        return view('admin.reports.index', ['kp' => $k] + compact('preset', 'from', 'to', 'days', 'labels', 'sales', 'orders', 'topProducts', 'byCat', 'topCustomers', 'byState', 'byPay', 'byShip', 'bySource', 'coupons', 'carts', 'leads'));
    }
}
