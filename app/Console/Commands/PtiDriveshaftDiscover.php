<?php

namespace App\Console\Commands;

use App\Services\PtiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Find complete driveshafts PTI has added and put them on the site (owner, 2026-10-24).
 * PTI's driveshaft search matches any part number containing the text, so the 1,000 three-digit strings
 * 000-999 cover the whole catalog. For each priced part we don't have: its details (description, weight,
 * box, photos) come from the part API, and its vehicles from the "applications" text, expanded into full
 * fitment rows (engine, trans, cab…) by asking PTI's vehicle catalog for each make / year / model / drive.
 * Removals and price changes are handled by pti:driveshaft-prices.
 */
class PtiDriveshaftDiscover extends Command
{
    protected $signature = 'pti:driveshaft-discover {--dry : list new shafts, add nothing} {--max=150 : most shafts to add in one run}';
    protected $description = 'Add complete driveshafts PTI has listed since the last run';

    private const FIT_COLS = ['part_number', 'make', 'year', 'model', 'drive', 'loc', 'engine', 'trans', 'trans_speeds', 'trans_code',
        'cab', 'bed', 'chassis', 'doors', 'trim', 'rear_wheels', 'transfer_case', 'differential', 'c_brg', 'notes',
        'weld_to_weld', 'weld_to_weld_1', 'weld_to_weld_2', 'weld_to_weld_3'];

