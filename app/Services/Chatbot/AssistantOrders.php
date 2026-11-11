<?php

namespace App\Services\Chatbot;

use App\Http\Controllers\ShopController;
use App\Models\User;
use App\Services\B2bAccounts;
use App\Services\CartService;
use App\Support\OrderEvents;
use App\Support\PayLink;
use App\Support\StripeCards;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What UDS Assistant can do on the customer's account: read it (business tier, invoices, addresses, saved
 * cards), manage the cart, and place an order for them.
 *
 * Placing an order is two steps on purpose: prepare() prices the cart for an address, a shipping option and a
 * payment method and parks that draft; place() only runs it after the customer confirmed it in a new message —
 * the summary card's Yes button (which carries the draft's nonce) or a short typed "yes" — and only if the
 * cart and prices are still the same.
 */
class AssistantOrders
{
    private const PAYMENTS = ['saved_card', 'net_terms', 'pay_link'];
    public const DRAFT_MINUTES = 20;
    /** A typed confirmation: short, affirmative (en / es / fr), and without a "no" / "wait" / "change" in it. */
    private const YES = "/(?<!\\p{L})(yes|yep|yeah|sure|ok|okay|confirm|confirmed|place it|go ahead|s[ií]|dale|confirmo|oui|d'accord)(?!\\p{L})/iu";
    private const NOT = "/(?<!\\p{L})(no|not|nope|don't|dont|wait|change|cancel|cambia|cambiar|espera|non|attends|annule)(?!\\p{L})/iu";
    /** Same cart ordered again this soon is more likely a repeat than a second order. */
    private const REPEAT_MINUTES = 10;

    public function __construct(private CartService $cart) {}

    /* ------------------------------------------------------------ account */

    public function account(User $u): array
    {
        $out = [
            'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'company' => $u->company,
            'business_account' => ['status' => $u->wholesale_status ?: 'none'],
            'addresses' => $this->addresses($u),
            'saved_cards' => array_map(fn ($c) => ['card_id' => $c['id'], 'card' => $c['label'], 'expires' => $c['exp']], $this->cards($u)),
            'cart' => ['items' => $this->cart->count(), 'subtotal_usd' => $this->cart->subtotal()],
        ];
        if (B2bAccounts::approved($u)) {
            $st = B2bAccounts::status($u);
            $tiers = $st['tiers'];
            [$termsOk, $balance, $overdue, $limit, $why] = B2bAccounts::terms($u);
            $out['business_account'] += [
                'tier' => B2bAccounts::label($st['tier']),
                'tier_rule' => sprintf('Bronze %s%% from approval, Silver %s%% from $%s a month, Gold %s%% from $%s a month. Reaching a level applies at once and holds through next month; it drops on the 1st if last month fell short.',
                    $tiers['bronze']['pct'], $tiers['silver']['pct'], number_format($tiers['silver']['min']), $tiers['gold']['pct'], number_format($tiers['gold']['min'])),
                'spend_this_month_usd' => $st['mtd'],
                'spend_last_month_usd' => $st['last'],
                'next_tier' => $st['next'] ? $tiers[$st['next']]['name'] : null,
                'to_reach_next_tier_usd' => $st['next'] ? $st['toNext'] : null,
                'extra_catalog_discount_pct' => (float) $u->wholesale_discount ?: null,
                'net_terms' => [
                    'available' => $termsOk, 'days' => B2bAccounts::termsDays(), 'credit_limit_usd' => $limit,
                    'open_balance_usd' => $balance, 'credit_available_usd' => max(0, round($limit - $balance, 2)), 'overdue_invoices' => $overdue,
                    'why_not' => $termsOk ? null : $why,
                ],
                'open_invoices' => B2bAccounts::openInvoices($u->id)->map(fn ($o) => [
                    'order_number' => $o->order_number, 'total_usd' => (float) $o->total, 'balance_usd' => PayLink::balance($o),
                    'due' => $o->terms_due_at, 'po' => $o->po_number, 'pay_link' => PayLink::url($o),
                ])->all(),
                'page' => '/account/business',
            ];
        } else {
            $out['business_account']['how_to_apply'] = 'Call apply_for_business_account with the business details, or the customer can use /wholesale.';
        }
        return $out;
    }

    private function cards(User $u): array
    {
        try { return StripeCards::list($u); } catch (\Throwable $e) { report($e); return []; }
    }

