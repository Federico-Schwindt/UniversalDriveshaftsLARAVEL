<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public static function status($row): string
    {
        if (!$row->tracked) return 'off';
        $s = (int) $row->stock; $r = $row->reorder_at;
        if ($s <= 0) return 'out';
        if ($r !== null && $s <= $r) return 'critical';
        if ($r !== null && $s <= $r * 2) return 'low';
        return 'ok';
    }

    public function index(Request $r)
    {
        $filter = $r->query('status', '');
        $q = DB::table('inventory');
        if ($s = trim((string) $r->query('q', ''))) $q->where(fn ($w) => $w->where('sku', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        if ($cat = $r->query('cat')) $q->where('category', $cat);
        if ($src = $r->query('source')) $q->where('source', $src);
        match ($filter) {
            'untracked' => $q->where('tracked', 0),
            'out' => $q->where('tracked', 1)->where('stock', '<=', 0),
            'critical' => $q->where('tracked', 1)->where('stock', '>', 0)->whereNotNull('reorder_at')->whereColumn('stock', '<=', 'reorder_at'),
            'low' => $q->where('tracked', 1)->whereNotNull('reorder_at')->whereColumn('stock', '>', 'reorder_at')->whereRaw('stock <= reorder_at * 2'),
            'ok' => $q->where('tracked', 1)->where('stock', '>', 0)->where(fn ($w) => $w->whereNull('reorder_at')->orWhereRaw('stock > reorder_at * 2')),
            default => null,
        };
        $sort = $r->query('sort', 'urgent');
        match ($sort) { 'sku' => $q->orderBy('sku'), 'name' => $q->orderBy('name'), 'stock_desc' => $q->orderByDesc('stock'), 'value' => $q->orderByDesc('stock'), default => $q->orderByRaw('(tracked = 1 AND stock <= 0) DESC, (reorder_at IS NOT NULL AND stock <= reorder_at) DESC, stock ASC') };
        if ($r->query('export') === 'csv') {
            abort_unless(adm_can('exports'), 403, 'Your admin role cannot export inventory.');
            $out = fopen('php://temp', 'w+'); \App\Support\Csv::put($out, ['SKU', 'Name', 'Category', 'Stock', 'Reorder at', 'Target', 'Source', 'Tracked', 'Synced']);
            foreach ((clone $q)->limit(10000)->get() as $i) \App\Support\Csv::put($out, [$i->sku, $i->name, $i->category, $i->stock, $i->reorder_at, $i->target_qty, $i->source, $i->tracked ? 'yes' : 'no', $i->stock_synced_at]);
            rewind($out); return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="inventory-' . date('Ymd') . '.csv"']);
        }
        $rows = $q->paginate(40)->withQueryString();
        $counts = \Illuminate\Support\Facades\Cache::remember('adm_inv_counts', 60, fn () => [
            'tracked' => DB::table('inventory')->where('tracked', 1)->count(),
            'out' => DB::table('inventory')->where('tracked', 1)->where('stock', '<=', 0)->count(),
            'critical' => DB::table('inventory')->where('tracked', 1)->where('stock', '>', 0)->whereNotNull('reorder_at')->whereColumn('stock', '<=', 'reorder_at')->count(),
            'low' => DB::table('inventory')->where('tracked', 1)->whereNotNull('reorder_at')->whereColumn('stock', '>', 'reorder_at')->whereRaw('stock <= reorder_at * 2')->count(),
            'untracked' => DB::table('inventory')->where('tracked', 0)->count(),
        ]);
        $cats = DB::table('inventory')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $thumbs = app(\App\Services\CatalogService::class)->thumbsFor($rows->pluck('wp_product_id')->all());
        $prices = DB::connection('wp')->table('wp_postmeta')->whereIn('post_id', $rows->pluck('wp_product_id')->all())->where('meta_key', '_price')->selectRaw('post_id, MAX(meta_value+0) v')->groupBy('post_id')->pluck('v', 'post_id');
        foreach ($rows as $row) { $row->image = $thumbs[(int) $row->wp_product_id] ?? ''; $row->price = (float) ($prices[$row->wp_product_id] ?? 0); $row->st = self::status($row); }
        return view('admin.inventory.index', compact('rows', 'counts', 'cats', 'filter', 'sort') + ['f' => $r->query()]);
    }

    /** Inline JSON edit of reorder point / target / source / tracked / stock (manual source only). */
    public function update(Request $r, int $id)
    {
        $d = $r->validate(['stock' => 'nullable|integer|min:0', 'reorder_at' => 'nullable|integer|min:0', 'target_qty' => 'nullable|integer|min:0', 'source' => 'nullable|in:manual,auto', 'tracked' => 'nullable|boolean']);
        $row = DB::table('inventory')->find($id); if (!$row) return response()->json(['ok' => false], 404);
        $upd = ['updated_at' => now()];
        foreach (['reorder_at', 'target_qty', 'source'] as $k) if ($r->has($k)) $upd[$k] = $d[$k] ?? null;
        if ($r->has('tracked')) $upd['tracked'] = (int) $d['tracked'];
        if ($r->has('stock') && (($d['source'] ?? $row->source) === 'manual')) {
            $upd['stock'] = (int) $d['stock'];
            if ((int) $d['stock'] !== (int) $row->stock) DB::table('inventory_adjustments')->insert(['inventory_id' => $id, 'delta' => (int) $d['stock'] - (int) $row->stock, 'before' => (int) $row->stock, 'after' => (int) $d['stock'], 'reason' => 'Inline edit', 'user_id' => auth()->id(), 'created_at' => now()]);
            if ($row->wp_product_id) app(\App\Services\Admin\CatalogWriter::class)->setStock((int) $row->wp_product_id, (int) $d['stock']);   // stock only: no sync lock
        }
        DB::table('inventory')->where('id', $id)->update($upd);
        \Illuminate\Support\Facades\Cache::forget('adm_inv_counts'); \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        $fresh = DB::table('inventory')->find($id);
        return response()->json(['ok' => true, 'status' => self::status($fresh), 'stock' => $fresh->stock]);
    }

    /** Stock adjustment with a reason (receive / count / damaged …), logged. */
    public function adjust(Request $r, int $id)
    {
        $row = DB::table('inventory')->find($id); abort_unless($row, 404);
        $d = $r->validate(['delta' => 'required|integer', 'reason' => 'required|string|max:160']);
        $after = max(0, (int) $row->stock + (int) $d['delta']);
        DB::table('inventory')->where('id', $id)->update(['stock' => $after, 'updated_at' => now()]);
        DB::table('inventory_adjustments')->insert(['inventory_id' => $id, 'delta' => $after - (int) $row->stock, 'before' => (int) $row->stock, 'after' => $after, 'reason' => $d['reason'], 'user_id' => auth()->id(), 'created_at' => now()]);
        if ($row->wp_product_id) app(\App\Services\Admin\CatalogWriter::class)->setStock((int) $row->wp_product_id, $after);
        AdminLog::record('inventory.adjust', "{$row->sku}: " . ($d['delta'] > 0 ? '+' : '') . "{$d['delta']} ({$d['reason']}) → {$after}", 'product', $row->wp_product_id, $d);
        \Illuminate\Support\Facades\Cache::forget('adm_inv_counts'); \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('admin_ok', "{$row->sku} adjusted to {$after}.");
    }

    public function history(int $id)
    {
        $row = DB::table('inventory')->find($id); abort_unless($row, 404);
        $adj = DB::table('inventory_adjustments as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->where('a.inventory_id', $id)->orderByDesc('a.id')->limit(100)->get(['a.*', 'u.name as user_name']);
        $sold = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.wp_product_id', $row->wp_product_id)->where('o.status', '!=', 'cancelled')->orderByDesc('o.id')->limit(30)->get(['o.id', 'o.order_number', 'o.created_at', 'i.qty']);
        return view('admin.inventory.history', compact('row', 'adj', 'sold'));
    }

    public function bulk(Request $r)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $r->input('ids'))));
        $act = $r->input('bulk'); $n = 0;
        if ($act === 'reorder') $r->validate(['value' => 'required|integer|min:0|max:100000']);
        foreach ($ids as $id) {
            if ($act === 'reorder') { DB::table('inventory')->where('id', $id)->update(['reorder_at' => (int) $r->input('value'), 'updated_at' => now()]); $n++; }
            elseif ($act === 'track') { DB::table('inventory')->where('id', $id)->update(['tracked' => 1, 'updated_at' => now()]); $n++; }
            elseif ($act === 'untrack') { DB::table('inventory')->where('id', $id)->update(['tracked' => 0, 'updated_at' => now()]); $n++; }
        }
        if ($n) AdminLog::record('inventory.bulk', "Bulk {$act} on {$n} inventory rows", 'inventory', null, ['ids' => $ids, 'value' => $r->input('value')]);
        \Illuminate\Support\Facades\Cache::forget('adm_inv_counts');
        return back()->with('admin_ok', "{$n} item(s) updated.");
    }
}
