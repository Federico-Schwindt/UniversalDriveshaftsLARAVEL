<?php

namespace App\Support;

use App\Services\CartService;
use App\Services\OrderMailer;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;

/**
 * Append-only order timeline (`order_events`) and the single place that
 * decides whether a customer notification has already gone out.
 *
 * Event types that drive the public timeline: placed, paid, processing,
 * shipped, delivered, cancelled, tracking. Anything prefixed "notify:" is a
 * bookkeeping row (notify:sms:shipped, notify:email:delivered …) — written
 * right before a message is sent so it can never be sent twice, and hidden
 * from the customer-facing page.
 */
class OrderEvents
{
    /** Record an event. Returns false (and writes nothing) when `$once` and that type already exists for the order. */
    public static function record(int|object $order, string $type, string $label, ?string $detail = null, bool $once = false): bool
    {
        $id = is_object($order) ? (int) $order->id : $order;
        // history imported from the old store never sends anything (e-mail, text, CRM sync, coupon count)
        if (str_starts_with($type, 'notify:') && self::imported($order)) return false;
        $row = ['order_id' => $id, 'type' => $type, 'label' => $label, 'detail' => $detail, 'created_at' => now()];
        if (!$once) { DB::table('order_events')->insert($row); return true; }
        // has() keeps rows written without a key (non-once) counting; the unique (order_id, dedupe_key) makes two racing callers produce one row
        if (self::has($id, $type)) return false;
        return DB::table('order_events')->insertOrIgnore($row + ['dedupe_key' => $type]) === 1;
    }

    /** An order imported from the WooCommerce store (woo:import): a record only, its customer is never contacted about it. */
    public static function imported(int|object $order): bool
    {
        if (is_object($order) && property_exists($order, 'source')) return $order->source === 'woocommerce';
        return DB::table('orders')->where('id', is_object($order) ? (int) $order->id : $order)->value('source') === 'woocommerce';
    }

    /** Undo a `$once` bookkeeping row (e.g. the message it guarded failed to send) so the next run retries. */
    public static function forget(int|object $order, string $type): void
    {
        $id = is_object($order) ? (int) $order->id : $order;
        DB::table('order_events')->where('order_id', $id)->where('type', $type)->delete();
    }

    public static function has(int|object $order, string $type): bool
    {
        $id = is_object($order) ? (int) $order->id : $order;
        return DB::table('order_events')->where('order_id', $id)->where('type', $type)->exists();
    }

    /** All events for an order, oldest first (notify: rows included — filter in the view). */
    public static function for(int|object $order)
    {
        $id = is_object($order) ? (int) $order->id : $order;
        return DB::table('order_events')->where('order_id', $id)->orderBy('created_at')->orderBy('id')->get();
    }

    /** Customer-facing status page link (order number + the e-mail used at checkout). */
    public static function statusUrl(object $order, bool $absolute = true): string
    {
        $path = '/orders/' . rawurlencode($order->order_number) . '/status?e=' . rawurlencode($order->email);
        return $absolute ? rtrim(config('app.url'), '/') . $path : $path;
    }

    public static function carrierUrl(?string $carrier, ?string $number): ?string
    {
        if (!$number) return null;
        $c = strtolower((string) $carrier);
        if (str_contains($c, 'dhl')) return 'https://www.dhl.com/us-en/home/tracking/tracking-express.html?submit=1&tracking-id=' . urlencode($number);
        $c = strtolower($carrier ?: 'UPS');
        if (str_contains($c, 'ups')) return 'https://www.ups.com/track?tracknum=' . urlencode($number);
        if (str_contains($c, 'fedex')) return 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($number);
        if (str_contains($c, 'usps')) return 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . urlencode($number);
        if (str_contains($c, 'dhl')) return 'https://www.dhl.com/us-en/home/tracking.html?tracking-id=' . urlencode($number);
        return 'https://www.google.com/search?q=' . urlencode($carrier . ' tracking ' . $number);
    }

