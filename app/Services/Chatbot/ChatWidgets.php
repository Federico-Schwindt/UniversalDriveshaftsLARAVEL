<?php

namespace App\Services\Chatbot;

use App\Http\Controllers\AccountController;
use App\Support\OrderEvents;
use App\Support\PayLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The visual pieces under an assistant answer: pick-an-order list, shipment tracking, order summary to confirm,
 * order placed, quick replies. Every widget is built here from the database (never from what the model wrote),
 * from the tools the assistant used in the turn plus the [[…]] directives at the end of its answer.
 */
class ChatWidgets
{
    public const MAX_CHOICES = 4;

    /** Customer wording that asks where an order is (English and Spanish). */
    private const TRACK_WORDS = '/\b(track|tracking|where|ship|shipped|shipping|deliver|delivery|arriv|status|eta|when will|rastre|d[oó]nde|llega|env[ií]o|enviad|entrega|estado|cu[aá]ndo)/iu';

    /**
     * Split an answer into what the customer sees and the assistant's directives:
     * [[voice]] / [[text]], [[widget:orders]], [[widget:tracking:UDS-…]], [[choices:A|B|C]].
     *
     * @return array{0:string,1:?string,2:array}  [text, format hint, directives]
     */
    public static function directives(string $text): array
    {
        $hint = null;
        $tags = ['widgets' => [], 'choices' => []];
        $clean = preg_replace_callback('/\[\[\s*([a-z_]+)\s*(?::\s*([^\]]*))?\]\]/iu', function ($m) use (&$hint, &$tags) {
            $k = strtolower($m[1]); $v = trim($m[2] ?? '');
            if ($k === 'voice' || $k === 'text') $hint = $k;
            elseif ($k === 'widget' && $v !== '') { [$type, $ref] = array_pad(array_map('trim', explode(':', $v, 2)), 2, ''); $tags['widgets'][] = [strtolower($type), $ref]; }
            elseif ($k === 'choices' && $v !== '') $tags['choices'] = array_merge($tags['choices'], array_map('trim', explode('|', $v)));
            return '';
        }, $text);
        // a directive cut in half at the very end (long answer), or malformed anywhere ("[[voice]" / "[[widget"), must not show either
        $clean = preg_replace('/\[\[[^\]]*$/u', '', (string) $clean);
        $clean = preg_replace('/\[\[[^\]\n]*\]?/u', '', (string) $clean);
        // a tag taken out mid-sentence leaves "word  word" or "done ."
        $clean = preg_replace('/[ \t]{2,}/u', ' ', (string) $clean);
        $clean = preg_replace('/[ \t]+([.,!?;:])/u', '$1', (string) $clean);
        return [trim(preg_replace("/[ \t]+\n/", "\n", preg_replace("/\n{3,}/", "\n\n", $clean))), $hint, $tags];
    }

