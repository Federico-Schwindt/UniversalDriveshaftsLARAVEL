<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponsController extends Controller
{
    public const TYPES = ['percent' => 'Percentage off', 'fixed_cart' => 'Fixed amount off', 'free_shipping' => 'Free shipping only'];

    public function index(Request $r)
    {
        $q = DB::table('coupons');
        $tab = $r->query('tab', 'active');
        match ($tab) { 'active' => $q->where('active', 1)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', now())), 'expired' => $q->where('expires_at', '<', now()), 'inactive' => $q->where('active', 0), default => null };
        if ($s = trim((string) $r->query('q', ''))) $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
        $rows = $q->orderByDesc('id')->paginate(30)->withQueryString();
        $usage = DB::table('orders')->whereIn('coupon_code', collect($rows->items())->pluck('code'))->where('status', '!=', 'cancelled')->selectRaw('coupon_code, COUNT(*) n, SUM(discount_total) d, SUM(total) t')->groupBy('coupon_code')->get()->keyBy(fn ($x) => strtoupper($x->coupon_code));
        $counts = ['active' => DB::table('coupons')->where('active', 1)->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->count(), 'expired' => DB::table('coupons')->where('expires_at', '<', now())->count(), 'inactive' => DB::table('coupons')->where('active', 0)->count(), 'all' => DB::table('coupons')->count()];
        return view('admin.coupons.index', compact('rows', 'usage', 'tab', 'counts') + ['f' => $r->query()]);
    }

    public function create()
    {
        return view('admin.coupons.form', ['c' => null, 'categories' => collect(app(\App\Services\Admin\CatalogWriter::class)->categories())->sortBy('name')->values()]);
    }

    public function edit(int $id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        $c->r = json_decode($c->restrictions ?: '{}', true) ?: [];
        $orders = DB::table('orders')->whereRaw('LOWER(coupon_code) = ?', [strtolower($c->code)])->orderByDesc('id')->limit(15)->get();
        return view('admin.coupons.form', ['c' => $c, 'orders' => $orders, 'categories' => collect(app(\App\Services\Admin\CatalogWriter::class)->categories())->sortBy('name')->values()]);
    }

    private function validated(Request $r, ?int $id): array
    {
        $d = $r->validate(['code' => 'required|string|max:60|unique:coupons,code' . ($id ? ',' . $id : ''), 'type' => 'required|in:percent,fixed_cart,free_shipping', 'amount' => 'nullable|numeric|min:0' . ($r->input('type') === 'percent' ? '|max:100' : '|max:100000'), 'description' => 'nullable|string|max:255',
            'minimum_amount' => 'nullable|numeric|min:0', 'maximum_amount' => 'nullable|numeric|min:0', 'usage_limit' => 'nullable|integer|min:1', 'per_customer_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date', 'expires_at' => 'nullable|date', 'free_shipping' => 'nullable', 'active' => 'nullable', 'categories' => 'nullable|array', 'skus' => 'nullable|string|max:2000', 'exclude_sale' => 'nullable', 'wholesale_only' => 'nullable']);
        return ['code' => strtoupper(trim($d['code'])), 'type' => $d['type'], 'amount' => $d['type'] === 'free_shipping' ? 0 : (float) ($d['amount'] ?? 0), 'description' => $d['description'] ?? null,
            'minimum_amount' => ($d['minimum_amount'] ?? '') !== '' ? $d['minimum_amount'] : null, 'maximum_amount' => ($d['maximum_amount'] ?? '') !== '' ? $d['maximum_amount'] : null,
            'usage_limit' => $d['usage_limit'] ?? null, 'per_customer_limit' => $d['per_customer_limit'] ?? null,
            'starts_at' => !empty($d['starts_at']) ? Carbon::parse($d['starts_at'])->startOfDay() : null, 'expires_at' => !empty($d['expires_at']) ? Carbon::parse($d['expires_at'])->endOfDay() : null,
            'free_shipping' => $d['type'] === 'free_shipping' || $r->boolean('free_shipping'), 'active' => $r->boolean('active'),
            'restrictions' => json_encode(['categories' => array_values(array_filter((array) ($d['categories'] ?? []))), 'skus' => array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($d['skus'] ?? ''))))), 'exclude_sale' => $r->boolean('exclude_sale'), 'wholesale_only' => $r->boolean('wholesale_only')]),
            'updated_at' => now()];
    }

    public function store(Request $r)
    {
        $row = $this->validated($r, null);
        $id = DB::table('coupons')->insertGetId($row + ['used_count' => 0, 'created_at' => now()]);
        AdminLog::record('coupon.create', 'Created coupon ' . $row['code'], 'coupon', $id);
        return redirect('/admin/coupons')->with('admin_ok', 'Coupon ' . $row['code'] . ' created.');
    }

    public function update(Request $r, int $id)
    {
        abort_unless(DB::table('coupons')->find($id), 404);
        $row = $this->validated($r, $id);
        DB::table('coupons')->where('id', $id)->update($row);
        AdminLog::record('coupon.update', 'Updated coupon ' . $row['code'], 'coupon', $id);
        return back()->with('admin_ok', 'Coupon saved.');
    }

    public function toggle(int $id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        DB::table('coupons')->where('id', $id)->update(['active' => !$c->active, 'updated_at' => now()]);
        AdminLog::record('coupon.toggle', 'Coupon ' . $c->code . ($c->active ? ' deactivated' : ' activated'), 'coupon', $id);
        return back()->with('admin_ok', 'Coupon ' . $c->code . ($c->active ? ' deactivated.' : ' activated.'));
    }

    public function destroy(int $id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        DB::table('coupons')->where('id', $id)->delete();
        AdminLog::record('coupon.delete', 'Deleted coupon ' . $c->code, 'coupon', $id);
        return redirect('/admin/coupons')->with('admin_ok', 'Coupon deleted.');
    }
}
