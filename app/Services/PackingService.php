<?php

namespace App\Services;

use App\Services\CatalogService;
use App\Services\ProductService;

/**
 * How an order actually leaves the shop: which parcels it is packed into and what each
 * one weighs. Every shipping quote, the product-page estimator and the packing note on
 * the admin order page are built from this one answer.
 *
 *   - a complete driveshaft (built-to-order or pre-made) ships one per box
 *   - a cut length of tubing ships one per box
 *   - a single component over 35 lb ships one per box
 *   - everything else (u-joints, yokes, bearings, small parts) shares boxes,
 *     never more than 50 lb in one box
 */
class PackingService
{
    /** Fallbacks for a parcel whose product carries no box of its own, longest side first. */
    private const DEFAULT_DIMS = [
        'driveshaft' => [48.0, 8.0, 8.0],
        'tube' => [48.0, 8.0, 8.0],
        'big' => [24.0, 12.0, 12.0],
        'parts' => [18.0, 14.0, 12.0],
    ];

    /**
     * Palletising. Several long pieces are not shipped as several parcels — they are strapped
     * to one pallet and go out as freight, which is both what the shop actually does and far
     * cheaper than N oversize parcels. A pallet is 40" wide and 15" tall, as long as it has to
     * be, and carries its own deck weight.
     */
    private const PALLET_MIN_PIECES = 5;      // this many long pieces (tubes/shafts) and it's a pallet
    private const PALLET_MIN_LB = 100.0;      // …or this much weight all in

    /** The decks the shop actually loads, smallest first. Anything longer gets a deck cut to fit. */
    private const DECKS = [
        ['l' => 48.0, 'w' => 40.0, 'h' => 48.0],   // GMA standard 48x40
        ['l' => 48.0, 'w' => 48.0, 'h' => 48.0],   // 48x48
    ];

    /** A long bundle — tube or driveshaft — is strapped low and wide; 80" tubes give the shop's 82x40x15. */
    private const LONG_DECK_W = 40.0;
    private const LONG_DECK_H = 15.0;
    private const LONG_DECK_MIN_L = 48.0;     // a long deck is cut to its load, never under a standard deck

    private const PALLET_TARE_LB = 40.0;      // the deck itself, which the carrier weighs too
    private const PALLET_DECK_IN = 6.0;       // …and measures too: a GMA pallet stands about 6" tall
    private const PALLET_MAX_LB = 2000.0;     // past this a carrier wants it split
    private const PACK_EFFICIENCY = 0.85;     // nothing stacks perfectly

    /** No carrier takes a single piece longer than this, so a longer run is cut. */
    private const MAX_TUBE_FT = 20;

    /** UPS and FedEx both refuse a parcel longer than this, so it can only go freight. */
    private const MAX_PARCEL_IN = 108.0;

    private const MAX_BOX_LB = 50.0;
    private const DEFAULT_UNIT_LB = 15.0; // a driveshaft/tube with no real WP weight row
    private const BIG_PART_LB = 35.0;     // a single component this heavy ships in its own box

    /**
     * Split a run of tube into pieces a carrier will actually accept.
     *
     * @return array<int,int> the length in feet of each piece
     */
    private function cutLengths(int $feet): array
    {
        $out = [];
        while ($feet > self::MAX_TUBE_FT) { $out[] = self::MAX_TUBE_FT; $feet -= self::MAX_TUBE_FT; }
        if ($feet > 0) $out[] = $feet;
        return $out ?: [1];
    }