    /** Address book (own + dropship) plus the billing address on the profile, each with an id the model can pass back. */
    private function addresses(User $u): array
    {
        $out = [];
        if ($u->billing_address_1) {
            $out[] = ['address_id' => 'profile', 'kind' => 'billing (profile)', 'name' => $u->name, 'company' => $u->company,
                'street' => trim($u->billing_address_1 . ' ' . $u->billing_address_2), 'city' => $u->billing_city, 'state' => $u->billing_state,
                'zip' => $u->billing_postcode, 'country' => $u->billing_country ?: 'US', 'default' => false];
        }
        foreach (DB::table('dropship_addresses')->where('user_id', $u->id)->orderByDesc('is_default')->orderByDesc('id')->limit(40)->get() as $a) {
            $out[] = ['address_id' => (string) $a->id, 'kind' => $a->kind === 'dropship' ? 'dropship (a customer of theirs)' : 'shipping', 'label' => $a->label,
                'name' => $a->name, 'company' => $a->company, 'street' => trim($a->street . ' ' . $a->unit), 'city' => $a->city, 'state' => $a->state,
                'zip' => $a->postcode, 'country' => $a->country ?: 'US', 'residential' => (bool) $a->residential, 'default' => (bool) $a->is_default];
        }
        return $out;
    }

    /** One address in order-field form, or null. */
    private function address(User $u, string $id): ?array
    {
        if ($id === 'profile') {
            if (!$u->billing_address_1) return null;
            [$f, $l] = $this->splitName($u->name ?: $u->email);
            return ['first' => $f, 'last' => $l, 'company' => $u->company, 'street' => $u->billing_address_1, 'unit' => $u->billing_address_2,
                'city' => $u->billing_city, 'state' => $u->billing_state, 'zip' => $u->billing_postcode, 'country' => strtoupper($u->billing_country ?: 'US'),
                'phone' => $u->phone, 'residential' => true, 'kind' => 'profile'];
        }
        $a = ctype_digit($id) ? DB::table('dropship_addresses')->where('user_id', $u->id)->where('id', (int) $id)->first() : null;
        if (!$a) return null;
        [$f, $l] = $this->splitName($a->name ?: ($a->company ?: $u->name));
        return ['first' => $f, 'last' => $l, 'company' => $a->company, 'street' => $a->street, 'unit' => $a->unit, 'city' => $a->city,
            'state' => $a->state, 'zip' => $a->postcode, 'country' => strtoupper($a->country ?: 'US'), 'phone' => $a->phone ?: $u->phone,
            'residential' => (bool) $a->residential, 'kind' => $a->kind];
    }

    private function splitName(string $name): array
    {
        $p = preg_split('/\s+/', trim($name), 2);
        return [$p[0] ?: 'Customer', $p[1] ?? ''];
    }

    /* ------------------------------------------------------------ cart */

    public function viewCart(): array
    {
        $items = array_values(array_map(fn ($i) => ['sku' => $i['sku'], 'name' => $i['name'], 'qty' => (int) $i['qty'], 'price_usd' => (float) $i['price'],
            'line_usd' => round($i['price'] * $i['qty'], 2)], $this->cart->items()));
        $coupon = $this->cart->getCoupon();
        return ['items' => $items, 'count' => $this->cart->count(), 'subtotal_usd' => $this->cart->subtotal(),
            'coupon' => $coupon ?: null, 'cart_url' => '/cart', 'checkout_url' => '/checkout'];
    }

    public function setQty(string $sku, int $qty): array
    {
        $sku = trim($sku);
        foreach ($this->cart->items() as $key => $i) {
            if (strcasecmp($i['sku'], $sku) === 0) {
                $this->cart->updateQty($key, max(0, min(999, $qty)));
                return ['updated' => true, 'sku' => $i['sku'], 'qty' => max(0, $qty), 'removed' => $qty <= 0] + $this->viewCart();
            }
        }
        return ['updated' => false, 'error' => "$sku is not in the cart."] + $this->viewCart();
    }

    /** Put the lines of a past order back in the cart, at today's prices. */
    public function reorder(User $u, object $order, \Closure $add): array
    {
        $added = []; $skipped = [];
        foreach (DB::table('order_items')->where('order_id', $order->id)->get() as $i) {
            if (!$i->sku || $i->type === 'custom') { $skipped[] = $i->name; continue; }
            [$res] = $add($i->sku, (int) $i->qty);
            if (!empty($res['added'])) $added[] = ['sku' => $i->sku, 'qty' => (int) $i->qty];
            else $skipped[] = $i->sku . ($res['error'] ?? '' ? ' (' . $res['error'] . ')' : '');
        }
        return ['added' => $added, 'not_added' => $skipped, 'note' => 'Prices are today\'s prices, not the old order\'s.'] + $this->viewCart();
    }

