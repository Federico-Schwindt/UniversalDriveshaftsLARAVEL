<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use App\Services\EasyPostService;
use App\Services\PackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commerce features (routes/commerce.php):
 *  - /account/buy-again: every product the signed-in customer has bought, from this shop's
 *    orders AND the historical WooCommerce orders (read-only "wp" connection), matched by e-mail.
 *  - /shop/estimate: the product-page shipping estimator (live carrier rates for one line).
 */
class CommerceController extends Controller
{
    /** Local order statuses that are not a purchase any more. */
    private const DEAD_STATUSES = ['cancelled', 'failed', 'refunded'];

    /** Middleware a background lookup runs without: no session is read or written (see routes/commerce.php). */
    public const STATELESS = [\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\TrackCustomerActivity::class];

    public function __construct(private CatalogService $catalog, private CartService $cart, private DriveshaftFinder $finder)
    {
    }

    /* ---------- Buy again ---------- */

    public function buyAgain()
    {
        $u = Auth::user();
        [$items, $orders] = $this->history($u);
        return view('account.buy-again', ['user' => $u, 'items' => $items, 'pastOrders' => $orders]);
    }

    /** POST /account/buy-again/reorder {order: l{id}|w{id}} — every reorderable line of that order into the cart. */
    public function reorder(Request $r)
    {
        $u = Auth::user();
        $key = (string) $r->input('order', '');
        [$items, $orders] = $this->history($u);
        $order = collect($orders)->firstWhere('key', $key);
        if (!$order) return response()->json(['success' => false, 'error' => __('Order not found.')], 404);

        $added = 0; $skipped = 0;
        foreach ($order['lines'] as $line) {
            if ($line['kind'] === 'product') {
                $p = $this->catalog->productById((int) $line['id']);
                if (!$p || (float) $p['price'] <= 0) { $skipped++; continue; } // $0 = call for price, never free
                $this->cart->add(['type' => 'product', 'wp_product_id' => $p['id'], 'name' => $p['name'], 'sku' => $p['sku'],
                    'image' => $p['image'], 'price' => $this->price((float) $p['price']), 'qty' => max(1, (int) $line['qty'])]);
                $added++;
            } else {
                $part = $this->finder->publicPart($line['pn']);
                if (!$part || !$part['can_buy']) { $skipped++; continue; }
                $this->cart->add($this->driveshaftLine($part, max(1, min(20, (int) $line['qty']))));
                $added++;
            }
        }
        return response()->json(['success' => $added > 0, 'added' => $added, 'skipped' => $skipped, 'count' => $this->cart->count(),
            'error' => $added ? null : __('None of the parts on that order can be reordered online. Call (813) 524-6491.')], $added ? 200 : 422);
    }

    /** The cart line for a vehicle-finder driveshaft (same shape DriveshaftFinderController::add builds). */
    private function driveshaftLine(array $part, int $qty): array
    {
        return [
            'type' => 'driveshaft', 'wp_product_id' => null,
            'name' => trim(($part['description'] ?: 'Complete driveshaft') . ' ' . $part['sku']),
            'sku' => $part['sku'], 'image' => $part['image'] ?: '', 'price' => $part['price'], 'qty' => $qty,
            'meta' => array_filter(['part_number' => $part['part_number'], 'weight_lb' => $part['weight'],
                'core_price' => $part['core_price'], 'c_brg' => implode(', ', $part['c_brg'])]),
        ];
    }

