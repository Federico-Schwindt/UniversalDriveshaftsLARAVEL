<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CouponService;
use App\Services\DsbCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    /** Unpaid pay-at-pickup orders one account may have open at a time. */
    public const OPEN_PICKUP_ORDERS = 2;

    public function __construct(private CartService $cart, private DsbCatalog $catalog, private CouponService $coupons)
    {
    }

    // ---- CART ----------------------------------------------------------

    public function cart()
    {
        $this->settlePending();
        $items = $this->cart->items();
        return view('cart', [
            'items' => $items,
            // parts that go with what is in the cart, matched on fitment (CompleteJob)
            'job' => $items ? app(\App\Services\CompleteJob::class)->forCart(array_values($items)) : ['items' => [], 'build' => false],
            'subtotal' => $this->cart->subtotal(),
            'freeMin' => CartService::freeMin(),
            'freeBase' => $this->cart->freeShippingBase(),
        ]);
    }

    /** POST /cart/add — add a catalog product by id (wishlist, quick re-order). Published products only, at the shopper's price. */
    public function add(Request $r)
    {
        // drafts, private and trashed rows are not for sale, whatever id is posted
        $p = app(\App\Services\CatalogService::class)->productById((int) $r->input('product_id'));
        if (!$p) return response()->json(['success' => false, 'error' => __('Product not found')], 404);
        if ((float) $p['price'] <= 0) return response()->json(['success' => false, 'error' => __('This part has no online price — please call (813) 524-6491 for a quote.')], 422);
        $this->cart->add([
            'type' => 'product', 'wp_product_id' => (int) $p['id'], 'name' => $p['name'], 'sku' => (string) ($p['sku'] ?? ''), 'image' => (string) ($p['image'] ?? ''),
            'price' => self::shopperPrice((float) $p['price']), 'qty' => max(1, (int) $r->input('quantity', 1)),
        ]);
        return $this->cartPayload();
    }

    /** Same price the catalog pages show (CatalogController::price): approved wholesale accounts get their discount. */
    public static function shopperPrice(float $base): float
    {
        $u = \Auth::user();
        if ($u && ($u->wholesale_status ?? '') === 'approved' && ($u->wholesale_discount ?? 0) > 0) {
            return round($base * (1 - ((float) $u->wholesale_discount) / 100), 2);
        }
        return round($base, 2);
    }

    /**
     * A card checkout keeps the cart until Stripe says it is paid, so a declined card can simply be tried again.
     * Once the pending order is paid (webhook, pay page or the return from Stripe), the cart it came from goes.
     */
    private function settlePending(): void
    {
        $id = session('pending_order_id');
        if (!$id) return;
        $o = DB::table('orders')->where('id', $id)->first(['payment_status', 'status']);
        if (!$o || in_array($o->status, ['cancelled', 'refunded'], true)) { session()->forget('pending_order_id'); return; }
        if ($o->payment_status === 'paid') { $this->cart->clear(); session()->forget('pending_order_id'); }
    }

    /** The unpaid card order this session started (a declined or abandoned payment), which a retry updates instead of duplicating. */
    private function pendingOrder(): ?object
    {
        $id = session('pending_order_id');
        if (!$id) return null;
        $o = DB::table('orders')->where('id', $id)->first();
        $ok = $o && $o->payment_status === 'unpaid' && $o->payment_method === 'stripe' && in_array($o->status, ['pending', 'failed'], true)
            && (int) $o->user_id === (int) \Auth::id() && \Carbon\Carbon::parse($o->created_at)->gt(now()->subDays(2))
            && \App\Support\OrderEvents::has($o, 'notify:placed:deferred');
        if (!$ok) { session()->forget('pending_order_id'); return null; }
        return $o;
    }

    /** GET /checkout/pickup-info: the pickup address, asked for only when the customer chooses local pickup (kept out of page source). */
    public function pickupInfo()
    {
        return response()->json(['address' => config('uds.pickup.address'), 'maps' => config('uds.pickup.maps'), 'hours' => config('uds.pickup.hours')])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** GET /cart/mini: the cart as JSON for the slide-in cart panel. */
    public function mini()
    {
        $this->settlePending();
        return $this->cartPayload();
    }

    public function update(Request $r)
    {
        $this->cart->updateQty((string) $r->input('key'), (int) $r->input('qty'));
        return $this->cartPayload();
    }

    public function remove(Request $r)
    {
        $this->cart->remove((string) $r->input('key'));
        return $this->cartPayload();
    }

    private function cartPayload()
    {
        return response()->json(['success' => true, 'data' => $this->cart->mini()]);
    }


    // ---- COUPONS -------------------------------------------------------

    public function applyCoupon(\Illuminate\Http\Request $r)
    {
        $sub = $this->cart->subtotal();
        [$ok, $c, $err] = $this->coupons->validate((string) $r->input('code'), $sub, $this->cart->items(), null, (int) session('pending_order_id') ?: null);
        if (!$ok) return response()->json(['success' => false, 'message' => __($err)], 422);
        $this->cart->setCoupon($c->code);
        $disc = $this->coupons->discountFor($c, $sub, $this->cart->items());
        return response()->json(['success' => true, 'data' => [
            'code' => $c->code, 'discount' => $disc, 'free_shipping' => (bool) $c->free_shipping,
            'label' => $c->type === 'percent' ? ($c->amount . '% off') : ('$' . number_format($c->amount, 2) . ' off'),
        ]]);
    }

    public function removeCoupon()
    {
        $this->cart->clearCoupon();
        return response()->json(['success' => true]);
    }

    /** Resolve the currently-applied coupon into [coupon|null, discount, freeShip]. */
    private function activeCoupon(?string $email = null): array
    {
        $code = $this->cart->getCoupon();
        if (!$code) return [null, 0.0, false];
        [$ok, $c] = $this->coupons->validate($code, $this->cart->subtotal(), $this->cart->items(), $email, (int) session('pending_order_id') ?: null);
        if (!$ok) { $this->cart->clearCoupon(); return [null, 0.0, false]; }
        return [$c, $this->coupons->discountFor($c, $this->cart->subtotal(), $this->cart->items()), (bool) $c->free_shipping];
    }

    // ---- CHECKOUT ------------------------------------------------------

    public function checkout()
    {
        $this->settlePending();
        if (!$this->cart->count()) return redirect('/cart');
        [$cpn,$cpnDisc,$cpnFree] = $this->activeCoupon();
        $u = \Auth::user();
        [$tier, $tierPct, $tierAmt] = \App\Services\B2bAccounts::discount($u, $this->cart->subtotal());
        [$termsOk, $termsOpen, $termsOverdue, $termsLimit, $termsWhy] = \App\Services\B2bAccounts::terms($u);
        $savedCards = [];
        if ($u) { try { $savedCards = \App\Support\StripeCards::list($u); } catch (\Throwable $e) { report($e); } }
        return view('checkout', [
            'authUser' => $u,
            'savedCards' => $savedCards,
            // approved business accounts: the tier discount and pay-by-invoice (Net terms) with a PO number
            'b2b' => $tier ? [
                'tier' => $tier, 'label' => \App\Services\B2bAccounts::label($tier), 'pct' => $tierPct, 'amount' => $tierAmt,
                'terms' => $termsOk, 'termsWhy' => $termsWhy, 'days' => \App\Services\B2bAccounts::termsDays(),
                'due' => now('America/New_York')->addDays(\App\Services\B2bAccounts::termsDays()),
                'available' => max(0, $termsLimit - $termsOpen), 'limit' => $termsLimit,
            ] : null,
            'coupon' => $cpn ? ['code'=>$cpn->code,'discount'=>$cpnDisc,'free_shipping'=>$cpnFree] : null,
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            // Florida sales tax: a pickup is taxed at the shop's county; a delivery's comes with its rates (shippingOptions)
            'taxPickup' => \App\Support\SalesTax::profile('US', 'FL', '', true, $u),
            'freeMin' => CartService::freeMin(),
            'freeBase' => $this->cart->freeShippingBase(),
            'stripeKey' => config('services.stripe.key', ''),
            // a pallet needs the delivery-type question, which wallets cannot ask: no express checkout then
            'freightCart' => (bool) array_filter(app(\App\Services\PackingService::class)->packageSummary($this->cart->items())['boxes'] ?? [], fn ($b) => ($b['kind'] ?? '') === 'pallet'),
            'dropship' => \Auth::check() ? \Illuminate\Support\Facades\DB::table('dropship_addresses')->where('user_id', \Auth::id())->orderByDesc('is_default')->orderByDesc('id')->limit(40)->get() : collect(),
        ]);
    }

    /** POST /checkout/shipping-options — options for the entered address (AJAX). */
    public function shippingOptions(Request $r)
    {
        $country = strtoupper((string) $r->input('country', 'US'));
        $dest = $this->destination($r->only(['street', 'city', 'company', 'delivery']));
        // Apple Pay / Google Pay share only city, state and ZIP until the customer authorises: the quote is held
        // without a street, and placeOrder looks it up the same way (see there)
        if ($r->boolean('express')) { $dest['street'] = ''; $dest['company'] = ''; }
        $pack = app(\App\Services\PackingService::class)->packageSummary($this->cart->items());
        $freight = $country === 'US' && (bool) array_filter($pack['boxes'], fn ($b) => ($b['kind'] ?? '') === 'pallet');
        $meta = [
            'freight' => $freight,                       // the checkout asks how the truck delivers
            'delivery' => $dest['delivery'],
            'international' => $country !== '' && $country !== 'US',
            'ships' => '',   // the live countdown at the top of the checkout already says it
            // how a delivery to this ZIP is taxed (null: no Florida tax); the page works the amount out as the total moves
            'tax' => \App\Support\SalesTax::profile($country, (string) $r->input('state', ''), (string) $r->input('zip', ''), false, \Auth::user()),
        ];

        if (self::isPoBox($dest['street'])) {
            $opts = CartService::pickupFor($country, (string) $r->input('state', '')) ? [CartService::pickupOption()] : [];
            return response()->json(['success' => true, 'data' => $opts, 'carrier_rates' => false, 'reason' => 'po_box'] + $meta);
        }

        $opts = $this->cart->shippingOptions($country, (string) $r->input('state', ''), (string) $r->input('zip', ''), null, $dest);
        $carrier = array_filter($opts, fn ($o) => $o['id'] !== CartService::PICKUP_ID);
        // internal routing flags stay on the server
        $opts = array_map(fn ($o) => ['label' => CartService::labelFor((string) $o['label'])] + array_diff_key($o, ['freight' => 1, 'days' => 1, 'carrier' => 1, 'transit_days' => 1]), $opts);
        return response()->json(['success' => true, 'data' => array_values($opts), 'carrier_rates' => (bool) $carrier] + $meta);
    }

    /** Street, city, company and the freight delivery type, cleaned for a rate request. */
    private function destination(array $in): array
    {
        $delivery = (string) ($in['delivery'] ?? '');
        return [
            'street' => mb_substr(trim((string) ($in['street'] ?? '')), 0, 190),
            'city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 120),
            'company' => mb_substr(trim((string) ($in['company'] ?? '')), 0, 190),
            'delivery' => array_key_exists($delivery, \App\Services\WarpFreightService::DELIVERY) ? $delivery : 'residential',
        ];
    }

    /** UPS and FedEx do not deliver to post-office boxes; better to say so before payment than at the label. */
    public static function isPoBox(string $street): bool
    {
        return (bool) preg_match('/\b(?:p\.?\s*o\.?\s*box|post\s*office\s*box|apartado\s+postal|casilla\s+de\s+correo)\b|\bpmb\s*#?\s*\d/i', $street);
    }

    /** POST /checkout/place — validate, price server-side, create the order. */
    public function place(Request $r)
    {
        // one checkout per session at a time: a double click / second tab can no longer create two orders
        $lock = \Illuminate\Support\Facades\Cache::lock('checkout:' . $r->session()->getId(), 180);   // a slow carrier re-quote + Stripe can pass a minute
        if (!$lock->get()) return response()->json(['success' => false, 'errors' => [__('Your order is already being processed — please wait a moment.')]], 409);
        try { return $this->placeOrder($r); } finally { $lock->release(); }
    }

    private function placeOrder(Request $r)
    {
        $this->settlePending();
        // a retry after a declined card: if that payment actually went through meanwhile, never take a second one
        $pending = $this->pendingOrder();
        if ($pending && $pending->stripe_payment_intent) {
            $state = \App\Support\PayLink::existingState($pending);
            if ($state === 'succeeded') return $this->paidResponse($pending);
            if ($state === 'processing') return response()->json(['success' => false, 'errors' => [__('A payment for this order is already being processed. Please check back in a few minutes — you have not been charged twice.')]], 409);
        }
        if (!$this->cart->count()) return response()->json(['success' => false, 'errors' => [__('Your cart is empty.')]], 422);
        foreach ($this->cart->items() as $line) {
            if (($line['price'] ?? 0) <= 0) return response()->json(['success' => false, 'errors' => [__('An item in your cart has no online price — please call (813) 524-6491 for a quote.')]], 422);
        }

        $v = validator($r->all(), [
            'email' => 'required|email',
            'billing_first_name' => 'required|string|max:80',
            'billing_last_name' => 'required|string|max:80',
            'phone' => 'required|string|max:40',
            'billing_company' => 'nullable|string|max:120',
            'po_number' => 'nullable|string|max:80',
            'ship_instructions' => 'nullable|string|max:500',
            'sms_opt_in' => 'nullable|boolean',
            'billing_address_1' => 'required_unless:shipping_method,local_pickup|nullable|string|max:190',
            'billing_city' => 'required_unless:shipping_method,local_pickup|nullable|string|max:120',
            'billing_state' => 'nullable|string|max:60',
            'billing_postcode' => 'required_unless:shipping_method,local_pickup|nullable|string|max:20',
            'billing_country' => 'required|string|size:2',
            'shipping_method' => 'required|string',
            'pickup_ack' => 'accepted_if:shipping_method,' . CartService::PICKUP_ID,
            'payment_method' => 'nullable|in:card,payment_link,call,pay_at_pickup,net_terms',
            'express' => 'nullable|boolean',
            'dont_save_card' => 'nullable|boolean',
            'saved_card' => 'nullable|string|max:64',
            'agree_terms' => 'accepted',
        ], [
            'pickup_ack.accepted_if' => __('Please confirm you will pick this order up in person at our shop.'),
        ], [
            'billing_first_name' => __('first name'), 'billing_last_name' => __('last name'),
            'billing_address_1' => __('street address'), 'billing_city' => __('city'),
            'billing_postcode' => __('ZIP code'), 'billing_country' => __('country'), 'agree_terms' => __('Terms of Use'),
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()->all()], 422);
        if ($r->boolean('ship_to_different') && $r->input('shipping_method') !== CartService::PICKUP_ID) {
            $sv = validator($r->all(), [
                'shipping_first_name' => 'required|string|max:80', 'shipping_last_name' => 'required|string|max:80', 'shipping_company' => 'nullable|string|max:190',
                'shipping_address_1' => 'required|string|max:190', 'shipping_address_2' => 'nullable|string|max:190',
                'shipping_city' => 'required|string|max:120', 'shipping_state' => 'required_if:shipping_country,US|nullable|string|max:60',
                'shipping_postcode' => 'required|string|max:20', 'shipping_country' => 'required|string|size:2',
            ], [], ['shipping_first_name' => __('shipping first name'), 'shipping_last_name' => __('shipping last name'), 'shipping_address_1' => __('shipping street address'),
                'shipping_city' => __('shipping city'), 'shipping_state' => __('shipping state'), 'shipping_postcode' => __('shipping ZIP code'), 'shipping_country' => __('shipping country')]);
            if ($sv->fails()) return response()->json(['success' => false, 'errors' => $sv->errors()->all()], 422);
        }

        $isPickup = $r->input('shipping_method') === CartService::PICKUP_ID;
        $netTerms = $r->input('payment_method') === 'net_terms';
        if ($netTerms && !\App\Services\B2bAccounts::approved(\Auth::user())) {
            return response()->json(['success' => false, 'errors' => [__('Paying by invoice is for approved business accounts. Please choose another payment method.')]], 422);
        }
        $payAtPickup = !$netTerms && $isPickup && $r->input('payment_method') === 'pay_at_pickup';
        if (!$isPickup && $r->input('payment_method') === 'pay_at_pickup') {
            return response()->json(['success' => false, 'errors' => [__('Pay at pickup is only available with local pickup. Please choose another payment method.')]], 422);
        }
        // nothing is paid up front, yet the order takes the parts off the shelf, texts and e-mails, and queues shop work:
        // only for a signed-in account with a confirmed e-mail, and a couple of open ones at a time
        if ($payAtPickup && !\Auth::user()?->email_verified_at) {
            return response()->json(['success' => false, 'errors' => [__('Pay at pickup needs a signed-in account with a confirmed e-mail. Sign in, or pay by card now.')]], 422);
        }
        if ($payAtPickup && DB::table('orders')->where('user_id', \Auth::id())->where('payment_method', 'pay_at_pickup')->where('payment_status', 'unpaid')
                ->whereNotIn('status', ['completed', 'cancelled', 'refunded', 'failed'])->count() >= self::OPEN_PICKUP_ORDERS) {
            return response()->json(['success' => false, 'errors' => [__('You already have orders waiting to be paid at pickup. Pay this one by card, or call (813) 524-6491.')]], 422);
        }

        // a saved card must be one of this account's own cards
        $savedCard = trim((string) $r->input('saved_card', ''));
        if ($savedCard !== '' && !$netTerms && !$payAtPickup) {
            if (!\Auth::check() || !\App\Support\StripeCards::find(\Auth::user(), $savedCard)) {
                return response()->json(['success' => false, 'errors' => [__('That saved card is not available. Please choose another card.')]], 422);
            }
        } else {
            $savedCard = '';
        }

        // price shipping against the address the parts actually go to
        $toShip = $r->boolean('ship_to_different') ? 'shipping' : 'billing';
        $country = strtoupper((string) $r->input("{$toShip}_country", $r->input('billing_country')));
        $state = strtoupper((string) $r->input("{$toShip}_state", ''));
        $zip = (string) $r->input("{$toShip}_postcode", '');
        $dest = $this->destination([
            'street' => $r->input("{$toShip}_address_1", $r->input('billing_address_1')),
            'city' => $r->input("{$toShip}_city", $r->input('billing_city')),
            'company' => $r->input("{$toShip}_company", $r->input('billing_company')),
            'delivery' => $r->input('freight_delivery'),
        ]);
        if ($r->input('shipping_method') !== CartService::PICKUP_ID && self::isPoBox($dest['street'])) {
            return response()->json(['success' => false, 'errors' => [__('UPS and FedEx cannot deliver to a PO box. Please enter a street address, or choose local pickup.')]], 422);
        }
        // wallet checkout: the rate was quoted before the wallet revealed the street, so it is honoured the same way
        if ($r->boolean('express')) { $dest['street'] = ''; $dest['company'] = ''; }
        $ship = $this->cart->shippingCost($r->input('shipping_method'), $country, $state, $zip, null, $dest);
        if (!$ship) return response()->json(['success' => false, 'errors' => [__('The selected shipping method is not available for your address.')]], 422);

        $subtotal = $this->cart->subtotal();
        $hadCoupon = (bool) $this->cart->getCoupon();
        [$cpn, $cpnDisc, $cpnFree] = $this->activeCoupon((string) $r->input('email'));
        if ($hadCoupon && !$cpn) return response()->json(['success' => false, 'errors' => [__('The coupon on your cart is no longer valid for this order. It has been removed — please review the new total and place the order again.')]], 422);
        // a free-shipping coupon covers parcels, not a truck (an LTL pallet runs $450–$1,000), and only inside the
        // continental U.S. — never international or AK/HI/territories (owner, 2026-11-01)
        $cpnFree = $cpnFree && self::freeShipCountry($country, $state);
        $shipCost = ($cpnFree && !CartService::isFreight((string) $ship['id'])) ? 0.0 : (float) $ship['cost'];
        // a business account's tier discount and a coupon's money-off do not stack: the larger one applies
        // (a free-shipping coupon still covers the shipping)
        [$tier, , $tierAmt] = \App\Services\B2bAccounts::discount(\Auth::user(), $subtotal);
        $useTier = $tierAmt > $cpnDisc;
        $disc = $useTier ? $tierAmt : $cpnDisc;
        if ($useTier && $cpn && !$cpnFree) $cpn = null;   // the coupon added nothing; leave it unused
        // Florida sales tax: deliveries in Florida and pickups at the shop, unless the account has a certificate on file
        $taxProfile = \App\Support\SalesTax::profile($country, $state, $zip, $isPickup, \Auth::user());
        $tax = \App\Support\SalesTax::amount($taxProfile, $this->cart->items(), $disc, $shipCost);
        $total = round(max(0, $subtotal - $disc) + $shipCost + $tax, 2);
        if ($netTerms) {
            [$ok, , , , $why] = \App\Services\B2bAccounts::terms(\Auth::user(), $total);
            if (!$ok) return response()->json(['success' => false, 'errors' => [match ($why) {
                'overdue' => __('Paying by invoice is paused while an invoice is past due. Pay it from your business account page, or pay this order by card.'),
                'limit' => __('This order is over the credit available on your account. Pay by card, or call (813) 524-6491 to raise your limit.'),
                default => __('Paying by invoice is not available on this account. Please pay by card.'),
            }]], 422);
        }
        $cpnDisc = $disc;
        // what the customer saw on the button is what gets charged: a carrier repricing, a coupon or tier change, or the
        // cart edited in another tab stops the order with the new numbers instead of charging a different amount
        if ($r->filled('expected_total') && abs((int) round($total * 100) - (int) $r->input('expected_total')) > 1) {
            return response()->json(['success' => false, 'code' => 'total_changed',
                'errors' => [__('Your total changed to :amount — please review it and place the order again.', ['amount' => '$' . number_format($total, 2)])],
                'totals' => ['subtotal' => $subtotal, 'discount' => $disc, 'shipping' => $shipCost, 'tax' => $tax, 'total' => $total]], 409);
        }
        $stripeCard = \App\Support\PayLink::stripeReady() && !$payAtPickup && !$netTerms;
        if ($pending && $pending->stripe_payment_intent && \App\Support\PayLink::stripeReady() && ($stop = $this->retireIntent($pending))) return $stop;

        $order = DB::transaction(function () use ($r, $ship, $subtotal, $total, $shipCost, $cpnDisc, $cpn, $payAtPickup, $dest, $netTerms, $useTier, $tier, $tierAmt, $pending, $stripeCard, $tax, $taxProfile) {
            // the last use of a limited coupon goes to one checkout only (the row lock makes the other one wait for this commit)
            if ($cpn && !$this->coupons->claim($cpn, (string) $r->input('email'), $pending->id ?? null)) return 'coupon';
            $row = [
                'locale' => app()->getLocale(),   // e-mails and texts go out in it
                'user_id' => \Auth::id(),
                // on terms the order goes straight into production; the invoice stays unpaid until it is paid
                'status' => $netTerms ? 'processing' : 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $netTerms ? 'net_terms' : ($payAtPickup ? 'pay_at_pickup' : (config('services.stripe.secret') ? 'stripe' : 'manual')),
                'terms_due_at' => $netTerms ? now('America/New_York')->addDays(\App\Services\B2bAccounts::termsDays())->toDateString() : null,
                'b2b_tier' => $tier,
                'tier_discount' => $useTier ? $tierAmt : 0,
                'email' => $r->input('email'),
                'phone' => $r->input('phone'),
                'sms_opt_in' => $r->boolean('sms_opt_in') && \App\Services\SmsService::on(),
                'billing_first_name' => $r->input('billing_first_name'),
                'billing_last_name' => $r->input('billing_last_name'),
                'billing_company' => $r->input('billing_company'),
                'billing_address_1' => $r->input('billing_address_1'),
                'billing_address_2' => $r->input('billing_address_2'),
                'billing_city' => $r->input('billing_city'),
                'billing_state' => $r->input('billing_state'),
                'billing_postcode' => $r->input('billing_postcode'),
                'billing_country' => strtoupper($r->input('billing_country', 'US')),
                'ship_to_different' => $r->boolean('ship_to_different'),
                'shipping_first_name' => $r->input('shipping_first_name'),
                'shipping_last_name' => $r->input('shipping_last_name'),
                // the ship-to company priced the rate (business vs home) and goes on the label
                'shipping_company' => $r->boolean('ship_to_different') ? (mb_substr(trim((string) $r->input('shipping_company', '')), 0, 190) ?: null) : null,
                'shipping_address_1' => $r->input('shipping_address_1'),
                'shipping_address_2' => $r->input('shipping_address_2'),
                'shipping_city' => $r->input('shipping_city'),
                'shipping_state' => $r->input('shipping_state'),
                'shipping_postcode' => $r->input('shipping_postcode'),
                'shipping_country' => $r->input('shipping_country'),
                'shipping_method' => $ship['id'],
                'shipping_label' => $ship['label'],
                // how the freight truck must deliver — the booking reuses it so the bill matches the quote
                'freight_delivery' => CartService::isFreight((string) $ship['id']) ? $dest['delivery'] : null,
                'subtotal' => $subtotal,
                'shipping_total' => $shipCost,
                'discount_total' => $cpnDisc,
                'coupon_code' => $cpn->code ?? null,
                // where the Florida tax went, for the DR-15 return (an exempt Florida sale keeps its certificate number)
                'tax_total' => $tax,
                'tax_county' => $taxProfile['county'] ?? null,
                'tax_rate' => $taxProfile ? $taxProfile['rate'] : null,
                'taxable_total' => $taxProfile ? round(max(0, $subtotal - $cpnDisc) + ($taxProfile['shipping'] ? $shipCost : 0), 2) : null,
                'tax_exempt_cert' => ($taxProfile['exempt'] ?? false) ? (\Auth::user()->tax_cert_number ?: 'on file') : null,
                'total' => $total,
                'customer_note' => (string) $r->input('customer_note', ''),
                'po_number' => (string) $r->input('po_number', '') ?: null,
                'signature_required' => $r->boolean('signature_required'),
                'ship_instructions' => (string) $r->input('ship_instructions', '') ?: null,
                'ip' => $r->ip(),
                'updated_at' => now(),
            ];
            if ($pending) {
                // the same checkout tried again: one order, re-priced to what is in the cart now — unless the webhook
                // marked it paid since it was read (a paid order is never re-priced or turned unpaid again)
                $orderId = $pending->id;
                $now = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first(['payment_status', 'status']);
                if (!$now || $now->payment_status !== 'unpaid' || !in_array($now->status, ['pending', 'failed'], true)) return 'changed';
                DB::table('orders')->where('id', $orderId)->update($row);
                DB::table('order_items')->where('order_id', $orderId)->delete();
            } else {
                $orderId = DB::table('orders')->insertGetId($row + ['order_number' => CartService::newOrderNumber(), 'created_at' => now()]);
            }
            foreach ($this->cart->items() as $i) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId, 'type' => $i['type'],
                    'wp_product_id' => $i['wp_product_id'], 'name' => $i['name'], 'sku' => $i['sku'],
                    'price' => $i['price'], 'qty' => $i['qty'],
                    'line_total' => round($i['price'] * $i['qty'], 2),
                    'meta' => json_encode($i['meta'] ?? []),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $order = DB::table('orders')->find($orderId);
            // an invoice / pickup order is committed now, so it uses the coupon under the same lock (placedNotices then
            // skips it); a card order holds it until paid (CouponService::taken)
            if ($cpn && !$stripeCard && \App\Support\OrderEvents::record($order, 'notify:coupon:used', 'Coupon counted', $cpn->code, true)) $this->coupons->markUsed($cpn->code);
            return $order;
        });
        if ($order === 'coupon') {
            $this->cart->clearCoupon();
            return response()->json(['success' => false, 'errors' => [__('The coupon on your cart is no longer valid for this order. It has been removed — please review the new total and place the order again.')]], 422);
        }
        if ($order === 'changed') {
            $now = DB::table('orders')->find($pending->id);
            if ($now && $now->payment_status === 'paid') return $this->paidResponse($now);
            session()->forget('pending_order_id');
            return response()->json(['success' => false, 'errors' => [__('Your earlier order changed while you were checking out. Please review your cart and place the order again.')]], 409);
        }

        // Stripe PaymentIntent when keys are configured; otherwise manual-payment mode.
        $clientSecret = null;
        if ($stripeCard) {
            // the coupon, the "order received" text and the CRM sync wait for the money (PayLink::markPaid)
            \App\Support\OrderEvents::record($order, 'notify:placed:deferred', 'Waiting for card payment', null, true);
            session(['pending_order_id' => $order->id]);
            $clientSecret = $this->stripeIntent($order, \App\Support\StripeCards::intentParams(\Auth::user(), !$r->boolean('dont_save_card'), $savedCard ?: null));
            if ($clientSecret === null) {
                // never a live order without a way to pay it; a retry from this session picks the same row up again
                DB::table('orders')->where('id', $order->id)->update(['status' => 'failed', 'updated_at' => now()]);
                \App\Support\OrderEvents::record($order, 'payment_init_failed', 'Card payment could not be started', 'Stripe did not create the payment; the customer was asked to try again.');
                return response()->json(['success' => false, 'errors' => [__('Payment could not be initialized. Please try again or call us.')]], 500);
            }
        } elseif ($pending && $pending->stripe_payment_intent) {
            // switched from card to invoice / pay at pickup: the card payment started earlier must never go through
            $this->cancelIntent($pending->stripe_payment_intent);
            DB::table('orders')->where('id', $order->id)->update(['stripe_payment_intent' => null]);
        }

        // order timeline (drives /orders/{number}/status)
        \App\Support\OrderEvents::record($order, 'placed', 'Order placed', $isPickup ? 'Local pickup' : ($order->shipping_label ?: null), true);
        if ($netTerms) {
            \App\Support\OrderEvents::record($order, 'terms', 'On Net ' . \App\Services\B2bAccounts::termsDays() . ' terms', 'Invoice due ' . \Carbon\Carbon::parse($order->terms_due_at)->format('M j, Y') . ($order->po_number ? ' · PO ' . $order->po_number : ''), true);
            \App\Support\OrderEvents::record($order, 'processing', 'In production / packing', null, true);
            try { \App\Services\B2bAccounts::evaluate((int) $order->user_id); } catch (\Throwable $e) { report($e); }
        }
        // confirmation to the customer + build sheet to the shop. Read the items
        // back from the DB so the e-mail is exactly what was persisted.
        // A card / wallet / Affirm order is confirmed by e-mail once Stripe says it is paid (PayLink::markPaid):
        // sent now, it went out as "not paid yet" with a link to pay again. Invoice, pickup and manual orders go now,
        // with the coupon, the consented "order received" text and the CRM sync; a card order keeps its cart until paid.
        if ($clientSecret === null) {
            \App\Services\OrderMailer::confirmOnce($order);
            \App\Support\PayLink::placedNotices($order);
            $this->cart->clear();
            session()->forget('pending_order_id');
        }
        return response()->json(['success' => true, 'data' => [
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'client_secret' => $clientSecret,
            'redirect' => '/order-received/' . $order->order_number . '?email=' . urlencode($order->email),
        ]]);
    }

    private function stripeIntent(object $order, array $extra = []): ?string
    {
        $params = [
            'amount' => (int) round($order->total * 100),
            'currency' => 'usd',
            'description' => 'Order ' . $order->order_number,
            'receipt_email' => $order->email,
            'metadata[order_number]' => $order->order_number,
        ] + $extra + \App\Support\PayLink::methodParams((float) $order->total, (string) ($order->billing_country ?: 'US'));
        // a retry of the same order replaces its earlier, unpaid intent (cancelled, so only one can ever be paid)
        $key = 'checkout-' . $order->order_number;
        if ($order->stripe_payment_intent) {
            $this->cancelIntent($order->stripe_payment_intent);
            $key .= '-' . substr(md5($order->stripe_payment_intent . json_encode($params)), 0, 12);
        }
        try {
            // a timed-out request retried by Stripe's client or a double submit never makes a second intent
            $resp = \Http::asForm()->withToken(config('services.stripe.secret'))->withHeaders(['Idempotency-Key' => $key])
                ->post('https://api.stripe.com/v1/payment_intents', $params);
        } catch (\Throwable $e) { \Log::warning('checkout intent ' . $order->order_number . ': ' . $e->getMessage()); return null; }
        if (!$resp->successful()) { \Log::warning('checkout intent ' . $order->order_number . ': ' . $resp->json('error.message')); return null; }
        DB::table('orders')->where('id', $order->id)->update(['stripe_payment_intent' => $resp->json('id')]);
        return $resp->json('client_secret');
    }

    /** Best effort: an unpaid intent that a retry replaced. Stripe refuses to cancel one that already succeeded, which is what we want. */
    private function cancelIntent(string $pi): void
    {
        try {
            \Http::asForm()->withToken(config('services.stripe.secret'))->post('https://api.stripe.com/v1/payment_intents/' . rawurlencode($pi) . '/cancel');
        } catch (\Throwable $e) { report($e); }
    }

    /**
     * A retry is about to re-price the unpaid card order: its earlier payment is cancelled first, so it can no longer go
     * through for the old cart. Null once Stripe confirms it is cancelled; otherwise the answer for the customer (it was
     * paid meanwhile, it is still clearing, or Stripe could not be asked).
     */
    private function retireIntent(object $pending): ?\Illuminate\Http\JsonResponse
    {
        $url = 'https://api.stripe.com/v1/payment_intents/' . rawurlencode($pending->stripe_payment_intent);
        try {
            $st = \Http::asForm()->withToken(config('services.stripe.secret'))->post($url . '/cancel')->json('status');
            // refused: an earlier retry already cancelled it, or it went through — ask Stripe what it is now
            if ($st !== 'canceled') $st = \Http::withToken(config('services.stripe.secret'))->get($url)->json('status');
        } catch (\Throwable $e) { report($e); $st = null; }
        if ($st === 'canceled') return null;
        if ($st === 'succeeded' && \App\Support\PayLink::existingState($pending) === 'succeeded') return $this->paidResponse($pending);
        if (in_array($st, ['succeeded', 'processing'], true)) {
            return response()->json(['success' => false, 'errors' => [__('A payment for this order is already being processed. Please check back in a few minutes — you have not been charged twice.')]], 409);
        }
        return response()->json(['success' => false, 'errors' => [__('We could not reach the card processor. Please try again in a moment.')]], 503);
    }

    /** The answer to a checkout whose order turned out to be paid already: its cart is done with, show the receipt. */
    private function paidResponse(object $order): \Illuminate\Http\JsonResponse
    {
        $this->cart->clear(); session()->forget('pending_order_id');
        return response()->json(['success' => true, 'data' => ['order_number' => $order->order_number, 'total' => (float) $order->total, 'client_secret' => null,
            'redirect' => '/order-received/' . $order->order_number . '?email=' . urlencode($order->email)]]);
    }

    // ---- CONFIRMATION + TRACKING --------------------------------------

    public function received(Request $r, string $number)
    {
        $order = DB::table('orders')->where('order_number', $number)->first();
        abort_unless($order && trim((string) $order->email) !== '' && strcasecmp($order->email, (string) $r->query('email')) === 0, 404);
        // the customer lands here straight from Stripe, usually before the webhook: ask Stripe directly
        $processing = false;
        if ($order->payment_status === 'unpaid' && $order->stripe_payment_intent) {
            $processing = \App\Support\PayLink::existingState($order) === 'processing';
            $order = DB::table('orders')->find($order->id);
        }
        $order->payment_processing = $processing;
        // paid (or Stripe is clearing it): the cart this checkout came from is done with
        if ((int) session('pending_order_id') === (int) $order->id && ($order->payment_status === 'paid' || $processing)) {
            $this->cart->clear(); session()->forget('pending_order_id');
        }
        // a card saved with this payment shows up in the account right away (or goes, if they opted out)
        if ($order->payment_status === 'paid' && $order->stripe_payment_intent) \App\Support\StripeCards::dropIfUnwanted($order->stripe_payment_intent);
        if (\Auth::check()) \App\Support\StripeCards::forget(\Auth::user());
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        return view('order-received', ['order' => $order, 'items' => $items]);
    }

    /** POST /track-order — now looks up REAL orders (replaces the interim notice). */
    public function track(Request $r)
    {
        $number = trim((string) $r->input('orderid'));
        $email = trim((string) $r->input('order_email'));
        $order = DB::table('orders')
            ->where(function ($q) use ($number) {
                $q->where('order_number', $number)->orWhere('order_number', 'UDS-' . $number);
            })
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();
        if (!$order) {
            return back()->with('track_notice', 'We could not find an order matching that ID and email. Double-check both, or call (813) 524-6491 and we will look it up for you.');
        }
        return redirect(\App\Support\OrderEvents::statusUrl($order, false));
    }

    /** POST /stripe/webhook — verify signature, mark orders paid. */
    public function webhook(\Illuminate\Http\Request $r)
    {
        $secret = config('services.stripe.webhook_secret');
        $payload = $r->getContent();
        $sig = $r->header('Stripe-Signature');
        // never act on an unverified event: no configured secret means Stripe is not live here,
        // and a missing signature means it did not come from Stripe
        if (!config('services.stripe.enabled') || !$secret) return response('stripe webhook not enabled', 403);
        if (!$sig) return response('missing signature', 400);
        {
            // verify signature (t + v1) per Stripe spec; while a secret is being rolled Stripe signs with
            // both, so the header carries several v1 values and any one matching is enough
            $t = ''; $v1s = [];
            foreach (explode(',', $sig) as $p) {
                [$k, $v] = array_pad(explode('=', trim($p), 2), 2, '');
                if ($k === 't') $t = $v; elseif ($k === 'v1' && $v !== '') $v1s[] = $v;
            }
            $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
            $match = (bool) array_filter($v1s, fn ($v) => hash_equals($expected, $v));
            if (!$match || $t === '' || abs(time() - (int) $t) > 600) return response('bad signature', 400);
        }
        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $obj = $event['data']['object'] ?? [];
        // one payment fires both events: the PaymentIntent is the one acted on. A charge only counts when it has
        // no PaymentIntent behind it (a legacy direct charge), or it would be a second "paid" for the same money.
        if ($type === 'payment_intent.succeeded' || ($type === 'charge.succeeded' && empty($obj['payment_intent']))) {
            $num = $obj['metadata']['order_number'] ?? ($obj['description'] ?? '');
            $num = str_replace('Order ', '', $num);
            $pi = $obj['id'] ?? ($obj['payment_intent'] ?? '');
            $q = \Illuminate\Support\Facades\DB::table('orders');
            $order = $num ? $q->where('order_number', $num)->first() : ($pi ? $q->where('stripe_payment_intent', $pi)->first() : null);
            // the amount Stripe took must cover the order (checked inside markPaid)
            if ($order) \App\Support\PayLink::markPaid($order, 'Card payment via Stripe', 'stripe', (array) $obj);
            if ($type === 'payment_intent.succeeded' && ($obj['metadata']['dont_save_card'] ?? '') === '1') \App\Support\StripeCards::dropIfUnwanted((string) $pi);
        }
        return response('ok', 200);
    }

    /** Where a free-shipping coupon applies: the continental U.S. (same rule as the $1,000 free shipping). */
    public static function freeShipCountry(string $country, string $state): bool
    {
        return strtoupper($country) === 'US' && !in_array(strtoupper($state), CartService::NON_CONTIGUOUS, true);
    }

    /** "ups_walleted" -> "UPS", "fedex_walleted" -> "FedEx", "dhl_express_worldwide" -> "DHL" */
    public static function carrierLabel(string $code): string
    {
        $c = strtolower($code);
        if (str_contains($c, 'ups')) return 'UPS';
        if (str_contains($c, 'fedex')) return 'FedEx';
        if (str_contains($c, 'usps') || str_contains($c, 'stamps')) return 'USPS';
        if (str_contains($c, 'dhl')) return 'DHL';
        return strtoupper(str_replace('_', ' ', $code)) ?: 'UPS';
    }

}