    /* ------------------------------------------------------------ orders */

    private function draftKey(int $convId): string
    {
        return 'assistant_order:' . $convId;
    }

    /**
     * Called by ChatbotController on every customer message: a tap on the summary's Yes button carries the draft's
     * nonce and is remembered; any other message (incl. "Change something") clears an earlier tap.
     */
    public static function confirmTap(int $convId, ?string $nonce): void
    {
        $key = 'assistant_order:' . $convId . ':ok';
        if ($nonce !== null && $nonce !== '') Cache::put($key, $nonce, now()->addMinutes(self::DRAFT_MINUTES));
        else Cache::forget($key);
    }

    /** Is this customer line a clear yes (and not "yes but change…")? */
    public static function saysYes(string $text): bool
    {
        $t = trim($text);
        return $t !== '' && mb_strlen($t) <= 60 && preg_match(self::YES, $t) && !preg_match(self::NOT, $t);
    }

    private function recentKey(int $userId): string
    {
        return 'assistant_recent_order:' . $userId;
    }

    /** An order this customer placed through the chat in the last few minutes with exactly this cart, if any. */
    private function recentSame(User $u): ?array
    {
        $r = Cache::get($this->recentKey($u->id));
        return $r && ($r['cart'] ?? null) === $this->cartHash() ? $r : null;
    }

    private function cartHash(): string
    {
        return md5(json_encode(array_map(fn ($i) => [$i['sku'], (int) $i['qty'], (float) $i['price']], array_values($this->cart->items()))));
    }

