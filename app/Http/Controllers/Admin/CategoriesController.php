<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CatalogWriter;
use App\Support\AdminLog;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function __construct(private CatalogWriter $writer) {}

    public function index()
    {
        $cats = collect($this->writer->categories());
        $byParent = $cats->groupBy('parent');
        $groups = config('catalog.groups');
        return view('admin.categories.index', ['cats' => $cats, 'byParent' => $byParent, 'groups' => $groups]);
    }

    public function create()
    {
        return view('admin.categories.form', ['c' => ['term_id' => null, 'name' => '', 'slug' => '', 'description' => '', 'parent' => 0, 'image' => '', 'order' => 0, 'seo_title' => '', 'seo_desc' => '', 'blurb' => '', 'group' => '', 'hidden' => false, 'count' => 0], 'all' => $this->writer->categories(), 'groups' => config('catalog.groups')]);
    }

    public function edit(string $slug)
    {
        $c = $this->writer->category($slug); abort_unless($c, 404);
        $schema = app(\App\Services\CatalogService::class)->categorySchema($slug);
        return view('admin.categories.form', ['c' => $c, 'all' => $this->writer->categories(), 'groups' => config('catalog.groups'), 'schema' => $schema]);
    }

    public function store(Request $r)
    {
        $d = $this->validated($r);
        $slug = $this->writer->saveCategory(null, $d);
        AdminLog::record('category.create', 'Created category ' . $d['name'], 'category', $slug);
        return redirect('/admin/categories/' . $slug)->with('admin_ok', 'Category created.');
    }

    public function update(Request $r, string $slug)
    {
        $c = $this->writer->category($slug); abort_unless($c, 404);
        $d = $this->validated($r);
        // a category under itself or its own child loops the menu tree forever
        if (isset($d['parent']) && (int) $d['parent'] && in_array((int) $d['parent'], $this->selfAndDescendants((int) $c['term_id']), true))
            return back()->withInput()->with('admin_err', 'A category cannot be placed under itself or one of its own subcategories.');
        $this->writer->saveCategory($slug, $d);
        AdminLog::record('category.update', 'Updated category ' . $d['name'], 'category', $slug);
        return back()->with('admin_ok', 'Category saved.');
    }

    /** term_id plus every term below it. */
    private function selfAndDescendants(int $termId): array
    {
        $kids = collect($this->writer->categories())->groupBy('parent');
        $out = []; $todo = [$termId];
        while ($todo) { $t = array_pop($todo); if (in_array($t, $out, true)) continue; $out[] = $t; foreach ($kids[$t] ?? [] as $k) $todo[] = (int) $k['term_id']; }
        return $out;
    }

    private function validated(Request $r): array
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'slug' => 'nullable|string|max:120', 'description' => 'nullable|string|max:5000', 'parent' => 'nullable|integer', 'image' => 'nullable|string|max:500', 'order' => 'nullable|integer',
            'seo_title' => 'nullable|string|max:160', 'seo_desc' => 'nullable|string|max:320', 'blurb' => 'nullable|string|max:400', 'group' => 'nullable|string|max:60', 'hidden' => 'nullable', 'images' => 'nullable|array']);
        $d['image'] = $d['images'][0] ?? ($d['image'] ?? '');
        $d['hidden'] = $r->boolean('hidden');
        if (!empty($d['parent']) && !collect($this->writer->categories())->contains('term_id', (int) $d['parent'])) throw \Illuminate\Validation\ValidationException::withMessages(['parent' => 'That parent category does not exist.']);
        return $d;
    }

    public function destroy(string $slug)
    {
        $c = $this->writer->category($slug); abort_unless($c, 404);
        if (!$this->writer->deleteCategory($slug)) return back()->with('admin_err', 'Move or delete the ' . $c['count'] . ' products in this category first.');
        AdminLog::record('category.delete', 'Deleted category ' . $c['name'], 'category', $slug);
        return redirect('/admin/categories')->with('admin_ok', 'Category deleted.');
    }
}
