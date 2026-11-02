<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CatalogWriter;
use App\Services\CatalogService;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function __construct(private CatalogWriter $writer, private CatalogService $catalog) {}

    public function index(Request $r)
    {
        $db = DB::connection('wp');
        $q = $db->table('wp_posts as p')->where('p.post_type', 'product')
            ->leftJoinSub($db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) v'))->where('meta_key', '_sku')->groupBy('post_id'), 'sk', 'sk.post_id', '=', 'p.ID')
            ->leftJoinSub($db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value+0) v'))->where('meta_key', '_price')->groupBy('post_id'), 'pr', 'pr.post_id', '=', 'p.ID')
            ->leftJoinSub($db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) v'))->where('meta_key', '_stock_status')->groupBy('post_id'), 'ss', 'ss.post_id', '=', 'p.ID')
            ->leftJoinSub($db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value+0) v'))->where('meta_key', '_stock')->groupBy('post_id'), 'st', 'st.post_id', '=', 'p.ID');
        $tab = $r->query('tab', 'all');
        match ($tab) {
            'published' => $q->where('p.post_status', 'publish'),
            'draft' => $q->whereIn('p.post_status', ['draft', 'pending', 'private']),
            'out' => $q->where('ss.v', 'outofstock'),
            'nosku' => $q->where(fn ($w) => $w->whereNull('sk.v')->orWhere('sk.v', '')),
            'noimage' => $q->whereNotExists(fn ($e) => $e->selectRaw('1')->from('wp_postmeta as tm')->whereColumn('tm.post_id', 'p.ID')->where('tm.meta_key', '_thumbnail_id')->where('tm.meta_value', '!=', '11868'))->whereNotExists(fn ($e) => $e->selectRaw('1')->from(DB::connection()->getDatabaseName() . '.catalog_live as cl')->whereColumn('cl.wp_product_id', 'p.ID')->whereNotNull('cl.image')),
            'edited' => $q->whereExists(fn ($e) => $e->selectRaw('1')->from(DB::connection()->getDatabaseName() . '.catalog_live as cl')->whereColumn('cl.wp_product_id', 'p.ID')->whereNotNull('cl.locked_at')),
            default => $q->whereIn('p.post_status', ['publish', 'draft', 'pending', 'private']),
        };
        if ($s = trim((string) $r->query('q', ''))) {
            $like = "%$s%";
            $q->where(fn ($w) => $w->where('p.post_title', 'like', $like)->orWhere('sk.v', 'like', $like)->orWhere('p.ID', ctype_digit($s) ? (int) $s : 0));
        }
        if ($cat = $r->query('cat')) {
            $term = $this->catalog->term($cat);
            if ($term) $q->whereExists(fn ($e) => $e->selectRaw('1')->from('wp_term_relationships as cr')->whereColumn('cr.object_id', 'p.ID')->where('cr.term_taxonomy_id', $term->term_taxonomy_id));
        }
        if ($r->query('attr') && $r->query('val')) {
            $q->whereExists(fn ($e) => $e->selectRaw('1')->from('wp_term_relationships as ar')->join('wp_term_taxonomy as att', 'att.term_taxonomy_id', '=', 'ar.term_taxonomy_id')->join('wp_terms as at', 'at.term_id', '=', 'att.term_id')
                ->whereColumn('ar.object_id', 'p.ID')->where('att.taxonomy', $r->query('attr'))->where('at.name', $r->query('val')));
        }
        $sort = $r->query('sort', 'newest');
        match ($sort) {
            'name' => $q->orderBy('p.post_title'), 'sku' => $q->orderBy('sk.v'), 'price' => $q->orderBy('pr.v'), 'price_desc' => $q->orderByDesc('pr.v'),
            'stock' => $q->orderBy('st.v'), 'modified' => $q->orderByDesc('p.post_modified'), default => $q->orderByDesc('p.ID'),
        };
        $q->select('p.ID', 'p.post_title', 'p.post_status', 'p.post_modified', 'sk.v as sku', 'pr.v as price', 'ss.v as stock_status', 'st.v as stock');
        if ($r->query('export') === 'csv') { abort_unless(adm_can('exports'), 403, 'Your admin role cannot export the catalog.'); return $this->export((clone $q)->limit(10000)->get()); }
        $rows = $q->paginate(40)->withQueryString();
        $ids = collect($rows->items())->pluck('ID')->all();
        $thumbs = $this->catalog->thumbsFor($ids);
        $cats = $db->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->whereIn('tr.object_id', $ids)->where('tt.taxonomy', 'product_cat')->get(['tr.object_id', 't.name', 't.slug'])->groupBy('object_id');
        $live = DB::table('catalog_live')->whereIn('wp_product_id', $ids)->get()->keyBy('wp_product_id');
        $inv = DB::table('inventory')->whereIn('wp_product_id', $ids)->get()->keyBy('wp_product_id');
        $pti = DB::table('pti_parts')->whereIn('wp_product_id', $ids)->whereNotNull('stock_synced_at')->pluck('stock_total', 'wp_product_id');
        foreach ($rows as $p) {
            $p->image = $thumbs[(int) $p->ID] ?? '';
            $p->cats = ($cats[$p->ID] ?? collect())->map(fn ($c) => html_entity_decode($c->name))->all();
            $p->cat_slug = ($cats[$p->ID] ?? collect())->first()->slug ?? null;
            $p->locked = (bool) ($live[$p->ID]->locked_at ?? false);
            $p->live_price = $live[$p->ID]->price ?? null;
            $p->inv = $inv[$p->ID] ?? null;
            $p->pti_stock = $pti[$p->ID] ?? null;   // supplier on-hand (pti:sync --stock)
        }
        $counts = \Illuminate\Support\Facades\Cache::remember('adm_prod_tabs', 60, fn () => [
            'all' => $db->table('wp_posts')->where('post_type', 'product')->whereIn('post_status', ['publish', 'draft', 'pending', 'private'])->count(),
            'published' => $db->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->count(),
            'draft' => $db->table('wp_posts')->where('post_type', 'product')->whereIn('post_status', ['draft', 'pending', 'private'])->count(),
            'out' => $db->table('wp_postmeta')->where('meta_key', '_stock_status')->where('meta_value', 'outofstock')->distinct()->count('post_id'),
            'edited' => DB::table('catalog_live')->whereNotNull('locked_at')->count(),
        ]);
        $categories = collect($this->writer->categories())->sortBy('name')->values();
        return view('admin.products.index', compact('rows', 'tab', 'sort', 'counts', 'categories') + ['f' => $r->query()]);
    }

    private function export($rows)
    {
        $out = fopen('php://temp', 'w+');
        \App\Support\Csv::put($out, ['ID', 'SKU', 'Name', 'Status', 'Price', 'Stock status', 'Stock', 'Modified']);
        foreach ($rows as $p) \App\Support\Csv::put($out, [$p->ID, $p->sku, html_entity_decode($p->post_title), $p->post_status, $p->price, $p->stock_status, $p->stock, $p->post_modified]);
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="products-' . date('Ymd') . '.csv"']);
    }

    public function create(Request $r)
    {
        $p = ['id' => null, 'name' => '', 'slug' => '', 'status' => 'publish', 'description' => '', 'short' => '', 'sku' => '', 'regular_price' => '', 'sale_price' => '', 'cost' => '', 'weight' => '', 'length' => '', 'width' => '', 'height' => '',
            'manage_stock' => true, 'stock' => 0, 'stock_status' => 'instock', 'backorders' => 'no', 'sold_by_foot' => false, 'seo_title' => '', 'seo_desc' => '', 'interchange' => '', 'categories' => array_filter([$r->query('cat')]), 'attrs' => [], 'attr_order' => [], 'images' => [], 'locked' => false];
        if ($dup = (int) $r->query('from')) { $src = $this->writer->product($dup); if ($src) { $p = $src; $p['id'] = null; $p['sku'] = ''; $p['slug'] = ''; $p['name'] = $src['name']; } }
        return $this->form($p);
    }

    public function edit(int $id)
    {
        $p = $this->writer->product($id); abort_unless($p, 404);
        return $this->form($p);
    }

    private function form(array $p)
    {
        $categories = collect($this->writer->categories())->sortBy('name')->values();
        $allAttrs = $this->writer->attributeTaxonomies();
        $suggested = [];
        foreach ($p['categories'] as $c) foreach ($this->writer->categoryAttributes($c) as $t) $suggested[$t] = true;
        $order = array_values(array_unique(array_merge($p['attr_order'], array_keys($p['attrs']), array_keys($suggested))));
        $stats = null; $orders = collect();
        if ($p['id']) {
            $stats = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.wp_product_id', $p['id'])->where('o.status', '!=', 'cancelled')->selectRaw('COUNT(DISTINCT o.id) n, SUM(i.qty) q, SUM(i.line_total) t, MAX(o.created_at) last')->first();
            $orders = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.wp_product_id', $p['id'])->orderByDesc('o.id')->limit(8)->get(['o.id', 'o.order_number', 'o.created_at', 'o.status', 'i.qty', 'i.price', 'o.billing_first_name', 'o.billing_last_name']);
        }
        $inv = $p['id'] ? DB::table('inventory')->where('wp_product_id', $p['id'])->first() : null;
        $pti = $p['id'] ? DB::table('pti_parts')->where('wp_product_id', $p['id'])->first() : null;
        $activity = $p['id'] ? AdminLog::for('product', $p['id'], 10) : collect();
        $publicUrl = $p['id'] && $p['status'] === 'publish' ? '/product/' . $p['slug'] : null;
        return view('admin.products.form', compact('p', 'categories', 'allAttrs', 'order', 'stats', 'orders', 'inv', 'pti', 'activity', 'publicUrl'));
    }

    private function rules(?int $id): array
    {
        return [
            'name' => 'required|string|max:255', 'slug' => 'nullable|string|max:200', 'status' => 'required|in:publish,draft,private',
            'sku' => 'required|string|max:80', 'regular_price' => 'nullable|numeric|min:0', 'sale_price' => 'nullable|numeric|min:0', 'cost' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0', 'length' => 'nullable|numeric|min:0', 'width' => 'nullable|numeric|min:0', 'height' => 'nullable|numeric|min:0',
            'manage_stock' => 'nullable', 'stock' => 'nullable|integer', 'stock_status' => 'nullable|in:instock,outofstock,onbackorder', 'backorders' => 'nullable|in:no,notify,yes',
            'description' => 'nullable|string|max:20000', 'short' => 'nullable|string|max:2000', 'sold_by_foot' => 'nullable',
            'seo_title' => 'nullable|string|max:160', 'seo_desc' => 'nullable|string|max:320', 'interchange' => 'nullable|string|max:2000',
            'categories' => 'nullable|array', 'attrs' => 'nullable|array', 'images' => 'nullable|array',
        ];
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules(null));
        if (DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', trim($d['sku']))->exists()) return back()->withInput()->with('admin_err', 'SKU ' . $d['sku'] . ' already exists.');
        $d = $this->normalize($d, $r);
        $id = $this->writer->saveProduct(null, $d);
        AdminLog::record('product.create', 'Created product ' . $d['sku'], 'product', $id);
        return redirect('/admin/products/' . $id)->with('admin_ok', 'Product created.');
    }

    public function update(Request $r, int $id)
    {
        abort_unless($this->writer->product($id), 404);
        $d = $r->validate($this->rules($id));
        $dupe = DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', trim($d['sku']))->where('post_id', '!=', $id)->value('post_id');
        if ($dupe) return back()->withInput()->with('admin_err', 'SKU ' . $d['sku'] . ' is already used by product #' . $dupe . '.');
        $d = $this->normalize($d, $r);
        $this->writer->saveProduct($id, $d);
        AdminLog::record('product.update', 'Updated product ' . $d['sku'], 'product', $id);
        return redirect('/admin/products/' . $id)->with('admin_ok', 'Product saved.');
    }

    private function normalize(array $d, Request $r): array
    {
        foreach (['regular_price', 'sale_price', 'cost', 'weight', 'length', 'width', 'height', 'slug', 'description', 'short', 'seo_title', 'seo_desc', 'interchange'] as $k) $d[$k] = $d[$k] ?? '';
        $d['manage_stock'] = $r->boolean('manage_stock'); $d['sold_by_foot'] = $r->boolean('sold_by_foot');
        $d['stock_status'] = $d['stock_status'] ?? 'instock'; $d['backorders'] = $d['backorders'] ?? 'no';
        $d['categories'] = array_values(array_filter((array) ($d['categories'] ?? [])));
        $attrs = [];
        foreach ((array) ($d['attrs'] ?? []) as $tax => $val) { if (is_string($val) && trim($val) !== '') $attrs[$tax] = $val; }
        // "new attribute" rows: attr_new_key[] / attr_new_val[]
        foreach ((array) $r->input('attr_new_key', []) as $i => $k) {
            $k = trim((string) $k); $v = trim((string) ($r->input('attr_new_val')[$i] ?? ''));
            if ($k === '' || $v === '') continue;
            $tax = str_starts_with($k, 'pa_') ? $k : 'pa_' . \Illuminate\Support\Str::slug($k);
            $attrs[$tax] = isset($attrs[$tax]) ? $attrs[$tax] . '|' . $v : $v;
        }
        $d['attrs'] = $attrs;
        $d['images'] = array_values(array_filter((array) ($d['images'] ?? [])));
        return $d;
    }

    public function destroy(int $id)
    {
        $p = $this->writer->product($id); abort_unless($p, 404);
        $this->writer->deleteProduct($id);
        AdminLog::record('product.delete', 'Deleted product ' . ($p['sku'] ?: '#' . $id), 'product', $id);
        return redirect('/admin/products')->with('admin_ok', 'Product deleted.');
    }

    public function duplicate(int $id)
    {
        $new = $this->writer->duplicate($id); abort_unless($new, 404);
        AdminLog::record('product.duplicate', 'Duplicated product #' . $id . ' → #' . $new, 'product', $new);
        return redirect('/admin/products/' . $new)->with('admin_ok', 'Duplicated as a draft — set the SKU and publish when ready.');
    }

    public function bulk(Request $r)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $r->input('ids'))));
        $act = $r->input('bulk'); $n = 0; $skipped = [];
        if (!$ids) return back()->with('admin_err', 'Nothing selected.');
        // a blank or bad value must never zero out prices / stock across the selection
        if ($act === 'price') $r->validate(['mode' => 'required|in:pct,add,set', 'value' => array_merge(['required', 'numeric'], match ($r->input('mode')) { 'pct' => ['min:-90', 'max:1000'], 'set' => ['gt:0', 'max:1000000'], default => ['max:1000000'] })],
            ['value.gt' => 'A price must be more than $0.', 'value.min' => 'A price cut cannot be more than 90%.']);
        if ($act === 'stock') $r->validate(['value' => 'required|integer|min:0|max:1000000']);
        foreach ($ids as $id) {
            if (in_array($act, ['publish', 'draft'])) { $this->writer->setStatus($id, $act); $n++; }
            elseif ($act === 'delete') { $this->writer->deleteProduct($id); $n++; }
            elseif ($act === 'price') {
                $p = $this->writer->product($id); if (!$p) continue;
                $mode = $r->input('mode'); $v = (float) $r->input('value');
                $reg = (float) $p['regular_price'];
                $new = round(match ($mode) { 'pct' => $reg * (1 + $v / 100), 'add' => $reg + $v, 'set' => $v, default => $reg }, 2);
                if ($new <= 0) { $skipped[] = $p['sku'] ?: "#$id"; continue; }   // $0 / no price: leave that product alone
                $this->writer->quick($id, ['regular_price' => $new]); $n++;
            } elseif ($act === 'category') {
                $p = $this->writer->product($id); if (!$p) continue;
                $slug = (string) $r->input('value'); if (!$slug) break;
                $cats = $r->input('mode') === 'replace' ? [$slug] : array_values(array_unique(array_merge($p['categories'], [$slug])));
                $this->writer->quick($id, ['categories' => $cats]); $n++;
            } elseif ($act === 'stock') {
                $p = $this->writer->product($id); if (!$p) continue;
                $this->writer->setStock($id, (int) $r->input('value')); $this->syncInventoryStock($id, (int) $r->input('value')); $n++;
            }
        }
        AdminLog::record('product.bulk', "Bulk {$act} on {$n} products", 'product', null, ['ids' => $ids, 'mode' => $r->input('mode'), 'value' => $r->input('value'), 'skipped' => $skipped]);
        \Illuminate\Support\Facades\Cache::forget('adm_prod_tabs');
        $res = back()->with('admin_ok', "{$n} product(s) updated.");
        return $skipped ? $res->with('admin_err', count($skipped) . ' product(s) skipped — the new price would be $0 or less: ' . implode(', ', array_slice($skipped, 0, 8))) : $res;
    }

    /** Inline field save from the list (price / stock / status). */
    public function quick(Request $r, int $id)
    {
        $d = $r->validate(['field' => 'required|in:regular_price,sale_price,stock,status', 'value' => 'nullable|string|max:40']);
        $v = $d['value'] ?? '';
        if ($d['field'] === 'stock') { $this->writer->setStock($id, (int) $v); $this->syncInventoryStock($id, (int) $v); }
        elseif ($d['field'] === 'status') $this->writer->setStatus($id, in_array($v, ['publish', 'draft', 'private']) ? $v : 'draft');
        else $this->writer->quick($id, [$d['field'] => $v === '' ? '' : round((float) $v, 2)]);
        AdminLog::record('product.quick', "Product #$id: {$d['field']} = {$v}", 'product', $id);
        return response()->json(['ok' => true]);
    }

    /** Keep the admin stock list in step with a stock-only write (what saveProduct() did for manual rows). */
    private function syncInventoryStock(int $id, int $stock): void
    {
        $inv = DB::table('inventory')->where('wp_product_id', $id)->first();
        if ($inv && $inv->source === 'manual') DB::table('inventory')->where('id', $inv->id)->update(['stock' => max(0, $stock), 'updated_at' => now()]);
        elseif (!$inv && ($p = $this->writer->product($id))) DB::table('inventory')->insert(['wp_product_id' => $id, 'sku' => $p['sku'], 'name' => mb_substr($p['name'], 0, 255), 'category' => $p['categories'][0] ?? null, 'stock' => max(0, $stock), 'source' => 'manual', 'tracked' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Attribute term values for the form's datalists. */
    public function apiTerms(Request $r)
    {
        $tax = (string) $r->query('tax', '');
        if (!preg_match('/^pa_[a-z0-9\-]+$/', $tax)) return response()->json(['items' => []]);
        return response()->json(['items' => $this->writer->termValues($tax, (string) $r->query('q', ''), 40)]);
    }

    /** "$1,250.00" → "1250.00"; null when it is not a usable number. */
    public static function csvMoney(string $v): ?string
    {
        $v = preg_replace('/[^\d.\-]/', '', $v);
        return is_numeric($v) ? $v : null;
    }

    public function import(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $fh = fopen($r->file('file')->getRealPath(), 'r');
        $first = fgetcsv($fh) ?: [];
        if (isset($first[0])) $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', $first[0]);   // Excel's UTF-8 BOM
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), $first);
        if (!in_array('sku', $head, true)) return back()->with('admin_err', 'The CSV needs a "sku" column header (found: ' . (implode(', ', array_filter($head)) ?: 'nothing') . ').');
        $n = 0; $created = 0; $errors = []; $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            try {
                if ($row === [null]) continue;   // blank line
                $rec = array_combine($head, array_slice(array_pad($row, count($head), ''), 0, count($head)));
                $sku = trim((string) ($rec['sku'] ?? '')); if ($sku === '') continue;
                $fields = []; $bad = null;
                foreach (['name', 'regular_price', 'sale_price', 'cost', 'stock', 'weight', 'status', 'description'] as $k) if (isset($rec[$k]) && trim($rec[$k]) !== '') $fields[$k] = trim($rec[$k]);
                foreach (['regular_price', 'sale_price', 'cost'] as $k) if (isset($fields[$k])) {
                    $m = self::csvMoney($fields[$k]);
                    if ($m === null || ($k !== 'cost' && (float) $m <= 0) || (float) $m < 0) { $bad = "$k \"{$fields[$k]}\" is not a valid price"; break; }
                    $fields[$k] = $m;
                }
                if ($bad) { $errors[] = "row $line ($sku): $bad"; continue; }
                $pid = DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->where('meta_value', $sku)->value('post_id');
                if (isset($rec['categories']) && $rec['categories'] !== '') $fields['categories'] = array_map('trim', explode('|', $rec['categories']));
                if (isset($fields['stock'])) { $fields['manage_stock'] = true; $fields['stock'] = (int) $fields['stock']; }
                $attrs = [];
                foreach ($rec as $k => $v) if (str_starts_with($k, 'pa_') && $v !== '') $attrs[$k] = $v;
                if ($pid) { if ($attrs) { $p = $this->writer->product($pid); $fields['attrs'] = array_merge(array_map(fn ($x) => implode('|', $x), $p['attrs']), $attrs); } $this->writer->quick((int) $pid, $fields); $n++; }
                else {
                    if (empty($fields['name'])) { $errors[] = "row $line ($sku): name required for new products"; continue; }
                    $d = ['name' => $fields['name'], 'slug' => '', 'status' => $fields['status'] ?? 'draft', 'sku' => $sku, 'regular_price' => $fields['regular_price'] ?? '', 'sale_price' => $fields['sale_price'] ?? '', 'cost' => $fields['cost'] ?? '', 'weight' => $fields['weight'] ?? '', 'length' => '', 'width' => '', 'height' => '',
                        'manage_stock' => isset($fields['stock']), 'stock' => $fields['stock'] ?? 0, 'stock_status' => 'instock', 'backorders' => 'no', 'description' => $fields['description'] ?? '', 'short' => '', 'seo_title' => '', 'seo_desc' => '', 'interchange' => '', 'categories' => $fields['categories'] ?? [], 'attrs' => $attrs, 'images' => []];
                    $this->writer->saveProduct(null, $d); $created++;
                }
            } catch (\Throwable $e) { report($e); $errors[] = "row $line" . (isset($sku) ? " ($sku)" : '') . ': ' . $e->getMessage(); }
        }
        AdminLog::record('product.import', "CSV import: {$n} updated, {$created} created", 'product', null, ['errors' => array_slice($errors, 0, 20)]);
        return back()->with($errors ? 'admin_err' : 'admin_ok', "{$n} updated, {$created} created." . ($errors ? ' Problems: ' . implode(' · ', array_slice($errors, 0, 5)) : ''));
    }
}
