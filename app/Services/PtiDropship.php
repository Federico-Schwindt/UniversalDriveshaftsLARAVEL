<?php

namespace App\Services;

use App\Support\OrderEvents;
use Illuminate\Support\Facades\DB;

/**
 * "Dropship from PTI" on the admin order page — always started by a person, never automatic
 * (owner, 2026-10-24: "we may have that part in our warehouse").
 *
 *   prepare(): put the chosen lines in PTI's (empty) cart, add and attach the customer's address as a
 *              dropship address, and read back PTI's shipping rates and totals. Nothing is ordered.
 *   place():   set shipping, card, PO (our order number) and blind-ship instructions, then PTI's own
 *              "Place Order" call (PUT my/orders/order). The PTI order id is kept on the dropship row.
 *   cancel():  take our lines back out of PTI's cart.
 *   syncTracking(): PTI's tracking number onto our order once they ship (pti:dropship-tracking).
 */
class PtiDropship
{
    public const STORES = ['100' => 'PTI picks (drop ship, usually ships in 2 business days)', '12' => 'Reno, Nevada', '2' => 'Virginia Beach, Virginia'];
    private const STORE_NAMES = ['12' => 'NV', '2' => 'VA', '100' => 'Drop ship'];

    /**
     * Our product attribute => PTI spec key(s), per PTI component type. Used to prove a linked PTI part is the
     * same physical part as ours before it is dropshipped: our SKUs can differ from PTI's (a trailing "U", another
     * prefix), so a matching number alone is never trusted (owner, 2026-10-24).
     */
    private const ATTR_MAP = [
        'transmission-yokes' => ['pa_series' => 'series', 'pa_splines-count-dia' => ['spline_count', 'spline_diameter'], 'pa_hub-dia' => 'hub_diameter',
            'pa_center-u-joint-to-end' => 'centerline_to_end', 'pa_counter-bore-dia' => 'counterbore_diameter'],
        'center-bearings' => ['pa_bearing-id' => 'bearing_id', 'pa_ctr-to-ctr-of-mount-holes' => 'center_to_center_of_mount_holes', 'pa_center-bearing-to-base' => 'centerline_to_base'],
        'flange-yokes' => ['pa_series' => 'series', 'pa_bolt-circle-dia' => 'bolt_circle_diameter', 'pa_bolt-holes' => 'bolt_hole_count', 'pa_bolt-hole-dia' => 'bolt_hole_diameter',
            'pa_cj-to-flat' => 'centerline_to_face', 'pa_pilot-dia' => 'pilot_diameter'],
        'tri-and-quad-prongs' => ['pa_bolt-circle-dia' => 'bolt_circle_diameter', 'pa_bolt-hole-dia' => 'bolt_hole_diameter', 'pa_nose-bushing-dia' => 'nose_bushing_diameter',
            'pa_butt-dia' => 'butt_diameter', 'pa_face-to-weld' => 'face_to_weld', 'pa_tube-dia-wall' => ['tube_diameter', 'tube_wall']],
        'cvj' => ['pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_bolt-hole-count' => 'bolt_hole_count', 'pa_bolt-hole-dia' => 'bolt_hole_diameter',
            'pa_outer-dia' => 'outer_diameter', 'pa_outer-race-width' => 'outer_race_width', 'pa_inner-race-width' => 'inner_race_width'],
        'cvj-kits' => ['pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_bolt-hole-count' => 'bolt_hole_count', 'pa_bolt-hole-dia' => 'bolt_hole_diameter',
            'pa_outer-dia' => 'outer_diameter', 'pa_outer-race-width' => 'outer_race_width', 'pa_inner-race-width' => 'inner_race_width'],
        'cv-heads' => ['pa_butt-dia' => 'butt_diameter', 'pa_pilot-dia' => 'pilot_diameter', 'pa_bolt-circle-dia' => 'bolt_circle_diameter', 'pa_bolt-hole-dia' => 'bolt_hole_diameter',
            'pa_end-to-weld' => 'end_to_weld', 'pa_tube-dia-wall' => ['tube_diameter', 'tube_wall']],
        'ujoints' => ['pa_series' => 'series', 'pa_style' => 'style', 'pa_cap-dia-1' => 'cap_1_diameter', 'pa_width-1' => 'stack_1', 'pa_lube' => 'lube'],
        'weld-yokes' => ['pa_series' => 'series', 'pa_material' => 'material', 'pa_butt-dia' => 'butt_diameter', 'pa_tube-dia-wall' => ['tube_diameter', 'tube_wall'], 'pa_center-u-j-to-weld' => 'centerline_to_weld'],
        'end-yokes' => ['pa_series' => 'series', 'pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_hub-dia' => 'hub_diameter', 'pa_cj-to-end' => 'centerline_to_end', 'pa_through-hole' => 'thru_hole'],
        'slip-yokes' => ['pa_series' => 'series', 'pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_center-u-joint-to-end' => 'centerline_to_end'],
        'splined-stub-shafts' => ['pa_tube-dia' => 'tube_diameter', 'pa_tube-wall' => 'tube_wall', 'pa_butt-dia' => 'butt_diameter', 'pa_end-to-weld' => 'end_to_weld',
            'pa_splines-count-dia' => ['spline_count', 'spline_diameter'], 'pa_bearing-dia' => 'bearing_diameter', 'pa_splines-length' => 'spline_length'],
        'rubber-flex-discs' => ['pa_bolt-circle-dia' => 'bolt_circle_diameter', 'pa_bolt-hole-count' => 'bolt_hole_count', 'pa_bolt-hole-dia' => 'bolt_hole_diameter', 'pa_disc-width' => 'disc_width', 'pa_pilot-dia' => 'bolt_hole_pilot_od'],
        'yoke-shafts' => ['pa_series' => 'series', 'pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_splines-length' => 'spline_length', 'pa_c-l-to-end' => 'centerline_to_end'],
        'pto-end-yokes' => ['pa_series' => 'series', 'pa_cj-to-end' => 'centerline_to_end', 'pa_hub-dia' => 'hub_diameter', 'pa_through-hole' => 'thru_hole'],
        'cv-flange-yokes' => ['pa_series' => 'series', 'pa_bolt-circle-dia' => 'bolt_circle_diameter', 'pa_bolt-hole-dia' => 'bolt_hole_diameter', 'pa_pilot-dia' => 'pilot_diameter',
            'pa_c-l-to-face' => 'centerline_to_face', 'pa_ball-stud-dia' => 'ball_stud_diameter'],
        'splined-sleeves' => ['pa_material' => 'material', 'pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_butt-dia' => 'butt_diameter', 'pa_end-to-weld' => 'end_to_weld'],
        'companion-flanges' => ['pa_hub-dia' => 'hub_diameter', 'pa_splines-count' => 'spline_count', 'pa_splines-dia' => 'spline_diameter', 'pa_bolt-circle-dia' => 'bolt_circle_diameter',
            'pa_through-hole' => 'thru_hole', 'pa_pilot-dia' => 'pilot_diameter', 'pa_face-to-end' => 'face_to_end', 'pa_bolt-holes' => 'bolt_holes'],
        'ball-stud-yokes' => ['pa_series' => 'series', 'pa_butt-dia' => 'butt_diameter', 'pa_tube-dia-wall' => ['tube_diameter', 'tube_wall'], 'pa_center-u-j-to-weld' => 'centerline_to_weld'],
    ];

