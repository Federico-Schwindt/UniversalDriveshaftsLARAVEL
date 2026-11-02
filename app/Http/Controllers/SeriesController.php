<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Support\Facades\Auth;

/**
 * U-joint series hub pages: /series and /series/{series} (1310, 1350, 3R, SPL170…).
 * Everything is read through CatalogService's cached queries.
 */
class SeriesController extends Controller
{
    public function __construct(private CatalogService $catalog)
    {
    }

    public function index()
    {
        $list = $this->catalog->seriesList();
        return view('series.index', ['series' => $list, 'total' => array_sum(array_column($list, 'count'))]);
    }

    public function show(string $series)
    {
        $slug = strtolower($series);
        $s = $this->catalog->seriesTerm($slug);
        if (!$s) {
            // "/series/SPL 170" or a name typed with different casing -> canonical slug
            $byName = collect($this->catalog->seriesList())->first(fn ($x) => strcasecmp(str_replace(' ', '', $x['name']), str_replace(' ', '', $series)) === 0);
            if ($byName) return redirect('/series/' . $byName['slug'], 301);
            abort(404);
        }
        if ($series !== $slug) return redirect('/series/' . $slug, 301);

        $cats = $this->catalog->seriesByCategory($slug, 12);
        foreach ($cats as &$c) {
            foreach ($c['items'] as &$it) { $it['price'] = $this->price($it['price']); $it['regular'] = $this->price($it['regular']); }
            unset($it);
        }
        unset($c);
        $all = $this->catalog->seriesList();
        $sameCap = $s['cap'] ? array_values(array_filter($all, fn ($x) => $x['slug'] !== $slug && $x['cap'] === $s['cap'])) : [];

        return view('series.show', [
            's' => $s,
            'cats' => $cats,
            'total' => array_sum(array_column($cats, 'total')),
            'all' => $all,
            'sameCap' => $sameCap,
        ]);
    }

    private function price(float $base): float
    {
        $u = Auth::user();
        if ($u && ($u->wholesale_status ?? '') === 'approved' && ($u->wholesale_discount ?? 0) > 0) {
            return round($base * (1 - ((float) $u->wholesale_discount) / 100), 2);
        }
        return round($base, 2);
    }
}