    /**
     * @param array $tools   [['name' => …, 'in' => […], 'result' => […]], the tools run this turn, in order
     * @param callable $own  fn(string $orderNumber): ?object, the customer's own order (same rule as the tools)
     */
    public static function build(array $tools, array $tags, string $customerText, string $answer, callable $own): array
    {
        $w = [];
        $used = fn (string $name) => array_values(array_filter($tools, fn ($t) => $t['name'] === $name));

        // order placed: a receipt (with the pay button when it waits for payment)
        foreach ($used('place_order') as $t) {
            $r = $t['result'];
            if (!empty($r['placed']) && !empty($r['order_number']) && ($o = $own((string) $r['order_number']))) $w[] = self::placed($o, $r);
        }
        // summary ready: the customer confirms with a button (which sends a new message, as place_order requires)
        if (!$w) {
            foreach (array_reverse($used('prepare_order')) as $t) {
                $r = $t['result'];
                if (!empty($r['ready'])) { $w[] = self::confirm($r['summary'], $r['summary_id'] ?? null, $r['expires_at'] ?? null); break; }
                if (!empty($r['choose_shipping'])) { $w[] = self::choices(array_map(fn ($o) => trim($o['label'] . ' · $' . number_format($o['cost_usd'], 2)), array_slice($r['choose_shipping'], 0, self::MAX_CHOICES))); break; }
                if (!empty($r['choose_payment'])) {
                    $labels = ['saved_card' => 'Charge my saved card', 'net_terms' => 'Invoice me (Net terms)', 'pay_link' => 'Send me a payment link'];
                    $w[] = self::choices(array_map(fn ($k) => $labels[$k] ?? $k, $r['choose_payment'])); break;
                }
            }
        }

        // tracking: asked for by the assistant, or an order looked up because the customer asked where it is
        $trackRefs = [];
        foreach ($tags['widgets'] as [$type, $ref]) if (in_array($type, ['tracking', 'order', 'status'], true) && $ref !== '') $trackRefs[] = $ref;
        $details = $used('order_details');
        if (!$trackRefs && $details && preg_match(self::TRACK_WORDS, $customerText)) {
            foreach ($details as $t) if (!empty($t['result']['found'])) $trackRefs[] = (string) $t['result']['order']['order_number'];
        }
        foreach (array_slice(array_unique($trackRefs), 0, 2) as $ref) if ($o = $own($ref)) $w[] = self::tracking($o);

        // several orders and the assistant is asking which one: a list to tap
        $wantOrders = in_array('orders', array_column($tags['widgets'], 0), true);
        $lists = $used('my_orders');
        if (!$trackRefs && $lists) {
            $rows = $lists[count($lists) - 1]['result']['orders'] ?? [];
            if ($wantOrders || (count($rows) > 1 && str_contains($answer, '?') && !$details)) $w[] = self::orders($rows, $own);
        }

        // the guided driveshaft build: the current question's options to tap, the finished build, or the cart line it became
        $builds = $used('driveshaft_builder');
        if ($builds && ($bw = self::builder((array) $builds[count($builds) - 1]['result']))) $w[] = $bw;

        if ($tags['choices'] && !array_filter($w, fn ($x) => in_array($x['type'], ['choices', 'confirm', 'build_choices', 'build'], true))) $w[] = self::choices($tags['choices']);
        return array_values(array_filter($w, fn ($x) => $x && ($x['type'] !== 'orders' || $x['orders']) && ($x['type'] !== 'choices' || $x['options'])));
    }

    /** Up to this many builder options show as buttons; longer lists are typed. */
    public const MAX_BUILD_OPTIONS = 12;