    public function __construct(private PtiClient $pti) {}

    /**
     * Compare our product's measurements with a PTI part's specs.
     * @return array{status:string, checked:int, diffs:array, note?:string}  status ok | mismatch | unverified
     */
    public function verify(int $wpProductId, string $ptiType, array $specs): array
    {
        $map = self::ATTR_MAP[$ptiType] ?? null;
        if (!$map) return ['status' => 'unverified', 'checked' => 0, 'diffs' => [], 'note' => str_starts_with($ptiType, 'small:') ? 'Small part: PTI lists no measurements to compare.' : 'No measurement map for this PTI category.'];
        $attrs = app(\App\Services\Admin\CatalogWriter::class)->product($wpProductId)['attrs'] ?? [];
        $checked = 0; $diffs = []; $compared = [];
        foreach ($map as $tax => $keys) {
            $ours = trim(implode(' ', (array) ($attrs[$tax] ?? [])));
            $theirs = trim(implode(' ', array_map(fn ($k) => (string) ($specs[$k] ?? ''), (array) $keys)));
            if (self::blank($ours) || self::blank($theirs)) continue;
            $checked++;
            $label = ucwords(str_replace(['pa_', '-'], ['', ' '], $tax));
            $same = self::same($ours, $theirs, $tax === 'pa_series');
            $compared[] = ['label' => $label, 'ours' => $ours, 'pti' => $theirs, 'same' => $same];
            if (!$same) $diffs[] = ['label' => $label, 'ours' => $ours, 'pti' => $theirs];
        }
        if ($diffs) return ['status' => 'mismatch', 'checked' => $checked, 'diffs' => $diffs, 'compared' => $compared];
        if ($checked === 0) return ['status' => 'unverified', 'checked' => 0, 'diffs' => [], 'compared' => [], 'note' => 'Neither side has measurements to compare.'];
        return ['status' => 'ok', 'checked' => $checked, 'diffs' => [], 'compared' => $compared];
    }

    /** Our attribute tax => PTI spec key(s) for a PTI category (for fixing our values from PTI's). */
    public static function attrMap(string $ptiType): array
    {
        return self::ATTR_MAP[$ptiType] ?? [];
    }

    private static function blank(string $v): bool
    {
        $v = strtoupper(trim($v));
        return $v === '' || in_array($v, ['N/A', 'NA', '-', 'NONE', 'NULL'], true) || (bool) preg_match('/^0+(\.0+)?$/', $v);
    }

