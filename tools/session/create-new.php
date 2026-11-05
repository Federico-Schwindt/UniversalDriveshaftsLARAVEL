<?php
$c = DB::connection('wp'); $w = app(App\Services\Admin\CatalogWriter::class);
$have = $c->table('wp_posts')->where('post_type', 'product')->pluck('ID')->flip();
$ids = DB::table('catalog_live')->pluck('wp_product_id')->filter(fn ($id) => !isset($have[$id]))->values()->all();
$prods = [];
foreach (array_chunk($ids, 50) as $ch) foreach (App\Support\LiveOrigin::wc()->get('products', ['include' => implode(',', $ch), 'per_page' => 100, 'context' => 'edit', 'status' => 'any'])->json() as $p) $prods[] = $p;
$ok = 0;
foreach ($prods as $p) {
    if ($c->table('wp_posts')->where('ID', $p['id'])->exists()) { echo "  ID {$p['id']} ya existe (otro tipo de post), salto\n"; continue; }
    $now = now()->format('Y-m-d H:i:s');
    // same ID as the live store, so the hourly sync keeps matching it
    $c->table('wp_posts')->insert(['ID' => $p['id'], 'post_author' => 1, 'post_date' => $now, 'post_date_gmt' => $now, 'post_modified' => $now, 'post_modified_gmt' => $now,
        'post_title' => $p['name'], 'post_name' => $p['slug'], 'post_status' => 'draft', 'post_type' => 'product', 'post_content' => '', 'post_excerpt' => '',
        'comment_status' => 'closed', 'ping_status' => 'closed', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '', 'guid' => 'https://universaldriveshafts.com/?p=' . $p['id'], 'menu_order' => 0]);
    $attrs = [];
    foreach ($p['attributes'] ?? [] as $a) { if (empty($a['id'])) continue; $tax = !empty($a['slug']) && str_starts_with($a['slug'], 'pa_') ? $a['slug'] : 'pa_' . Str::slug($a['name']); $attrs[$tax] = $a['options']; }
    $w->saveProduct($p['id'], [
        'name' => $p['name'], 'slug' => $p['slug'], 'status' => $p['status'] === 'publish' ? 'publish' : 'draft',
        'description' => $p['description'] ?? '', 'short' => $p['short_description'] ?? '',
        'regular_price' => (string) ($p['regular_price'] ?? ''), 'sale_price' => '',   // public regular price; sale prices are left to the owner
        'sku' => $p['sku'], 'cost' => '', 'weight' => $p['weight'] ?? '', 'length' => $p['dimensions']['length'] ?? '', 'width' => $p['dimensions']['width'] ?? '', 'height' => $p['dimensions']['height'] ?? '',
        'manage_stock' => !empty($p['manage_stock']), 'stock' => $p['stock_quantity'] ?? 0, 'backorders' => $p['backorders'] ?? 'no', 'stock_status' => $p['stock_status'] ?? 'instock',
        'sold_by_foot' => false, 'seo_title' => '', 'seo_desc' => '', 'interchange' => '',
        'categories' => array_column($p['categories'] ?? [], 'name'), 'attrs' => $attrs,
        'images' => array_column($p['images'] ?? [], 'src'),
    ]);
    DB::table('catalog_live')->where('wp_product_id', $p['id'])->update(['locked_at' => null]);   // keep following the live store
    $cats = $c->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('tr.object_id', $p['id'])->where('tt.taxonomy', 'product_cat')->count();
    printf("  %-10s %-8s $%-8s %s img=%d cats=%d/%d attrs=%d | %s\n", $p['sku'] ?: '(sin)', $p['status'], $p['regular_price'], $p['stock_status'], count($p['images'] ?? []), $cats, count($p['categories'] ?? []), count($attrs), mb_substr($p['name'], 0, 60));
    $ok++;
}
App\Services\Admin\CatalogWriter::invalidate();
echo "creados: $ok de " . count($ids) . "\n";
