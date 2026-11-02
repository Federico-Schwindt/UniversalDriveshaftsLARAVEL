<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Illuminate\Http\Request;

class DriveshaftFinderController extends Controller
{
    use Concerns\ScalarInput;

    public function __construct(private DriveshaftFinder $finder) {}

    public function page(Request $r)
    {
        $logos = array_map(fn ($f) => basename($f, '.png'), glob(public_path('img/makes/*.png')) ?: []);
        return view('finder', ['state' => $this->finder->step($r->query()), 'labels' => DriveshaftFinder::LABELS, 'logos' => $logos]);
    }

    /** Part page. The public URL carries the U-prefixed SKU; the bare supplier number redirects to it. */
    public function part(string $pn)
    {
        $bare = DriveshaftFinder::pn($pn);
        abort_if(!$this->finder->partPage($bare), 404);
        if ($pn !== DriveshaftFinder::sku($bare)) return redirect('/catalog/driveshafts/part/' . rawurlencode(DriveshaftFinder::sku($bare)), 301);
        $part = $this->finder->partPage($bare);
        abort_if(!$part, 404);
        $logos = array_map(fn ($f) => basename($f, '.png'), glob(public_path('img/makes/*.png')) ?: []);
        return view('finder-part', ['p' => $part, 'logos' => $logos]);
    }

    /** /shop/complete-driveshafts/by-vehicle: every finder part as a catalog table. */
    public function all(Request $r, CatalogService $catalog = null)
    {
        $catalog ??= app(CatalogService::class);
        $q = mb_substr(trim($this->qs($r, 'q')), 0, 80);
        $sort = in_array($r->query('sort'), ['part_asc', 'part_desc', 'price_asc', 'price_desc'], true) ? $r->query('sort') : 'part_asc';
        $vehicle = ['make' => trim($this->qs($r, 'make')), 'year' => (int) $this->qs($r, 'year', '0'), 'model' => trim($this->qs($r, 'model'))];
        if ($vehicle['make'] === '') $vehicle = ['make' => '', 'year' => 0, 'model' => ''];
        if (!$vehicle['year']) $vehicle['model'] = '';
        $data = $this->finder->listing($q, (int) $this->qs($r, 'page', '1'), 60, $sort, $vehicle);
        $grp = $catalog->group('complete-driveshafts');
        $options = $this->finder->options($vehicle['make'], $vehicle['year']);
        return view('finder-all', ['data' => $data, 'q' => $q, 'sort' => $sort, 'vehicle' => $vehicle, 'options' => $options,
            'group' => ['slug' => 'complete-driveshafts', 'label' => $grp['label'] ?? 'Complete Driveshafts'],
            'cat' => $catalog->vehicleDriveshaftsCategory(), 'siblings' => $grp['subcategories'] ?? []]);
    }

    public function makes() { return response()->json(['makes' => $this->finder->makes()]); }

    /** Cascading options for the category filters: ?make= -> years, ?make=&year= -> models. */
    public function options(Request $r) { return response()->json($this->finder->options(mb_substr(trim($this->qs($r, 'make')), 0, 60), (int) $this->qs($r, 'year', '0'))); }

    public function step(Request $r) { return response()->json($this->finder->step($r->query())); }

    public function search(Request $r) { return response()->json(['results' => $this->finder->search(mb_substr(trim($this->qs($r, 'q')), 0, 80))]); }

    /** Price is always re-read server side; the client only names the part. */
    public function add(Request $r, CartService $cart)
    {
        $part = $this->finder->publicPart(is_scalar($pn = $r->input('part_number')) ? (string) $pn : '');
        if (!$part || !$part['can_buy']) {
            return response()->json(['success' => false, 'message' => 'This driveshaft is priced on request. Call (813) 524-6491.'], 422);
        }
        $vehicle = mb_substr(trim(is_scalar($v = $r->input('vehicle')) ? (string) $v : ''), 0, 160);
        $cart->add([
            'type' => 'driveshaft', 'wp_product_id' => null,
            'name' => trim(($part['description'] ?: 'Complete driveshaft') . ' ' . $part['sku']) . ($vehicle ? " · fits $vehicle" : ''),
            'sku' => $part['sku'], 'image' => $part['image'] ?: '', 'price' => $part['price'],
            'qty' => max(1, min(20, (int) $r->input('qty', 1))),
            'meta' => array_filter(['part_number' => $part['part_number'], 'vehicle' => $vehicle, 'weight_lb' => $part['weight'],
                'core_price' => $part['core_price'], 'c_brg' => implode(', ', $part['c_brg'])]),
        ]);
        return response()->json(['success' => true, 'count' => $cart->count()]);
    }
}
