<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\SalesStats;

/**
 * Month-by-month sales (owner, 2026-11-04: "controlar cómo va mes a mes el sitio web en ventas").
 * Net sales the WooCommerce Analytics way (App\Support\SalesStats): products after coupons, without shipping or tax,
 * refunds taken off in the month they were made, Eastern months. The old store's history (woo:import) is included, so
 * every month has last year to beat; Nov 2025 – Oct 2026 match WooCommerce's own report to the cent.
 */
class AnalyticsController extends Controller
{
    public const TZ = 'America/New_York';
    public const CHANNELS = ['woocommerce' => 'Old website', 'web' => 'Website', 'manual' => 'Phone & admin', 'assistant' => 'Assistant'];

    public function index(Request $r)
    {
        $now = now(self::TZ);
        $byMonth = $this->months();
        $years = collect(array_keys($byMonth))->map(fn ($m) => (int) substr($m, 0, 4))->push($now->year)->unique()->sortDesc()->values();
        $year = (int) $r->query('year', 0);
        if (!$years->contains($year)) $year = 0;

        // the period shown: one calendar year, or the 12 months ending with this one
        $start = $year ? Carbon::create($year, 1, 1, 0, 0, 0, self::TZ) : $now->copy()->startOfMonth()->subMonths(11);
        $rows = [];
        for ($m = $start->copy(), $i = 0; $i < 12; $i++, $m->addMonth()) {
            $key = $m->format('Y-m');
            $cur = $byMonth[$key] ?? null;
            $rows[] = [
                'key' => $key, 'label' => $m->format('M Y'), 'short' => $m->format($year ? 'M' : 'M y'), 'future' => $key > $now->format('Y-m'), 'current' => $key === $now->format('Y-m'),
                'cur' => $cur ?? self::blank(), 'prev' => $byMonth[$m->copy()->subMonth()->format('Y-m')] ?? null, 'ly' => $byMonth[$m->copy()->subYear()->format('Y-m')] ?? null,
            ];
        }

        // this month so far against the same days of last month, and the month-end it is heading for
        $day = $now->day;
        $mtd = $byMonth[$now->format('Y-m')] ?? self::blank();
        $lastKey = $now->copy()->subMonthNoOverflow()->format('Y-m');
        $lastSameDays = array_sum(array_slice($byMonth[$lastKey]['days'] ?? [], 0, $day));
        $elapsed = ($day - 1 + ($now->secondsSinceMidnight() / 86400)) / $now->daysInMonth;
        $ytd = $lyYtd = 0.0;
        foreach ($byMonth as $key => $v) {
            if (str_starts_with($key, $now->year . '-')) $ytd += $v['net'];
            if (str_starts_with($key, ($now->year - 1) . '-')) {
                $mm = (int) substr($key, 5, 2);
                if ($mm < $now->month) $lyYtd += $v['net'];
                elseif ($mm === $now->month) $lyYtd += array_sum(array_slice($v['days'], 0, $day));
            }
        }
        $best = collect($byMonth)->sortByDesc('net')->keys()->first();
        $kp = [
            'mtd' => $mtd['net'], 'mtd_prev' => $lastSameDays, 'day' => $day,
            'projected' => $elapsed > 0.05 ? $mtd['net'] / $elapsed : null, 'last' => $byMonth[$lastKey]['net'] ?? 0,
            'last_prev' => $byMonth[$now->copy()->subMonthsNoOverflow(2)->format('Y-m')]['net'] ?? 0,
            'last_ly' => $byMonth[$now->copy()->subMonthNoOverflow()->subYear()->format('Y-m')]['net'] ?? 0,
            'ytd' => $ytd, 'ly_ytd' => $lyYtd, 'aov' => $mtd['orders'] ? $mtd['net'] / $mtd['orders'] : 0,
            'aov_last' => ($byMonth[$lastKey]['orders'] ?? 0) ? $byMonth[$lastKey]['net'] / $byMonth[$lastKey]['orders'] : 0,
            'customers' => $mtd['customers'], 'new' => $mtd['new'],
            'best' => $best ? ['label' => Carbon::createFromFormat('Y-m-d', $best . '-01', self::TZ)->format('F Y'), 'net' => $byMonth[$best]['net']] : null,
        ];

        $chart = ['type' => 'bar', 'money' => true, 'height' => 260, 'labels' => array_column($rows, 'short'), 'maxLabels' => 12, 'series' => [
            ['name' => $year ?: 'Last 12 months', 'data' => array_map(fn ($x) => round($x['cur']['net'], 2), $rows)],
            ['name' => 'A year earlier', 'data' => array_map(fn ($x) => round($x['ly']['net'] ?? 0, 2), $rows)],
        ]];
        $channels = collect(self::CHANNELS)->keys()->merge(collect($rows)->flatMap(fn ($x) => array_keys($x['cur']['channels'])))->unique()
            ->filter(fn ($c) => collect($rows)->sum(fn ($x) => $x['cur']['channels'][$c] ?? 0) > 0)->values();
        $totals = self::blank();
        foreach ($rows as $x) foreach (['net', 'orders', 'units', 'refunds', 'shipping', 'tax', 'b2b'] as $f) $totals[$f] += $x['cur'][$f];

        return view('admin.analytics.index', compact('rows', 'chart', 'kp', 'years', 'year', 'channels', 'totals', 'now'));
    }

