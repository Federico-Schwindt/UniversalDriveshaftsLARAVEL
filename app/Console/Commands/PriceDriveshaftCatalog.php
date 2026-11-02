<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PriceDriveshaftCatalog extends Command
{
    protected $signature = 'catalog:price-driveshafts {--dry : show what would change without saving}';
    protected $description = 'Apply config/driveshaft_pricing.php to ds_parts.price_auto';

    public function handle(): int
    {
        $cfg = config('driveshaft_pricing'); $baseCol = $cfg['base'] === 'price_cost' ? 'price_cost' : 'price_list';
        $apply = fn (float $b, array $r) => round($b * (1 + ($r['percent'] ?? 0) / 100) + ($r['add'] ?? 0), 2);

        $match = []; // part_number => [rule indexes]
        foreach ($cfg['rules'] as $i => $r) {
            $q = DB::table('ds_fitments')->whereIn('make', $r['makes']);
            if (!empty($r['model_like'])) $q->where('model', 'like', $r['model_like']);
            if (!empty($r['models'])) $q->whereIn('model', $r['models']);   // exact model names, as in ds_fitments
            foreach ($q->distinct()->pluck('part_number') as $pn) $match[$pn][] = $i;
        }
        $stats = []; $updates = [];
        foreach (DB::table('ds_parts')->whereNotNull($baseCol)->get(['part_number', $baseCol]) as $p) {
            $b = (float) $p->$baseCol; $best = $apply($b, $cfg['default']); $label = 'default';
            foreach ($match[$p->part_number] ?? [] as $i) {
                $v = $apply($b, $cfg['rules'][$i]);
                if ($v > $best) { $best = $v; $label = $cfg['rules'][$i]['name']; }
            }
            $updates[] = ['part_number' => $p->part_number, 'price_auto' => $best, 'price_rule' => $label];
            $stats[$label] = ($stats[$label] ?? 0) + 1;
        }
        foreach ($stats as $k => $n) $this->line(str_pad($k, 26) . $n . ' parts');
        if ($this->option('dry')) { $this->warn('dry run, nothing saved'); return 0; }
        foreach (array_chunk($updates, 500) as $chunk) {
            foreach ($chunk as $u) DB::table('ds_parts')->where('part_number', $u['part_number'])->update(['price_auto' => $u['price_auto'], 'price_rule' => $u['price_rule']]);
        }
        $this->info('saved ' . count($updates) . ' prices (base: ' . $baseCol . ')');
        return 0;
    }
}
