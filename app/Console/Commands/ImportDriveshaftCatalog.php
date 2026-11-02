<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDriveshaftCatalog extends Command
{
    protected $signature = 'catalog:import-driveshafts {dir : folder holding pti-parts.csv and fitments.csv}';
    protected $description = 'Idempotent import of the driveshaft parts and vehicle fitments (safe to re-run)';

    private const FIT_COLS = ['part_number', 'make', 'year', 'model', 'drive', 'loc', 'engine', 'trans', 'trans_speeds', 'trans_code',
        'cab', 'bed', 'chassis', 'doors', 'trim', 'rear_wheels', 'transfer_case', 'differential', 'c_brg', 'notes',
        'weld_to_weld', 'weld_to_weld_1', 'weld_to_weld_2', 'weld_to_weld_3'];

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        foreach (['pti-parts.csv', 'fitments.csv'] as $f) {
            if (!is_file("$dir/$f")) { $this->error("missing $dir/$f"); return 1; }
        }
        $now = now();
        $nn = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;

        // ---- parts ----
        $known = [];
        foreach (array_chunk($this->csv("$dir/pti-parts.csv"), 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $r) {
                $pn = $nn($r['part_number']); if (!$pn) continue;
                $known[$pn] = true;
                $rows[] = [
                    'part_number' => $pn, 'brand' => $nn($r['brand']), 'description' => $nn($r['description']),
                    'condition' => $nn($r['condition']), 'notes' => $nn($r['notes']),
                    'price_list' => $nn($r['price_list']), 'price_cost' => $nn($r['price_yours']), 'core_price' => $nn($r['core_price']),
                    'weight' => $nn($r['weight']), 'box_length' => $nn($r['box_length']), 'box_width' => $nn($r['box_width']),
                    'box_height' => $nn($r['box_height']), 'upc' => $nn($r['upc']), 'series' => $nn($r['series']),
                    'vehicle_class' => $nn($r['vehicle_class']), 'is_buildable' => (int) ($r['is_buildable'] ?: 0),
                    'picture_count' => (int) ($r['picture_count'] ?: 0), 'supplier' => 'PTI', 'is_active' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            // price_retail and is_active are ours: never overwritten by a re-import
            DB::table('ds_parts')->upsert($rows, ['part_number'], ['brand', 'description', 'condition', 'notes', 'price_list', 'price_cost',
                'core_price', 'weight', 'box_length', 'box_width', 'box_height', 'upc', 'series', 'vehicle_class', 'is_buildable',
                'picture_count', 'supplier', 'updated_at']);
        }

        // ---- fitments (+ stub parts for numbers missing from the price list) ----
        $stubs = []; $count = 0;
        foreach (array_chunk($this->csv("$dir/fitments.csv"), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $r) {
                $row = [];
                foreach (self::FIT_COLS as $c) $row[$c] = $nn($r[$c] ?? null);
                if (!$row['part_number'] || !$row['make'] || !$row['year'] || !$row['model']) continue;
                $row['year'] = (int) $row['year'];
                $row['fkey'] = md5(implode('|', array_map(fn ($v) => (string) $v, $row)));
                $rows[$row['fkey']] = $row;
                if (!isset($known[$row['part_number']])) $stubs[$row['part_number']] = $nn($r['price_list'] ?? null);
            }
            DB::table('ds_fitments')->upsert(array_values($rows), ['fkey'], ['part_number']);
            $count += count($rows);
        }
        foreach (array_chunk(array_keys($stubs), 500) as $chunk) {
            DB::table('ds_parts')->insertOrIgnore(array_map(fn ($pn) => [
                'part_number' => $pn, 'description' => null, 'price_list' => $stubs[$pn], 'supplier' => 'PTI',
                'is_active' => 0, 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }

        $this->call('catalog:price-driveshafts'); // re-apply selling-price rules after every import
        $noFit = DB::table('ds_parts')->whereNotIn('part_number', DB::table('ds_fitments')->select('part_number'))->count();
        $this->info('parts in table:      ' . DB::table('ds_parts')->count());
        $this->info('fitments in table:   ' . DB::table('ds_fitments')->count() . " (read $count rows)");
        $this->warn('stub parts (in fitments, not in price list, inactive): ' . count($stubs));
        $this->warn("parts with no fitment row (reach via search): $noFit");
        if ($stubs) file_put_contents(storage_path('logs/ds-import-stubs.txt'), implode("\n", array_keys($stubs)) . "\n");
        return 0;
    }

    private function csv(string $path): array
    {
        $h = fopen($path, 'r'); $head = fgetcsv($h);
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
        $out = [];
        while (($r = fgetcsv($h)) !== false) {
            if (count($r) === count($head)) $out[] = array_combine($head, $r);
        }
        fclose($h);
        return $out;
    }
}