    /** % change, null without a base to compare with. */
    public static function pct(float $cur, ?float $prev): ?float
    {
        return $prev !== null && $prev > 0 ? ($cur - $prev) / $prev * 100 : null;
    }

    private static function blank(): array
    {
        return ['net' => 0.0, 'orders' => 0, 'units' => 0, 'refunds' => 0.0, 'shipping' => 0.0, 'tax' => 0.0, 'b2b' => 0.0, 'customers' => 0, 'new' => 0, 'channels' => [], 'days' => array_fill(0, 31, 0.0)];
    }

    /** Every sale grouped by Eastern month: net, orders, units, refunds, shipping, B2B, customers (new = first purchase ever), channels, net per day. */
    private function months(): array
    {
        return Cache::remember('adm_analytics_months', 300, function () {
            $units = DB::table('order_items')->where('type', 'product')->selectRaw('order_id, SUM(qty) u')->groupBy('order_id');
            $orders = SalesStats::counted(DB::table('orders as o'), 'o.')
                ->leftJoinSub($units, 'iu', 'iu.order_id', '=', 'o.id')
                ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
                ->orderBy('o.created_at')->orderBy('o.id')
                ->get(['o.id', 'o.created_at', 'o.email', 'o.source', 'o.shipping_total', 'o.tax_total', DB::raw('(o.total - o.shipping_total - o.tax_total) net'), DB::raw('COALESCE(iu.u, 0) units'),
                    DB::raw("(o.b2b_tier IS NOT NULL OR u.wholesale_status = 'approved') b2b")]);
            $out = []; $seen = []; $who = []; $meta = [];
            $month = function (string $at) use (&$out) {
                $t = Carbon::parse($at, 'UTC')->timezone(self::TZ);
                $out[$t->format('Y-m')] ??= self::blank();
                return [$t->format('Y-m'), $t->day];
            };
            foreach ($orders as $o) {
                [$k, $day] = $month($o->created_at);
                $m = &$out[$k];
                $net = (float) $o->net;
                $m['net'] += $net; $m['orders']++; $m['units'] += (int) $o->units; $m['shipping'] += (float) $o->shipping_total; $m['tax'] += (float) $o->tax_total;
                if ($o->b2b) $m['b2b'] += $net;
                $src = $o->source ?: 'web';
                $m['channels'][$src] = ($m['channels'][$src] ?? 0) + $net;
                $m['days'][$day - 1] += $net;
                $meta[$o->id] = [$src, (bool) $o->b2b];
                $email = strtolower(trim((string) $o->email));
                if ($email !== '' && !isset($who[$k][$email])) {
                    $who[$k][$email] = true; $m['customers']++;
                    if (!isset($seen[$email])) { $seen[$email] = true; $m['new']++; }
                }
                unset($m);
            }
            // refunds in the month they were made, against the channel / B2B share of their order
            foreach (SalesStats::refundRows(Carbon::create(2000, 1, 1), now()->addDay()) as $r) {
                [$k, $day] = $month($r->created_at);
                $m = &$out[$k];
                $net = (float) $r->amount - (float) $r->shipping - (float) $r->tax;
                [$src, $b2b] = $meta[$r->order_id] ?? [$r->source ?: 'web', false];
                $m['net'] -= $net; $m['refunds'] += (float) $r->amount; $m['shipping'] -= (float) $r->shipping; $m['tax'] -= (float) $r->tax;
                $m['channels'][$src] = ($m['channels'][$src] ?? 0) - $net;
                if ($b2b) $m['b2b'] -= $net;
                $m['days'][$day - 1] -= $net;
                unset($m);
            }
            ksort($out);
            return $out;
        });
    }
}