    /**
     * "31/32 X 1.378" vs "31/32 1.378", "96MM" vs "96mm", "1.700\"" vs "1.7": the same numbers (±0.01) in the same order.
     * Two typing slips in our catalogue are forgiven: a dropped "0." ("910" for 0.910) and, for a series, PTI's
     * extra suffix ("1551" vs "1551-20"). Any other difference counts.
     */
    private static function same(string $a, string $b, bool $series = false): bool
    {
        // PTI writes "COMBO" for a combination joint; we write both series ("1310X1330", "1350×1410", "SPL25/1330")
        if ($series && (strtoupper(trim($a)) === 'COMBO' || strtoupper(trim($b)) === 'COMBO')) {
            $other = strtoupper(trim($a)) === 'COMBO' ? $b : $a;
            return strtoupper(trim($other)) === 'COMBO' || (bool) preg_match('#\S+\s*[X×/]\s*\S+#iu', $other);
        }
        $num = function (string $v) { preg_match_all('/\d*\.?\d+/', $v, $m); return array_map('floatval', $m[0]); };
        // metric sizes are rounded to a tenth of a millimetre on one side or the other
        $tol = (stripos($a, 'mm') !== false || stripos($b, 'mm') !== false) ? 0.11 : 0.011;
        [$x, $y] = [$num($a), $num($b)];
        if (!$x || !$y) {
            $w = fn ($v) => ['YES' => 'Y', 'NO' => 'N'][strtoupper(preg_replace('/[^a-z0-9]/i', '', $v))] ?? strtoupper(preg_replace('/[^a-z0-9]/i', '', $v));
            return $w($a) === $w($b);
        }
        if (count($x) !== count($y)) {
            if (!$series) return false;
            $n = min(count($x), count($y)); $x = array_slice($x, 0, $n); $y = array_slice($y, 0, $n);
        }
        $eq = fn ($p, $q) => abs($p - $q) <= $tol
            || ($p >= 100 && $p < 1000 && $q < 1 && abs($p / 1000 - $q) <= 0.0011 && !str_contains($a, '.'))
            || ($q >= 100 && $q < 1000 && $p < 1 && abs($q / 1000 - $p) <= 0.0011 && !str_contains($b, '.'));
        foreach ($x as $i => $v) if (!$eq($v, $y[$i])) return false;
        return true;
    }