    /**
     * Strap the shipment to pallets once it stops being a parcel shipment: five or more long
     * pieces, or 100 lb all in. Everything travelling together goes on the same pallets —
     * nobody ships a pallet with a loose box riding alongside it.
     *
     * @param  array<int,array{kind:string,weight:float,dims:array,excluded:bool}> $boxes
     * @return array<int,array{kind:string,weight:float,dims:array,excluded:bool}>
     */
    private function palletize(array $boxes): array
    {
        $boxes = array_values(array_filter($boxes, fn ($b) => ($b['weight'] ?? 0) > 0));
        if (!$boxes) return $boxes;

        // A piece over the carriers' length limit is freight whatever it weighs: left as a
        // parcel it prices nothing at all and the checkout shows only local pickup.
        $oversize = (bool) array_filter($boxes, fn ($b) => $this->dim($b, 0) > self::MAX_PARCEL_IN);
        $long = array_filter($boxes, fn ($b) => in_array($b['kind'], ['tube', 'driveshaft'], true));
        $totalLb = array_sum(array_column($boxes, 'weight'));
        if (!$oversize && count($boxes) < 2) return $boxes;
        if (!$oversize && count($long) < self::PALLET_MIN_PIECES && $totalLb < self::PALLET_MIN_LB) return $boxes;

        // Long goods ride a deck cut to their length; short cartons ride a standard deck. A
        // 7-foot tube and an 18" carton do not compete for the same space, and sizing one
        // against the other is what once put ten tubes and one carton on six pallets.
        $longest = max(array_map(fn ($b) => $this->dim($b, 0), $boxes));
        $isLong = fn (array $b) => $this->dim($b, 0) > self::DECKS[0]['l'];
        $longPieces = array_values(array_filter($boxes, $isLong));
        $shortPieces = array_values(array_filter($boxes, fn ($b) => !$isLong($b)));

        $pallets = [];
        if ($longPieces) {
            $widest = max(array_map(fn ($b) => $this->dim($b, 1), $longPieces));
            $deck = [
                'l' => max(self::LONG_DECK_MIN_L, $longest + 2),
                'w' => max(self::LONG_DECK_W, $widest + 2),
                'h' => self::LONG_DECK_H,
            ];
            // A long piece lies down the deck and eats into its end face; a carton riding
            // along sits on the deck and eats into its footprint.
            $pallets = $this->fill($longPieces, $deck, fn ($b) => $this->dim($b, 1) * $this->dim($b, 2), fn ($b) => 0.0);
            $shortPieces = $this->topUp($pallets, $deck, $shortPieces);
        }
        foreach ($this->byDeck($shortPieces) as [$deck, $group]) {
            // cartons stack: they fill the deck's volume, not just its floor
            foreach ($this->fill($group, $deck, fn ($b) => 0.0, fn ($b) => $this->dim($b, 0) * $this->dim($b, 1) * $this->dim($b, 2), true) as $p) $pallets[] = $p;
        }
        return $pallets;
    }

    /** One dimension of a box, longest side first, never zero. */
    private function dim(array $b, int $i): float
    {
        $d = $b['dims'] ?? [];
        return max(0.1, (float) ($d[$i] ?? 0));
    }

    /** Group short cartons onto the smallest standard deck that holds the widest of them. */
    private function byDeck(array $pieces): array
    {
        if (!$pieces) return [];
        $widest = max(array_map(fn ($b) => $this->dim($b, 1), $pieces));
        foreach (self::DECKS as $deck) {
            if ($deck['w'] >= $widest) return [[$deck, $pieces]];
        }
        $last = self::DECKS[count(self::DECKS) - 1];
        return [[['l' => $last['l'], 'w' => $widest + 2, 'h' => $last['h']], $pieces]];
    }

    /**
     * Bin pieces onto decks against three budgets at once: the deck's end face, its footprint
     * and what a carrier will take on one pallet.
     */
    private function fill(array $pieces, array $deck, callable $faceCost, callable $deckCost, bool $stack = false): array
    {
        $faceBudget = $deck['w'] * $deck['h'] * self::PACK_EFFICIENCY;
        $deckBudget = $deck['l'] * $deck['w'] * ($stack ? $deck['h'] : 1) * self::PACK_EFFICIENCY;
        usort($pieces, fn ($a, $b) => $this->dim($b, 0) <=> $this->dim($a, 0));

        $out = [];
        $cur = null;
        foreach ($pieces as $b) {
            $f = (float) $faceCost($b);
            $d = (float) $deckCost($b);
            $full = $cur && ($cur['face'] + $f > $faceBudget || $cur['deck'] + $d > $deckBudget || $cur['weight'] + $b['weight'] > self::PALLET_MAX_LB);
            if (!$cur || $full) {
                if ($cur) $out[] = $this->deckBox($cur, $deck);
                $cur = ['face' => 0.0, 'deck' => 0.0, 'weight' => 0.0, 'pieces' => 0, 'excluded' => false, 'vol' => 0.0, 'maxh' => 0.0];
            }
            $cur['vol'] += $this->dim($b, 0) * $this->dim($b, 1) * $this->dim($b, 2);
            $cur['maxh'] = max($cur['maxh'], $this->dim($b, 2));
            $cur['face'] += $f;
            $cur['deck'] += $d;
            $cur['weight'] += (float) $b['weight'];
            $cur['pieces']++;
            $cur['excluded'] = $cur['excluded'] || !empty($b['excluded']);
        }
        if ($cur) $out[] = $this->deckBox($cur, $deck);
        return $out;
    }

