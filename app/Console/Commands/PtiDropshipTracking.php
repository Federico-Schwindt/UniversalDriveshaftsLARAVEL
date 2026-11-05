<?php

namespace App\Console\Commands;

use App\Services\PtiDropship;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Every 15 minutes: PTI dropship tracking onto our orders (marks them shipped and tells the customer). Dropships still
 * waiting for a number are asked every run; ones that have it are asked hourly for 30 days, so a new label at PTI follows.
 */
class PtiDropshipTracking extends Command
{
    protected $signature = 'pti:dropship-tracking {--no-notify}';
    protected $description = 'Copy PTI dropship tracking numbers onto our orders';

    public function handle(PtiDropship $ds): int
    {
        $rows = DB::table('pti_dropships')->where('status', 'placed')->where('placed_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereNull('tracking_number')->orWhereNull('tracking_checked_at')->orWhere('tracking_checked_at', '<', now()->subHour()))->get();
        $n = 0;
        foreach ($rows as $r) {
            try { if ($ds->syncTracking($r, !$this->option('no-notify'))) $n++; } catch (\Throwable $e) { $this->warn("dropship {$r->id}: {$e->getMessage()}"); }
        }
        $this->info("checked {$rows->count()}, $n got tracking");
        return self::SUCCESS;
    }
}