    /** Every order line, with its PTI part number when PTI sells it (catalog parts via pti_parts, complete driveshafts via ds_parts). */
    public function lines(object $order): array
    {
        $items = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get();
        $byProduct = DB::table('pti_parts')->whereIn('wp_product_id', $items->pluck('wp_product_id')->filter())->whereIn('match', ['sku', 'sku_u', 'created'])->get()->keyBy('wp_product_id');
        $tubing = DB::connection('wp')->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'product_cat')->whereIn('t.slug', ['tubing', 'custom-cut-tubing'])
            ->whereIn('tr.object_id', $items->pluck('wp_product_id')->filter())->pluck('tr.object_id')->flip();
        $out = [];
        foreach ($items as $it) {
            // tubing is cut in our shop: never sent to PTI (owner, 2026-10-24)
            if (($it->wp_product_id && (isset($tubing[$it->wp_product_id]) || app(ProductService::class)->soldByFoot((int) $it->wp_product_id))) || in_array($it->type, ['custom_tube', 'tube'], true)) {
                $out[] = ['item_id' => (int) $it->id, 'sku' => (string) $it->sku, 'name' => (string) $it->name, 'qty' => (int) $it->qty, 'pti_part' => null, 'pti_cost' => null,
                    'stock_total' => null, 'stock_by_store' => [], 'pti_type' => null, 'wp_product_id' => (int) $it->wp_product_id, 'verify' => null, 'tubing' => true];
                continue;
            }
            $pn = null; $cost = null; $stock = null; $by = []; $check = ['status' => 'unverified', 'checked' => 0, 'diffs' => []]; $type = null;
            if ($it->wp_product_id && isset($byProduct[$it->wp_product_id])) {
                $p = $byProduct[$it->wp_product_id];
                $pn = $p->part_number; $cost = $p->pti_price !== null ? (float) $p->pti_price : null; $type = $p->pti_type;
                $stock = $p->stock_total; $by = json_decode((string) $p->stock_by_store, true) ?: [];
                $check = $this->verify((int) $it->wp_product_id, (string) $p->pti_type, json_decode((string) $p->specs, true) ?: []);
                // nuts, washers, snap rings…: PTI lists no measurements, so only the exact same part number counts
                if (str_starts_with((string) $p->pti_type, 'small:') && $check['status'] === 'unverified' && $p->match === 'sku') $check = ['status' => 'ok', 'checked' => 0, 'diffs' => [], 'note' => 'Small part, exact same part number.'];
            } else {
                $meta = json_decode((string) $it->meta, true) ?: [];
                $dsPn = $meta['part_number'] ?? (str_starts_with((string) $it->sku, 'U') ? substr((string) $it->sku, 1) : null);
                if ($dsPn && ($d = DB::table('ds_parts')->where('part_number', $dsPn)->where('supplier', 'PTI')->first())) {
                    $pn = (string) $d->part_number; $cost = $d->price_cost !== null ? (float) $d->price_cost : null;
                    // our complete-driveshaft records ARE PTI's (same part number, only shown with a "U" in front)
                    $check = ['status' => 'ok', 'checked' => 0, 'diffs' => [], 'note' => 'PTI driveshaft record (same part number).'];
                }
            }
            $out[] = [
                'item_id' => (int) $it->id, 'sku' => (string) $it->sku, 'name' => (string) $it->name, 'qty' => (int) $it->qty,
                'pti_part' => $pn, 'pti_cost' => $cost, 'stock_total' => $stock, 'pti_type' => $type, 'wp_product_id' => $it->wp_product_id ? (int) $it->wp_product_id : null,
                'verify' => $pn ? $check : null,
                'stock_by_store' => collect($by)->mapWithKeys(fn ($q, $sid) => [self::STORE_NAMES[$sid] ?? "Store $sid" => (int) $q])->all(),
            ];
        }
        return $out;
    }

    public function shipTo(object $order): array
    {
        $name = trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
        $company = trim((string) ($order->shipping_company ?? '')) ?: ($order->ship_to_different ? '' : trim((string) ($order->billing_company ?? '')));
        return [
            'company' => mb_substr($company ? "$company - $name" : $name, 0, 60),
            'street' => (string) ($order->shipping_address_1 ?: $order->billing_address_1),
            'unit' => (string) ($order->shipping_address_2 ?: ''),
            'city' => (string) ($order->shipping_city ?: $order->billing_city),
            'st' => (string) ($order->shipping_state ?: $order->billing_state),
            'zip' => (string) ($order->shipping_postcode ?: $order->billing_postcode),
            'country' => (string) (($order->shipping_country ?: $order->billing_country) ?: 'US'),
            'phone' => preg_replace('/\D/', '', (string) ($order->shipping_phone ?? $order->phone ?? $order->billing_phone ?? '')) ?: '8135246491',
            'is_residential' => '1',
        ];
    }

    /**
     * Load PTI's cart and return what PTI would charge. $pick = [item_id => qty] of the lines to dropship.
     * @return array{ok:bool, error?:string, dropship_id?:int, rates?:array, cards?:array, cart?:array, address?:array}
     */
    public function prepare(object $order, array $pick, array $shipTo, bool $residential, int $userId, array $override = []): array
    {
        return $this->withCartLock(fn () => $this->prepareLocked($order, $pick, $shipTo, $residential, $userId, $override));
    }

    /**
     * PTI has one cart per account, shared with the nightly `pti:sync --boxes` and with the other dropship steps:
     * hold Cache::lock('pti-cart') so two of them never fill / read / empty it at the same time.
     */
    private function withCartLock(callable $fn): array
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('pti-cart', 3600);
        try { $lock->block(20); }
        catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) { return ['ok' => false, 'error' => 'The PTI cart is busy (nightly box measuring or another dropship). Try again in a few minutes.']; }
        try { return $fn(); } finally { $lock->release(); }
    }

    private function prepareLocked(object $order, array $pick, array $shipTo, bool $residential, int $userId, array $override = []): array
    {
        $lines = collect($this->lines($order))->keyBy('item_id');
        // the form names lines by id: ids it no longer finds mean the order was edited since the page was loaded
        if ($pick && !array_intersect_key($pick, $lines->all())) return ['ok' => false, 'error' => 'This order changed since the page was opened. Reload the page and try again.'];
        $send = []; $blocked = []; $noStock = []; $subs = [];
        foreach ($pick as $itemId => $qty) {
            $l = $lines[(int) $itemId] ?? null; $qty = (int) $qty;
            if (!$l || !$l['pti_part'] || $qty < 1) continue;
            $qty = min($qty, $l['qty']);
            // 1) same physical part? our SKU may differ from PTI's, so the measurements decide
            if (($l['verify']['status'] ?? '') !== 'ok' && empty($override[$l['item_id']])) {
                $why = ($l['verify']['status'] ?? '') === 'mismatch'
                    ? implode('; ', array_map(fn ($d) => "{$d['label']}: ours {$d['ours']} / PTI {$d['pti']}", $l['verify']['diffs']))
                    : ($l['verify']['note'] ?? 'no measurements to compare');
                $blocked[] = "{$l['sku']} → PTI {$l['pti_part']}: $why";
                continue;
            }
            // 2) live stock at PTI for that exact part, else a stocked alternate with the same measurements
            $part = $l['pti_part']; $cost = $l['pti_cost'];
            $st = $this->pti->stock($part);
            if ($st['total'] + $st['can_build'] < $qty) {
                $alt = $l['wp_product_id'] ? $this->alternate($part, (int) $l['wp_product_id'], $qty) : null;
                if (!$alt) { $noStock[] = ['sku' => $l['sku'], 'part' => $part, 'qty' => $qty, 'stock' => $st['total']]; continue; }
                $subs[] = "{$l['sku']}: PTI {$part} has {$st['total']} on hand, using alternate {$alt['part_number']} (same measurements, {$alt['stock']} on hand)";
                $part = $alt['part_number']; $cost = $alt['cost'] ?? $cost;
            }
            $send[] = ['item_id' => $l['item_id'], 'part_number' => $part, 'qty' => $qty, 'cost' => $cost, 'sku' => $l['sku']] + ($part !== $l['pti_part'] ? ['replaces' => $l['pti_part']] : []);
        }
        if ($noStock) $this->mailNoStock($order, $noStock);
        if ($blocked || $noStock) {
            $msg = [];
            if ($blocked) $msg[] = "Measurements don't prove it's the same part — check and tick \"send anyway\" if it is: " . implode(' | ', $blocked);
            if ($noStock) $msg[] = 'No stock at PTI and no alternate with the same measurements (the shop was e-mailed): ' . implode(', ', array_map(fn ($n) => "{$n['sku']} (PTI {$n['part']}, need {$n['qty']}, have {$n['stock']})", $noStock)) . '. Set those lines to 0 to dropship the rest.';
            return ['ok' => false, 'error' => implode("\n\n", $msg)];
        }
        if (!$send) return ['ok' => false, 'error' => 'Pick at least one line PTI sells.'];
        if (DB::table('pti_dropships')->where('order_id', $order->id)->where('status', 'placed')->exists() && !request()->boolean('again')) {
            return ['ok' => false, 'error' => 'This order already has a PTI dropship placed. Tick "send another" to place a second one.'];
        }
        // PTI's one cart must hold this dropship and nothing else: whatever is in it (a dropship left half way, a box
        // measurement, something added by hand) is taken out first, and noted on the order (owner, 2026-11-04: fully automatic)
        try { $cleared = $this->emptyCart(); }
        catch (\Throwable $e) { report($e); return ['ok' => false, 'error' => 'PTI did not answer while emptying the cart — nothing was added. Try again in a minute.']; }
        if ($cleared) OrderEvents::record($order, 'pti_cart', 'PTI cart emptied before the dropship', 'Removed: ' . implode(', ', $cleared));

        $added = [];
        try {
            foreach ($send as $s) { $this->pti->call('PUT', 'my/cart/item/', ['part_number' => $s['part_number'], 'quantity' => $s['qty']]); $added[] = $s['part_number']; }

            $shipTo['is_residential'] = $residential ? '1' : '0';
            $check = $this->pti->call('PUT', 'my/dropship_addresses/confirm_dropship_address', $shipTo);
            if (!empty($check['errors'])) throw new \RuntimeException('PTI could not validate the address: ' . json_encode($check['errors']));
            $addr = $check['validated_address'] ?? $shipTo;
            $addr['is_residential'] = $shipTo['is_residential'];   // the customer's answer beats the validator's guess
            $saved = $this->pti->call('PUT', 'my/dropship_addresses/dropship_address', $addr);
            $addrId = (int) ($saved['id'] ?? $saved['dropship_address']['id'] ?? $saved['dropship_address_id'] ?? 0);
            if (!$addrId) {   // some API versions answer with the list: find the one we just added
                $list = $this->pti->call('GET', 'my/dropship_addresses', ['search' => $addr['zip']])['dropship_addresses'] ?? [];
                foreach ($list as $a) if (strcasecmp(trim($a['street'] ?? ''), trim($addr['street'])) === 0) { $addrId = (int) $a['id']; break; }
            }
            if (!$addrId) throw new \RuntimeException('PTI saved the address but did not return its id.');
            $this->pti->call('POST', 'my/cart/cart', ['dropship_address_id' => $addrId]);

            $rates = [];
            foreach ((array) $this->pti->call('GET', 'my/cart/shipping_rates') as $r) {
                if (!is_array($r) || empty($r['shipping_method'])) continue;
                $rates[] = ['method' => (string) $r['shipping_method'], 'name' => (string) ($r['name'] ?? $r['shipping_method']), 'price' => (float) ($r['price'] ?? 0), 'date' => $r['delivery_date'] ?? null];
            }
            usort($rates, fn ($a, $b) => $a['price'] <=> $b['price']);
            $cart = $this->pti->call('GET', 'my/cart/cart', ['is_checkout' => 'true']);
        } catch (\Throwable $e) {
            foreach ($added as $pn) { try { $this->pti->call('DELETE', 'my/cart/item/', ['part_number' => $pn, 'is_checkout' => 'false']); } catch (\Throwable $x) {} }
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $id = DB::table('pti_dropships')->insertGetId([
            'order_id' => $order->id, 'status' => 'prepared', 'lines' => json_encode($send), 'ship_to' => json_encode($addr),
            'pti_address_id' => $addrId, 'pti_total' => (float) str_replace(['$', ','], '', (string) ($cart['total_price'] ?? 0)) ?: null,
            'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($cleared) $subs[] = 'The PTI cart was emptied first (it had ' . implode(', ', $cleared) . ').';
        return ['ok' => true, 'dropship_id' => $id, 'rates' => $rates, 'cards' => $this->cards(), 'address' => $addr, 'substitutions' => $subs,
            'cart' => ['items' => array_map(fn ($i) => ['part_number' => $i['part_number'], 'qty' => (int) $i['quantity'], 'price' => $i['price'], 'description' => $i['description'] ?? ''], $cart['items'] ?? []),
                'total' => $cart['total_price'] ?? null, 'weight' => $cart['total_weight'] ?? null]];
    }

    /**
     * Take every item out of PTI's cart (and its dropship address). Returns what was removed. A prepared dropship of
     * another order loses its cart this way: it is marked cancelled so nobody places it on an empty cart.
     */
    private function emptyCart(?int $keepId = null): array
    {
        $removed = [];
        for ($pass = 0; $pass < 2; $pass++) {   // a second read proves the cart is really empty
            $items = $this->pti->cartItems();
            if (!$items) break;
            foreach ($items as $i) {
                $this->pti->call('DELETE', 'my/cart/item/', ['part_number' => $i['part_number'], 'is_checkout' => 'false']);
                $removed[] = $i['part_number'] . ' × ' . (int) ($i['quantity'] ?? 1);
            }
        }
        if ($this->pti->cartItems()) throw new \RuntimeException('PTI kept items in the cart after removing them.');
        try { $this->pti->call('POST', 'my/cart/cart', ['dropship_address_id' => 0]); } catch (\Throwable $e) {}
        DB::table('pti_dropships')->where('status', 'prepared')->when($keepId, fn ($q) => $q->where('id', '!=', $keepId))->update(['status' => 'cancelled', 'error' => 'Its PTI cart was replaced by a newer dropship; prepare it again.', 'updated_at' => now()]);
        return array_values(array_unique($removed));
    }

    /** A PTI "Alt Part" for $part that has $qty on hand AND the same measurements as our product. */
    private function alternate(string $part, int $wpProductId, int $qty): ?array
    {
        $info = $this->pti->call('GET', 'my/parts/part/' . rawurlencode($part))['part'] ?? [];
        $origSpecs = (array) ($info['catalog_result']['values'] ?? []);
        foreach ((array) ($info['related_parts'] ?? []) as $rp) {
            if (stripos((string) ($rp['type'] ?? ''), 'alt') === false) continue;
            $alt = (string) ($rp['related_part_number'] ?? '');
            if ($alt === '' || strcasecmp($alt, $part) === 0) continue;
            $st = $this->pti->stock($alt);
            if ($st['total'] + $st['can_build'] < $qty) continue;
            $ai = $this->pti->call('GET', 'my/parts/part/' . rawurlencode($alt))['part'] ?? [];
            $cr = $ai['catalog_result'] ?? [];
            $check = $this->verify($wpProductId, (string) ($cr['type'] ?? ''), (array) ($cr['values'] ?? []));
            if ($check['status'] !== 'ok') continue;
            // and identical to the original PTI part in every spec both list (depths, washer distance, venting…)
            if ($origSpecs && !$this->sameSpecs($origSpecs, (array) ($cr['values'] ?? []))) continue;
            $price = (float) str_replace(['$', ','], '', (string) ($ai['price'] ?? $rp['price'] ?? '')) ?: null;
            return ['part_number' => $alt, 'stock' => $st['total'], 'cost' => $price];
        }
        return null;
    }

    /** Two PTI parts' spec sheets agree on every field both fill in (part number, brand, notes and class aside). */
    private function sameSpecs(array $a, array $b): bool
    {
        foreach ($a as $k => $va) {
            if (in_array($k, ['part_number', 'brand', 'notes', 'vehicle_class', 'is_virtual', 'application'], true) || !array_key_exists($k, $b)) continue;
            $va = trim((string) $va); $vb = trim((string) $b[$k]);
            if (self::blank($va) && self::blank($vb)) continue;
            if (self::blank($va) !== self::blank($vb) || !self::same($va, $vb, $k === 'series')) return false;
        }
        return true;
    }

    /** "No stock at PTI to dropship order X" to the shop inbox (once per order and part list per 6 hours). */
    private function mailNoStock(object $order, array $noStock): void
    {
        $key = 'pti-nostock:' . $order->id . ':' . md5(json_encode(array_column($noStock, 'part')));
        if (!\Illuminate\Support\Facades\Cache::add($key, 1, now()->addHours(6))) return;
        $kv = [];
        foreach ($noStock as $n) $kv["{$n['sku']} (PTI {$n['part']})"] = "need {$n['qty']}, PTI has {$n['stock']}, no alternate with the same measurements";
        try {
            \App\Support\Mailer::simple(config('uds.email'), "No PTI stock to dropship order {$order->order_number}", [
                'kicker' => 'Dropship', 'title' => "No stock to dropship order {$order->order_number}",
                'intro' => 'Powertrain Industries has no stock of these parts, and none of their alternate parts has stock with the same measurements as ours. Ship them from our warehouse, order them in, or contact the customer.',
                'kv' => $kv, 'button' => ['Open the order', rtrim((string) config('app.url'), '/') . '/admin/orders/' . $order->id], 'signoff' => false,
            ]);
        } catch (\Throwable $e) { report($e); }
        OrderEvents::record($order, 'pti_nostock', 'No PTI stock to dropship', implode(', ', array_map(fn ($n) => "{$n['sku']} (PTI {$n['part']})", $noStock)));
    }

    /** Saved cards on the PTI account: [profile id => "Amex …1001 (04/2030)"]. */
    public function cards(): array
    {
        $res = $this->pti->call('GET', 'my/cim/payment_profiles');
        $out = [];
        foreach ((array) ($res['payment_profiles'] ?? []) as $pid => $c) {
            if (!preg_match('/^\d+$/', (string) $pid)) continue;
            $type = ['americanexpress' => 'Amex', 'mastercard' => 'Mastercard', 'visa' => 'Visa', 'discover' => 'Discover'][$c['type'] ?? ''] ?? ucfirst((string) ($c['type'] ?? 'Card'));
            $out[(string) $pid] = trim("$type …" . ($c['number'] ?? '') . (!empty($c['exp']) ? " ({$c['exp']})" : ''));
        }
        return $out;
    }

    private function lastFour(string $profileId): string
    {
        $c = $this->pti->call('GET', 'my/cim/payment_profiles')['payment_profiles'][$profileId] ?? null;
        return (string) ($c['number'] ?? '');
    }

    /** Place the prepared dropship at PTI. */
    public function place(int $dropshipId, string $method, string $methodName, float $shipCost, string $store, string $card, bool $signature): array
    {
        return $this->withCartLock(fn () => $this->placeLocked($dropshipId, $method, $methodName, $shipCost, $store, $card, $signature));
    }

    private function placeLocked(int $dropshipId, string $method, string $methodName, float $shipCost, string $store, string $card, bool $signature): array
    {
        $d = DB::table('pti_dropships')->find($dropshipId);
        if (!$d || $d->status !== 'prepared') return ['ok' => false, 'error' => 'This dropship is not waiting to be placed (maybe it was placed or cancelled already).'];
        $order = DB::table('orders')->find($d->order_id);
        $lines = json_decode($d->lines, true);

        // PTI's cart must still hold exactly what we prepared — nothing more, nothing less
        try { $cart = $this->pti->call('GET', 'my/cart/cart', ['is_checkout' => 'true']); }
        catch (\Throwable $e) { report($e); return ['ok' => false, 'error' => 'PTI did not answer while checking the cart — nothing was ordered. Try again in a minute.']; }
        $want = collect($lines)->mapWithKeys(fn ($l) => [strtoupper($l['part_number']) => (int) $l['qty']])->all();
        ksort($want);
        $matches = function (array $cart) use ($want, $d) {
            $have = collect($cart['items'] ?? [])->mapWithKeys(fn ($i) => [strtoupper($i['part_number']) => (int) $i['quantity']])->all();
            ksort($have);
            return $have === $want && (int) ($cart['dropship_address_id'] ?? 0) === (int) $d->pti_address_id;
        };
        if (!$matches($cart)) {
            // the cart moved since it was prepared (another dropship, the nightly box measuring, a person): put it back
            try {
                $this->emptyCart($d->id);
                foreach ($lines as $l) $this->pti->call('PUT', 'my/cart/item/', ['part_number' => $l['part_number'], 'quantity' => $l['qty']]);
                $this->pti->call('POST', 'my/cart/cart', ['dropship_address_id' => (int) $d->pti_address_id]);
                $cart = $this->pti->call('GET', 'my/cart/cart', ['is_checkout' => 'true']);
            } catch (\Throwable $e) { report($e); return ['ok' => false, 'error' => 'The PTI cart had changed and could not be rebuilt: ' . $e->getMessage() . ' — nothing was ordered.']; }
            if (!$matches($cart)) return ['ok' => false, 'error' => 'The PTI cart had changed and PTI did not accept the rebuilt one — nothing was ordered. Prepare the dropship again.'];
            OrderEvents::record($order, 'pti_cart', 'PTI cart rebuilt before placing', 'It had changed since the dropship was prepared');
        }

        $last4 = '';
        try {
            $this->pti->call('POST', 'my/cart/cart', [
                'billing_method' => 'credit', 'cim_payment_profile_id' => $card, 'cim_last_four' => $this->lastFour($card),
                'shipping_method' => $method, 'ship_from_store_id' => $store,
                'po' => $order->order_number, 'is_signature_required' => $signature ? '1' : '0',
                'notes' => "Dropship for our customer - blind ship, please do not include an invoice or prices. UDS order {$order->order_number}",
            ]);
            $chk = $this->pti->call('GET', 'my/cart/cart', ['is_checkout' => 'true']);
            $last4 = (string) ($chk['cim_last_four'] ?? '');
            if (($chk['shipping_method'] ?? '') !== $method || (string) ($chk['po'] ?? '') !== (string) $order->order_number) {
                return ['ok' => false, 'error' => 'PTI did not keep the shipping method / PO on the cart. Nothing was ordered — try again or place it on c2.powertrainindustries.com.'];
            }
            $res = $this->pti->call('PUT', 'my/orders/order');
        } catch (\Throwable $e) {
            report($e);
            DB::table('pti_dropships')->where('id', $d->id)->update(['error' => mb_substr($e->getMessage(), 0, 500), 'updated_at' => now()]);
            return ['ok' => false, 'error' => 'PTI refused the order: ' . $e->getMessage()];
        }
        $ptiId = (string) ($res['order_id'] ?? $res['ID'] ?? '');
        DB::table('pti_dropships')->where('id', $d->id)->update([
            'status' => 'placed', 'pti_order_id' => $ptiId ?: null, 'shipping_method' => $method, 'shipping_name' => $methodName, 'shipping_cost' => $shipCost,
            'ship_from_store_id' => $store, 'card_last_four' => $last4 ?: null, 'placed_at' => now(), 'error' => null, 'updated_at' => now(),
        ]);
        $parts = implode(', ', array_map(fn ($l) => $l['qty'] . ' × ' . $l['part_number'] . (!empty($l['replaces']) ? " (alternate for {$l['replaces']})" : ''), $lines));
        OrderEvents::record($order, 'pti_dropship', 'Dropshipped from PTI', "PTI order #$ptiId · $parts · $methodName");
        return ['ok' => true, 'pti_order_id' => $ptiId];
    }

    public function cancel(int $dropshipId): array
    {
        return $this->withCartLock(fn () => $this->cancelLocked($dropshipId));
    }

    private function cancelLocked(int $dropshipId): array
    {
        $d = DB::table('pti_dropships')->find($dropshipId);
        if (!$d || $d->status !== 'prepared') return ['ok' => false, 'error' => 'Nothing to cancel.'];
        foreach (json_decode($d->lines, true) as $l) { try { $this->pti->call('DELETE', 'my/cart/item/', ['part_number' => $l['part_number'], 'is_checkout' => 'false']); } catch (\Throwable $e) {} }
        try { $this->pti->call('POST', 'my/cart/cart', ['dropship_address_id' => 0]); } catch (\Throwable $e) {}
        DB::table('pti_dropships')->where('id', $d->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        return ['ok' => true];
    }

    /**
     * PTI's tracking onto our order (and the customer's "shipped" e-mail/text) once PTI ships, and again whenever PTI
     * changes it (a new label): the order follows PTI as long as it still shows PTI's previous number.
     */
    public function syncTracking(object $d, bool $notify = true): ?string
    {
        if (!$d->pti_order_id) return null;
        $o = $this->pti->call('GET', 'my/orders/order/' . rawurlencode($d->pti_order_id));
        $o = $o['order'] ?? $o;
        DB::table('pti_dropships')->where('id', $d->id)->update(['tracking_checked_at' => now()]);
        // PTI may list more than one parcel: "1Z…, 1Z…"
        $all = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($o['tracking_number'] ?? $o['tracking_numbers'] ?? '')) ?: [])));
        $tn = $all[0] ?? '';
        if ($tn === '' || implode(', ', $all) === (string) $d->tracking_number) return null;
        DB::table('pti_dropships')->where('id', $d->id)->update(['tracking_number' => mb_substr(implode(', ', $all), 0, 80), 'updated_at' => now()]);
        $order = DB::table('orders')->find($d->order_id);
        if (!$order) return $tn;
        $carrier = self::carrierFor($tn, (string) ($o['carrier'] ?? $o['shipping_carrier'] ?? $o['ship_via'] ?? ''));
        $previous = array_map('trim', explode(',', (string) $d->tracking_number));
        if (!$order->tracking_number || in_array($order->tracking_number, $previous, true)) {
            \App\Support\OrderTracking::markShipped($order, $tn, $carrier, 'Shipped by PTI (dropship)', $notify);
        } else {
            OrderEvents::record($order, 'pti_tracking', 'PTI shipped the dropship', "$carrier $tn");
        }
        if (count($all) > 1) OrderEvents::record($order, 'pti_tracking', 'PTI shipped it in ' . count($all) . ' parcels', implode(', ', $all));
        return $tn;
    }

    /** Carrier of a PTI tracking number: PTI's own carrier field when it sends one, else the number's shape. */
    public static function carrierFor(string $tn, string $ptiCarrier = ''): string
    {
        $c = strtolower($ptiCarrier);
        foreach (['ups' => 'UPS', 'fedex' => 'FedEx', 'fed ex' => 'FedEx', 'usps' => 'USPS', 'postal' => 'USPS', 'dhl' => 'DHL'] as $k => $name) if (str_contains($c, $k)) return $name;
        $t = strtoupper(preg_replace('/\s+/', '', $tn));
        if (str_starts_with($t, '1Z')) return 'UPS';
        if (preg_match('/^(420\d{5}(\d{4})?)?9[1-5]\d{18,24}$/', $t) || preg_match('/^[A-Z]{2}\d{9}US$/', $t)) return 'USPS';   // IMpb 92/93/94/95… (optionally behind a 420+ZIP prefix), international ..US
        if (preg_match('/^\d{10}$/', $t)) return 'DHL';                                   // DHL Express waybill
        if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$|^\d{22}$/', $t)) return 'FedEx';
        return 'UPS';
    }
}