    /**
     * Price the cart for an address, shipping option and payment method. With no shipping option yet, it
     * returns the options to choose from.
     */
    public function prepare(User $u, object $conv, int $lastMsgId, array $in): array
    {
        if (!$this->cart->count()) return ['ready' => false, 'error' => 'The cart is empty. Add the parts first (add_to_cart or reorder).'];
        foreach ($this->cart->items() as $i) {
            if (($i['price'] ?? 0) <= 0) return ['ready' => false, 'error' => "{$i['sku']} has no online price; the shop has to quote it (request_human_agent)."];
        }
        if ($this->cart->getCoupon()) return ['ready' => false, 'error' => 'There is a coupon on the cart; coupons are applied at checkout, so send the customer to /checkout to finish.'];
        // the same cart was just ordered: ask before a second identical order (allowed once they answer in a new message)
        $repeatOk = false;
        if ($same = $this->recentSame($u)) {
            $warn = Cache::get($this->recentKey($u->id) . ':warned');
            if (!$warn || $warn['cart'] !== $same['cart'] || $lastMsgId <= $warn['msg']) {
                Cache::put($this->recentKey($u->id) . ':warned', ['cart' => $same['cart'], 'msg' => $lastMsgId], now()->addMinutes(self::REPEAT_MINUTES));
                return ['ready' => false, 'error' => "This customer placed order {$same['order_number']} with exactly this cart a few minutes ago. Ask whether they really want a second identical order; only if they say yes in a new message, call prepare_order again."];
            }
            $repeatOk = true;
        }

        $shipTo = trim((string) ($in['address_id'] ?? ''));
        $pickup = strtolower($shipTo) === 'pickup';
        $addr = $pickup ? $this->address($u, 'profile') : $this->address($u, $shipTo);
        if (!$pickup && !$addr) return ['ready' => false, 'error' => 'Unknown address_id. Use one from my_account (addresses), or "pickup" for local pickup. New addresses are added at /account/addresses.', 'addresses' => $this->addresses($u)];
        if ($pickup && !CartService::pickupEnabled()) return ['ready' => false, 'error' => 'Local pickup is not offered right now.'];
        if (!$pickup && ShopController::isPoBox((string) $addr['street'])) return ['ready' => false, 'error' => 'UPS and FedEx cannot deliver to a PO box; ask for a street address.'];

        $dest = $pickup ? [] : ['street' => (string) $addr['street'], 'city' => (string) $addr['city'], 'company' => (string) ($addr['company'] ?? ''),
            'delivery' => $addr['residential'] ? 'residential' : 'liftgate'];
        $options = $pickup ? [['id' => CartService::PICKUP_ID, 'label' => (string) config('uds.pickup.label'), 'cost' => 0.0]]
            : $this->cart->shippingOptions((string) $addr['country'], strtoupper((string) $addr['state']), (string) $addr['zip'], null, $dest);
        $options = array_values(array_filter($options, fn ($o) => $pickup || ($o['id'] ?? '') !== CartService::PICKUP_ID));
        if (!$options) return ['ready' => false, 'error' => 'No shipping option is available to that address; offer request_human_agent.'];

        $shipId = trim((string) ($in['shipping_option'] ?? ''));
        $chosen = null;
        foreach ($options as $o) if ($o['id'] === $shipId) $chosen = $o;
        if (!$chosen) {
            return ['ready' => false, 'choose_shipping' => array_map(fn ($o) => ['shipping_option' => $o['id'], 'label' => $o['label'], 'cost_usd' => (float) $o['cost'], 'eta' => $o['eta'] ?? null], $options),
                'note' => 'Show these options (cheapest first) and ask which one; then call prepare_order again with shipping_option.'];
        }
        $ship = $pickup ? $chosen : $this->cart->shippingCost($chosen['id'], (string) $addr['country'], strtoupper((string) $addr['state']), (string) $addr['zip'], null, $dest);
        if (!$ship) return ['ready' => false, 'error' => 'That shipping option is no longer available; call prepare_order without shipping_option to see the current ones.'];

        $subtotal = $this->cart->subtotal();
        [$tier, $pct, $tierAmt] = B2bAccounts::discount($u, $subtotal);
        $total = round(max(0, $subtotal - $tierAmt) + (float) $ship['cost'], 2);

        $pay = (string) ($in['payment'] ?? '');
        $cards = $this->cards($u);
        [$termsOk, , , , $why] = B2bAccounts::terms($u, $total);
        $payOptions = array_values(array_filter([
            $cards ? 'saved_card' : null,
            $termsOk ? 'net_terms' : null,
            'pay_link',
        ]));
        if (!in_array($pay, self::PAYMENTS, true) || !in_array($pay, $payOptions, true)) {
            return ['ready' => false, 'choose_payment' => $payOptions,
                'saved_cards' => array_map(fn ($c) => ['card_id' => $c['id'], 'card' => $c['label'], 'expires' => $c['exp']], $cards),
                'net_terms' => B2bAccounts::approved($u) ? ($termsOk ? 'available: Net ' . B2bAccounts::termsDays() . ' invoice' : 'not available (' . $why . ')') : 'only for approved business accounts',
                'note' => 'pay_link = the order is placed and the customer gets a secure link to pay by card / Apple Pay / Google Pay / Affirm. Ask how they want to pay, then call prepare_order again.'];
        }
        $cardId = trim((string) ($in['card_id'] ?? ''));
        $card = null;
        if ($pay === 'saved_card') {
            foreach ($cards as $c) if ($c['id'] === $cardId || (count($cards) === 1 && $cardId === '')) $card = $c;
            if (!$card) return ['ready' => false, 'error' => 'Pick one of the saved cards (card_id from my_account).', 'saved_cards' => array_map(fn ($c) => ['card_id' => $c['id'], 'card' => $c['label']], $cards)];
        }

        $draft = [
            'conv' => $conv->id, 'after' => $lastMsgId, 'cart' => $this->cartHash(), 'at' => time(),
            // the summary card's Yes button sends this back; a newer draft gets a new one, so an old card can't place it
            'nonce' => Str::random(20), 'expires' => time() + self::DRAFT_MINUTES * 60, 'repeat_ok' => $repeatOk,
            'address_id' => $pickup ? 'pickup' : $shipTo, 'pickup' => $pickup, 'ship' => ['id' => $ship['id'], 'label' => $ship['label'], 'cost' => (float) $ship['cost']],
            'dest' => $dest, 'payment' => $pay, 'card' => $card['id'] ?? null,
            'po' => Str::limit(trim((string) ($in['po_number'] ?? '')), 80, ''), 'note' => Str::limit(trim((string) ($in['note'] ?? '')), 500, ''),
            'subtotal' => $subtotal, 'tier' => $tier, 'tier_amt' => $tierAmt, 'total' => $total,
        ];
        Cache::put($this->draftKey($conv->id), $draft, now()->addMinutes(self::DRAFT_MINUTES));

        return ['ready' => true, 'summary_id' => $draft['nonce'], 'expires_at' => $draft['expires'], 'summary' => [
            'items' => $this->viewCart()['items'],
            'subtotal_usd' => $subtotal,
            'business_discount' => $tier ? ['tier' => B2bAccounts::label($tier), 'amount_usd' => -$tierAmt] : null,
            'shipping' => ['method' => $ship['label'], 'cost_usd' => (float) $ship['cost'], 'eta' => $chosen['eta'] ?? null],
            'ship_to' => $pickup ? 'Local pickup at the shop in Florida (address and hours are in the confirmation e-mail)' : trim(implode(', ', array_filter([trim($addr['first'] . ' ' . $addr['last']), $addr['company'], $addr['street'], $addr['unit'], $addr['city'], trim($addr['state'] . ' ' . $addr['zip']), $addr['country']]))),
            'payment' => match ($pay) { 'saved_card' => 'Charge ' . $card['label'], 'net_terms' => 'Invoice, Net ' . B2bAccounts::termsDays() . ' (due ' . now('America/New_York')->addDays(B2bAccounts::termsDays())->format('M j, Y') . ')', default => 'Payment link by e-mail and here in the chat' },
            'po_number' => $draft['po'] ?: null,
            'total_usd' => $total,
        ], 'next' => 'The chat shows this summary as a card with Yes / Change buttons: say one short line asking them to check it and tap Yes (or reply yes). Call place_order only after they confirm in a NEW message; it will not work in this same reply.'];
    }

