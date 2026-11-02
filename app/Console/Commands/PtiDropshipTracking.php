<?php

namespace App\Console\Commands;

use App\Services\PtiDropship;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Hourly: tracking numbers of PTI dropships onto our orders (marks them shipped and tells the customer). */
class PtiDropshipTracking extends Command
{
    protected $signature = 'pti:dropship-tracking {--no-notify}';
    protected $description = 'Copy PTI dropship tracking numbers onto our orders';

    public function handle(PtiDropship $ds): int
    {
        $rows = DB::table('pti_dropships')->where('status', 'placed')->whereNull('tracking_number')->where('placed_at', '>=', now()->subDays(30))->get();
        $n = 0;
        foreach ($rows as $r) {
            try { if ($ds->syncTracking($r, !$this->option('no-notify'))) $n++; } catch (\Throwable $e) { $this->warn("dropship {$r->id}: {$e->getMessage()}"); }
        }
        $this->info("checked {$rows->count()}, $n got tracking");
        return self::SUCCESS;
    }
}
