<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populates the inventory table from the uds_shop catalogue (products Modi physically stocks:
 * the WooCommerce components with managed stock). Re-runnable: refreshes sku/name/category/stock
 * but NEVER overwrites Modi's own settings (reorder_at, target_qty, source, tracked).
 */
class SeedInventory extends Command
{
    protected $signature = 'inventory:seed-from-shop {--all : include products without managed stock too}';
    protected $description = 'Import stocked products from uds_shop into the inventory table';

    public function handle(): int
    {
        $wp = DB::connection('wp');
        $meta = fn (string $k) => $wp->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) v'))
            ->where('meta_key', $k)->groupBy('post_id');

        $q = $wp->table('wp_posts as p')
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')
            ->leftJoinSub($meta('_sku'), 'sku', 'sku.post_id', 'p.ID')
            ->leftJoinSub($meta('_stock'), 'st', 'st.post_id', 'p.ID')
            ->leftJoinSub($meta('_manage_stock'), 'ms', 'ms.post_id', 'p.ID')
            ->select('p.ID', 'p.post_title', 'sku.v as sku', 'st.v as stock', 'ms.v as manage');
        if (!$this->option('all')) $q->where('ms.v', 'yes');

        // category per product (first product_cat term)
        $cats = $wp->table('wp_term_relationships as tr')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', 'tt.term_id')
            ->where('tt.taxonomy', 'product_cat')
            ->select('tr.object_id', DB::raw('MIN(t.name) name'))->groupBy('tr.object_id')
            ->pluck('name', 'object_id');

        $now = now(); $n = 0;
        foreach ($q->get()->chunk(400) as $chunk) {
            foreach ($chunk as $p) {
                $stock = is_numeric($p->stock) ? (int) $p->stock : 0;
                $existing = DB::table('inventory')->where('wp_product_id', $p->ID)->first();
                $base = ['sku' => $p->sku, 'name' => $p->post_title,
                         'category' => $cats[$p->ID] ?? null, 'updated_at' => $now];
                if ($existing) {
                    // keep Modi's settings; only refresh catalogue fields, and stock only if this product is auto-sourced
                    if ($existing->source !== 'manual') $base['stock'] = $stock;
                    DB::table('inventory')->where('id', $existing->id)->update($base);
                } else {
                    DB::table('inventory')->insert($base + [
                        'wp_product_id' => $p->ID, 'stock' => $stock, 'source' => 'manual',
                        'tracked' => 1, 'created_at' => $now,
                    ]);
                }
                $n++;
            }
        }
        $this->info("inventory rows synced: $n");
        $this->info('total tracked: ' . DB::table('inventory')->where('tracked', 1)->count());
        return 0;
    }
}