    /** Put the short cartons on the long decks already built, before opening a standard one. */
    private function topUp(array &$pallets, array $deck, array $shortPieces): array
    {
        $deckBudget = $deck['l'] * $deck['w'] * self::PACK_EFFICIENCY;
        $left = [];
        foreach ($shortPieces as $b) {
            $cost = $this->dim($b, 0) * $this->dim($b, 1);
            $placed = false;
            foreach ($pallets as &$p) {
                if ($p['deck_used'] + $cost <= $deckBudget && $p['weight'] + $b['weight'] <= self::PALLET_MAX_LB) {
                    $p['deck_used'] += $cost;
                    $p['vol'] = ($p['vol'] ?? 0) + $this->dim($b, 0) * $this->dim($b, 1) * $this->dim($b, 2);
                    // a carton riding on top of the bundle raises it to the carton's height at least
                    $p['dims'][2] = max((float) $p['dims'][2], round(self::PALLET_DECK_IN + $this->dim($b, 2) + ($p['load_h'] ?? 0), 1));
                    $p['weight'] = round($p['weight'] + (float) $b['weight'], 2);
                    $p['pieces']++;
                    $p['excluded'] = $p['excluded'] || !empty($b['excluded']);
                    $placed = true;
                    break;
                }
            }
            unset($p);
            if (!$placed) $left[] = $b;
        }
        return $left;
    }

    /**
     * The pallet as the carrier will measure it. Freight is priced on weight AND cube, so the
     * height is what is actually stacked — the load's volume spread over the deck (never less
     * than its tallest piece) plus the wooden deck itself — not the deck's maximum.
     */
    private function deckBox(array $cur, array $deck): array
    {
        $usable = $deck['l'] * $deck['w'] * self::PACK_EFFICIENCY;
        $load = max($cur['maxh'] ?? 0, $usable > 0 ? ($cur['vol'] ?? 0) / $usable : 0);
        $load = min($load, $deck['h']);
        $height = round(self::PALLET_DECK_IN + ceil($load), 1);
        return [
            'kind' => 'pallet',
            'weight' => round($cur['weight'] + self::PALLET_TARE_LB, 2),
            'dims' => [round($deck['l'], 2), round($deck['w'], 2), $height],
            'load_h' => ceil($load),
            'excluded' => $cur['excluded'],
            'pieces' => $cur['pieces'],
            'deck_used' => $cur['deck'],
        ];
    }

    /**
     * The real box for one line: the product's own measurements where the catalog has them
     * (a label purchase writes them back), the vehicle-finder part's box next, and only then
     * a per-kind default. Sending a believable box matters beyond price — a parcel over the
     * carrier's 165" length-plus-girth limit stops being a parcel and prices as freight.
     *
     * @return array{0:float,1:float,2:float}
     */
    private function dimsFor(int $pid, $it, string $kind): array
    {
        if ($pid && ($d = app(ProductService::class)->dims($pid))) return $d;
        $sku = (string) (is_array($it) ? ($it['sku'] ?? '') : ($it->sku ?? ''));
        if ($sku && ($part = \App\Services\DriveshaftFinder::pn($sku))) {
            $row = \Illuminate\Support\Facades\DB::table('ds_parts')->where('part_number', $part)
                ->select('box_length', 'box_width', 'box_height')->first();
            if ($row && min((float) $row->box_length, (float) $row->box_width, (float) $row->box_height) > 0) {
                $d = [(float) $row->box_length, (float) $row->box_width, (float) $row->box_height];
                rsort($d);
                return $d;
            }
        }
        return self::DEFAULT_DIMS[$kind] ?? self::DEFAULT_DIMS['parts'];
    }

