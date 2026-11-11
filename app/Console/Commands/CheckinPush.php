<?php

namespace App\Console\Commands;

use App\Services\CheckinBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Work orders for online-builder shafts that did not reach the shop's station (App\Services\CheckinBridge).
 *
 *   php artisan checkin:push                         (scheduled every 10 min)
 *   php artisan checkin:push --order=UDS-261109-AB12 (send or re-check one order now)
 *
 * The send normally happens right after the order is committed; this picks up the ones whose send failed (station
 * asleep or unreachable). A job number already on the station is never created twice. Each run also reads the
 * status of the jobs already sent and tells their customers when the build starts and when it is finished.
 */
class CheckinPush extends Command
{
    protected $signature = 'checkin:push {--order= : A single order number} {--days=14 : Look back this many days}';
    protected $description = 'Send online-builder orders to the work order station and pass their build progress to the customer';

    public function handle(): int
    {
        if (!CheckinBridge::enabled()) {
            $this->warn('The work order station is not configured here (CHECKIN_DATABASE_URL); nothing to send.');
            return self::SUCCESS;
        }
        if ($num = $this->option('order')) {
            $order = DB::table('orders')->where('order_number', $num)->first();
            if (!$order) { $this->error("No order {$num}."); return self::FAILURE; }
            if (CheckinBridge::lines((int) $order->id)->isEmpty()) { $this->warn("{$num} has no online-builder shaft."); return self::SUCCESS; }
            $jobs = CheckinBridge::send((int) $order->id);
            foreach ($jobs as $job => $id) $this->line("Job {$job} = check-in #{$id}");
            return self::SUCCESS;
        }

        // queued when committed, not confirmed sent, and old enough that the after-response send is surely over
        $ids = DB::table('order_events as q')->where('q.type', 'checkin:queued')
            ->where('q.created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))->where('q.created_at', '<', now()->subMinutes(5))
            ->whereNotExists(fn ($s) => $s->from('order_events as s')->whereColumn('s.order_id', 'q.order_id')->where('s.type', 'checkin:sent'))
            ->pluck('q.order_id');
        $sent = 0;
        foreach ($ids as $id) $sent += CheckinBridge::deliver((int) $id) ? 1 : 0;
        if ($ids->count()) $this->line("{$sent} of {$ids->count()} order(s) sent to the station.");
        // still not on the station half an hour after it was committed: the shop is told once, so nobody waits on it
        $this->alertStuck();

        // what changed on the order since (paid, cancelled, shipped) reaches the work order
        try {
            if ($n = CheckinBridge::sync()) $this->line("{$n} work order(s) updated.");
        } catch (\Throwable $e) {
            report($e);
            $this->warn('Could not update work orders on the station: ' . $e->getMessage());
        }

        // the shop's progress back to the customer ("started building" / "built, ships soon" / "ready for pickup")
        try {
            [$started, $built] = CheckinBridge::follow();
            if ($started || $built) $this->line("Customers told: {$started} build(s) started, {$built} finished.");
        } catch (\Throwable $e) {
            report($e);
            $this->warn('Could not read job status from the station: ' . $e->getMessage());
            return self::FAILURE;
        }
        return $sent === $ids->count() ? self::SUCCESS : self::FAILURE;
    }

    /** One e-mail to the shop per order whose work order has not reached the station 30 minutes after it was committed. */
    private function alertStuck(): void
    {
        $stuck = DB::table('order_events as q')->join('orders as o', 'o.id', '=', 'q.order_id')->where('q.type', 'checkin:queued')
            ->where('q.created_at', '<', now()->subMinutes(30))->where('q.created_at', '>=', now()->subDays(14))
            ->whereNotExists(fn ($s) => $s->from('order_events as s')->whereColumn('s.order_id', 'q.order_id')->whereIn('s.type', ['checkin:sent', 'notify:checkin:stuck']))
            ->select('o.*')->get();
        foreach ($stuck as $o) {
            if (!\App\Support\OrderEvents::record($o, 'notify:checkin:stuck', 'Shop told: work order not on the station', config('uds.email'), true)) continue;
            $why = DB::table('order_events')->where('order_id', $o->id)->where('type', 'checkin:failed')->orderByDesc('id')->value('detail');
            try {
                \App\Support\Mailer::simple((string) config('uds.email'), "Work order missing on the shop station — order {$o->order_number}", [
                    'kicker' => 'Shop station', 'title' => "Order {$o->order_number} is not on the station yet",
                    'intro' => 'A custom driveshaft order was committed more than 30 minutes ago and its work order could not be written to the station. It keeps retrying every 10 minutes.',
                    'paragraphs' => array_filter(['Enter it by hand if the shop needs to start now, using the build sheet on the admin order page.', $why ? 'Last error: ' . $why : null]),
                    'button' => ['Open the order', url('/admin/orders/' . $o->id)],
                ]);
            } catch (\Throwable $e) { report($e); }
            $this->warn("Shop told: {$o->order_number} is not on the station.");
        }
    }
}
