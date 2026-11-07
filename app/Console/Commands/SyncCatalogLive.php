<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pull the live WooCommerce catalog (public Store API) into catalog_live. The WP database this app
 * reads is a read-only snapshot, so anything that changes on universaldriveshafts.com after the
 * snapshot (new images, prices, stock, new products) only reaches the storefront through this table.
 */
class SyncCatalogLive extends Command
{
    protected $signature = 'catalog:sync-live {--pages=0 : stop after N pages (0 = all)}';
    protected $description = 'Sync product images, prices, stock and names from the live WooCommerce Store API';

    /** wp_id => [price, regular, sale] collected this run; written to the product meta at the end */
    private array $prices = [];

    public function handle(): int
    {
        if (\App\Support\LiveOrigin::retired()) {
            $this->warn('The catalog is local since ' . \App\Support\Settings::get('catalog.detached_at') . ' (catalog:detach): nothing is read from WooCommerce. Skipped.');
            return self::SUCCESS;
        }
        if (\App\Support\LiveOrigin::isSelf()) {
            $this->warn('universaldriveshafts.com is this site now: there is no old WordPress store to read from. Skipped.');
            return self::SUCCESS;
        }
        $base = 'https://universaldriveshafts.com/wp-json/wc/store/v1/products';
        $max = (int) $this->option('pages'); $page = 1; $total = 0;
        do {
            $resp = Http::withOptions(\App\Support\LiveOrigin::httpOptions())->withHeaders(['User-Agent' => 'Mozilla/5.0 (UDS storefront sync)'])->timeout(40)->retry(2, 1500)->get($base, ['per_page' => 100, 'page' => $page, 'orderby' => 'id', 'order' => 'asc']);
            if (!$resp->successful()) { $this->error("page $page: HTTP " . $resp->status()); return self::FAILURE; }
            $items = $resp->json();
            if (!is_array($items) || !$items) break;
            $rows = [];
            foreach ($items as $p) {
                $minor = (int) ($p['prices']['currency_minor_unit'] ?? 2);
                $money = fn ($k) => isset($p['prices'][$k]) && $p['prices'][$k] !== '' ? round(((int) $p['prices'][$k]) / (10 ** $minor), 2) : null;
                // the public price on the live store is the price here (owner, 2026-10-23)
                // a $0 / missing price on the live store (draft, variable parent, glitch) is never copied: the part would sell for nothing
                if ($money('price') !== null && $money('price') > 0) $this->prices[(int) $p['id']] = [$money('price'), $money('regular_price'), $money('sale_price')];
                $rows[] = [
                    'wp_product_id' => (int) $p['id'], 'sku' => mb_substr((string) ($p['sku'] ?? ''), 0, 80) ?: null,
                    'name' => mb_substr(html_entity_decode((string) ($p['name'] ?? ''), ENT_QUOTES), 0, 255) ?: null, 'slug' => $p['slug'] ?? null,
                    'price' => ($money('price') ?? 0) > 0 ? $money('price') : null,
                    'in_stock' => !empty($p['is_in_stock']),
                    'image' => isset($p['images'][0]['src']) ? mb_substr($p['images'][0]['src'], 0, 500) : null,
                    'thumb' => isset($p['images'][0]['thumbnail']) ? mb_substr($p['images'][0]['thumbnail'], 0, 500) : null,
                    'gallery' => json_encode(array_values(array_map(fn ($i) => $i['src'], array_slice($p['images'] ?? [], 1, 8)))),
                    'categories' => json_encode(array_values(array_map(fn ($c) => $c['slug'], $p['categories'] ?? []))),
                    'synced_at' => now(),
                ];
            }
            // a photo that shows the wrong part stays off (config catalog.wrong_images)
            $wrong = config('catalog.wrong_images', []);
            foreach ($rows as &$r) if (isset($wrong[$r['wp_product_id']])) { $r['image'] = $r['thumb'] = null; $r['gallery'] = '[]'; }
            unset($r);
            // our own photos for a product (pti:sync --photos-for): the live store's picture never replaces them
            $over = (array) \App\Support\Settings::get('catalog.image_overrides', []);
            foreach ($rows as &$r) if (isset($over[(string) $r['wp_product_id']])) { $o = $over[(string) $r['wp_product_id']]; $r['image'] = $r['thumb'] = $o['image']; $r['gallery'] = json_encode($o['gallery'] ?? []); }
            unset($r);
            // products edited in the admin are locked: the live store must not overwrite them
            $locked = DB::table('catalog_live')->whereNotNull('locked_at')->pluck('wp_product_id')->flip();
            $rows = array_values(array_filter($rows, fn ($r) => !isset($locked[$r['wp_product_id']])));
            // no usable price this run: keep the last good one instead of blanking it
            $prev = DB::table('catalog_live')->whereIn('wp_product_id', array_column(array_filter($rows, fn ($r) => $r['price'] === null), 'wp_product_id'))->pluck('price', 'wp_product_id');
            foreach ($rows as &$r) if ($r['price'] === null && isset($prev[$r['wp_product_id']])) $r['price'] = $prev[$r['wp_product_id']];
            unset($r);
            if ($rows) DB::table('catalog_live')->upsert($rows, ['wp_product_id'], ['sku', 'name', 'slug', 'price', 'in_stock', 'image', 'thumb', 'gallery', 'categories', 'synced_at']);
            $total += count($rows); $this->line("page $page: " . count($rows));
            $page++;
        } while (count($items) === 100 && ($max === 0 || $page <= $max));
        $changed = $this->writePrices();
        \Illuminate\Support\Facades\Cache::forever('catalog_ver', time());   // invalidates the catalog read caches
        \Illuminate\Support\Facades\Cache::forget('catalog_live_map');
        $this->info("synced $total products into catalog_live; $changed price(s) updated");
        return self::SUCCESS;
    }

