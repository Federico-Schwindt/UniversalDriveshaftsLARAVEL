<?php

namespace App\Console\Commands;

use App\Services\Admin\CatalogWriter;
use App\Support\LiveOrigin;
use App\Support\Settings;
use App\Support\WpUploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Takes the catalog off the old WooCommerce store for good (owner, 2026-11-06: "que el catálogo ya no dependa de
 * woocommerce, quiero tener todo en local"). One last pull of prices and photos (catalog:sync-live,
 * catalog:localize-images), the old store's stock counts written into Inventory, every stored photo URL pointed at
 * the local mirror, then the setting catalog.detached_at switches the live sync, the image mirror and the media
 * fallback off (LiveOrigin::retired). From then on products, prices and stock are edited only in this admin.
 *
 * --refresh-stock (launch day, before the old site is deleted): the old store's counts once more, because it keeps
 * selling until the domain moves. A product whose stock or details were changed here after the cutover is left alone.
 */
class CatalogDetach extends Command
{
    protected $signature = 'catalog:detach
        {--refresh-stock : only read the stock counts again (launch day)}
        {--dry-run : show what would change, write nothing}
        {--no-backup : skip the JSON backup of the rows it changes}';
    protected $description = 'Make the catalog local for good: last pull from WooCommerce, then stop reading the old store';

    private const REASON = 'Old store count';

    public function handle(CatalogWriter $writer): int
    {
        $refresh = (bool) $this->option('refresh-stock');
        $dry = (bool) $this->option('dry-run');
        $since = Settings::get('catalog.detached_at');
        if ($since && !$refresh) { $this->info("The catalog is already local (since $since UTC). On launch day: catalog:detach --refresh-stock"); return self::SUCCESS; }
        if ($refresh && !$since) { $this->error('Run catalog:detach first; --refresh-stock is for launch day.'); return self::FAILURE; }
        if (LiveOrigin::isSelf()) { $this->error('universaldriveshafts.com already points at this site: the old store cannot be read any more.'); return self::FAILURE; }

        // 1. the last prices, names and photos from the old store, and every picture copied here
        if (!$refresh) {
            if (!$dry) {
                if ($this->call('catalog:sync-live') !== self::SUCCESS) { $this->error('The last catalog sync failed; nothing was switched.'); return self::FAILURE; }
                $this->call('catalog:localize-images');
            }
            $missing = array_values(array_filter((new LocalizeImages)->collect(), fn ($u) => WpUploads::localPath($u) === null));
            if ($missing) {
                $this->error(count($missing) . ' picture(s) are not copied here yet (catalog:localize-images):');
                foreach (array_slice($missing, 0, 10) as $u) $this->line("  $u");
                if (!$dry) return self::FAILURE;
            }
        }

        // 2. stock counts from the old store
        $wc = $this->wooStock();
        if ($wc === null) return self::FAILURE;
        $plan = $this->stockPlan($wc, $refresh ? $since : null);
        $this->info(count($wc) . ' products on the old store: ' . count($plan['count']) . ' count(s) and ' . count($plan['flag']) . ' in/out-of-stock flag(s) to update'
            . ($refresh ? ', ' . $plan['skipped'] . ' left alone (changed here since the cutover)' : '') . ', ' . $plan['missing'] . ' not in this catalog.');
        foreach (array_slice($plan['count'], 0, 15) as $c) $this->line("  {$c['sku']}: " . ($c['before'] ?? '—') . " → {$c['after']}");
        if (count($plan['count']) > 15) $this->line('  …');

        // 3. stored photo URLs pointed at the local copy
        $photos = $refresh ? [] : $this->photoPlan();
        if (!$refresh) $this->info(count($photos) . ' product(s) with photo URLs of the old site, pointed at the local copy.');

        if ($dry) { $this->comment('Dry run: nothing written.'); return self::SUCCESS; }
        if (!$this->option('no-backup')) $this->backup($plan, $refresh);

        foreach ($plan['count'] as $c) $this->writeCount($writer, $c, $refresh);
        foreach ($plan['flag'] as $f) $this->writeFlag($f);
        foreach ($photos as $id => $upd) DB::table('catalog_live')->where('wp_product_id', $id)->update($upd);

        if ($refresh) Settings::set('catalog.stock_refreshed_at', now()->toDateTimeString());
        else Settings::set('catalog.detached_at', now()->toDateTimeString());
        CatalogWriter::invalidate();
        $this->info($refresh ? 'Stock counts refreshed from the old store.'
            : 'The catalog is local now: catalog:sync-live and catalog:localize-images are off; edit products, prices and stock in the admin.');
        return self::SUCCESS;
    }

