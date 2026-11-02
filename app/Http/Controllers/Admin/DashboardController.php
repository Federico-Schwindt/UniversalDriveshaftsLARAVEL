<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Badge counts for the sidebar (60 s cache, cleared by writes that matter). */
    public static function navCounts(): array
    {
        return Cache::remember('adm_nav_counts', 60, function () {
            $c = [];
            try {
                $c['orders_open'] = DB::table('orders')->whereIn('status', ['pending', 'processing'])->count();
                $c['wholesale_pending'] = DB::table('users')->where('wholesale_status', 'pending')->count();
                $c['leads_new'] = DB::table('leads')->where('status', 'new')->count();
                $c['rma_new'] = DB::table('rma_requests')->where('status', 'new')->count();
                // assistant chats waiting for a person: asked for one, or taken over and the customer wrote last
                $c['chats_waiting'] = DB::table('chat_conversations as c')->where('c.status', 'open')->where(fn ($w) => $w->whereNotNull('c.needs_human_at')
                    ->orWhere(fn ($h) => $h->where('c.mode', 'human')->whereRaw("(SELECT m.role FROM chat_messages m WHERE m.conversation_id = c.id AND m.display IS NOT NULL AND m.role <> 'system' ORDER BY m.id DESC LIMIT 1) = 'user'")))->count();
                $c['carts_open'] = DB::table('abandoned_carts')->whereNull('recovered_at')->whereNull('unsubscribed_at')->where('last_seen_at', '>=', now()->subDays(7))->count();
                $c['low_stock'] = DB::table('inventory')->where('tracked', 1)->whereNotNull('reorder_at')->whereColumn('stock', '<=', 'reorder_at')->count();
            } catch (\Throwable $e) {}
            return $c;
        });
    }

    public function index(Request $r)
    {
        $range = $r->query('range', '30');
        $days = in_array($range, ['7', '30', '90', '365']) ? (int) $range : 30;
        $from = Carbon::today()->subDays($days - 1);
        $prevFrom = (clone $from)->subDays($days);
        $paid = fn ($q) => $q->where('payment_status', 'paid');

        // same definition of a sale as Reports: paid (net of refunds) or a Net-terms invoice; never cancelled/failed
        $net = ReportsController::NET;
        $cur = ReportsController::sold(DB::table('orders')->where('created_at', '>=', $from));
        $prev = ReportsController::sold(DB::table('orders')->whereBetween('created_at', [$prevFrom, $from]));
        $k = [
            'sales' => (float) (clone $cur)->sum(DB::raw($net)), 'sales_prev' => (float) (clone $prev)->sum(DB::raw($net)),
            'orders' => (clone $cur)->count(), 'orders_prev' => (clone $prev)->count(),
            'paid' => (float) $paid(clone $cur)->sum('total'),
            'unpaid' => (float) DB::table('orders')->where('payment_status', 'unpaid')->whereIn('status', ['pending', 'processing'])->sum('total'),
            'unpaid_n' => DB::table('orders')->where('payment_status', 'unpaid')->whereIn('status', ['pending', 'processing'])->count(),
            'to_ship' => DB::table('orders')->whereIn('status', ['pending', 'processing'])->whereNull('shipped_at')->where('shipping_method', '!=', \App\Services\CartService::PICKUP_ID)->count(),
            'pickup' => DB::table('orders')->whereIn('status', ['pending', 'processing'])->where('shipping_method', \App\Services\CartService::PICKUP_ID)->count(),
            'customers_new' => DB::table('users')->where('is_admin', 0)->where('created_at', '>=', $from)->count(),
            'leads_new' => DB::table('leads')->where('status', 'new')->count(),
            'rma_new' => DB::table('rma_requests')->where('status', 'new')->count(),
            'wholesale_pending' => DB::table('users')->where('wholesale_status', 'pending')->count(),
            'carts' => DB::table('abandoned_carts')->whereNull('recovered_at')->where('last_seen_at', '>=', now()->subDays(7))->count(),
            'carts_value' => (float) DB::table('abandoned_carts')->whereNull('recovered_at')->where('last_seen_at', '>=', now()->subDays(7))->sum('subtotal'),
            'low_stock' => DB::table('inventory')->where('tracked', 1)->whereNotNull('reorder_at')->whereColumn('stock', '<=', 'reorder_at')->count(),
            'out_stock' => DB::table('inventory')->where('tracked', 1)->where('stock', '<=', 0)->count(),
        ];
        $k['aov'] = $k['orders'] ? $k['sales'] / $k['orders'] : 0;
        $k['aov_prev'] = $k['orders_prev'] ? $k['sales_prev'] / $k['orders_prev'] : 0;

        // sales series (daily for ≤90 days, weekly beyond)
        $labels = []; $sales = []; $orders = [];
        $rows = ReportsController::sold(DB::table('orders')->where('created_at', '>=', $from))
            ->selectRaw("DATE(created_at) d, SUM($net) t, COUNT(*) c")->groupBy('d')->pluck('t', 'd');
        $cnts = ReportsController::sold(DB::table('orders')->where('created_at', '>=', $from))
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        if ($days <= 90) {
            for ($i = 0; $i < $days; $i++) { $d = (clone $from)->addDays($i); $key = $d->toDateString(); $labels[] = $d->format($days > 31 ? 'M j' : 'D j'); $sales[] = round((float) ($rows[$key] ?? 0), 2); $orders[] = (int) ($cnts[$key] ?? 0); }
        } else {
            $wk = (clone $from)->startOfWeek();
            while ($wk < Carbon::today()) {
                $end = (clone $wk)->addDays(6); $s = 0; $c = 0;
                for ($d = clone $wk; $d <= $end; $d->addDay()) { $s += (float) ($rows[$d->toDateString()] ?? 0); $c += (int) ($cnts[$d->toDateString()] ?? 0); }
                $labels[] = $wk->format('M j'); $sales[] = round($s, 2); $orders[] = $c; $wk->addDays(7);
            }
        }

        $recentOrders = DB::table('orders')->orderByDesc('id')->limit(8)->get();
        $topProducts = ReportsController::sold(DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.created_at', '>=', $from), 'o.')
            ->selectRaw('i.sku, i.name, i.wp_product_id, SUM(i.qty) q, SUM(i.line_total) t')->groupBy('i.sku', 'i.name', 'i.wp_product_id')
            ->orderByDesc('t')->limit(6)->get();
        $thumbs = app(CatalogService::class)->thumbsFor($topProducts->pluck('wp_product_id')->filter()->all());
        foreach ($topProducts as $p) $p->image = $thumbs[(int) $p->wp_product_id] ?? '';
        $recentLeads = DB::table('leads')->orderByDesc('id')->limit(6)->get();
        $lowStock = DB::table('inventory')->where('tracked', 1)->whereNotNull('reorder_at')->whereColumn('stock', '<=', 'reorder_at')->orderBy('stock')->limit(6)->get();
        $activity = DB::table('admin_activity as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->orderByDesc('a.id')->limit(8)->get(['a.*', 'u.name as user_name']);
        $pendingSpecs = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereIn('o.status', ['pending', 'processing'])->where('i.sku', 'like', 'U%')
            ->leftJoin('ds_specs as s', 's.part_number', '=', DB::raw("SUBSTRING(i.sku, 2)"))
            ->whereNull('s.part_number')->selectRaw('i.sku, i.name, o.order_number, o.id as order_id')->limit(6)->get();

        return view('admin.dashboard', compact('k', 'days', 'labels', 'sales', 'orders', 'recentOrders', 'topProducts', 'recentLeads', 'lowStock', 'activity', 'pendingSpecs'));
    }

    /* ---------- global search ---------- */

    public function apiSearch(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['groups' => []]);
        return response()->json(['groups' => $this->searchGroups($q, 5)]);
    }

    public function search(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        $groups = $q !== '' ? $this->searchGroups($q, 25) : [];
        return view('admin.search', compact('q', 'groups'));
    }

    private function searchGroups(string $q, int $per): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $g = [];
        $orders = DB::table('orders')->where(function ($w) use ($like, $q) {
            $w->where('order_number', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
              ->orWhereRaw("CONCAT(billing_first_name,' ',billing_last_name) LIKE ?", [$like])->orWhere('tracking_number', 'like', $like)->orWhere('po_number', 'like', $like);
            if (ctype_digit($q)) $w->orWhere('id', (int) $q);
        })->orderByDesc('id')->limit($per)->get();
        $g[] = ['label' => 'Orders', 'icon' => 'orders', 'items' => $orders->map(fn ($o) => [
            'title' => $o->order_number, 'sub' => trim($o->billing_first_name . ' ' . $o->billing_last_name) . ' · ' . $o->email,
            'meta' => money($o->total) . ' · ' . ucfirst($o->status), 'url' => '/admin/orders/' . $o->id,
        ])->all()];

        $cat = app(CatalogService::class);
        $prods = DB::connection('wp')->table('wp_posts as p')
            ->leftJoin('wp_postmeta as sk', fn ($j) => $j->on('sk.post_id', '=', 'p.ID')->where('sk.meta_key', '_sku'))
            ->where('p.post_type', 'product')->whereIn('p.post_status', ['publish', 'draft', 'private', 'pending'])
            ->where(fn ($w) => $w->where('p.post_title', 'like', $like)->orWhere('sk.meta_value', 'like', $like))
            ->orderByRaw('CASE WHEN sk.meta_value = ? THEN 0 WHEN sk.meta_value LIKE ? THEN 1 ELSE 2 END', [$q, $q . '%'])
            ->limit($per)->get(['p.ID', 'p.post_title', 'p.post_status', 'sk.meta_value as sku'])->unique('ID');
        $thumbs = $cat->thumbsFor($prods->pluck('ID')->all());
        $g[] = ['label' => 'Products', 'icon' => 'box', 'items' => $prods->map(fn ($p) => [
            'title' => $p->sku ?: '—', 'sub' => html_entity_decode($p->post_title), 'meta' => $p->post_status === 'publish' ? '' : ucfirst($p->post_status),
            'img' => $thumbs[(int) $p->ID] ?? '', 'url' => '/admin/products/' . $p->ID,
        ])->values()->all()];

        $pn = DriveshaftFinder::pn($q);
        $parts = DB::table('ds_parts')->where(fn ($w) => $w->where('part_number', 'like', '%' . $pn . '%')->orWhere('description', 'like', $like))
            ->orderByRaw('CASE WHEN part_number = ? THEN 0 WHEN part_number LIKE ? THEN 1 ELSE 2 END', [$pn, $pn . '%'])->limit($per)->get();
        $g[] = ['label' => 'Complete driveshafts', 'icon' => 'shaft', 'items' => $parts->map(fn ($p) => [
            'title' => DriveshaftFinder::sku($p->part_number), 'sub' => $p->description, 'meta' => $p->price_auto ? money($p->price_auto) : 'Call',
            'img' => $p->main_image ? media_url($p->main_image, 160) : '', 'url' => '/admin/driveshafts/' . rawurlencode($p->part_number),
        ])->all()];

        $custs = DB::table('users')->where('is_admin', 0)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('company', 'like', $like))->limit($per)->get();
        $g[] = ['label' => 'Customers', 'icon' => 'users', 'items' => $custs->map(fn ($u) => [
            'title' => $u->name, 'sub' => $u->email, 'meta' => $u->wholesale_status === 'approved' ? 'Wholesale' : '', 'url' => '/admin/customers/' . $u->id,
        ])->all()];

        // vehicle lookup: "2015 ford f-150" → driveshaft finder
        if (preg_match('/^(\d{4})?\s*([a-z][a-z\-]+)\s*(.*)$/i', $q, $m) && mb_strlen($q) >= 4) {
            $mk = DB::table('ds_fitments')->where('make', 'like', $m[2] . '%')->distinct()->limit(1)->value('make');
            if ($mk) $g[] = ['label' => 'Vehicles', 'icon' => 'car', 'items' => [[
                'title' => 'Find driveshafts for ' . trim(($m[1] ?? '') . ' ' . $mk . ' ' . ($m[3] ?? '')), 'sub' => '', 'meta' => '', 'url' => '/admin/driveshafts?make=' . urlencode($mk) . ($m[1] ? '&year=' . $m[1] : '') . ($m[3] ? '&model=' . urlencode(trim($m[3])) : ''),
            ]]];
        }
        return $g;
    }
}
