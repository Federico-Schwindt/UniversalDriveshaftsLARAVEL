<?php

namespace App\Console\Commands;

use App\Services\AmPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Refresh the online builder's reference price table (storage/app/builder/am-prices.json) from our supplier
 * Action Machine's public Shopify product JSON (/products/{handle}.js), one request per second, only for the
 * handles already in the table. Our price is the supplier price × AmPricing::MARKDOWN (0.85).
 *
 * Guard rails: a variant whose price moved more than ±35 % since the last sync is skipped and logged for a
 * person to look at (--force applies it anyway); a variant or product that disappeared is marked unavailable,
 * never deleted. Every change is written to storage/logs/builder-prices.log. Scheduled weekly (routes/console.php).
 */
class BuilderSyncPrices extends Command
{
    protected $signature = 'builder:sync-prices {--handles= : comma-separated handles (default: every handle in the table)} {--limit=0 : stop after this many products} {--force : apply price moves over the guard-rail threshold} {--dry : fetch and report, write nothing}';
    protected $description = 'Weekly: refresh the builder price table from the supplier\'s public product JSON (1 req/s), our price = 80 %; moves over ±35 % are skipped and logged, missing variants are marked unavailable';

    public const GUARD = 0.35;

    public function handle(AmPricing $am): int
    {
        $table = $am->table();
        $handles = $this->option('handles') ? array_filter(array_map('trim', explode(',', $this->option('handles')))) : array_keys($table['products']);
        if ($limit = (int) $this->option('limit')) $handles = array_slice($handles, 0, $limit);
        $base = rtrim((string) ($table['source'] ?? 'https://www.actionmachineinc.com'), '/');
        $today = now()->toDateString();
        $log = fn (string $line) => Log::build(['driver' => 'single', 'path' => storage_path('logs/builder-prices.log')])->info($line);
        $changed = $skipped = $missing = $fetched = 0;
        foreach ($handles as $i => $handle) {
            if (!isset($table['products'][$handle])) { $this->warn("unknown handle $handle"); continue; }
            if ($i > 0) usleep(1100000); // polite: about one request per second
            $res = $this->fetch($base . '/products/' . $handle . '.js');
            $fetched++;
            $p = &$table['products'][$handle];
            if ($res === null) {
                if ($p['available'] ?? true) { $log("$handle: product not reachable; marked unavailable"); $missing++; }
                $p['available'] = false;
                continue;
            }
            $p['available'] = true; $p['last_seen'] = $today;
            if (!empty($res['title'])) $p['title'] = $res['title'];
            $p['options'] = array_map(fn ($o) => ['name' => $o['name'], 'values' => $o['values']], $res['options'] ?? []);
            $seen = [];
            foreach ($res['variants'] ?? [] as $v) {
                $id = (string) $v['id']; $seen[$id] = true;
                $price = round(((int) $v['price']) / 100, 2); // Shopify .js prices are cents
                $opts = array_values(array_filter([$v['option1'] ?? null, $v['option2'] ?? null, $v['option3'] ?? null], fn ($x) => $x !== null));
                $old = $p['variants'][$id] ?? null;
                if ($old && (float) $old['am_price'] > 0 && abs($price - $old['am_price']) / $old['am_price'] > self::GUARD && !$this->option('force')) {
                    $log(sprintf('%s %s: price moved %.2f -> %.2f (%+.0f%%), SKIPPED for review', $handle, $v['sku'] ?? $id, $old['am_price'], $price, ($price / $old['am_price'] - 1) * 100));
                    $skipped++; $p['variants'][$id]['last_seen'] = $today; $p['variants'][$id]['available'] = (bool) ($v['available'] ?? true);
                    continue;
                }
                if (!$old) $log("$handle {$v['sku']}: new variant " . implode(' / ', $opts) . " at $price");
                elseif ((float) $old['am_price'] !== $price) $log(sprintf('%s %s: %.2f -> %.2f', $handle, $v['sku'] ?? $id, $old['am_price'], $price));
                if (!$old || (float) $old['am_price'] !== $price) $changed++;
                $p['variants'][$id] = [
                    'id' => $id, 'sku' => (string) ($v['sku'] ?? ''), 'options' => $opts, 'am_price' => $price,
                    'our_price' => AmPricing::ours($price),
                    'available' => (bool) ($v['available'] ?? true), 'first_seen' => $old['first_seen'] ?? $today, 'last_seen' => $today,
                ];
            }
            foreach ($p['variants'] as $id => &$old) {
                if (!isset($seen[$id]) && ($old['available'] ?? true)) { $old['available'] = false; $log("$handle {$old['sku']}: variant gone; marked unavailable"); $missing++; }
            }
            unset($old, $p);
            $this->line(sprintf('%-45s %d variants', $handle, count($res['variants'] ?? [])));
        }
        $table['synced_at'] = now()->toDateTimeString();
        if (!$this->option('dry')) $am->save($table);
        $msg = "builder:sync-prices fetched $fetched, changed $changed, skipped $skipped (guard rail), unavailable $missing";
        $log($msg); $this->info($msg);
        return self::SUCCESS;
    }

    private function fetch(string $url): ?array
    {
        try {
            $r = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; UDS price sync; +https://udshafts.com)', 'Accept' => 'application/json'])->timeout(20)->get($url);
            if ($r->status() === 404) return null;
            if (!$r->ok()) { $this->warn("$url -> " . $r->status()); return []; }
            $j = $r->json();
            return is_array($j) && isset($j['variants']) ? $j : [];
        } catch (\Throwable $e) {
            $this->warn("$url: " . $e->getMessage());
            return [];
        }
    }
}
