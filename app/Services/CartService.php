<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Session cart + the ORIGINAL store's shipping rules (from wp_woocommerce_shipping_zones):
 *  - US: live UPS rates per package (no flat rate since Oct 2026) + free shipping on parts from $1,000
 *    (tubing and complete driveshafts never count and are always rated)
 *  - Local pickup in Florida is offered from ANY address, always last and never preselected;
 *    the checkout makes the customer acknowledge it (nothing ships). Was FL-only originally.
 *  - No taxes anywhere (woocommerce_calc_taxes = no).
 */
class CartService
{
    public const FREE_SHIPPING_MIN = 1000.0;

    /** U.S. states and territories outside the lower 48 (APO/FPO included). */
    public const NON_CONTIGUOUS = ['AK', 'HI', 'PR', 'VI', 'GU', 'AS', 'MP', 'UM', 'AA', 'AE', 'AP'];

    /** A freight (LTL or express-freight) option — never made free by a coupon. */
    public static function isFreight(string $optionId): bool
    {
        return str_starts_with($optionId, 'warp-') || str_contains($optionId, 'freight');
    }   // default; the admin setting (shipping.free_min) overrides it

    public static function freeMin(): float { return (float) (config('uds.free_shipping_min') ?: self::FREE_SHIPPING_MIN); }

    public static function pickupEnabled(): bool { return (bool) setting('pickup.enabled', true); }
    public const PICKUP_ID = 'local_pickup';

    private const KEY = 'uds_cart';

    public function items(): array
    {
        return session(self::KEY, []);
    }

    /**
     * The cart as the slide-in panel shows it. Every page carries it (layouts/app), so the panel opens with its lines
     * instead of waiting on the network; /cart/mini and the panel's +/- answer with the same shape.
     */
    public function mini(): array
    {
        $items = $this->items();
        return [
            'count' => $this->count(), 'subtotal' => $this->subtotal(),
            'items' => array_values(array_map(fn ($i) => ['key' => $i['key'], 'sku' => (string) $i['sku'], 'name' => (string) $i['name'], 'image' => self::thumb((string) $i['image']),
                'price' => (float) $i['price'], 'qty' => (int) $i['qty'],
                // the id the analytics funnel knows the line by (App\Support\Funnel): add to cart and remove are sent from these lines
                'item_id' => \App\Support\Funnel::item(isset($i['wp_product_id']) ? (int) $i['wp_product_id'] : null, (string) $i['sku'], '', 0, 1, '', (string) ($i['type'] ?? 'product'))['item_id'] ?? ''], $items)),
            // free US shipping meter: tubing and complete driveshafts don't count toward it
            'free_min' => self::freeMin(), 'free_base' => $items ? $this->freeShippingBase($items) : 0.0,
        ];
    }

