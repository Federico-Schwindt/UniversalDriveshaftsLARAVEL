<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * What counts as a sale: not cancelled/failed, and either money came in (paid, incl. later refunded —
     * refunds are netted out of the value) or it is a Net-terms invoice, which the customer is committed to.
     * Unpaid card / pay-at-pickup / payment-link orders are not sales until they are paid.
     */
    public static function sold($q, string $p = '')
    {
        return $q->whereNotIn($p . 'status', ['cancelled', 'failed'])
            ->where(fn ($w) => $w->whereIn($p . 'payment_status', ['paid', 'partially_refunded', 'refunded'])->orWhere($p . 'payment_method', 'net_terms'));
    }

    /** Net revenue of an order row: total less what was refunded. */
    public const NET = '(total - COALESCE(refund_total, 0))';

    public function index(Request $r)
    {
        $preset = $r->query('range', '30d');
        // a hand-typed custom range must be two real dates; anything else falls back to the last 30 days
        if ($preset === 'custom') {
            $v = \Illuminate\Support\Facades\Validator::make($r->query(), ['from' => 'required|date|after:2000-01-01', 'to' => 'required|date|after_or_equal:from|before:2100-01-01']);
            if ($v->fails()) { $preset = '30d'; session()->now('admin_err', 'That custom date range is not valid — showing the last 30 days.'); }
        }
        [$from, $to] = match ($preset) {
            '7d' => [Carbon::today()->subDays(6), Carbon::today()], '90d' => [Carbon::today()->subDays(89), Carbon::today()],
            'mtd' => [Carbon::today()->startOfMonth(), Carbon::today()], 'lastm' => [Carbon::today()->subMonthNoOverflow()->startOfMonth(), Carbon::today()->subMonthNoOverflow()->endOfMonth()],
            'ytd' => [Carbon::today()->startOfYear(), Carbon::today()], '12m' => [Carbon::today()->subMonths(12)->startOfMonth(), Carbon::today()],
            'custom' => [Carbon::parse($r->query('from', Carbon::today()->subDays(29))), Carbon::parse($r->query('to', Carbon::today()))],
            default => [Carbon::today()->subDays(29), Carbon::today()],
        };
        $toEnd = (clone $to)->endOfDay();
        $days = $from->diffInDays($to) + 1;
        $base = fn () => self::sold(DB::table('orders')->whereBetween('created_at', [$from, $toEnd]));
        $prevFrom = (clone $from)->subDays($days); $prevTo = (clone $from)->subSecond();
        $prev = fn () => self::sold(DB::table('orders')->whereBetween('created_at', [$prevFrom, $prevTo]));
        $net = self::NET;

        $sum = $base()->selectRaw("COUNT(*) n, COALESCE(SUM($net),0) t, COALESCE(SUM(subtotal),0) s, COALESCE(SUM(shipping_total),0) sh, COALESCE(SUM(discount_total),0) d, COALESCE(SUM(tax_total),0) tx, COALESCE(SUM(refund_total),0) rf")->first();
        $psum = $prev()->selectRaw("COUNT(*) n, COALESCE(SUM($net),0) t")->first();
        $items = fn () => self::sold(DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereBetween('o.created_at', [$from, $toEnd]), 'o.');
        $units = (int) $items()->sum('i.qty');
        $customers = $base()->distinct()->count('email');
        $newCust = $base()->whereNotExists(fn ($e) => $e->selectRaw('1')->from('orders as p')->whereColumn('p.email', 'orders.email')->whereColumn('p.id', '<', 'orders.id'))->distinct()->count('email');
        $k = ['sales' => (float) $sum->t, 'sales_prev' => (float) $psum->t, 'orders' => (int) $sum->n, 'orders_prev' => (int) $psum->n, 'aov' => $sum->n ? $sum->t / $sum->n : 0, 'aov_prev' => $psum->n ? $psum->t / $psum->n : 0,
            'units' => $units, 'shipping' => (float) $sum->sh, 'discounts' => (float) $sum->d, 'tax' => (float) $sum->tx, 'refunds' => (float) $sum->rf, 'customers' => $customers, 'new_customers' => $newCust, 'paid' => (float) $base()->where('payment_status', 'paid')->sum('total'),
            'unpaid_open' => (float) DB::table('orders')->whereBetween('created_at', [$from, $toEnd])->whereNotIn('status', ['cancelled', 'failed'])->where('payment_status', 'unpaid')->where('payment_method', '!=', 'net_terms')->sum('total')];

        // series
        $byDay = $base()->selectRaw("DATE(created_at) d, SUM($net) t, COUNT(*) c")->groupBy('d')->get()->keyBy('d');
        $labels = []; $sales = []; $orders = [];
        if ($days <= 92) { for ($d = clone $from; $d <= $to; $d->addDay()) { $k2 = $d->toDateString(); $labels[] = $d->format($days > 31 ? 'M j' : 'D j'); $sales[] = round((float) ($byDay[$k2]->t ?? 0), 2); $orders[] = (int) ($byDay[$k2]->c ?? 0); } }
        else { $m = (clone $from)->startOfMonth(); while ($m <= $to) { $s = 0; $c = 0; foreach ($byDay as $d => $v) if (str_starts_with($d, $m->format('Y-m'))) { $s += $v->t; $c += $v->c; } $labels[] = $m->format('M Y'); $sales[] = round($s, 2); $orders[] = $c; $m->addMonth(); } }

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
        $carts = ['abandoned' => DB::table('abandoned_carts')->whereBetween('created_at', [$from, $toEnd])->count(), 'recovered' => DB::table('abandoned_carts')->whereBetween('recovered_at', [$from, $toEnd])->count(), 'recovered_value' => (float) DB::table('abandoned_carts')->whereBetween('recovered_at', [$from, $toEnd])->sum('subtotal')];
        $leads = DB::table('leads')->whereBetween('created_at', [$from, $toEnd])->selectRaw('source, COUNT(*) n')->groupBy('source')->orderByDesc('n')->get();
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
