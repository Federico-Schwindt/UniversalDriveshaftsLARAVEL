<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private ProductService $products, private CartService $cart)
    {
    }

    // ---- SEARCH -------------------------------------------------------

    public function search(Request $r)
    {
        $items = $this->products->search(mb_substr(is_string($q = $r->query('q')) ? $q : '', 0, 80), 8);
        $items = array_map(function ($p) {
            $p['price'] = $this->wholesalePrice($p['price']);
            return $p;
        }, $items);
        return response()->json(['results' => $items]);
    }

    // ---- QUICK ORDER --------------------------------------------------

    public function quickOrderPage()
    {
        return view('quick-order');
    }

    public function quickResolve(Request $r)
    {
        $p = $this->products->bySku(is_scalar($sku = $r->input('sku')) ? (string) $sku : '');
        if (!$p) return response()->json(['found' => false]);
        $p['price'] = $this->wholesalePrice($p['price']);
        return response()->json(['found' => true, 'product' => $p]);
    }

    public function quickAdd(Request $r)
    {
        $lines = json_decode(is_string($raw = $r->input('lines', '[]')) ? $raw : '[]', true);
        $added = 0; $skipped = [];
        foreach (array_slice(is_array($lines) ? $lines : [], 0, 100) as $l) {
            if (!is_array($l) || !is_scalar($l['sku'] ?? null)) continue;
            $p = $this->products->bySku((string) $l['sku']);
            if (!$p) continue;
            // a $0.00 price is "call for price", never a free part
            if ($p['price'] <= 0) { $skipped[] = $p['sku']; continue; }
            $qty = min(999, max(1, (int) ($l['qty'] ?? 1)));
            $this->cart->add([
                'type' => 'product', 'wp_product_id' => $p['id'], 'name' => $p['name'],
                'sku' => $p['sku'], 'image' => $p['image'], 'price' => $this->wholesalePrice($p['price']), 'qty' => $qty,
            ]);
            $added++;
        }
        return response()->json(['success' => $added > 0, 'added' => $added, 'skipped' => $skipped, 'count' => $this->cart->count(),
            'error' => $added ? null : ($skipped ? __('This part is priced on request. Call (813) 524-6491 for a quote.') : __('Part not found.'))], $added ? 200 : 422);
    }

    // ---- WHOLESALE (B2B) ----------------------------------------------

    /** Apply the logged-in user's approved wholesale discount to a catalog price. */
    private function wholesalePrice(float $price): float
    {
        $u = Auth::user();
        if ($u && $u->wholesale_status === 'approved' && $u->wholesale_discount > 0) {
            return round($price * (1 - $u->wholesale_discount / 100), 2);
        }
        return $price;
    }
}