    /** Copy the live public price into the product meta the storefront and cart read; admin-locked products are left alone. */
    private function writePrices(): int
    {
        $wp = DB::connection('wp');
        $locked = DB::table('catalog_live')->whereNotNull('locked_at')->pluck('wp_product_id')->flip();
        $have = $wp->table('wp_postmeta')->whereIn('meta_key', ['_price', '_regular_price', '_sale_price'])->whereIn('post_id', array_keys($this->prices))
            ->get(['post_id', 'meta_key', 'meta_value'])->groupBy('post_id');
        $fmt = fn ($v) => $v === null ? '' : number_format((float) $v, 2, '.', '');
        $n = 0;
        foreach ($this->prices as $id => [$price, $regular, $sale]) {
            if (isset($locked[$id]) || $price <= 0) continue;
            $regular = $regular ?? $price;
            $was = (float) collect($have[$id] ?? [])->firstWhere('meta_key', '_price')?->meta_value;
            if ($was > 0 && abs($price - $was) / $was > 0.5) {   // applied (the live store is the price authority) but flagged for staff
                $this->warn("#$id price moved more than 50%: \$" . number_format($was, 2) . ' → $' . number_format($price, 2));
                \Illuminate\Support\Facades\Log::warning('catalog:sync-live big price change', ['wp_product_id' => $id, 'from' => $was, 'to' => $price]);
            }
            $want = ['_price' => $fmt($price), '_regular_price' => $fmt($regular), '_sale_price' => $sale !== null && $sale < $regular ? $fmt($sale) : ''];
            $cur = [];
            foreach ($have[$id] ?? [] as $m) $cur[$m->meta_key] = $m->meta_value;
            $diff = false;
            foreach ($want as $k => $v) {
                $c = $cur[$k] ?? null;
                if ($c !== null && $c !== '' && $v !== '' && abs((float) $c - (float) $v) < 0.005) continue;
                if (($c ?? '') === $v) continue;
                $diff = true;
                // the local copy holds duplicate meta rows: update every row, or add one when none exists
                if ($c === null) $wp->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
                else $wp->table('wp_postmeta')->where('post_id', $id)->where('meta_key', $k)->update(['meta_value' => $v]);
            }
            if ($diff) $n++;
        }
        return $n;
    }
}