    /** A driveshaft_builder result as a widget (AssistantBuilder made it from the builder's own data). */
    public static function builder(array $r): ?array
    {
        if (!empty($r['added'])) {
            return ['type' => 'build_added', 'title' => self::buildTitle((string) ($r['title'] ?? '')), 'price' => round((float) ($r['price_each_usd'] ?? 0), 2), 'qty' => (int) ($r['qty'] ?? 1),
                'cart' => '/cart', 'checkout' => '/checkout', 'labels' => ['done' => __('Added to your cart'), 'cart' => __('View cart'), 'checkout' => __('Checkout')]];
        }
        if (!empty($r['ready']) && !empty($r['build'])) {
            $b = $r['build'];
            return [
                'type' => 'build', 'title' => self::buildTitle((string) ($b['title'] ?? '')), 'price' => round((float) ($b['price_usd'] ?? 0), 2),
                'photo' => (string) ($b['photo'] ?? ''), 'fits' => $b['fits'] ?? null,
                'lines' => array_map(fn ($l) => ['label' => (string) $l['label'], 'usd' => round((float) $l['usd'], 2)], array_slice((array) ($b['lines_shown'] ?? $b['lines'] ?? []), 0, 6)),
                'specs' => array_map(fn ($a) => ['q' => __((string) $a['q']), 'a' => (string) ($a['shown'] ?? __((string) $a['a']))], array_slice(array_values(array_filter((array) ($r['answers'] ?? []), fn ($a) => ($a['change'] ?? '') !== 'tree')), 0, 10)),
                'speed' => $b['speed_shown'] ?? $b['speed_check'] ?? null, 'link' => (string) ($r['builder_link'] ?? '/online-builder'),
                'labels' => ['add' => __('Add to cart'), 'add_say' => __('Yes, add it to my cart'), 'open' => __('Open in the builder'), 'total' => __('Total'), 'speed' => __('Speed check'),
                    'photo' => __('photo of a shaft we build; yours is made to your measurements'), 'fits' => __('Fits')],
            ];
        }
        $step = (string) ($r['step'] ?? '');
        $opts = (array) ($r['options'] ?? []);
        if ($opts && count($opts) <= self::MAX_BUILD_OPTIONS && !in_array($step, ['measure', 'speed', 'review'], true)) {
            return ['type' => 'build_choices', 'stage' => self::buildStage((string) ($r['stage'] ?? '')), 'options' => array_map(fn ($o) => array_filter([
                'label' => Str::limit((string) $o['label'], 80), 'note' => !empty($o['note']) ? Str::limit((string) $o['note'], 110) : null,
                'price' => isset($o['price_usd']) ? round((float) $o['price_usd'], 2) : null, 'from' => isset($o['from_usd']) ? round((float) $o['from_usd'], 2) : null,
            ], fn ($v) => $v !== null), $opts), 'labels' => ['from' => __('from :price', ['price' => ':price'])]];
        }
        if ($step === 'measure') {
            return ['type' => 'build_link', 'stage' => self::buildStage((string) ($r['stage'] ?? '')), 'link' => (string) ($r['builder_link'] ?? '/online-builder'),
                'text' => __('See where A1 and C1 are measured on the drawing for your ends.'), 'labels' => ['open' => __('Open the measuring drawing')]];
        }
        return null;
    }

    /** "Custom 1350 series steel driveshaft, …" in the customer's language (the builder page's own wording). */
    private static function buildTitle(string $t): string
    {
        if (!preg_match('/^Custom (.+?) series (steel|aluminum) driveshaft(?:, (.+?) to (.+?))?(?:, ([\d.]+) in)?$/u', $t, $m)) return $t;
        $tl = fn (string $s) => mb_strtolower(__($s));
        $front = isset($m[3]) && $m[3] !== '' ? $tl(ucfirst($m[3])) : ''; $rear = isset($m[4]) && $m[4] !== '' ? $tl(ucfirst($m[4])) : '';
        // labels are stored lower-cased in the title; the translation keys are the settings labels
        foreach (['front', 'rear'] as $side) foreach ((array) (app(\App\Services\DsbPricing::class)->settings()[$side . '_apps'] ?? []) as $a) {
            if (!empty($m[3]) && mb_strtolower((string) $a['label']) === $m[3] && $side === 'front') $front = $tl((string) $a['label']);
            if (!empty($m[4]) && mb_strtolower((string) $a['label']) === $m[4] && $side === 'rear') $rear = $tl((string) $a['label']);
        }
        $mat = mb_strtolower(__(ucfirst($m[2])));
        if (!empty($m[5])) return __('Custom :series series :material driveshaft, :front to :rear, :len in', ['series' => $m[1], 'material' => $mat, 'front' => $front, 'rear' => $rear, 'len' => $m[5]]);
        if ($front !== '') return __('Custom :series series :material driveshaft, :front to :rear', ['series' => $m[1], 'material' => $mat, 'front' => $front, 'rear' => $rear]);
        return $t;
    }