    /** Place the prepared order. $lastUserMsgId = id of the customer's newest message in this conversation. */
    public function place(User $u, object $conv, int $lastUserMsgId): array
    {
        $key = $this->draftKey($conv->id);
        $lock = Cache::lock('assistant_place:' . $u->id, 30);
        if (!$lock->get()) return ['placed' => false, 'error' => 'An order is already being placed; wait a moment.'];
        try {
            $d = Cache::get($key);
            if (!$d) {
                $done = Cache::get($key . ':done');
                return $done ? ['placed' => true, 'already' => true] + $done : ['placed' => false, 'error' => 'There is no confirmed order summary (it expires after 20 minutes). Call prepare_order first and show the summary.'];
            }
            if ($lastUserMsgId <= $d['after']) return ['placed' => false, 'error' => 'The customer has not confirmed yet. Show the summary and wait for their reply before calling place_order.'];
            // a tap on THIS draft's Yes button, or a short typed yes as their newest message; anything else is not a confirmation
            $tap = (string) Cache::get($key . ':ok', '');
            $byTap = $tap !== '' && !empty($d['nonce']) && hash_equals((string) $d['nonce'], $tap);
            $typed = self::saysYes((string) DB::table('chat_messages')->where('id', $lastUserMsgId)->where('conversation_id', $conv->id)->value('display'));
            if (!$byTap && !$typed) return ['placed' => false, 'error' => 'The customer has not clearly confirmed this summary (their last message is not a plain yes). Ask them to tap Yes on the summary or reply yes; if they want changes, make them and call prepare_order again.'];
            if ($d['cart'] !== $this->cartHash()) { Cache::forget($key); return ['placed' => false, 'error' => 'The cart changed since the summary. Call prepare_order again and show the new summary.']; }
            if (empty($d['repeat_ok']) && ($same = $this->recentSame($u))) { Cache::forget($key); return ['placed' => false, 'error' => "Order {$same['order_number']} with exactly this cart was just placed. Ask the customer whether they want a second identical order before preparing it again."]; }

            // shipping is re-priced: a quote can move between the summary and the "yes"
            $addr = $d['pickup'] ? $this->address($u, 'profile') : $this->address($u, $d['address_id']);
            if (!$d['pickup'] && !$addr) return ['placed' => false, 'error' => 'That address was removed. Call prepare_order again.'];
            if (!$d['pickup']) {
                $ship = $this->cart->shippingCost($d['ship']['id'], (string) $addr['country'], strtoupper((string) $addr['state']), (string) $addr['zip'], null, $d['dest']);
                if (!$ship || abs((float) $ship['cost'] - $d['ship']['cost']) > 0.01) { Cache::forget($key); return ['placed' => false, 'error' => 'The shipping price changed. Call prepare_order again and show the new total.']; }
            }
            if ($d['payment'] === 'net_terms' && !B2bAccounts::terms($u, $d['total'])[0]) return ['placed' => false, 'error' => 'Paying by invoice is not available for this order any more; ask for another payment method.'];

            $order = $this->create($u, $d, $addr);
            // written before any payment call: whatever happens next, a retry answers "already placed", never a second order
            $base = ['order_number' => $order->order_number, 'total_usd' => (float) $order->total, 'order_page' => OrderEvents::statusUrl($order, false), 'emailed_to' => $u->email];
            Cache::put($key . ':done', $base + ['payment' => 'pending_check', 'pay_link' => PayLink::url($order)], now()->addMinutes(self::DRAFT_MINUTES));
            Cache::put($this->recentKey($u->id), ['cart' => $d['cart'], 'order_number' => $order->order_number], now()->addMinutes(self::REPEAT_MINUTES));
            Cache::forget($key);
            Cache::forget($key . ':ok');
            try {
                $result = $this->pay($u, $order, $d);
            } catch (\Throwable $e) {
                // the order exists; its payment state is for the shop / the pay link to settle
                report($e);
                $result = $base + ['payment' => 'pending_check', 'pay_link' => PayLink::url($order),
                    'message' => 'The order was created but we could not confirm the payment step. Do not place it again: the customer can check it or pay with the link, and the shop will follow up.'];
            }
            Cache::put($key . ':done', $result, now()->addMinutes(self::DRAFT_MINUTES));
            return ['placed' => true] + $result;
        } finally {
            $lock->release();
        }
    }

