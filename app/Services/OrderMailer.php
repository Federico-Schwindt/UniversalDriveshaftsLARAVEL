<?php

namespace App\Services;

use App\Support\Mailer;
use App\Support\OrderEvents;
use App\Support\PayLink;
use Illuminate\Support\Facades\DB;

/** Order e-mails (branded HTML + text): confirmation to the customer, build sheet to the shop, shipped, delivered. */
class OrderMailer
{
    /** Hydrate order_items rows with thumbnails for the templates. */
    public static function items(iterable $items): array
    {
        $rows = collect($items)->values();
        $thumbs = [];
        try { $thumbs = app(CatalogService::class)->thumbsFor($rows->pluck('wp_product_id')->filter()->all()); } catch (\Throwable $e) {}
        foreach ($rows as $it) {
            $it->image = $thumbs[(int) ($it->wp_product_id ?? 0)] ?? '';
            if (!$it->image && !empty($it->sku) && str_starts_with($it->sku, 'U')) { try { $img = DB::table('ds_parts')->where('part_number', DriveshaftFinder::pn($it->sku))->value('main_image'); if ($img) $it->image = media_url($img, 160); } catch (\Throwable $e) {} }
        }
        return $rows->all();
    }

    /** The confirmation (and the shop's copy) exactly once per order, whichever path gets there first. */
    public static function confirmOnce(object $order): void
    {
        if (!OrderEvents::record($order, 'notify:email:confirmation', 'Confirmation e-mail sent', $order->email, true)) return;
        $fresh = DB::table('orders')->find($order->id) ?: $order;
        try { app(self::class)->send($fresh, DB::table('order_items')->where('order_id', $fresh->id)->get()); } catch (\Throwable $e) { report($e); }
    }

    public function send(object $order, iterable $items): void
    {
        $items = self::items($items);
        $name = trim($order->billing_first_name ?: 'there');
        $pickup = $order->shipping_method === CartService::PICKUP_ID;
        $unpaid = ($order->payment_status ?? 'unpaid') === 'unpaid' && $order->payment_method !== 'pay_at_pickup';
        $data = ['order' => $order, 'items' => $items, 'statusUrl' => OrderEvents::statusUrl($order), 'payUrl' => $unpaid && PayLink::payable($order) ? PayLink::url($order) : null,
            'hero' => ['kicker' => 'Order ' . $order->order_number, 'title' => "Thanks, {$name} — we have your order.", 'text' => $pickup ? 'We will let you know as soon as it is ready for pickup in Florida.' : 'Stock parts leave Florida the same business day when ordered before 2 pm ET; custom shafts are built to order.'],
            'preheader' => 'Order ' . $order->order_number . ' · $' . number_format($order->total, 2) . ($unpaid ? ' · payment pending' : '')];
        try { Mailer::send($order->email, "Order {$order->order_number} confirmed — Universal Driveshafts", 'emails.order.confirmation', $data, ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) { report($e); }
        if (!\App\Support\Settings::get('notify.new_order', true)) return;
        try {
            Mailer::send(config('uds.email'), "New order {$order->order_number} — \$" . number_format($order->total, 2) . ($pickup ? ' · PICKUP' : ''), 'emails.order.shop',
                ['order' => $order, 'items' => $items, 'hero' => ['kicker' => 'New order', 'title' => $order->order_number . ' · $' . number_format($order->total, 2), 'text' => trim($order->billing_first_name . ' ' . $order->billing_last_name) . ($order->billing_company ? ' · ' . $order->billing_company : '') . ' · ' . ($order->shipping_label ?: '')], 'preheader' => count($items) . ' line(s)'],
                ['replyTo' => [$order->email, trim($order->billing_first_name . ' ' . $order->billing_last_name)]]);
        } catch (\Throwable $e) { report($e); }
    }

    /** "Your order has shipped" — the one shipped e-mail, whichever path marked it shipped. */
    public function sendShipped(object $order, string $carrier, string $trackingNumber, ?string $service = null): void
    {
        $name = trim($order->billing_first_name ?: 'there');
        // a PTI dropship never passes through our shop: don't claim it left Florida
        $dropship = str_contains((string) ($order->tracking_service ?? ''), 'PTI') || rescue(fn () => DB::table('pti_dropships')->where('order_id', $order->id)->where('status', 'placed')->exists(), false, false);
        $text = $dropship ? "Order {$order->order_number} is on its way with {$carrier}." : "Order {$order->order_number} left our shop in Florida with {$carrier}.";
        Mailer::send($order->email, "Your order {$order->order_number} has shipped — {$carrier} {$trackingNumber}", 'emails.order.shipped', [
            'order' => $order, 'carrier' => $carrier, 'tracking' => $trackingNumber, 'service' => $service, 'trackUrl' => OrderEvents::carrierUrl($carrier, $trackingNumber), 'statusUrl' => OrderEvents::statusUrl($order),
            'items' => self::items(DB::table('order_items')->where('order_id', $order->id)->get()),
            'hero' => ['kicker' => 'On its way', 'title' => "Good news, {$name} — your order shipped.", 'text' => $text], 'preheader' => "{$carrier} {$trackingNumber}",
        ], ['replyTo' => [config('uds.email')]]);
    }

    /** "Your order was delivered" — sent once by OrderEvents::markDelivered(). */
    public function sendDelivered(object $order): void
    {
        $name = trim($order->billing_first_name ?: 'there');
        // stored in UTC (the carrier's scan time when known); customers read Eastern, labelled so it's never ambiguous
        $when = $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at, 'UTC')->timezone('America/New_York')->format('l, F j \a\t g:i A') . ' ET' : 'today';
        Mailer::simple($order->email, "Delivered: order {$order->order_number} — Universal Driveshafts", [
            'kicker' => 'Delivered', 'title' => "Your order arrived, {$name}.", 'intro' => "Order {$order->order_number} was delivered {$when}." . ($order->tracking_number ? " ({$order->tracking_carrier} · {$order->tracking_number})" : ''),
            'paragraphs' => ['Please check the parts against your order as soon as you can. If anything is damaged in transit or does not match what you ordered, reply to this e-mail or call (813) 524-6491 within 48 hours and we will sort it out.'],
            'button' => ['Order details', OrderEvents::statusUrl($order)],
            'after' => ['Thanks for choosing Universal Driveshafts — we would love to hear how the install went.'],
        ], ['replyTo' => [config('uds.email')]]);
    }

    /** To the shop: the carrier reports a problem (returned, failed, waiting at a pickup point). */
    public function sendTrackingProblem(object $order, array $st): void
    {
        $name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? '')) ?: $order->email;
        Mailer::simple(config('uds.email'), "Carrier problem: {$order->order_number} — " . ($st['status'] ?? $st['raw'] ?? 'check tracking'), [
            'kicker' => 'Tracking alert', 'title' => ($st['status'] ?? 'Carrier problem') . " · {$order->order_number}",
            'intro' => "The carrier reports \"" . ($st['status'] ?? $st['raw'] ?? '') . "\" for {$name}'s order. The customer was not e-mailed about this; please follow up.",
            'kv' => ['Order' => $order->order_number, 'Customer' => $name, 'E-mail' => $order->email, 'Phone' => $order->phone ?: null,
                'Tracking' => trim(($order->tracking_carrier ?? '') . ' ' . ($order->tracking_number ?? '')), 'Estimated' => $st['est'] ?? null],
            'button' => ['Open order', rtrim(config('app.url'), '/') . '/admin/orders/' . $order->id],
            'signoff' => false,
        ], ['replyTo' => [$order->email]]);
    }
}
