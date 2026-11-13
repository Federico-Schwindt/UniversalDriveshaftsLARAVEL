<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Support\ResourceGuides;

/** The old store's measuring guides at their old /resources addresses (App\Support\ResourceGuides). */
class ResourcesController extends Controller
{
    public function index()
    {
        return view('resources.index', ['guides' => ResourceGuides::top(), 'all' => ResourceGuides::all()]);
    }

    public function show(string $path, CatalogService $catalog)
    {
        $g = ResourceGuides::find($path);
        if (!$g) {
            // the old designer chooser is the designer here; a guide linked under the wrong folder (the old site's own
            // buttons did that) goes to the guide; anything else the old library had goes to the guide list
            $last = basename(trim($path, '/'));
            if ($last === 'driveshaft-designer-choose-type') return redirect('/driveshaft-designer', 301);
            foreach (ResourceGuides::all() as $p) if (basename($p['path']) === $last) return redirect(ResourceGuides::url($p['path']), 301);
            return redirect('/resources', 301);
        }
        $parent = $g['parent'] ? ResourceGuides::find($g['parent']) : null;
        $chart = null;
        if (collect($g['blocks'])->contains('type', 'bearing-chart')) {
            // every center support bearing in the catalog, live, by the measurements the old chart listed
            $chart = $catalog->products('center-support-bearing', [], 1, 1000, 'part_asc', '', ['pa_bearing-id', 'pa_center-bearing-to-base', 'pa_ctr-to-ctr-of-mount-holes', 'pa_application'])['items'];
        }
        return view('resources.show', [
            'g' => $g, 'parent' => $parent,
            'siblings' => $parent ? ResourceGuides::children($parent['path']) : ResourceGuides::children($g['path']),
            'top' => ResourceGuides::top(), 'chart' => $chart,
        ]);
    }
}