    /**
     * Distinct products the customer has bought (newest first) and the orders they came from.
     * @return array{0: array, 1: array}  [items, orders]
     */
    private function history($u): array
    {
        $email = strtolower(trim((string) $u->email));
        $lines = [];    // line key => ['kind', 'id'|'pn', 'last', 'qty', 'times']
        $orders = [];   // order key => ['key','number','date','count','total','source','url','lines'=>[]]

        // this shop's orders
        $local = DB::table('orders')
            ->where(fn ($q) => $q->where('user_id', $u->id)->when(!empty($u->email_verified_at), fn ($w) => $w->orWhereRaw('LOWER(email) = ?', [$email])))
            ->whereNotIn('status', self::DEAD_STATUSES)->orderByDesc('id')->limit(200)
            ->get(['id', 'order_number', 'created_at', 'total', 'email']);
        if ($local->count()) {
            $byOrder = DB::table('order_items')->whereIn('order_id', $local->pluck('id'))->get()->groupBy('order_id');
            foreach ($local as $o) {
                $ls = [];
                foreach ($byOrder[$o->id] ?? [] as $it) {
                    $line = $this->lineOf((string) $it->type, (int) $it->wp_product_id, (string) $it->sku, (int) $it->qty);
                    if (!$line) continue;
                    $ls[] = $line;
                    $this->tally($lines, $line, (string) $o->created_at);
                }
                if ($ls) $orders['l' . $o->id] = ['key' => 'l' . $o->id, 'number' => $o->order_number, 'date' => (string) $o->created_at,
                    'count' => count($ls), 'total' => (float) $o->total, 'source' => 'local', 'url' => \App\Support\OrderEvents::statusUrl($o, false), 'lines' => $ls];
            }
        }

        // historical WooCommerce orders (read-only), matched through the customer lookup e-mail
        try {
            $wp = DB::connection('wp');
            $cids = empty($u->email_verified_at) ? collect() : $wp->table('wp_wc_customer_lookup')->whereRaw('LOWER(email) = ?', [$email])->pluck('customer_id');
            if ($cids->count()) {
                $wo = $wp->table('wp_wc_order_stats')->whereIn('customer_id', $cids)->whereIn('status', CatalogService::WC_PAID)
                    ->orderByDesc('date_created')->limit(200)->get(['order_id', 'date_created', 'total_sales']);
                if ($wo->count()) {
                    $pl = $wp->table('wp_wc_order_product_lookup')->whereIn('order_id', $wo->pluck('order_id'))
                        ->get(['order_id', 'product_id', 'product_qty'])->groupBy('order_id');
                    foreach ($wo as $o) {
                        $ls = [];
                        foreach ($pl[$o->order_id] ?? [] as $it) {
                            if ((int) $it->product_id <= 0) continue;
                            $line = ['kind' => 'product', 'key' => 'p' . (int) $it->product_id, 'id' => (int) $it->product_id, 'qty' => max(1, (int) $it->product_qty)];
                            $ls[] = $line;
                            $this->tally($lines, $line, (string) $o->date_created);
                        }
                        if ($ls) $orders['w' . $o->order_id] = ['key' => 'w' . $o->order_id, 'number' => '#' . $o->order_id, 'date' => (string) $o->date_created,
                            'count' => count($ls), 'total' => (float) $o->total_sales, 'source' => 'wp', 'url' => null, 'lines' => $ls];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('buy-again: wp history unavailable: ' . $e->getMessage());
        }

        // hydrate against today's catalog
        $pids = array_map(fn ($l) => $l['id'], array_filter($lines, fn ($l) => $l['kind'] === 'product'));
        $products = [];
        foreach ($this->catalog->productsByIds(array_values($pids)) as $p) $products[$p['id']] = $p;

        $items = [];
        foreach ($lines as $l) {
            if ($l['kind'] === 'product') {
                $p = $products[$l['id']] ?? null;
                if (!$p) continue;   // not in the catalog any more
                $items[] = ['kind' => 'product', 'id' => $p['id'], 'pn' => '', 'sku' => $p['sku'], 'name' => $this->catalog->cleanName($p), 'image' => $p['image'],
                    'price' => $this->price((float) $p['price']), 'stock_status' => $p['stock_status'], 'stock' => $p['stock'],
                    'url' => '/parts/' . rawurlencode($p['sku']), 'last' => $l['last'], 'qty' => $l['qty'], 'times' => $l['times']];
            } else {
                $part = $this->finder->publicPart($l['pn']);
                if (!$part) continue;
                $items[] = ['kind' => 'driveshaft', 'id' => null, 'pn' => $part['part_number'], 'sku' => $part['sku'], 'name' => trim((string) ($part['description'] ?: 'Complete driveshaft')),
                    'image' => $part['image'] ?: '', 'price' => $part['can_buy'] ? (float) $part['price'] : null, 'stock_status' => $part['can_buy'] ? 'instock' : 'call', 'stock' => null,
                    'url' => $part['url'], 'last' => $l['last'], 'qty' => $l['qty'], 'times' => $l['times']];
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['last'], $a['last']));
        $orders = array_values($orders);
        usort($orders, fn ($a, $b) => strcmp($b['date'], $a['date']));
        return [$items, $orders];
    }

    /** A reorderable line from a local order_items row: catalog products and vehicle-finder driveshafts (sku U…). */
    private function lineOf(string $type, int $pid, string $sku, int $qty): ?array
    {
        $qty = max(1, $qty);
        if ($type === 'product' && $pid > 0) return ['kind' => 'product', 'key' => 'p' . $pid, 'id' => $pid, 'qty' => $qty];
        if ($type === 'driveshaft' && preg_match('/^U\d/i', $sku)) {
            $pn = DriveshaftFinder::pn($sku);
            return ['kind' => 'driveshaft', 'key' => 'd' . $pn, 'pn' => $pn, 'qty' => $qty];
        }
        return null;   // built-to-order driveshafts are configured in the designer, not reordered as-is
    }

    /** Fold one order line into the distinct-product list (last date, qty on that last order, times ordered). */
    private function tally(array &$lines, array $line, string $date): void
    {
        $k = $line['key'];
        if (!isset($lines[$k])) {
            $lines[$k] = ['kind' => $line['kind'], 'id' => $line['id'] ?? null, 'pn' => $line['pn'] ?? null, 'last' => $date, 'qty' => $line['qty'], 'times' => 0];
        } elseif (strcmp($date, $lines[$k]['last']) > 0) {
            $lines[$k]['last'] = $date; $lines[$k]['qty'] = $line['qty'];
        }
        $lines[$k]['times']++;
    }

    /* ---------- Shipping estimator ---------- */

    /** POST /shop/estimate {id, qty, zip} → up to 3 cheapest carrier options for that one line + free-shipping eligibility. */
    public function estimate(Request $r)
    {
        $id = (int) $r->input('id');
        $qty = max(1, min(999, (int) $r->input('qty', 1)));
        $zip = preg_replace('/\D/', '', (string) $r->input('zip', ''));
        if (strlen($zip) !== 5) return response()->json(['success' => false, 'error' => 'Enter a 5-digit US ZIP code.'], 422);
        $p = $this->catalog->productById($id);
        if (!$p) return response()->json(['success' => false, 'error' => 'Product not found.'], 404);

        $price = $this->price((float) $p['price']);
        $key = "est:$id:$qty:$zip";
        $quote = Cache::remember($key, 3600, function () use ($id, $qty, $zip, $price) {
            $summary = app(PackingService::class)->packageSummary([['type' => 'product', 'wp_product_id' => $id, 'qty' => $qty, 'price' => $price]]);
            $rates = [];
            try {
                $rates = app(EasyPostService::class)->ratesForPack($summary, '', $zip, 'US');
            } catch (\Throwable $e) {
                Log::warning('estimate: ' . $e->getMessage());
            }
            $rates = array_slice(array_map(fn ($x) => [
                'label' => preg_replace('/ · \d+ packages$/u', '', (string) $x['label']),
                'cost' => round((float) $x['cost'], 2),
                'packages' => (int) ($x['packages'] ?? $summary['packages']),
            ], $rates), 0, 3);
            return ['rates' => $rates, 'packages' => (int) $summary['packages'], 'weight_lb' => (float) $summary['weight_lb'], 'excluded' => $summary['excluded_value'] > 0];
        });
        if (!$quote['rates']) {
            Cache::forget($key);   // never cache a carrier outage
            return response()->json(['success' => false, 'error' => 'Could not get a rate right now. Call (813) 524-6491 and we will quote it.']);
        }
        $lineTotal = round($price * $qty, 2);
        $min = CartService::freeMin();
        $eligible = !$quote['excluded'] && $lineTotal >= $min;
        return response()->json([
            'success' => true,
            'rates' => $quote['rates'],
            'packages' => $quote['packages'],
            'weight_lb' => $quote['weight_lb'],
            'free' => ['eligible' => $eligible, 'excluded' => $quote['excluded'], 'min' => $min, 'line_total' => $lineTotal,
                       'remaining' => $quote['excluded'] ? null : max(0, round($min - $lineTotal, 2))],
            'pickup' => 'Local pickup in Florida — free',
        ]);
    }

    /**
     * GET /api/arrival?zip=…&id=… (one part) or &items=id:qty,id:qty (the cart's parts, written into the cart page) —
     * when they would arrive. No session: the page supplies the ZIP (the visitor's last one, or the account's saved one).
     */
    public function arrival(Request $r)
    {
        $zip = substr(preg_replace('/\D/', '', (string) $r->query('zip', '')), 0, 5);
        if (strlen($zip) !== 5) return response()->json(['success' => false, 'need_zip' => true]);
        $lines = $r->query('id') ? [(int) $r->query('id') . ':1'] : array_slice(explode(',', (string) $r->query('items', '')), 0, 40);
        $items = [];
        foreach ($lines as $l) {
            [$id, $qty] = array_map('intval', explode(':', $l) + [1 => 1]);
            $p = $id > 0 ? $this->catalog->productById($id) : null;
            if ($p && (float) $p['price'] > 0) $items[] = ['type' => 'product', 'wp_product_id' => $id, 'qty' => max(1, min(999, $qty)), 'price' => (float) $p['price']];
        }
        if (!$items) return response()->json(['success' => false, 'zip' => $zip], 404);
        $a = \App\Support\Arrival::forItems($items, $zip);
        if (!$a) return response()->json(['success' => false, 'zip' => $zip]);
        return response()->json(['success' => true, 'zip' => $zip, 'date' => \App\Support\Arrival::format($a['date']), 'iso' => $a['date']->toDateString(),
            'service' => $a['service'], 'days' => $a['days']]);
    }

    /**
     * GET /api/zip-near?lat=…&lng=… — the US ZIP at the visitor's location (the browser asked them first), for the
     * delivery date. US Census reverse geocoder (free, no key): ZIP Code Tabulation Areas, layer 84. Coordinates are
     * rounded to ~100 m and nothing is stored but the cached ZIP.
     */
    public function zipNear(Request $r)
    {
        $lat = round((float) $r->query('lat'), 3); $lng = round((float) $r->query('lng'), 3);
        if ($lat < 17 || $lat > 72 || $lng < -180 || $lng > -64) return response()->json(['success' => false, 'outside' => true]);
        $zip = Cache::get($key = "zipnear:$lat:$lng");
        if (!$zip) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(6)->get('https://geocoding.geo.census.gov/geocoder/geographies/coordinates',
                    ['x' => $lng, 'y' => $lat, 'benchmark' => 'Public_AR_Current', 'vintage' => 'Census2020_Current', 'layers' => '84', 'format' => 'json']);
                $area = collect($resp->json('result.geographies') ?: [])->first(fn ($v, $k) => stripos((string) $k, 'zip') !== false)[0] ?? [];
                $zip = (string) ($area['ZCTA5'] ?? $area['GEOID'] ?? $area['BASENAME'] ?? '');
            } catch (\Throwable $e) {
                Log::warning('zip-near: ' . $e->getMessage());
                $zip = '';
            }
            if (!preg_match('/^\d{5}$/', $zip)) return response()->json(['success' => false]);
            Cache::put($key, $zip, now()->addDays(30));
        }
        return response()->json(['success' => true, 'zip' => $zip]);
    }

    /* helpers */

    /** Wholesale pricing (same rule as CatalogController::price). */
    private function price(float $base): float
    {
        $u = Auth::user();
        if ($u && ($u->wholesale_status ?? '') === 'approved' && ($u->wholesale_discount ?? 0) > 0) {
            return round($base * (1 - ((float) $u->wholesale_discount) / 100), 2);
        }
        return round($base, 2);
    }
}
