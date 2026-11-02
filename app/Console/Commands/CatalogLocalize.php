<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies the WooCommerce catalog tables from the read-only snapshot connection into the
 * app's own database so the admin can edit products, categories and attributes in place.
 * The snapshot stays untouched; after this runs the `wp` connection points at the local copy.
 */
class CatalogLocalize extends Command
{
    protected $signature = 'catalog:localize {--from=wp_src : source connection} {--to=mysql : destination connection} {--table=* : only these tables}';
    protected $description = 'Copy the WooCommerce catalog tables into the local (writable) database';

    public const TABLES = [
        'wp_posts', 'wp_postmeta', 'wp_terms', 'wp_term_taxonomy', 'wp_term_relationships', 'wp_termmeta',
        'wp_wc_product_meta_lookup', 'wp_woocommerce_attribute_taxonomies',
        'wp_wc_order_product_lookup', 'wp_woocommerce_order_items', 'wp_woocommerce_order_itemmeta',
        'wp_wc_order_stats', 'wp_wc_customer_lookup', 'wp_woocommerce_shipping_zones', 'wp_users', 'wp_usermeta',
    ];

    public function handle(): int
    {
        $src = DB::connection($this->option('from'));
        $dst = DB::connection($this->option('to'));
        $tables = $this->option('table') ?: self::TABLES;
        // WordPress DDL carries '0000-00-00' datetime defaults that the app's strict session rejects
        $dst->statement("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
        foreach ($tables as $t) {
            $create = $src->select("SHOW CREATE TABLE `$t`")[0];
            $ddl = ((array) $create)['Create Table'];
            $dst->statement("DROP TABLE IF EXISTS `$t`");
            $dst->statement($ddl);
            $total = (int) $src->table($t)->count();
            $cols = array_map(fn ($c) => ((array) $c)['Field'], $src->select("SHOW COLUMNS FROM `$t`"));
            $pk = collect($src->select("SHOW KEYS FROM `$t` WHERE Key_name='PRIMARY'"))->pluck('Column_name')->all();
            $order = $pk ?: [$cols[0]];
            $done = 0;
            $dst->statement('SET FOREIGN_KEY_CHECKS=0');
            $q = $src->table($t);
            foreach ($order as $o) $q->orderBy($o);
            $q->chunk(2000, function ($rows) use ($dst, $t, &$done) {
                $batch = array_map(fn ($r) => (array) $r, $rows->all());
                foreach (array_chunk($batch, 500) as $part) $dst->table($t)->insert($part);
                $done += count($batch);
            });
            $this->line(sprintf('%-40s %7d / %7d', $t, $done, $total));
        }
        \Illuminate\Support\Facades\Cache::increment('catalog_ver');
        return self::SUCCESS;
    }
}