    /** A cart line's picture at list size (the 320 px copy the catalog lists already loaded), not the product page's 1200 px one. */
    public static function thumb(string $src): string
    {
        return preg_replace('#^/media/\d+/#', '/media/320/', $src);
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'qty'));
    }

    public function subtotal(): float
    {
        return round(array_reduce($this->items(), fn ($s, $i) => $s + $i['price'] * $i['qty'], 0), 2);
    }

    /** Add an item. Identical configs merge into one line (the original's ubs_dsb_unique behavior). */
    public function add(array $item): string
    {
        // last line of defence: a catalog part with no price is "call for a quote", never a free cart line
        if (($item['type'] ?? 'product') === 'product' && (float) ($item['price'] ?? 0) <= 0) throw new \InvalidArgumentException('This part has no online price.');
        $items = $this->items();
        // the price is part of the line: the same part added at a different price (a wholesale login, a price
        // change) is a separate line rather than silently taking the first line's price
        $hash = md5(json_encode([$item['type'], $item['wp_product_id'] ?? null, $item['meta'] ?? [], round((float) $item['price'], 2)]));
        if (isset($items[$hash])) {
            $items[$hash]['qty'] = min(999, $items[$hash]['qty'] + max(1, (int) ($item['qty'] ?? 1)));
        } else {
            $items[$hash] = [
                'key'           => $hash,
                'type'          => $item['type'] ?? 'product',
                'wp_product_id' => $item['wp_product_id'] ?? null,
                // catalog parts carry the product page's title, not the old store's "UDS 5-1310X …" (the SKU shows beside it)
                'name'          => ($item['type'] ?? 'product') === 'product' && !empty($item['sku'])
                    ? (app(CatalogService::class)->cleanNameStored(['name' => (string) $item['name'], 'sku' => (string) $item['sku']]) ?: $item['name']) : $item['name'],
                'sku'           => $item['sku'] ?? '',
                'image'         => $item['image'] ?? '',
                'price'         => round((float) $item['price'], 2),
                'qty'           => min(999, max(1, (int) ($item['qty'] ?? 1))),
                'meta'          => $item['meta'] ?? [],
            ];
        }
        session([self::KEY => $items]);
        return $hash;
    }

    public function updateQty(string $key, int $qty): void
    {
        $items = $this->items();
        if (!isset($items[$key])) return;
        if ($qty <= 0) unset($items[$key]);
        else $items[$key]['qty'] = min(999, $qty);
        session([self::KEY => $items]);
    }

    public function remove(string $key): void
    {
        $items = $this->items();
        unset($items[$key]);
        session([self::KEY => $items]);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
        session()->forget('uds_coupon');
    }

    /**
     * Shipping options for a destination — ported from the live shipping zones.
     * Returns [ [id, label, cost], ... ]
     */
    /** The part of the cart that counts toward free shipping: everything except tubing and complete driveshafts. */
    public function freeShippingBase(?array $items = null): float
    {
        $items ??= $this->items();
        $pack = app(\App\Services\PackingService::class)->packageSummary($items);
        return round(self::subtotalOf($items) - $pack['excluded_value'], 2);
    }

    public static function subtotalOf(array $items): float
    {
        return round(array_reduce($items, fn ($s, $i) => $s + $i['price'] * $i['qty'], 0), 2);
    }

    /** $items defaults to the session cart; the admin order builder passes its own lines. */
    /**
     * @param array $dest  optional: street, city, company and delivery ('dock' | 'liftgate' | 'residential',
     *                     how a freight truck must deliver). With it the quote is the address's real price.
     */
    public function shippingOptions(string $country, string $state, string $zip = '', ?array $items = null, array $dest = []): array
    {
        $opts = $this->quoteOptions($country, $state, $zip, $items, $dest);
        $this->rememberQuote($this->quoteKey($country, $state, $zip, $items, $dest), $opts);
        return $opts;
    }

    private function quoteOptions(string $country, string $state, string $zip, ?array $items, array $dest): array
    {
        $items ??= $this->items();
        $pack = app(\App\Services\PackingService::class)->packageSummary($items);
        $subtotal = self::subtotalOf($items);
        // tubing and complete driveshafts never count toward the $1,000 and never ship free: when the rest of
        // the cart qualifies, the customer pays the carrier's rate for those parcels only
        // the terms promise free ground shipping in the continental U.S. only: one 83" shaft to
        // Alaska, Hawaii or Puerto Rico costs $170–$210 by ground
        $freeEligible = $country === 'US' && !in_array(strtoupper($state), self::NON_CONTIGUOUS, true)
            && ($subtotal - $pack['excluded_value']) >= self::freeMin();
        $ratedBoxes = $pack['excluded_boxes'];
        $opts = [];

        // Live carrier rates — EasyPost (the shop's own carrier wallet), with Shippo as a fallback.
        // Weight comes from what's actually in the cart (real per-product weight
        // where the catalog has it, the shop's own packing rules otherwise) —
        // not a flat guess, so the quote matches what the box will really weigh.
        $live = [];
        if ($zip !== '') {
            $weightLb = $pack['weight_lb'];
            // A palletised load is an LTL move and nothing else: it is quoted by Warp, and the
            // parcel carriers are not asked at all. That is both what the customer should see —
            // freight services, not ten separate parcels — and the whole EasyPost round trip saved.
            $live = app(\App\Services\WarpFreightService::class)->rates($pack, $zip, $country ?: 'US', (string) ($dest['delivery'] ?? 'residential'));
            $ss = app(\App\Services\EasyPostService::class);

            // Warp's marketplace is US-only and no carrier here sells international LTL, so
            // abroad a pallet prices nothing at all. The parcels it was built from are what
            // the customer can actually buy, so those are what gets quoted.
            $ratePack = $pack;
            if (($country ?: 'US') !== 'US' && !empty($pack['loose_boxes'])) {
                $ratePack['boxes'] = $pack['loose_boxes'];
                $ratePack['loose_boxes'] = null;
            }

            // Not freight (or the lane has no LTL service): parcel carriers answer.
            if (!$live && $ss->enabled()) {
                $live = $ss->ratesForPack($ratePack, $state, $zip, $country ?: 'US', $dest);
                // "every carrier except FedEx" for pallets: EasyPost's only freight service is FedEx Express Freight
                if (\App\Services\WarpFreightService::carrierMode() === 'no_fedex') $live = array_values(array_filter($live ?: [], fn ($o) => !str_contains(strtolower((string) ($o['id'] ?? '')), 'freight')));
            }
            if (empty($live)) {
                $shippo = app(\App\Services\ShippoService::class);
                if ($shippo->enabled()) $live = $shippo->rates(['zip' => $zip, 'state' => $state, 'country' => $country ?: 'US'], $weightLb);
            }
            if (!empty($live)) {
                // A palletised order travels as ONE freight shipment: the parts are strapped to
                // the same deck as the tubing, so there is no separate parcel to ship free and
                // no "free on parts" to apply. Free shipping is a parcel-only promise.
                $palletised = (bool) array_filter($pack['boxes'], fn ($b) => ($b['kind'] ?? '') === 'pallet');
                if ($freeEligible && !$palletised) {
                    if (!$ratedBoxes) {
                        array_unshift($live, ['id' => 'free_shipping', 'label' => 'Free shipping', 'cost' => 0.0]);
                    } else {
                        // parts ship free; the tube / driveshaft parcels are rated on their own and offered per service
                        $w = array_sum(array_column($ratedBoxes, 'weight'));
                        $what = self::describeBoxes($ratedBoxes);
                        $rated = $ss->enabled() ? $ss->rates($state, $zip, $country ?: 'US', $w, $ratedBoxes) : [];
                        if ($rated) {
                            $live = array_map(fn ($r) => ['id' => 'free_' . $r['id'], 'label' => 'Free shipping on parts + ' . preg_replace('/ · \d+ packages$/', '', $r['label']) . ' for ' . $what, 'cost' => $r['cost'], 'packages' => count($ratedBoxes)], $rated);
                        }
                    }
                }
                $live = $this->withEta($live, $items);
                if ($country === 'US' && self::pickupEnabled()) $live[] = self::pickupOption();
                return $live;
            }
        }

        // No live carrier rate (no ZIP yet, or the carrier could not price this address / parcel): nothing
        // is invented — the checkout shows the reason instead of a "quoted after order" placeholder.
        if ($zip !== '' && $freeEligible && !$ratedBoxes) {
            $opts[] = ['id' => 'free_shipping', 'label' => 'Free shipping', 'cost' => 0.0];
        }
        if ($country === 'US' && self::pickupEnabled()) $opts[] = self::pickupOption();   // pickup in Florida only makes sense for U.S. addresses
        return $opts;
    }

    /** How long a shown price is honoured at checkout. */
    private const QUOTE_TTL_MIN = 45;

    private function quoteKey(string $country, string $state, string $zip, ?array $items, array $dest): string
    {
        $cart = array_map(fn ($i) => [($i['type'] ?? ''), ($i['wp_product_id'] ?? ''), ($i['sku'] ?? ''), (int) ($i['qty'] ?? 1), (float) ($i['price'] ?? 0)], $items ?? $this->items());
        return md5(json_encode([strtoupper($country), strtoupper($state), trim($zip), strtolower(trim((string) ($dest['street'] ?? ''))),
            trim((string) ($dest['company'] ?? '')) === '', (string) ($dest['delivery'] ?? 'residential'), $cart]));
    }

    private function rememberQuote(string $key, array $opts): void
    {
        if (!$opts || !app()->bound('session') || !session()->isStarted()) return;
        $all = array_filter((array) session('ship_quotes', []), fn ($q) => ($q['at'] ?? 0) > time() - self::QUOTE_TTL_MIN * 60);
        $all[$key] = ['at' => time(), 'opts' => $opts];
        session(['ship_quotes' => array_slice($all, -6, null, true)]);
    }

    private function heldQuote(string $key): ?array
    {
        if (!app()->bound('session') || !session()->isStarted()) return null;
        $q = session('ship_quotes', [])[$key] ?? null;
        return $q && ($q['at'] ?? 0) > time() - self::QUOTE_TTL_MIN * 60 ? $q['opts'] : null;
    }

    /**
     * When it ships: today if the order is in before 2 pm EST on a weekday (the promise the
     * product pages make), otherwise the next weekday; a longer handling time from Settings
     * pushes it out. Holidays are not modelled, which is why the date is shown as an estimate.
     */
    /** Same-day cutoff, Eastern time (admin Settings → Shipping): [hour, minute]. */
    public static function cutoff(): array
    {
        $v = (string) \App\Support\Settings::get('shipping.cutoff', '14:00');
        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i', trim($v), $m)) return [14, 0];
        $h = (int) $m[1] % 24; $min = min(59, (int) ($m[2] ?? 0));
        if (!empty($m[3])) $h = ($h % 12) + (strtolower($m[3]) === 'pm' ? 12 : 0);
        return [$h, $min];
    }

    /** "2pm" / "2:30pm" for copy. */
    public static function cutoffLabel(): string
    {
        [$h, $m] = self::cutoff();
        return \Carbon\Carbon::createFromTime($h, $m, 0, 'America/New_York')->format($m ? 'g:ia' : 'ga');
    }

    /** When today's cutoff passes (Eastern), or null when nothing ships today (weekend, after the cutoff, longer handling). */
    public static function cutoffToday(): ?\Carbon\Carbon
    {
        $ship = self::shipDate();
        $now = now('America/New_York');
        if (!$ship->isSameDay($now)) return null;
        [$h, $m] = self::cutoff();
        return $now->copy()->setTime($h, $m);
    }

    public static function shipDate(): \Carbon\Carbon
    {
        $now = now('America/New_York');
        $handling = (int) \App\Support\Settings::get('shipping.handling_days', 1);
        $d = $now->copy()->startOfDay();
        [$ch, $cm] = self::cutoff();
        if ($handling <= 1) {
            if ($now->isWeekday() && ($now->hour * 60 + $now->minute) < ($ch * 60 + $cm)) return $d;
            do { $d->addDay(); } while ($d->isWeekend());
            return $d;
        }
        for ($i = 0; $i < $handling; $i++) { do { $d->addDay(); } while ($d->isWeekend()); }
        return $d;
    }

    /** "Arrives by Tue, Oct 28" on every rate whose carrier quotes transit days. */
    private function withEta(array $opts, array $items): array
    {
        // a driveshaft built to the customer's measurements ships when it is finished, not by 2 pm
        $built = (bool) array_filter($items, fn ($i) => in_array($i['type'] ?? '', ['custom_build', 'premade_build'], true));
        $ship = self::shipDate();
        return array_map(function ($o) use ($built, $ship) {
            $days = (int) ($o['days'] ?? 0);
            if ($days <= 0) return $o;
            if ($built) { $o['eta'] = trans_choice(':count business day in transit once built|:count business days in transit once built', $days); return $o; }
            $d = $ship->copy();
            for ($i = 0; $i < $days; $i++) { do { $d->addDay(); } while ($d->isWeekend()); }
            $o['eta'] = __('arrives by :date', ['date' => $d->copy()->locale(app()->getLocale())->translatedFormat(['es' => 'D j \\d\\e M', 'fr' => 'D j M', 'zh' => 'n月j日 D', 'ja' => 'n月j日(D)'][app()->getLocale()] ?? 'D, M j')]);
            $o['eta_date'] = $d->toDateString();
            return $o;
        }, $opts);
    }

    /**
     * A shipping option's label in the visitor's language. Labels are stored on the order in
     * English (the admin, the label modal and the e-mails read them), so they are translated
     * only on the way to the screen, fragment by fragment; carrier service names ("UPS
     * Ground", "FedEx Freight Economy") are product names and stay as they are.
     */
    public static function labelFor(string $label): string
    {
        if (app()->getLocale() === 'en' || $label === '') return $label;
        if ($label === 'Free shipping') return __('Free shipping');
        if ($label === config('uds.pickup.label')) return __($label);
        if (preg_match('/^Free shipping on parts \+ (.+) for (.+)$/', $label, $m)) {
            return __('Free shipping on parts + :service for :what', ['service' => self::labelFor($m[1]), 'what' => self::boxesFor($m[2])]);
        }
        $label = preg_replace_callback('/ · (\d+) packages$/', fn ($m) => ' · ' . trans_choice(':count package|:count packages', (int) $m[1]), $label);
        $label = preg_replace_callback('/ · (\d+) pallets$/', fn ($m) => ' · ' . trans_choice(':count pallet|:count pallets', (int) $m[1]), $label);
        return str_replace(' · on a pallet', ' · ' . __('on a pallet'), $label);
    }

    /** "1 tube and 2 driveshafts" → the visitor's language. */
    private static function boxesFor(string $what): string
    {
        return preg_replace_callback('/(\d+) (tubes?|driveshafts?)/', function ($m) {
            $n = (int) $m[1];
            return str_starts_with($m[2], 'tube') ? trans_choice(':count tube|:count tubes', $n) : trans_choice(':count driveshaft|:count driveshafts', $n);
        }, str_replace(' and ', ' ' . __('and') . ' ', $what));
    }

    /** "1 tube and 2 driveshafts" for the rated-parcel labels. */
    private static function describeBoxes(array $boxes): string
    {
        $t = count(array_filter($boxes, fn ($b) => $b['kind'] === 'tube'));
        $d = count($boxes) - $t;
        $parts = [];
        if ($t) $parts[] = $t . ' tube' . ($t === 1 ? '' : 's');
        if ($d) $parts[] = $d . ' driveshaft' . ($d === 1 ? '' : 's');
        return implode(' and ', $parts);
    }

    /** Always offered, always last, never preselected: the customer comes to the shop. */
    public static function pickupOption(): array
    {
        return ['id' => self::PICKUP_ID, 'label' => config('uds.pickup.label'), 'cost' => 0.0];
    }

    public function shippingCost(string $method, string $country, string $state, string $zip = '', ?array $items = null, array $dest = []): ?array
    {
        // Pickup needs no carrier call, but only a U.S. address may choose it.
        if ($method === self::PICKUP_ID) return strtoupper($country ?: 'US') === 'US' && self::pickupEnabled() ? self::pickupOption() : null;
        // The price the customer was shown is the price they pay: a quote for this exact cart,
        // address and delivery type, still fresh, is honoured instead of re-quoted at submit —
        // where a carrier repricing would silently change the total, or a carrier hiccup fail
        // the order outright.
        $held = $this->heldQuote($this->quoteKey($country, $state, $zip, $items, $dest));
        if ($held !== null) {
            foreach ($held as $o) if ($o['id'] === $method) return $o;
        }
        // The ZIP has to come along: live carrier rates only exist when it does,
        // so without it a chosen UPS rate could never be matched here.
        foreach ($this->shippingOptions($country, $state, $zip, $items, $dest) as $o) {
            if ($o['id'] === $method) return $o;
        }
        return null;
    }

    public static function newOrderNumber(): string
    {
        return 'UDS-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
    }

    public function setCoupon(string $code): void { session(['uds_coupon' => $code]); }
    public function getCoupon(): ?string { return session('uds_coupon'); }
    public function clearCoupon(): void { session()->forget('uds_coupon'); }

}