    /** [wc product id => [manage, qty, status, sku]] for every product on the old store; null when it cannot be read. */
    private function wooStock(): ?array
    {
        $out = []; $page = 1; $pages = 1;
        try {
            do {
                $r = LiveOrigin::wc()->get('products', ['per_page' => 100, 'page' => $page, 'status' => 'any', 'orderby' => 'id', 'order' => 'asc',
                    '_fields' => 'id,sku,manage_stock,stock_quantity,stock_status,backorders']);
                $items = (array) $r->json();
                foreach ($items as $p) {
                    $out[(int) $p['id']] = ['manage' => ($p['manage_stock'] ?? false) === true, 'qty' => isset($p['stock_quantity']) ? (int) $p['stock_quantity'] : null,
                        'status' => (string) ($p['stock_status'] ?? 'instock'), 'backorders' => (string) ($p['backorders'] ?? 'no'), 'sku' => (string) ($p['sku'] ?? '')];
                }
                $pages = max(1, (int) $r->header('X-WP-TotalPages'));
                $page++;
            } while ($items && $page <= $pages);
        } catch (\Throwable $e) {
            $this->error("Could not read the old store's stock (page $page): " . $e->getMessage() . '. Nothing changed.');
            return null;
        }
        return $out;
    }

    /** What the old store's stock changes here: numeric counts (it counts the part) and plain in/out flags (it does not). */
    private function stockPlan(array $wc, ?string $since): array
    {
        $ids = array_keys($wc);
        $titles = [];
        foreach (array_chunk($ids, 1000) as $chunk) $titles += DB::connection('wp')->table('wp_posts')->where('post_type', 'product')->whereIn('ID', $chunk)->pluck('post_title', 'ID')->all();
        $meta = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::connection('wp')->table('wp_postmeta')->whereIn('post_id', $chunk)->whereIn('meta_key', ['_stock', '_stock_status', '_manage_stock', '_backorders'])
                ->selectRaw('post_id, meta_key, MAX(meta_value) v')->groupBy('post_id', 'meta_key')->get() as $m) $meta[(int) $m->post_id][$m->meta_key] = (string) $m->v;
        }
        $inv = DB::table('inventory')->get()->keyBy('wp_product_id');
        $live = DB::table('catalog_live')->pluck('in_stock', 'wp_product_id');
        // launch day: stock changed here after the cutover (a count, a sale, a product edit) wins over the old store
        $touched = [];
        if ($since) {
            $touched = DB::table('inventory_adjustments as a')->join('inventory as i', 'i.id', '=', 'a.inventory_id')->where('a.created_at', '>', $since)
                ->where('a.reason', 'not like', self::REASON . '%')->pluck('i.wp_product_id')->flip()->all();
            $touched += DB::table('catalog_live')->where('locked_at', '>', $since)->pluck('wp_product_id')->flip()->all();
        }
        $plan = ['count' => [], 'flag' => [], 'skipped' => 0, 'missing' => 0];
        foreach ($wc as $id => $w) {
            if (!isset($titles[$id])) { $plan['missing']++; continue; }
            if (isset($touched[$id])) { $plan['skipped']++; continue; }
            $m = $meta[$id] ?? []; $row = $inv[$id] ?? null;
            if ($w['manage'] && $w['qty'] !== null) {
                $qty = max(0, $w['qty']);
                // (a product without an overlay row reads its stock from the meta alone)
                if ($row && (int) $row->stock === $qty && ($m['_stock'] ?? '') === (string) $qty && ($m['_manage_stock'] ?? '') === 'yes' && ($m['_backorders'] ?? 'no') === $w['backorders']
                    && ($m['_stock_status'] ?? '') === $w['status'] && (!isset($live[$id]) || (bool) $live[$id] === ($w['status'] !== 'outofstock'))) continue;
                $plan['count'][] = ['id' => $id, 'sku' => $w['sku'] ?: "#$id", 'name' => (string) $titles[$id], 'before' => $row ? (int) $row->stock : (isset($m['_stock']) ? (int) $m['_stock'] : null), 'after' => $qty,
                    'backorders' => $w['backorders'], 'row' => $row?->id];
            } else {
                // the old store does not count this part (made to order, always available): only its in/out of stock flag
                $in = $w['status'] !== 'outofstock';
                if (($m['_stock_status'] ?? '') === $w['status'] && ($m['_manage_stock'] ?? 'no') !== 'yes' && (bool) ($live[$id] ?? !$in) === $in) continue;
                $plan['flag'][] = ['id' => $id, 'sku' => $w['sku'] ?: "#$id", 'status' => $w['status']];
            }
        }
        return $plan;
    }

    /** Inventory (the admin's stock list, sales take parts off it) + the storefront's stock meta, like a count typed in the admin. */
    private function writeCount(CatalogWriter $writer, array $c, bool $refresh): void
    {
        DB::transaction(function () use ($writer, $c, $refresh) {
            $now = now();
            $row = $c['row'] ? DB::table('inventory')->lockForUpdate()->find($c['row']) : null;
            if ($row) DB::table('inventory')->where('id', $row->id)->update(['stock' => $c['after'], 'source' => 'manual', 'tracked' => 1, 'updated_at' => $now]);
            else $row = (object) ['id' => DB::table('inventory')->insertGetId(['wp_product_id' => $c['id'], 'sku' => $c['sku'], 'name' => mb_substr(html_entity_decode($c['name']), 0, 255),
                'stock' => $c['after'], 'source' => 'manual', 'tracked' => 1, 'created_at' => $now, 'updated_at' => $now]), 'stock' => 0];
            DB::table('inventory_adjustments')->insert(['inventory_id' => $row->id, 'delta' => $c['after'] - (int) $row->stock, 'before' => (int) $row->stock, 'after' => $c['after'],
                'reason' => self::REASON . ($refresh ? ' on launch day' : ' at the cutover'), 'user_id' => null, 'created_at' => $now]);
            // the old store's backorder rule decides in stock / on backorder / out of stock at zero, as it did there
            $this->setMeta($c['id'], '_backorders', $c['backorders'] ?? 'no');
            $writer->setStock($c['id'], $c['after']);
        });
    }

    private function writeFlag(array $f): void
    {
        foreach (['_manage_stock' => 'no', '_stock_status' => $f['status']] as $k => $v) $this->setMeta($f['id'], $k, $v);
        DB::table('catalog_live')->where('wp_product_id', $f['id'])->update(['in_stock' => $f['status'] !== 'outofstock']);
    }

    /** The local copy holds duplicate meta rows: update every row, or add one when none exists. */
    private function setMeta(int $id, string $key, string $value): void
    {
        $wp = DB::connection('wp');
        if ($wp->table('wp_postmeta')->where('post_id', $id)->where('meta_key', $key)->exists()) $wp->table('wp_postmeta')->where('post_id', $id)->where('meta_key', $key)->update(['meta_value' => $value]);
        else $wp->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => $key, 'meta_value' => $value]);
    }

    /** [wp product id => changed columns]: old-site photo URLs (main, thumbnail, gallery) replaced by the mirrored copy. */
    private function photoPlan(): array
    {
        $loc = fn ($u) => is_string($u) ? (WpUploads::localPath($u) ?? $u) : $u;
        $out = [];
        foreach (DB::table('catalog_live')->get(['wp_product_id', 'image', 'thumb', 'gallery']) as $r) {
            $upd = [];
            foreach (['image', 'thumb'] as $k) if ($r->$k && $loc($r->$k) !== $r->$k) $upd[$k] = $loc($r->$k);
            $g = json_decode((string) $r->gallery, true) ?: [];
            $ng = array_map($loc, $g);
            if ($ng !== $g) $upd['gallery'] = json_encode($ng);
            if ($upd) $out[(int) $r->wp_product_id] = $upd;
        }
        return $out;
    }

    /** The rows this run changes, as they are now (storage/app/backups). */
    private function backup(array $plan, bool $refresh): void
    {
        $ids = array_merge(array_column($plan['count'], 'id'), array_column($plan['flag'], 'id'));
        $meta = [];
        foreach (array_chunk($ids, 1000) as $chunk) $meta = array_merge($meta, DB::connection('wp')->table('wp_postmeta')->whereIn('post_id', $chunk)
            ->whereIn('meta_key', ['_stock', '_stock_status', '_manage_stock', '_backorders'])->get(['post_id', 'meta_key', 'meta_value'])->all());
        $data = ['at' => now()->toDateTimeString(), 'mode' => $refresh ? 'refresh-stock' : 'detach', 'plan' => $plan,
            'catalog_live' => $refresh ? DB::table('catalog_live')->whereIn('wp_product_id', $ids)->get() : DB::table('catalog_live')->get(),
            'inventory' => DB::table('inventory')->whereIn('wp_product_id', $ids)->get(), 'wp_postmeta' => $meta];
        $file = storage_path('app/backups/catalog-detach-' . now()->format('Ymd-His') . '.json');
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line('Backup: ' . $file);
    }
}
