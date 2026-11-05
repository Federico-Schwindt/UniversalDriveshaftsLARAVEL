<?php

namespace App\Console\Commands;

use App\Services\EasyPostTrackingService;
use App\Support\OrderEvents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refresh carrier status for recent orders from EasyPost.
 *
 *   php artisan orders:track            (scheduled every 30 min)
 *   php artisan orders:track --order=UDS-260910-W0GM
 *   php artisan orders:track --days=60 --dry
 *
 * Every shipped-but-undelivered order from the last N days is read back from the
 * carrier through its tracking number. An order is marked delivered only on the
 * carrier's own "delivered" status — never guessed from elapsed time.
 */
class TrackOrders extends Command
{
    protected $signature = 'orders:track {--days=30 : Look back this many days} {--order= : A single order number} {--dry : Report only, change nothing}';
    protected $description = 'Poll EasyPost for carrier status of recent, undelivered orders';

    public function handle(EasyPostTrackingService $tracker): int
    {
        if (!$tracker->enabled()) {
            $this->warn('EasyPost is not enabled on this environment; nothing to poll.');
            return self::SUCCESS;
        }
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry');

        $q = DB::table('orders')->whereNull('delivered_at')->where('status', '!=', 'cancelled')
            ->where('shipping_method', '!=', \App\Services\CartService::PICKUP_ID)
            ->whereNotNull('tracking_number')
            ->where('source', '!=', 'woocommerce');   // history imported from the old store: never polled, never notified
        if ($num = $this->option('order')) {
            $q->where('order_number', $num);
        } else {
            $q->where('shipped_at', '>=', now()->subDays($days));
        }
        $orders = $q->orderBy('id')->get();
        $this->info('Checking ' . $orders->count() . ' order(s)' . ($dry ? ' [dry run]' : ''));

        $checked = $delivered = $updated = 0;
        foreach ($orders as $order) {
            $checked++;
            $st = $tracker->status((string) $order->tracking_number, $this->carrierCode($order));
            $fields = ['last_tracking_checked_at' => now()];
            if ($st) $fields += ['tracking_events' => json_encode($st['events'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'tracking_est' => $st['est'], 'tracking_events_at' => now()];
            if (!$st) {
                $this->line("  {$order->order_number}: no carrier scan yet (left as shipped)");
                if (!$dry) DB::table('orders')->where('id', $order->id)->update($fields);
                continue;
            }
            $this->line("  {$order->order_number}: \"{$st['status']}\"" . ($st['delivered'] ? ' → DELIVERED' : '') . ($st['est'] ? " · due {$st['est']}" : ''));
            if ($st['status'] !== $order->last_tracking_status) {
                $fields['last_tracking_status'] = $st['status'];
                if (!$dry) OrderEvents::record($order, 'tracking', 'Carrier update: ' . $st['status'], $st['est'] ? 'Estimated delivery ' . $st['est'] : null);
                $updated++;
            }
            if (!$dry && OrderEvents::notifyShopOfProblem($order, $st)) $this->warn("  {$order->order_number}: shop e-mailed ({$st['raw']})");   // returned / failed / at a pickup point: once per status
            if ($st['delivered'] && !$dry) {
                $scan = collect($st['events'])->firstWhere('status', 'delivered');
                $at = $scan && $scan['at'] ? rescue(fn () => \Carbon\Carbon::parse($scan['at']), null, false) : null;
                if ($at && $order->shipped_at && $at->lt(\Carbon\Carbon::parse($order->shipped_at)->subDay())) {
                    // a delivery from before this order shipped: the number belongs to another parcel; staff decide
                    OrderEvents::record($order, 'tracking:mismatch', 'Tracking number shows an older delivery',
                        'Carrier delivery dated ' . $at->format('M j, Y') . ', before this order shipped. Check the tracking number.', true);
                    $this->warn("  {$order->order_number}: delivery scan predates shipping — left for staff");
                } else {
                    OrderEvents::markDelivered($order, trim(($order->tracking_carrier ?: strtoupper((string) $st['carrier'])) . ' reported delivery'), true, $at);
                    $delivered++;
                }
            }
            if (!$dry) DB::table('orders')->where('id', $order->id)->update($fields);
        }
        $this->info("Done: {$checked} checked, {$updated} status changes, {$delivered} delivered.");
        return self::SUCCESS;
    }

    /** EasyPost guesses the carrier from the number, but the order usually already knows it. */
    private function carrierCode(object $order): ?string
    {
        return match (strtoupper((string) ($order->tracking_carrier ?? ''))) {
            'UPS' => 'UPSDAP',
            'FEDEX' => 'FedEx',
            'USPS' => 'USPS',
            'DHL', 'DHL EXPRESS' => 'DHLExpress',
            default => null,
        };
    }
}
