<?php

namespace App\Support;

use App\Services\OrderMailer;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;

/**
 * The one place an order gets marked shipped and the customer told about it —
 * used by both the admin's manual tracking form and a label purchase,
 * so the two paths can never drift into different email/SMS wording.
 */
class OrderTracking
{
    public static function markShipped(object $order, string $trackingNumber, ?string $carrier, ?string $service = null, bool $notify = true): void
    {
        $carrier = $carrier ?: 'UPS';
        DB::table('orders')->where('id', $order->id)->update([
            'tracking_number' => $trackingNumber,
            'tracking_carrier' => $carrier,
            'tracking_service' => $service,
            'shipped_at' => $order->shipped_at ?? now(),
            'status' => in_array($order->status, ['pending', 'processing'], true) ? 'completed' : $order->status,
            'updated_at' => now(),
        ]);

        // timeline: one "shipped" row, plus a "tracking" row whenever the number changes later
        $detail = trim($carrier . ($service ? " · {$service}" : '') . " · {$trackingNumber}");
        if (!OrderEvents::record($order, 'shipped', 'Shipped', $detail, true) && $order->tracking_number !== $trackingNumber) {
            OrderEvents::record($order, 'tracking', 'Tracking number updated', $detail);
        }

        if (!$notify) return;
        $fresh = DB::table('orders')->find($order->id) ?: $order;
        // every notification is recorded BEFORE it is sent so a webhook replay,
        // the admin form and `orders:track` can never send the same message twice
        if (OrderEvents::record($fresh, 'notify:email:shipped', 'Shipping e-mail sent', $fresh->email, true)) {
            try { app(OrderMailer::class)->sendShipped($fresh, $carrier, $trackingNumber, $service); } catch (\Throwable $e) { report($e); }
        }
        if (($fresh->sms_opt_in ?? false) && $fresh->phone && OrderEvents::record($fresh, 'notify:sms:shipped', 'Shipping text sent', $fresh->phone, true)) {
            $link = OrderEvents::carrierUrl($carrier, $trackingNumber);
            try {
                app(SmsService::class)->send($fresh->phone, "Universal Driveshafts: order {$fresh->order_number} shipped via {$carrier}, tracking {$trackingNumber}. Track: {$link} Status: " . OrderEvents::statusUrl($fresh));
            } catch (\Throwable $e) { report($e); }
        }
    }
}
