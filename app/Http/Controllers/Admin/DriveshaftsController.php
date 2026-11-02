<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DriveshaftFinder;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Complete driveshafts (ds_parts) + the shop's own build specs (ds_specs) per part number. */
class DriveshaftsController extends Controller
{
    public const SPEC_FIELDS = ['series_front', 'series_center', 'series_rear', 'u_joint_front', 'u_joint_center', 'u_joint_rear', 'tube_od', 'tube_wall', 'tube_material',
        'length_cc', 'length_overall', 'length_compressed', 'length_extended', 'front_end', 'rear_end', 'slip_yoke', 'slip_spline', 'flange_front', 'flange_rear', 'cv_type', 'center_bearing', 'pieces',
        'balance_rpm', 'weight_lb', 'build_cost', 'build_minutes', 'build_notes', 'customer_notes', 'status'];

    public function __construct(private DriveshaftFinder $finder) {}

    public function index(Request $r)
    {
        $q = DB::table('ds_parts as p')->leftJoin('ds_specs as s', 's.part_number', '=', 'p.part_number');
        $tab = $r->query('tab', 'all');
        match ($tab) {
            'spec' => $q->whereNotNull('s.part_number'),
            'verified' => $q->where('s.status', 'verified'),
            'nospec' => $q->whereNull('s.part_number'),
            'ordered' => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('order_items')->whereRaw("order_items.sku = CONCAT('U', p.part_number)")),
            'inactive' => $q->where('p.is_active', 0),
            'buildable' => $q->where('p.is_buildable', 1),
            default => null,
        };
        if ($s = trim((string) $r->query('q', ''))) { $pn = DriveshaftFinder::pn($s); $q->where(fn ($w) => $w->where('p.part_number', 'like', "%$pn%")->orWhere('p.description', 'like', "%$s%")->orWhere('p.upc', $s)); }
        if ($ser = $r->query('series')) $q->where('p.series', $ser);
        $make = $r->query('make'); $year = (int) $r->query('year'); $model = $r->query('model');
        if ($make) {
            $q->whereExists(function ($e) use ($make, $year, $model) {
                $e->selectRaw('1')->from('ds_fitments as f')->whereColumn('f.part_number', 'p.part_number')->where('f.make', $make);
                if ($year) $e->where('f.year', $year);
                if ($model) $e->where('f.model', $model);
            });
        }
        $sort = $r->query('sort', 'part_asc');
        match ($sort) { 'part_desc' => $q->orderByDesc('p.part_number'), 'price' => $q->orderBy('p.price_auto'), 'price_desc' => $q->orderByDesc('p.price_auto'), 'updated' => $q->orderByDesc('s.updated_at'), default => $q->orderBy('p.part_number') };
        $q->select('p.*', 's.status as spec_status', 's.updated_at as spec_updated', 's.series_front', 's.series_rear', 's.tube_od', 's.length_cc');
        if ($r->query('export') === 'csv') return $this->export((clone $q)->limit(10000)->get());
        $rows = $q->paginate(40)->withQueryString();
        $pns = collect($rows->items())->pluck('part_number');
        $fit = DB::table('ds_fitments')->whereIn('part_number', $pns)->selectRaw('part_number, COUNT(*) n, MIN(year) y0, MAX(year) y1, COUNT(DISTINCT make) makes, MIN(make) make, MIN(model) model')->groupBy('part_number')->get()->keyBy('part_number');
        $ordered = DB::table('order_items')->whereIn('sku', $pns->map(fn ($p) => 'U' . $p))->selectRaw('sku, SUM(qty) n')->groupBy('sku')->pluck('n', 'sku');
        foreach ($rows as $p) { $p->fit = $fit[$p->part_number] ?? null; $p->ordered = (int) ($ordered['U' . $p->part_number] ?? 0); $p->sku = DriveshaftFinder::sku($p->part_number); $p->price = DriveshaftFinder::customerPrice($p); }
        $counts = \Illuminate\Support\Facades\Cache::remember('adm_ds_tabs', 60, fn () => [
            'all' => DB::table('ds_parts')->count(), 'spec' => DB::table('ds_specs')->count(), 'verified' => DB::table('ds_specs')->where('status', 'verified')->count(),
            'nospec' => DB::table('ds_parts')->count() - DB::table('ds_specs')->count(),
            'ordered' => DB::table('ds_parts as p')->whereExists(fn ($e) => $e->selectRaw('1')->from('order_items')->whereRaw("order_items.sku = CONCAT('U', p.part_number)"))->count(),
            'inactive' => DB::table('ds_parts')->where('is_active', 0)->count(), 'buildable' => DB::table('ds_parts')->where('is_buildable', 1)->count(),
        ]);
        $series = DB::table('ds_parts')->whereNotNull('series')->where('series', '!=', '')->distinct()->orderBy('series')->pluck('series');
        $makes = $this->finder->makes();
        $years = $make ? DB::table('ds_fitments')->where('make', $make)->distinct()->orderByDesc('year')->pluck('year') : collect();
        $models = $make ? DB::table('ds_fitments')->where('make', $make)->when($year, fn ($w) => $w->where('year', $year))->distinct()->orderBy('model')->pluck('model') : collect();
        return view('admin.driveshafts.index', compact('rows', 'tab', 'sort', 'counts', 'series', 'makes', 'years', 'models', 'make', 'year', 'model') + ['f' => $r->query()]);
    }

    private function export($rows)
    {
        $out = fopen('php://temp', 'w+');
        $cols = array_merge(['part_number', 'sku', 'description', 'series', 'price_list', 'price_cost', 'price_retail', 'price_auto', 'weight', 'is_active', 'is_buildable'], array_diff(self::SPEC_FIELDS, ['build_notes', 'customer_notes']));
        \App\Support\Csv::put($out, $cols);
        $specs = DB::table('ds_specs')->whereIn('part_number', $rows->pluck('part_number'))->get()->keyBy('part_number');
        foreach ($rows as $p) { $s = $specs[$p->part_number] ?? null; $line = []; foreach ($cols as $c) $line[] = $c === 'sku' ? 'U' . $p->part_number : ($p->$c ?? $s?->$c ?? ''); \App\Support\Csv::put($out, $line); }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="driveshafts-' . date('Ymd') . '.csv"']);
    }

    /** Vehicle lookup: make → year → model → parts with their spec status. Mirrors what the shop does when an order comes in. */
    public function lookup(Request $r)
    {
        $make = (string) $r->query('make', ''); $year = (int) $r->query('year'); $model = (string) $r->query('model', '');
        $makes = $this->finder->makes();
        $years = $make ? DB::table('ds_fitments')->where('make', $make)->distinct()->orderByDesc('year')->pluck('year') : collect();
        $models = $make && $year ? DB::table('ds_fitments')->where('make', $make)->where('year', $year)->distinct()->orderBy('model')->pluck('model') : collect();
        $parts = collect(); $fits = collect();
        if ($make && $year && $model) {
            $fits = DB::table('ds_fitments')->where('make', $make)->where('year', $year)->where('model', $model)->orderBy('loc')->orderBy('part_number')->get();
            $pns = $fits->pluck('part_number')->unique();
            $parts = DB::table('ds_parts as p')->leftJoin('ds_specs as s', 's.part_number', '=', 'p.part_number')->whereIn('p.part_number', $pns)->select('p.*', 's.status', 's.series_front', 's.series_center', 's.series_rear', 's.u_joint_front', 's.u_joint_rear', 's.tube_od', 's.tube_wall', 's.tube_material', 's.length_cc', 's.length_overall', 's.length_compressed', 's.front_end', 's.rear_end', 's.slip_yoke', 's.slip_spline', 's.center_bearing', 's.pieces', 's.balance_rpm', 's.build_notes')->get()->keyBy('part_number');
            foreach ($parts as $p) { $p->sku = DriveshaftFinder::sku($p->part_number); $p->price = DriveshaftFinder::customerPrice($p); }
        }
        $notes = $make ? DB::table('ds_vehicle_notes')->where('make', $make)->where(fn ($w) => $w->whereNull('model')->orWhere('model', $model ?: '—'))->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $year ?: 9999))->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year ?: 0))->orderByDesc('id')->get() : collect();
        $recent = DB::table('ds_spec_history as h')->join('ds_parts as p', 'p.part_number', '=', 'h.part_number')->leftJoin('users as u', 'u.id', '=', 'h.user_id')->orderByDesc('h.id')->limit(8)->get(['h.part_number', 'h.created_at', 'p.description', 'u.name as user_name']);
        return view('admin.driveshafts.lookup', compact('make', 'year', 'model', 'makes', 'years', 'models', 'parts', 'fits', 'notes', 'recent'));
    }

    public function show(string $pn)
    {
        $pn = DriveshaftFinder::pn($pn);
        $part = DB::table('ds_parts')->where('part_number', $pn)->first(); abort_unless($part, 404);
        $part->sku = DriveshaftFinder::sku($pn); $part->price = DriveshaftFinder::customerPrice($part);
        $part->imageList = array_values(array_filter(array_unique(array_merge([$part->main_image], json_decode($part->images ?: '[]', true) ?: []))));
        $spec = DB::table('ds_specs')->where('part_number', $pn)->first();
        $specArr = $spec ? (array) $spec : array_fill_keys(self::SPEC_FIELDS, null) + ['status' => 'draft'];
        $components = $spec ? (json_decode($spec->components ?: '[]', true) ?: []) : [];
        $photos = $spec ? (json_decode($spec->photos ?: '[]', true) ?: []) : [];
        $page = $this->finder->partPage($pn);
        $fitRows = DB::table('ds_fitments')->where('part_number', $pn)->orderBy('make')->orderBy('model')->orderBy('year')->get();
        $history = DB::table('ds_spec_history as h')->leftJoin('users as u', 'u.id', '=', 'h.user_id')->where('h.part_number', $pn)->orderByDesc('h.id')->limit(20)->get(['h.*', 'u.name as user_name']);
        $orders = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.sku', 'U' . $pn)->orderByDesc('o.id')->limit(12)->get(['o.id', 'o.order_number', 'o.created_at', 'o.status', 'o.billing_first_name', 'o.billing_last_name', 'i.qty', 'i.price', 'i.meta']);
        $makes = $fitRows->pluck('make')->unique()->values();
        $notes = $makes->count() ? DB::table('ds_vehicle_notes')->whereIn('make', $makes)->orderByDesc('id')->limit(20)->get() : collect();
        // sibling parts: same series + same vehicle (good spec donors)
        $siblings = DB::table('ds_specs as s')->join('ds_parts as p', 'p.part_number', '=', 's.part_number')->where('s.part_number', '!=', $pn)
            ->where(fn ($w) => $w->where('p.series', $part->series ?: '—')->orWhereExists(fn ($e) => $e->selectRaw('1')->from('ds_fitments as a')->join('ds_fitments as b', fn ($j) => $j->on('a.make', '=', 'b.make')->on('a.model', '=', 'b.model'))->whereColumn('b.part_number', 'p.part_number')->where('a.part_number', $pn)))
            ->orderByDesc('s.updated_at')->limit(8)->get(['p.part_number', 'p.description', 's.status', 's.series_front', 's.tube_od', 's.length_cc']);
        $updatedBy = $spec && $spec->updated_by ? DB::table('users')->where('id', $spec->updated_by)->value('name') : null;
        $activity = AdminLog::for('driveshaft', $pn, 10);
        return view('admin.driveshafts.show', compact('part', 'spec', 'specArr', 'components', 'photos', 'page', 'fitRows', 'history', 'orders', 'notes', 'siblings', 'updatedBy', 'activity', 'pn'));
    }

    public function update(Request $r, string $pn)
    {
        $pn = DriveshaftFinder::pn($pn);
        abort_unless(DB::table('ds_parts')->where('part_number', $pn)->exists(), 404);
        $d = $r->validate(['description' => 'nullable|string|max:255', 'condition' => 'nullable|string|max:30', 'notes' => 'nullable|string|max:5000', 'series' => 'nullable|string|max:60', 'vehicle_class' => 'nullable|string|max:60',
            'price_list' => 'nullable|numeric|min:0', 'price_cost' => 'nullable|numeric|min:0', 'price_retail' => 'nullable|numeric|min:0', 'core_price' => 'nullable|numeric|min:0', 'weight' => 'nullable|numeric|min:0',
            'box_length' => 'nullable|numeric|min:0', 'box_width' => 'nullable|numeric|min:0', 'box_height' => 'nullable|numeric|min:0', 'upc' => 'nullable|string|max:40', 'is_active' => 'nullable', 'is_buildable' => 'nullable', 'images' => 'nullable|array']);
        $upd = ['updated_at' => now()];
        foreach (['description', 'condition', 'notes', 'series', 'vehicle_class', 'upc'] as $k) if ($r->has($k)) $upd[$k] = $d[$k] ?? null;
        foreach (['price_list', 'price_cost', 'price_retail', 'core_price', 'weight', 'box_length', 'box_width', 'box_height'] as $k) if ($r->has($k)) $upd[$k] = ($d[$k] ?? '') === '' ? null : $d[$k];
        $upd['is_active'] = $r->boolean('is_active'); $upd['is_buildable'] = $r->boolean('is_buildable');
        // hidden by a person stays hidden: the PTI price sync only brings back shafts it hid itself (pti_absent)
        $upd['hidden_reason'] = $upd['is_active'] ? null : 'admin';
        if ($r->has('images')) { $imgs = array_values(array_filter((array) $d['images'])); $upd['main_image'] = $imgs[0] ?? null; $upd['images'] = json_encode(array_slice($imgs, 1)); $upd['picture_count'] = count($imgs); }
        DB::table('ds_parts')->where('part_number', $pn)->update($upd);
        \Illuminate\Support\Facades\Cache::forget('catalog_ds_subcat'); \Illuminate\Support\Facades\Cache::forget('adm_ds_tabs');
        AdminLog::record('driveshaft.update', "Driveshaft U{$pn}: part details edited", 'driveshaft', $pn);
        return back()->with('admin_ok', 'Part saved.');
    }

    public function spec(Request $r, string $pn)
    {
        $pn = DriveshaftFinder::pn($pn);
        abort_unless(DB::table('ds_parts')->where('part_number', $pn)->exists(), 404);
        $rules = [];
        foreach (self::SPEC_FIELDS as $f) $rules[$f] = in_array($f, ['build_notes', 'customer_notes']) ? 'nullable|string|max:5000' : ($f === 'build_cost' ? 'nullable|numeric|min:0' : ($f === 'build_minutes' ? 'nullable|integer|min:0' : ($f === 'status' ? 'nullable|in:draft,verified' : 'nullable|string|max:120')));
        $rules['components'] = 'nullable|array'; $rules['photos'] = 'nullable|array';
        $d = $r->validate($rules);
        $row = ['updated_by' => auth()->id(), 'updated_at' => now()];
        foreach (self::SPEC_FIELDS as $f) $row[$f] = ($d[$f] ?? '') === '' ? null : $d[$f];
        $row['status'] = $d['status'] ?? 'draft';
        $comps = [];
        foreach ((array) ($d['components'] ?? []) as $c) { if (empty($c['sku']) && empty($c['name'])) continue; $comps[] = ['sku' => trim((string) ($c['sku'] ?? '')), 'name' => trim((string) ($c['name'] ?? '')), 'qty' => max(1, (int) ($c['qty'] ?? 1)), 'id' => (int) ($c['id'] ?? 0) ?: null]; }
        $row['components'] = json_encode($comps);
        $row['photos'] = json_encode(array_values(array_filter((array) ($d['photos'] ?? []))));
        $existing = DB::table('ds_specs')->where('part_number', $pn)->first();
        if ($existing) DB::table('ds_specs')->where('part_number', $pn)->update($row); else DB::table('ds_specs')->insert($row + ['part_number' => $pn, 'created_at' => now()]);
        DB::table('ds_spec_history')->insert(['part_number' => $pn, 'user_id' => auth()->id(), 'snapshot' => json_encode($row), 'created_at' => now()]);
        \Illuminate\Support\Facades\Cache::forget('adm_ds_tabs');
        AdminLog::record('driveshaft.spec', "Driveshaft U{$pn}: build spec " . ($existing ? 'updated' : 'created') . ' (' . $row['status'] . ')', 'driveshaft', $pn);
        return back()->with('admin_ok', 'Build spec saved' . ($row['status'] === 'verified' ? ' and marked verified.' : '.'));
    }

    /** Copy another part's spec onto this one (same vehicle family / sibling). */
    public function copySpec(Request $r, string $pn)
    {
        $pn = DriveshaftFinder::pn($pn); $from = DriveshaftFinder::pn((string) $r->input('from'));
        $src = DB::table('ds_specs')->where('part_number', $from)->first();
        if (!$src) return back()->with('admin_err', "No spec found on U{$from}.");
        $row = (array) $src; unset($row['part_number'], $row['created_at']);
        $row['status'] = 'draft'; $row['updated_by'] = auth()->id(); $row['updated_at'] = now();
        if (DB::table('ds_specs')->where('part_number', $pn)->exists()) DB::table('ds_specs')->where('part_number', $pn)->update($row); else DB::table('ds_specs')->insert($row + ['part_number' => $pn, 'created_at' => now()]);
        DB::table('ds_spec_history')->insert(['part_number' => $pn, 'user_id' => auth()->id(), 'snapshot' => json_encode($row + ['copied_from' => $from]), 'created_at' => now()]);
        AdminLog::record('driveshaft.spec', "Driveshaft U{$pn}: spec copied from U{$from}", 'driveshaft', $pn);
        return back()->with('admin_ok', "Spec copied from U{$from} — review and mark verified.");
    }

    public function vehicleNote(Request $r)
    {
        $d = $r->validate(['make' => 'required|string|max:40', 'model' => 'nullable|string|max:60', 'year_from' => 'nullable|integer|min:1900|max:2100', 'year_to' => 'nullable|integer|min:1900|max:2100', 'notes' => 'required|string|max:4000']);
        DB::table('ds_vehicle_notes')->insert(['make' => $d['make'], 'model' => $d['model'] ?: null, 'year_from' => $d['year_from'] ?: null, 'year_to' => $d['year_to'] ?: null, 'notes' => $d['notes'], 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        AdminLog::record('driveshaft.note', 'Vehicle note: ' . trim($d['make'] . ' ' . ($d['model'] ?? '')), 'vehicle', $d['make']);
        return back()->with('admin_ok', 'Vehicle note saved.');
    }

    public function deleteVehicleNote(int $id)
    {
        $n = DB::table('ds_vehicle_notes')->find($id);
        DB::table('ds_vehicle_notes')->where('id', $id)->delete();
        if ($n) \App\Support\AdminLog::record('driveshaft.note.delete', 'Deleted vehicle note ' . trim(($n->make ?? '') . ' ' . ($n->model ?? '')), 'ds_vehicle_note', $id, ['notes' => mb_substr((string) ($n->notes ?? ''), 0, 500)]);
        return back()->with('admin_ok', 'Note removed.');
    }

    public function printSheet(string $pn)
    {
        $pn = DriveshaftFinder::pn($pn);
        $part = DB::table('ds_parts')->where('part_number', $pn)->first(); abort_unless($part, 404);
        $spec = DB::table('ds_specs')->where('part_number', $pn)->first();
        $components = $spec ? (json_decode($spec->components ?: '[]', true) ?: []) : [];
        $photos = $spec ? (json_decode($spec->photos ?: '[]', true) ?: []) : [];
        $page = $this->finder->partPage($pn);
        return view('admin.driveshafts.sheet', compact('part', 'spec', 'components', 'photos', 'page', 'pn'));
    }
}
