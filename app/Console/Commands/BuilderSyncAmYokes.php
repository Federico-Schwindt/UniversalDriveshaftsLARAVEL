<?php

namespace App\Console\Commands;

use App\Support\YokeMakes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * The vehicle makes our supplier Action Machine tags its flange yokes with ("Vehicle_Ford"), with the part numbers
 * each one is listed under and its series and bolt circle, from their public Shopify collection JSON (one page a
 * second). Stored in storage/app/builder/am-flange-yokes.json; App\Support\YokeMakes gives a make to OUR yoke only
 * when the part number, the series and the bolt circle all agree (owner, 2026-11-11: "they can have useful information too").
 * Facts only: no text or photo of theirs is kept.
 */
class BuilderSyncAmYokes extends Command
{
    protected $signature = 'builder:sync-am-yokes {--dry : report only}';
    protected $description = 'Weekly: vehicle makes of the supplier\'s flange yokes (public collection JSON) for the builder\'s make step';

    public const FILE = 'builder/am-flange-yokes.json';
    private const SOURCE = 'https://www.actionmachineinc.com/collections/flange-yoke/products.json';

    public function handle(): int
    {
        $items = [];
        for ($page = 1; $page <= 10; $page++) {
            if ($page > 1) usleep(1100000);
            try {
                $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; UDS catalog sync; +https://universaldriveshafts.com)', 'Accept' => 'application/json'])
                    ->timeout(30)->get(self::SOURCE, ['limit' => 250, 'page' => $page]);
            } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
            if (!$res->successful()) { $this->error("page $page: HTTP {$res->status()}"); return self::FAILURE; }
            $products = (array) $res->json('products');
            if (!$products) break;
            foreach ($products as $p) {
                $tags = (array) ($p['tags'] ?? []);
                $tag = fn (string $k) => collect($tags)->first(fn ($t) => str_starts_with($t, $k . '_')) ? substr(collect($tags)->first(fn ($t) => str_starts_with($t, $k . '_')), strlen($k) + 1) : '';
                $makes = [];
                foreach ($tags as $t) if (str_starts_with($t, 'Vehicle_')) $makes = array_merge($makes, YokeMakes::detect(substr($t, 8)));
                $sku = trim((string) ($p['variants'][0]['sku'] ?? ''));
                // the bare part-number tags are the numbers the part is sold under (Spicer, Neapco, PTI…)
                $numbers = array_values(array_unique(array_filter(array_merge([$sku], array_filter($tags, fn ($t) => preg_match('/^[A-Z0-9][A-Z0-9\-.]{3,}$/', $t) && !str_contains($t, '_'))))));
                $items[] = ['sku' => $sku, 'numbers' => $numbers, 'series' => $tag('Series'), 'bolt_circle' => (string) (float) preg_replace('/[^0-9.]/', '', $tag('Bolt Circle')),
                    'makes' => array_values(array_unique($makes))];
            }
        }
        $withMake = count(array_filter($items, fn ($i) => $i['makes']));
        $this->info(count($items) . " flange yokes, $withMake with a vehicle make");
        if ($this->option('dry') || !$items) return self::SUCCESS;
        file_put_contents(storage_path('app/' . self::FILE), json_encode(['source' => self::SOURCE, 'synced' => now()->toIso8601String(), 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
