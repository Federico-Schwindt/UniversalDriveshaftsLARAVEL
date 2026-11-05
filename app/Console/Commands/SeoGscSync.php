<?php

namespace App\Console\Commands;

use App\Support\SearchConsole;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Search Console → seo_gsc_daily (property totals per day) and seo_gsc_rows (top 1,000 queries and pages per month).
 * First run: the 16 months Google keeps. Later runs (daily): the last 10 days and this month + last month again,
 * since Google keeps adjusting the last 2–3 days.
 */
class SeoGscSync extends Command
{
    protected $signature = 'seo:gsc-sync {--months= : months back to (re)load, default 16 on the first run, else 2}';
    protected $description = 'Pull clicks, impressions and average position from Google Search Console';

    public function handle(): int
    {
        if (!SearchConsole::configured()) { $this->warn('No Search Console key (' . SearchConsole::keyPath() . ').'); return self::SUCCESS; }
        $first = !DB::table('seo_gsc_daily')->exists();
        $months = (int) ($this->option('months') ?: ($first ? 16 : 2));
        $today = Carbon::today('America/Los_Angeles');   // Search Console days are Pacific time
        try {
            $from = $first || $this->option('months') ? $today->copy()->subMonthsNoOverflow($months - 1)->startOfMonth() : $today->copy()->subDays(10);
            $days = 0;
            foreach (SearchConsole::query($from->toDateString(), $today->toDateString(), ['date']) as $r) {
                DB::table('seo_gsc_daily')->updateOrInsert(['date' => $r['keys'][0]], ['clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round((float) $r['position'], 2), 'synced_at' => now()]);
                $days++;
            }
            $rows = 0;
            for ($i = 0; $i < $months; $i++) {
                $m = $today->copy()->startOfMonth()->subMonthsNoOverflow($i);
                $end = $i === 0 ? $today : $m->copy()->endOfMonth();
                foreach (['query', 'page'] as $kind) {
                    $data = SearchConsole::query($m->toDateString(), $end->toDateString(), [$kind], 1000);
                    DB::transaction(function () use ($data, $m, $kind, &$rows) {
                        DB::table('seo_gsc_rows')->where('month', $m->toDateString())->where('kind', $kind)->delete();
                        foreach (array_chunk($data, 500) as $chunk) {
                            DB::table('seo_gsc_rows')->insert(array_map(fn ($r) => ['month' => $m->toDateString(), 'kind' => $kind, 'key_hash' => sha1($r['keys'][0]), 'key' => $r['keys'][0],
                                'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round((float) $r['position'], 2)], $chunk));
                            $rows += count($chunk);
                        }
                    });
                }
            }
            Settings::set('seo.gsc_status', ['ok' => true, 'at' => now()->toIso8601String(), 'message' => "$days days, $rows query/page rows"]);
            $this->info("Search Console: $days days, $rows query/page rows ($months months).");
        } catch (\Throwable $e) {
            Settings::set('seo.gsc_status', ['ok' => false, 'at' => now()->toIso8601String(), 'message' => mb_substr($e->getMessage(), 0, 300)]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