    /**
     * Mark an order delivered (idempotent) and tell the customer once.
     * Called by `orders:track` when the carrier reports a delivery scan.
     */
    public static function markDelivered(object $order, ?string $detail = null, bool $notify = true, ?\DateTimeInterface $at = null): void
    {
        $fresh = DB::table('orders')->find($order->id);
        if (!$fresh) return;
        if (!$fresh->delivered_at) {
            DB::table('orders')->where('id', $fresh->id)->update([
                // the carrier's delivery scan time when we have it, never in the future
                'delivered_at' => $at && $at < now() ? $at : now(),
                'last_tracking_status' => 'Delivered',
                'last_tracking_checked_at' => now(),
                'status' => in_array($fresh->status, ['pending', 'processing'], true) ? 'completed' : $fresh->status,
                'updated_at' => now(),
            ]);
            self::record($fresh, 'delivered', 'Delivered', $detail, true);
            $fresh = DB::table('orders')->find($fresh->id);
        }
        if (!$notify) return;

        if (self::record($fresh, 'notify:email:delivered', 'Delivery e-mail sent', $fresh->email, true)) {
            try { app(OrderMailer::class)->sendDelivered($fresh); } catch (\Throwable $e) { report($e); }
        }
        if ($fresh->sms_opt_in && $fresh->phone && self::record($fresh, 'notify:sms:delivered', 'Delivery text sent', $fresh->phone, true)) {
            try {
                app(SmsService::class)->send($fresh->phone,
                    "Universal Driveshafts: order {$fresh->order_number} was delivered. Details: " . self::statusUrl($fresh) . " Questions? (813) 524-6491");
            } catch (\Throwable $e) { report($e); }
        }
    }

    /** Carrier statuses a person at the shop has to act on (the customer may never notice them in time). */
    public const PROBLEM_STATUSES = ['return_to_sender', 'failure', 'available_for_pickup'];

    /** E-mail the shop once per order + problem status (called by `orders:track` with the tracking result). */
    public static function notifyShopOfProblem(object $order, ?array $st): bool
    {
        $raw = (string) ($st['raw'] ?? '');
        if (!in_array($raw, self::PROBLEM_STATUSES, true)) return false;
        if (!self::record($order, 'notify:shop:' . $raw, 'Shop alerted: ' . ($st['status'] ?? $raw), config('uds.email'), true)) return false;
        try { app(OrderMailer::class)->sendTrackingProblem($order, $st); }
        catch (\Throwable $e) { report($e); self::forget($order, 'notify:shop:' . $raw); return false; }   // retried on the next poll
        return true;
    }

    /**
     * "Ready for pickup" e-mail + text (text only with the checkout consent), once per order: from the admin when a
     * pickup order is marked completed, or when the shop station finishes its builder shaft (CheckinBridge::follow).
     */
    public static function notifyPickupReady(object $o): void
    {
        $url = self::statusUrl($o);
        if (self::record($o, 'notify:email:ready', 'Pickup-ready e-mail sent', $o->email, true)) {
            try { Mailer::simple($o->email, "Order {$o->order_number} is ready for pickup", ['kicker' => 'Ready for pickup', 'title' => "Come and get it, {$o->billing_first_name}!", 'intro' => "Order {$o->order_number} is packed and waiting at the counter.", 'kv' => ['Where' => config('uds.pickup.address'), 'Hours' => config('uds.pickup.hours'), 'Bring' => 'Your order number' . ($o->payment_method === 'pay_at_pickup' ? ' and payment of $' . number_format($o->total, 2) : '')], 'button' => ['Order status', $url], 'after' => ['Need another time? Call (813) 524-6491 first so we can have it ready.']], ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) { report($e); }
        }
        if ($o->sms_opt_in && $o->phone && self::record($o, 'notify:sms:ready', 'Pickup-ready text sent', $o->phone, true)) {
            try { app(SmsService::class)->send($o->phone, "Universal Driveshafts: order {$o->order_number} is ready for pickup in Odessa. Hours: " . config('uds.pickup.hours') . ". {$url}"); } catch (\Throwable $e) {}
        }
    }

    /** True for local-pickup orders (no carrier steps on the timeline). */
    public static function isPickup(object $order): bool
    {
        return ($order->shipping_method ?? null) === CartService::PICKUP_ID;
    }
}