    public function handle(PtiClient $pti): int
    {
        $queries = array_map(fn ($n) => str_pad((string) $n, 3, '0', STR_PAD_LEFT), range(0, 999));
        $this->line('scanning PTI driveshafts (' . count($queries) . ' searches)…');
        try { $scan = $pti->searchDriveshafts($queries, 4); } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
        if (count($scan['failed']) > 50) { $this->error(count($scan['failed']) . ' searches failed — PTI unreachable, nothing added'); return self::FAILURE; }

        $ours = DB::table('ds_parts')->pluck('part_number')->map(fn ($p) => strtoupper((string) $p))->flip();
        $new = array_filter($scan['rows'], fn ($r, $pn) => !isset($ours[strtoupper((string) $pn)]) && !empty($r['price']), ARRAY_FILTER_USE_BOTH);
        $this->info(count($scan['rows']) . ' shafts at PTI, ' . count($new) . ' new and priced' . ($scan['failed'] ? ', ' . count($scan['failed']) . ' searches failed' : ''));
        if (!$new) return self::SUCCESS;
        if ($this->option('dry')) { foreach (array_slice($new, 0, 40, true) as $pn => $r) $this->line("  $pn  {$r['price']}  " . str_replace("\n", ' ', mb_strimwidth((string) ($r['applications'] ?? ''), 0, 90, '…'))); return self::SUCCESS; }

        $makes = $this->makes($pti);
        $added = 0; $fitTotal = 0; $vehCache = [];
        foreach (array_slice($new, 0, (int) $this->option('max'), true) as $pn => $row) {
            $pn = (string) $pn;
            try { $d = $pti->call('GET', 'my/parts/part/' . rawurlencode($pn))['part'] ?? null; } catch (\Throwable $e) { $this->warn("$pn: {$e->getMessage()}"); continue; }
            if (!$d) continue;
            $money = fn ($v) => ($n = (float) str_replace(['$', ','], '', (string) $v)) > 0 ? $n : null;

            // vehicles: "2016-2019 Ford Explorer AWD/4WD,\n2002 GMC Envoy AWD/4WD"
            $fits = [];
            foreach ($this->applications((string) ($row['applications'] ?? $d['catalog_result']['values']['applications'] ?? ''), $makes) as $app) {
                for ($y = $app['from']; $y <= $app['to']; $y++) {
                    $f = ['make' => $app['make'], 'year' => $y, 'model' => $app['model']] + ($app['drive'] ? ['drive' => $app['drive']] : []);
                    $ck = md5(json_encode($f));
                    if (!isset($vehCache[$ck])) { try { $vehCache[$ck] = $pti->call('GET', 'catalogs/driveshafts/driveshafts', $f)['results'] ?? []; } catch (\Throwable $e) { $vehCache[$ck] = []; } usleep(150000); }
                    $hit = array_filter($vehCache[$ck], fn ($r) => strcasecmp((string) ($r['part_number'] ?? ''), $pn) === 0);
                    // PTI's vehicle catalog may not echo every application back: keep at least make/year/model/drive
                    foreach ($hit ?: [['make' => $app['make'], 'model' => $app['model'], 'drive' => $app['drive'], 'loc' => $row['loc'] ?? null, 'c_brg' => $row['c_brg'] ?? null]] as $h) {
                        $fit = [];
                        foreach (self::FIT_COLS as $c) $fit[$c] = isset($h[$c]) && trim((string) $h[$c]) !== '' ? trim((string) $h[$c]) : null;
                        $fit['part_number'] = $pn; $fit['year'] = $y; $fit['make'] = $fit['make'] ?? $app['make']; $fit['model'] = $fit['model'] ?? $app['model'];
                        $fit['fkey'] = md5(implode('|', array_map(fn ($v) => (string) $v, array_intersect_key($fit, array_flip(self::FIT_COLS)))));
                        $fits[$fit['fkey']] = $fit;
                    }
                }
            }
            if (!$fits) { $this->warn("$pn: no vehicle could be read from \"" . str_replace("\n", ' ', (string) ($row['applications'] ?? '')) . '" — skipped'); continue; }

            $images = $this->photos($pn, (array) ($d['part_picture_urls'] ?? []));
            $now = now();
            DB::table('ds_parts')->insertOrIgnore([
                'part_number' => $pn, 'brand' => $d['brand'] ?? null, 'description' => $d['description'] ?? null, 'condition' => $d['condition'] ?? 'new',
                'notes' => null, 'price_list' => $money($d['strike_price'] ?? null) ?? $money($d['price'] ?? null), 'price_cost' => $money($d['price'] ?? null),
                'core_price' => $money($d['core_price'] ?? null), 'weight' => (float) ($d['weight'] ?? 0) ?: null,
                'box_length' => (float) ($d['box_length'] ?? 0) ?: null, 'box_width' => (float) ($d['box_width'] ?? 0) ?: null, 'box_height' => (float) ($d['box_height'] ?? 0) ?: null,
                'upc' => ($d['upc'] ?? '') ?: null, 'series' => $d['series'] ?? null, 'vehicle_class' => $d['vehicle_class'] ?? null,
                'is_buildable' => (int) ($d['is_buildable'] ?? 0), 'picture_count' => count($images), 'main_image' => $images[0] ?? null, 'images' => $images ? json_encode($images) : null,
                'supplier' => 'PTI', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('ds_fitments')->insertOrIgnore(array_values($fits));
            $added++; $fitTotal += count($fits);
            $this->line("  + $pn  " . ($d['description'] ?? '') . '  ' . count($fits) . ' vehicle row(s)');
        }
        if ($added) {
            Artisan::call('catalog:price-driveshafts');
            foreach (['finder:makes', 'finder:search-index-v2', 'catalog_ds_subcat'] as $k) Cache::forget($k);
            Cache::forever('catalog_ver', time());
        }
        $this->info("added $added shaft(s), $fitTotal vehicle row(s)" . (count($new) > $added ? '; ' . (count($new) - $added) . ' left for the next run' : ''));
        return self::SUCCESS;
    }

    private function makes(PtiClient $pti): array
    {
        $m = $pti->call('GET', 'catalogs/driveshafts/driveshafts')['filter_options']['make'] ?? [];
        usort($m, fn ($a, $b) => strlen($b) <=> strlen($a));   // "Land Rover" before "Land"
        return $m;
    }

    /** "1988-1993 GMC K10/K1500 Pick Up AWD/4WD" → [from, to, make, model, drive] */
    private function applications(string $text, array $makes): array
    {
        $out = [];
        foreach (preg_split('/,\s*\n|\n|;\s*/', $text) as $line) {
            $line = trim($line, " ,\t");
            if (!preg_match('/^(\d{4})(?:\s*-\s*(\d{4}))?\s+(.+)$/', $line, $m)) continue;
            [$from, $to] = [(int) $m[1], (int) ($m[2] ?: $m[1])];
            if ($to < $from || $to - $from > 40) continue;
            $rest = $m[3]; $make = null;
            foreach ($makes as $mk) if (stripos($rest, $mk . ' ') === 0) { $make = $mk; $rest = trim(substr($rest, strlen($mk))); break; }
            if (!$make) continue;
            $drive = null;
            if (preg_match('#\s+((?:AWD|RWD|FWD|4WD|2WD)(?:/(?:AWD|RWD|FWD|4WD|2WD))?)$#i', $rest, $dm)) { $drive = $dm[1]; $rest = trim(substr($rest, 0, -strlen($dm[0]))); }
            if ($rest === '') continue;
            $out[] = ['from' => $from, 'to' => $to, 'make' => $make, 'model' => $rest, 'drive' => $drive];
        }
        return $out;
    }

    /** Photos go where the imported ones live: public/img/driveshafts/{part}/{n}.{ext} */
    private function photos(string $pn, array $urls): array
    {
        $dir = public_path('img/driveshafts/' . $pn);
        $out = [];
        foreach (array_slice(array_values(array_filter($urls, fn ($u) => !str_contains($u, 'picture-missing'))), 0, 8) as $i => $u) {
            $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
            $file = "$dir/" . ($i + 1) . ".$ext";
            if (!is_file($file)) {
                $r = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Referer' => 'https://c2.powertrainindustries.com/'])
                    ->timeout(30)->retry(2, 1000, throw: false)->get($u);
                if (!$r->successful() || !str_starts_with((string) $r->header('Content-Type'), 'image/')) continue;
                @mkdir($dir, 0775, true);
                file_put_contents($file, $r->body());
            }
            $out[] = "/img/driveshafts/$pn/" . ($i + 1) . ".$ext";
        }
        return $out;
    }
}