    /** "Step 2 of 5 · Shaft · U-joint series" in the customer's language. */
    private static function buildStage(string $stage): string
    {
        if (!preg_match('/^Step (\d) of (\d) · (.+)$/u', $stage, $m)) return $stage;
        return __('Step :n of :total', ['n' => $m[1], 'total' => $m[2]]) . ' · ' . implode(' · ', array_map(fn ($p) => __($p), explode(' · ', $m[3])));
    }

    public static function choices(array $options): array
    {
        $opts = [];
        foreach ($options as $o) {
            $o = trim(strip_tags((string) $o));
            if ($o !== '' && mb_strlen($o) <= 60 && !in_array($o, $opts, true)) $opts[] = $o;
        }
        return ['type' => 'choices', 'options' => array_slice($opts, 0, self::MAX_CHOICES)];
    }

    /** Pick-an-order: up to five, newest first, with what is on each. */
    public static function orders(array $rows, callable $own): array
    {
        $numbers = array_slice(array_column($rows, 'order_number'), 0, 5);
        $orders = [];
        foreach ($numbers as $n) if ($o = $own((string) $n)) $orders[] = $o;
        $lines = $orders ? DB::table('order_items')->whereIn('order_id', array_column($orders, 'id'))->orderBy('id')->get(['order_id', 'name', 'sku', 'qty'])->groupBy('order_id') : collect();
        return ['type' => 'orders', 'orders' => array_map(function ($o) use ($lines) {
            [$key, $label] = AccountController::orderState($o);
            $items = $lines[$o->id] ?? collect();
            $firstItem = $items->first();
            return [
                'number' => $o->order_number, 'date' => Carbon::parse($o->created_at)->format('M j, Y'), 'total' => round((float) $o->total, 2),
                'status' => $key, 'status_label' => $label,
                'items' => $firstItem ? Str::limit(trim(($firstItem->qty > 1 ? $firstItem->qty . ' × ' : '') . ($firstItem->sku ?: $firstItem->name)), 34) . ($items->count() > 1 ? ' +' . ($items->count() - 1) . ' more' : '') : '',
                'qty' => (int) $items->sum('qty'),
                'tracking' => (bool) $o->tracking_number,
            ];
        }, $orders)];
    }

