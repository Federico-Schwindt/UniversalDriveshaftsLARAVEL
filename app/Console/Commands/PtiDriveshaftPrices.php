<?php

namespace App\Console\Commands;

use App\Services\PtiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Refresh complete-driveshaft prices and availability from Powertrain Industries (every 4 h), then re-apply
 * config/driveshaft_pricing.php. ds_parts.price_list = PTI's strike-through list price, price_cost = PTI's
 * discounted price, which is the pricing base (owner, 2026-10-24).
 * A shaft PTI no longer lists, or lists without a price, is taken off the site (is_active = 0) and comes back by
 * itself when PTI sells it again (owner, 2026-10-24). If PTI suddenly "loses" a large share of the catalog the run refuses to hide anything.
 * Only shafts this command hid (hidden_reason = pti_absent) come back; one hidden by staff (hidden_reason admin / null) stays hidden.
 * A list price that falls below 50% or rises above 200% of the current one is not applied — logged for staff to check.
 */
class PtiDriveshaftPrices extends Command
{
    protected $signature = 'pti:driveshaft-prices {--limit=0} {--dry : report only}';
    protected $description = 'Pull current complete-driveshaft prices from PTI, hide shafts PTI dropped, and reprice the catalog';

    private const MAX_HIDE_SHARE = 0.10;   // more than 10% missing in one run = PTI trouble, not real removals

    public function handle(PtiClient $pti): int
    {
        $parts = DB::table('ds_parts')->where('supplier', 'PTI')->when((int) $this->option('limit'), fn ($q, $l) => $q->limit($l))->get(['part_number', 'price_list', 'is_active', 'hidden_reason'])->keyBy(fn ($p) => (string) $p->part_number);
        $this->line('asking PTI for ' . $parts->count() . ' driveshafts…');
        try { $res = $pti->driveshaftPrices($parts->keys()->map(fn ($k) => (string) $k)->all(), 4); } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
        $prices = $res['prices']; $absent = $res['absent'];
        $unknown = $parts->count() - count($prices) - count($absent);

        $changed = 0; $back = []; $jumps = [];
        foreach ($prices as $pn => $p) {
            $pn = (string) $pn; $cur = $parts[$pn]; $old = (float) $cur->price_list;
            // a price that halves or more than doubles in one run is more likely a PTI data glitch than a real change
            if ((float) $p['list'] <= 0 || ($old > 0 && ((float) $p['list'] < $old * 0.5 || (float) $p['list'] > $old * 2))) {
                $jumps[] = "$pn: \$" . number_format($old, 2) . ' → $' . number_format((float) $p['list'], 2);
                continue;
            }
            if (abs($old - $p['list']) >= 0.01) $changed++;
            $upd = ['price_list' => $p['list'], 'price_cost' => $p['net'], 'updated_at' => now()];
            // only shafts this command took off come back by themselves; staff-hidden ones stay hidden
            if (!$cur->is_active && $cur->hidden_reason === 'pti_absent') { $back[] = $pn; $upd += ['is_active' => 1, 'hidden_reason' => null]; }
            if (!$this->option('dry')) DB::table('ds_parts')->where('part_number', $pn)->update($upd);
        }
        if ($jumps) {
            $this->warn('price jump skipped (outside 50%–200% of the current list price): ' . count($jumps) . ' — ' . implode('; ', array_slice($jumps, 0, 10)));
            \Illuminate\Support\Facades\Log::warning('pti:driveshaft-prices skipped ' . count($jumps) . ' price jump(s)', ['parts' => array_slice($jumps, 0, 50)]);
        }
        $hide = array_values(array_filter(array_map('strval', $absent), fn ($pn) => $parts[$pn]->is_active));
        $this->info(count($prices) . " listed at PTI ($changed price change(s)), " . count($absent) . ' no longer listed or unpriced, ' . $unknown . ' not checked (request failed)');
        if (count($absent) > self::MAX_HIDE_SHARE * $parts->count()) {
            $this->error('PTI is missing ' . count($absent) . ' shafts at once — not hiding anything this run');
            $hide = [];
        }
        if ($back) $this->line('back on the site: ' . count($back) . ' (' . implode(', ', array_slice($back, 0, 10)) . ')');
        if ($hide) $this->line('taken off the site: ' . count($hide) . ' (' . implode(', ', array_slice($hide, 0, 10)) . (count($hide) > 10 ? ', …' : '') . ')');
        if ($this->option('dry')) return self::SUCCESS;

        foreach (array_chunk($hide, 500) as $c) DB::table('ds_parts')->whereIn('part_number', $c)->update(['is_active' => 0, 'hidden_reason' => 'pti_absent', 'updated_at' => now()]);
        if ($hide || $back) { foreach (['finder:makes', 'finder:search-index-v2', 'catalog_ds_subcat'] as $k) Cache::forget($k); Cache::forever('catalog_ver', time()); }
        Artisan::call('catalog:price-driveshafts');
        $this->line(trim(collect(explode("\n", Artisan::output()))->last(fn ($l) => trim($l) !== '')));
        return self::SUCCESS;
    }
}