    /**
     * Classify + weigh every line so both the live rate quote and the
     * packing slip note are built from the same numbers.
     *
     * @param  iterable $items  cart lines or order_items rows — anything with
     *                          type, wp_product_id, qty, and optionally meta.config
     * @return array{packages:int, weight_lb:float, note:string, breakdown:array}
     */
    public function packageSummary(iterable $items): array
    {
        $catalog = app(CatalogService::class);
        $products = app(ProductService::class);

        $boxes = [];        // every parcel that will carry a label: ['kind' => ..., 'weight' => lb]
        $small = [];        // loose component weights, bin-packed below (max 50 lb per box)
        $driveshaftUnits = $tubeUnits = $bigUnits = 0;
        $totalWeight = 0.0;
        $excludedValue = 0.0;   // tubing and complete driveshafts never count toward free shipping and never ship free

        foreach ($items as $it) {
            $type = is_array($it) ? ($it['type'] ?? 'product') : ($it->type ?? 'product');
            $qty = max(1, (int) (is_array($it) ? ($it['qty'] ?? 1) : ($it->qty ?? 1)));
            $pid = (int) (is_array($it) ? ($it['wp_product_id'] ?? 0) : ($it->wp_product_id ?? 0));
            $meta = is_array($it) ? ($it['meta'] ?? []) : (is_array($it->meta ?? null) ? $it->meta : (json_decode($it->meta ?? '{}', true) ?: []));

            if (in_array($type, ['custom_build', 'premade_build', 'driveshaft'], true)) {
                // a driveshaft (builder, premade build or vehicle-finder shaft): one box each, always.
                $w = (float) ($meta['weight_lb'] ?? 0) ?: ($pid ? $products->weight($pid) : 0.0) ?: self::DEFAULT_UNIT_LB;
                $dims = $this->dimsFor($pid, $it, 'driveshaft');
                for ($i = 0; $i < $qty; $i++) $boxes[] = ['kind' => 'driveshaft', 'weight' => $w, 'dims' => $dims];
                $driveshaftUnits += $qty; $totalWeight += $w * $qty;
                $excludedValue += (float) (is_array($it) ? ($it['price'] ?? 0) : ($it->price ?? 0)) * $qty;
                continue;
            }

            $group = $pid ? $catalog->groupOfProduct($pid) : null;
            $unitWeight = $pid ? $products->weight($pid) : 0.0;
            $slug = $group['slug'] ?? null;

            if ($slug === 'complete-driveshafts') {            // premade, short couple, unwelded assemblies
                $w = $unitWeight ?: self::DEFAULT_UNIT_LB;
                $dims = $this->dimsFor($pid, $it, 'driveshaft');
                for ($i = 0; $i < $qty; $i++) $boxes[] = ['kind' => 'driveshaft', 'weight' => $w, 'dims' => $dims];
                $driveshaftUnits += $qty; $totalWeight += $w * $qty;
                $excludedValue += (float) (is_array($it) ? ($it['price'] ?? 0) : ($it->price ?? 0)) * $qty;
            } elseif ($slug === 'custom-cut-tubing') {         // every tube ships alone
                $w = $unitWeight ?: self::DEFAULT_UNIT_LB;
                $dims = $this->dimsFor($pid, $it, 'tube');
                if ($pid && app(ProductService::class)->soldByFoot($pid)) {
                    // Ten feet of tube is ONE tube ten feet long, not ten one-foot pieces. The
                    // listing's weight and box are per foot, so both grow with the length ordered,
                    // and anything past MAX_TUBE_FT is cut into pieces that a carrier will take.
                    foreach ($this->cutLengths($qty) as $feet) {
                        $boxes[] = ['kind' => 'tube', 'weight' => round($w * $feet, 2), 'dims' => [$feet * 12 + 2, $dims[1], $dims[2]]];
                        $tubeUnits++;
                    }
                } else {
                    for ($i = 0; $i < $qty; $i++) $boxes[] = ['kind' => 'tube', 'weight' => $w, 'dims' => $dims];
                    $tubeUnits += $qty;
                }
                $totalWeight += $w * $qty;
                $excludedValue += (float) (is_array($it) ? ($it['price'] ?? 0) : ($it->price ?? 0)) * $qty;
            } elseif ($unitWeight >= self::BIG_PART_LB) {        // big parts (heavy flanges, center sections…) ship alone
                $dims = $this->dimsFor($pid, $it, 'big');
                for ($i = 0; $i < $qty; $i++) $boxes[] = ['kind' => 'big', 'weight' => $unitWeight, 'dims' => $dims];
                $bigUnits += $qty; $totalWeight += $unitWeight * $qty;
            } else {
                // components / small parts share boxes up to 50 lb
                for ($i = 0; $i < $qty; $i++) $small[] = $unitWeight;
                $totalWeight += $unitWeight * $qty;
            }
        }

        // first-fit bin packing of the loose parts, heaviest first, 50 lb cap per box
        rsort($small);
        $bins = [];
        foreach ($small as $w) {
            $placed = false;
            foreach ($bins as &$b) { if ($b + $w <= self::MAX_BOX_LB) { $b += $w; $placed = true; break; } }
            unset($b);
            if (!$placed) $bins[] = $w;
        }
        $smallWeight = array_sum($bins);
        foreach ($bins as $b) $boxes[] = ['kind' => 'parts', 'weight' => $b];
        $smallBoxes = count($bins);
        $packages = count($boxes);

        $parts = [];
        if ($driveshaftUnits) $parts[] = $driveshaftUnits . ' complete driveshaft' . ($driveshaftUnits === 1 ? '' : 's') . ' (own box each)';
        if ($tubeUnits) $parts[] = $tubeUnits . ' cut tube' . ($tubeUnits === 1 ? '' : 's') . ' (own box each)';
        if ($bigUnits) $parts[] = $bigUnits . ' large part' . ($bigUnits === 1 ? '' : 's') . ' over ' . self::BIG_PART_LB . ' lb (own box each)';
        if ($smallBoxes) $parts[] = $smallBoxes . ' box' . ($smallBoxes === 1 ? '' : 'es') . ' of components (~' . round($smallWeight, 1) . ' lb, max 50 lb/box)';

        $note = $packages
            ? 'PACK AS ' . $packages . ' PACKAGE' . ($packages === 1 ? '' : 'S') . ': ' . implode('; ', $parts) . '.'
            : '';

        // a parcel with no catalog weight still needs a believable weight for the rate call
        $boxes = array_map(fn ($b) => [
            'kind' => $b['kind'],
            'weight' => round($b['weight'] > 0 ? $b['weight'] : self::DEFAULT_UNIT_LB, 2),
            'dims' => $b['dims'] ?? self::DEFAULT_DIMS[$b['kind']] ?? self::DEFAULT_DIMS['parts'],
            // tubing and complete driveshafts never ship free, and that has to survive palletising
            'excluded' => in_array($b['kind'], ['tube', 'driveshaft'], true),
        ], $boxes);
        if (!$boxes) $boxes[] = ['kind' => 'parts', 'weight' => self::DEFAULT_UNIT_LB, 'dims' => self::DEFAULT_DIMS['parts'], 'excluded' => false];

        $loose = $boxes;
        $boxes = $this->palletize($boxes);
        $packages = count($boxes);
        $palletised = $boxes !== $loose;
        if ($palletised) {
            $pallets = count($boxes);
            $note = 'PACK ON ' . $pallets . ' PALLET' . ($pallets === 1 ? '' : 'S') . ': '
                . implode('; ', array_map(fn ($t) => str_replace(' (own box each)', '', $t), $parts)) . '.';
        }

        return [
            'packages' => count($boxes),
            'boxes' => $boxes,
            // parcels and value that sit outside the free-shipping rule (tubing + complete driveshafts)
            'excluded_boxes' => array_values(array_filter($boxes, fn ($b) => $b['excluded'])),
            // the same goods loose, when they were palletised: a pallet is not always the cheaper
            // way to send them, so both shapes get priced and the customer is offered the better one
            'loose_boxes' => $palletised ? $loose : null,
            'excluded_value' => round($excludedValue, 2),
            'weight_lb' => round($totalWeight, 2) ?: self::DEFAULT_UNIT_LB,
            'note' => $note,
            'breakdown' => compact('driveshaftUnits', 'tubeUnits', 'bigUnits', 'smallBoxes', 'smallWeight'),
        ];
    }
}