    /** Where the order is: the steps, carrier and number, delivery estimate and the latest scans. */
    public static function tracking(object $o): array
    {
        [$key, $label] = AccountController::orderState($o);
        $scans = json_decode((string) ($o->tracking_events ?? ''), true) ?: [];
        $latest = $scans[0]['status'] ?? null;
        $pickup = OrderEvents::isPickup($o);
        $paid = $o->payment_status === 'paid' || $o->paid_at || in_array($o->payment_method, ['net_terms'], true);
        $outAt = null;
        foreach ($scans as $s) if (($s['status'] ?? '') === 'out_for_delivery') { $outAt = $s['at'] ?? null; break; }
        $fmt = fn ($t) => $t ? Carbon::parse($t)->format('M j, g:i A') : null;
        $steps = $pickup ? [
            ['label' => 'Placed', 'at' => $fmt($o->created_at), 'done' => true],
            ['label' => 'Paid', 'at' => $fmt($o->paid_at), 'done' => (bool) $paid],
            ['label' => 'Ready for pickup', 'at' => $fmt($o->shipped_at), 'done' => (bool) $o->shipped_at],
            ['label' => 'Picked up', 'at' => $fmt($o->delivered_at), 'done' => (bool) $o->delivered_at],
        ] : [
            ['label' => 'Placed', 'at' => $fmt($o->created_at), 'done' => true],
            ['label' => 'Paid', 'at' => $fmt($o->paid_at), 'done' => (bool) $paid],
            ['label' => 'Shipped', 'at' => $fmt($o->shipped_at), 'done' => (bool) $o->shipped_at],
            ['label' => 'Out for delivery', 'at' => $fmt($outAt), 'done' => (bool) ($outAt || $o->delivered_at)],
            ['label' => 'Delivered', 'at' => $fmt($o->delivered_at), 'done' => (bool) $o->delivered_at],
        ];
        $stopped = in_array($key, ['cancelled', 'refunded'], true);
        $names = ['pre_transit' => 'Label created', 'in_transit' => 'On the way', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered',
            'available_for_pickup' => 'Ready for pickup at the carrier', 'return_to_sender' => 'Returning to sender', 'failure' => 'Delivery problem', 'cancelled' => 'Cancelled'];
        $unpaid = !$paid && !$stopped && $o->payment_method !== 'net_terms';
        return [
            'type' => 'tracking', 'number' => $o->order_number, 'date' => Carbon::parse($o->created_at)->format('M j, Y'), 'total' => round((float) $o->total, 2),
            'status' => $key, 'status_label' => $label, 'stopped' => $stopped,
            'trouble' => in_array($latest, ['failure', 'return_to_sender'], true),
            'steps' => $stopped ? [] : $steps,
            'carrier' => $o->tracking_number ? ($o->tracking_carrier ?: 'Carrier') : null,
            'tracking_number' => $o->tracking_number ?: null,
            'tracking_url' => $o->tracking_number ? OrderEvents::carrierUrl($o->tracking_carrier, $o->tracking_number) : null,
            'eta' => !$o->delivered_at && $o->tracking_est ? Carbon::parse($o->tracking_est)->format('l, M j') : null,
            'scans' => array_map(fn ($s) => ['label' => $names[$s['status'] ?? ''] ?? ($s['message'] ?? 'Update'), 'detail' => ($s['message'] ?? '') !== ($names[$s['status'] ?? ''] ?? null) ? (string) ($s['message'] ?? '') : '',
                'place' => (string) ($s['place'] ?? ''), 'at' => $fmt($s['at'] ?? null)], array_slice($scans, 0, 3)),
            'pickup' => $pickup,
            'page' => OrderEvents::statusUrl($o, false),
            'pay_link' => $unpaid ? PayLink::url($o) : null,
        ];
    }

    /** prepare_order's summary, to confirm with one tap (the button sends the draft's nonce; it goes quiet once the draft expires). */
    public static function confirm(array $s, ?string $nonce = null, ?int $expiresAt = null): array
    {
        return [
            'type' => 'confirm', 'nonce' => $nonce, 'expires_at' => $expiresAt,
            'items' => array_map(fn ($i) => ['sku' => (string) ($i['sku'] ?? ''), 'name' => Str::limit((string) ($i['name'] ?? ''), 60), 'qty' => (int) ($i['qty'] ?? 1), 'line' => round((float) ($i['line_usd'] ?? (($i['price_usd'] ?? 0) * ($i['qty'] ?? 1))), 2)], array_slice($s['items'] ?? [], 0, 8)),
            'subtotal' => (float) ($s['subtotal_usd'] ?? 0),
            'discount' => $s['business_discount'] ? ['label' => $s['business_discount']['tier'], 'amount' => (float) $s['business_discount']['amount_usd']] : null,
            'shipping' => ['label' => (string) ($s['shipping']['method'] ?? ''), 'cost' => (float) ($s['shipping']['cost_usd'] ?? 0), 'eta' => $s['shipping']['eta'] ?? null],
            'ship_to' => (string) ($s['ship_to'] ?? ''), 'payment' => (string) ($s['payment'] ?? ''), 'po' => $s['po_number'] ?? null,
            'total' => (float) ($s['total_usd'] ?? 0),
            'yes' => 'Yes, place the order', 'no' => 'Change something',
        ];
    }

    public static function placed(object $o, array $r): array
    {
        return [
            'type' => 'placed', 'number' => $o->order_number, 'total' => round((float) $o->total, 2),
            'payment' => (string) ($r['payment'] ?? ''), 'pay_link' => $r['pay_link'] ?? null,
            'page' => OrderEvents::statusUrl($o, false),
        ];
    }
}