    private function create(User $u, array $d, ?array $addr): object
    {
        $terms = $d['payment'] === 'net_terms';
        [$bf, $bl] = $this->splitName($u->name ?: $u->email);
        $hasBilling = (bool) $u->billing_address_1;
        // the address the parts go to doubles as billing when the profile has none
        $bill = $hasBilling ? ['street' => $u->billing_address_1, 'unit' => $u->billing_address_2, 'city' => $u->billing_city, 'state' => $u->billing_state, 'zip' => $u->billing_postcode, 'country' => strtoupper($u->billing_country ?: 'US')]
            : ($addr ? ['street' => $addr['street'], 'unit' => $addr['unit'], 'city' => $addr['city'], 'state' => $addr['state'], 'zip' => $addr['zip'], 'country' => $addr['country']] : ['street' => null, 'unit' => null, 'city' => null, 'state' => null, 'zip' => null, 'country' => 'US']);
        $shipDiff = !$d['pickup'] && $addr && ($addr['kind'] !== 'profile');

        return DB::transaction(function () use ($u, $d, $addr, $terms, $bf, $bl, $bill, $shipDiff) {
            $id = DB::table('orders')->insertGetId([
                'order_number' => CartService::newOrderNumber(), 'locale' => $u->locale ?: app()->getLocale(),
                'user_id' => $u->id, 'source' => 'assistant',
                'status' => $terms ? 'processing' : 'pending', 'payment_status' => 'unpaid',
                'payment_method' => $terms ? 'net_terms' : 'stripe',
                'terms_due_at' => $terms ? now('America/New_York')->addDays(B2bAccounts::termsDays())->toDateString() : null,
                'b2b_tier' => $d['tier'], 'tier_discount' => $d['tier_amt'],
                'email' => $u->email, 'phone' => $u->phone, 'sms_opt_in' => false,
                'billing_first_name' => $bf, 'billing_last_name' => $bl, 'billing_company' => $u->company,
                'billing_address_1' => $bill['street'], 'billing_address_2' => $bill['unit'], 'billing_city' => $bill['city'],
                'billing_state' => $bill['state'], 'billing_postcode' => $bill['zip'], 'billing_country' => $bill['country'] ?: 'US',
                'ship_to_different' => $shipDiff,
                'shipping_first_name' => $shipDiff ? $addr['first'] : null, 'shipping_last_name' => $shipDiff ? $addr['last'] : null,
                'shipping_address_1' => $shipDiff ? $addr['street'] : null, 'shipping_address_2' => $shipDiff ? $addr['unit'] : null,
                'shipping_city' => $shipDiff ? $addr['city'] : null, 'shipping_state' => $shipDiff ? $addr['state'] : null,
                'shipping_postcode' => $shipDiff ? $addr['zip'] : null, 'shipping_country' => $shipDiff ? $addr['country'] : null,
                'shipping_method' => $d['ship']['id'], 'shipping_label' => $d['ship']['label'],
                'freight_delivery' => CartService::isFreight((string) $d['ship']['id']) ? ($d['dest']['delivery'] ?? 'residential') : null,
                'subtotal' => $d['subtotal'], 'shipping_total' => $d['ship']['cost'], 'discount_total' => $d['tier_amt'], 'total' => $d['total'],
                'customer_note' => $d['note'] ?: null, 'po_number' => $d['po'] ?: null,
                'ip' => request()?->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($this->cart->items() as $i) {
                DB::table('order_items')->insert([
                    'order_id' => $id, 'type' => $i['type'], 'wp_product_id' => $i['wp_product_id'], 'name' => $i['name'], 'sku' => $i['sku'],
                    'price' => $i['price'], 'qty' => $i['qty'], 'line_total' => round($i['price'] * $i['qty'], 2),
                    'meta' => json_encode($i['meta'] ?? []), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return DB::table('orders')->find($id);
        });
    }

    /** Events, payment and e-mail after the order exists; the cart is emptied. */
    private function pay(User $u, object $order, array $d): array
    {
        $this->cart->clear();
        OrderEvents::record($order, 'placed', 'Order placed through UDS Assistant', $d['pickup'] ? 'Local pickup' : $order->shipping_label, true);
        // committed like a checkout order: an invoice now, a card or pay link once the money is in (PayLink::markPaid).
        // Stock comes off, a custom driveshaft becomes a work order on the shop station, the CRM syncs (it used to skip all of it)
        if ($d['payment'] !== 'net_terms') OrderEvents::record($order, 'notify:placed:deferred', 'Waiting for payment', null, true);
        $out = ['order_number' => $order->order_number, 'total_usd' => (float) $order->total, 'order_page' => OrderEvents::statusUrl($order, false), 'emailed_to' => $u->email];

        if ($d['payment'] === 'net_terms') {
            OrderEvents::record($order, 'terms', 'On Net ' . B2bAccounts::termsDays() . ' terms', 'Invoice due ' . \Carbon\Carbon::parse($order->terms_due_at)->format('M j, Y') . ($order->po_number ? ' · PO ' . $order->po_number : ''), true);
            OrderEvents::record($order, 'processing', 'In production / packing', null, true);
            try { B2bAccounts::evaluate($u->id); } catch (\Throwable $e) { report($e); }
            \App\Services\OrderMailer::confirmOnce($order);
            PayLink::placedNotices($order);
            $out += ['payment' => 'invoice', 'invoice_due' => $order->terms_due_at, 'pay_link' => PayLink::url($order)];
        } elseif ($d['payment'] === 'saved_card') {
            try {
                [$st, $msg] = StripeCards::chargeOrder($order, $u, (string) $d['card']);
            } catch (\Throwable $e) {
                // a network error after the charge request may or may not have charged: never "failed", never retried here
                report($e);
                $fresh = DB::table('orders')->find($order->id);
                \App\Services\OrderMailer::confirmOnce($fresh);
                $out += ['payment' => 'pending_check', 'pay_link' => PayLink::url($fresh),
                    'message' => "We couldn't confirm the card payment. Do not place the order again: check the order page in a few minutes; if it still shows unpaid, pay with the link."];
                try { app(\App\Services\BrevoService::class)->syncOrder($order); } catch (\Throwable $e) {}
                return $out;
            }
            $fresh = DB::table('orders')->find($order->id);
            if ($st === 'processing') {
                // the confirmation e-mail goes out from markPaid once Stripe settles it (webhook)
                $out += ['payment' => 'processing', 'message' => $msg];
            } elseif ($st !== 'paid') {
                // the order stands; the customer pays it with the link (the bank may want them to confirm, or the card failed)
                \App\Services\OrderMailer::confirmOnce($fresh);
                $out += ['payment' => $st === 'action' ? 'needs_customer_confirmation' : 'card_failed', 'message' => $msg, 'pay_link' => PayLink::url($fresh)];
            } else {
                $out += ['payment' => 'paid', 'message' => $msg];
            }
        } else {
            \App\Services\OrderMailer::confirmOnce($order);
            $out += ['payment' => 'awaiting_payment', 'pay_link' => PayLink::url($order)];
        }
        try { app(\App\Services\BrevoService::class)->syncOrder($order); } catch (\Throwable $e) {}
        return $out;
    }
}
