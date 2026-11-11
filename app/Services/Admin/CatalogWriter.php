<?php

namespace App\Services\Admin;

use App\Support\AdminLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes products, categories and attribute terms into the (local) WooCommerce-schema catalog tables.
 * Every read path in CatalogService / ProductService keeps working because the schema is unchanged.
 */
class CatalogWriter
{
    private function db() { return DB::connection('wp'); }

    public static function invalidate(): void
    {
        Cache::forever('catalog_ver', time());
        Cache::forget('catalog_live_map'); Cache::forget('catalog_ds_subcat'); Cache::forget('adm_nav_counts');
    }

    /* ---------- products ---------- */

    public function product(int $id): ?array
    {
        $p = $this->db()->table('wp_posts')->where('ID', $id)->where('post_type', 'product')->first();
        if (!$p) return null;
        $meta = $this->db()->table('wp_postmeta')->where('post_id', $id)->orderBy('meta_id')->pluck('meta_value', 'meta_key')->all();
        $terms = $this->db()->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tr.object_id', $id)->get(['tt.taxonomy', 't.slug', 't.name', 't.term_id']);
        $attrs = []; $cats = [];
        foreach ($terms as $t) {
            if ($t->taxonomy === 'product_cat') $cats[] = $t->slug;
            elseif (str_starts_with($t->taxonomy, 'pa_')) $attrs[$t->taxonomy][] = html_entity_decode($t->name);
        }
        $order = @unserialize($meta['_product_attributes'] ?? '', ['allowed_classes' => false]) ?: [];
        $live = DB::table('catalog_live')->where('wp_product_id', $id)->first();
        $images = [];
        if ($live && $live->image) { $images[] = $live->image; foreach (json_decode($live->gallery ?: '[]', true) ?: [] as $g) $images[] = $g; }
        else {
            if (!empty($meta['_thumbnail_id']) && ($u = $this->attachmentUrl((int) $meta['_thumbnail_id']))) $images[] = $u;
            foreach (array_filter(array_map('trim', explode(',', (string) ($meta['_product_image_gallery'] ?? '')))) as $gid) if ($u = $this->attachmentUrl((int) $gid)) $images[] = $u;
        }
        return [
            'id' => $id, 'name' => html_entity_decode($p->post_title), 'slug' => $p->post_name, 'status' => $p->post_status,
            'description' => $p->post_content, 'short' => $p->post_excerpt, 'created' => $p->post_date, 'modified' => $p->post_modified,
            'sku' => $meta['_sku'] ?? '', 'price' => $meta['_price'] ?? '', 'regular_price' => $meta['_regular_price'] ?? ($meta['_price'] ?? ''), 'sale_price' => $meta['_sale_price'] ?? '',
            'cost' => $meta['_uds_cost'] ?? '', 'weight' => $meta['_weight'] ?? '', 'length' => $meta['_length'] ?? '', 'width' => $meta['_width'] ?? '', 'height' => $meta['_height'] ?? '',
            'manage_stock' => ($meta['_manage_stock'] ?? 'no') === 'yes', 'stock' => $meta['_stock'] ?? '', 'stock_status' => $meta['_stock_status'] ?? 'instock',
            'backorders' => $meta['_backorders'] ?? 'no', 'sold_by_foot' => ($meta['_uds_by_foot'] ?? '') === 'yes',
            'seo_title' => $meta['_uds_seo_title'] ?? '', 'seo_desc' => $meta['_uds_seo_desc'] ?? '', 'interchange' => $meta['_uds_interchange'] ?? '',
            'categories' => $cats, 'attrs' => $attrs, 'attr_order' => array_keys($order), 'images' => array_values(array_unique($images)),
            'locked' => (bool) ($live->locked_at ?? false), 'live' => $live,
        ];
    }

    private function attachmentUrl(int $aid): string
    {
        if (!$aid || $aid === 11868) return '';
        $f = $this->db()->table('wp_postmeta')->where('post_id', $aid)->where('meta_key', '_wp_attached_file')->value('meta_value');
        if (!$f || str_contains($f, 'placeholder')) return '';
        return rtrim(config('catalog.uploads'), '/') . '/' . ltrim($f, '/');
    }

    private function setMeta(int $id, string $key, $value): void
    {
        $this->db()->table('wp_postmeta')->where('post_id', $id)->where('meta_key', $key)->delete();
        if ($value === null) return;
        $this->db()->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => $key, 'meta_value' => (string) $value]);
    }

    /**
     * Record the box a part actually shipped in, measured when a label was bought,
     * so every later rate quote stops guessing at this product's weight and size.
     * Only non-empty measurements overwrite what is already stored.
     */
    public function setShipBox(int $id, ?float $weight, ?float $length, ?float $width, ?float $height): array
    {
        $written = [];
        foreach (['_weight' => $weight, '_length' => $length, '_width' => $width, '_height' => $height] as $k => $v) {
            if ($v === null || $v <= 0) continue;
            $this->setMeta($id, $k, round($v, 2));
            $written[] = ltrim($k, '_');
        }
        if ($written) self::invalidate();
        return $written;
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null, string $type = 'product'): string
    {
        $base = Str::slug($name) ?: 'product'; $slug = $base; $n = 2;
        while ($this->db()->table('wp_posts')->where('post_name', $slug)->where('post_type', $type)->when($ignoreId, fn ($q) => $q->where('ID', '!=', $ignoreId))->exists()) $slug = $base . '-' . $n++;
        return $slug;
    }

    /** Create or update a product from the admin form. Returns the product id. */
    public function saveProduct(?int $id, array $d): int
    {
        $db = $this->db();
        $now = now()->format('Y-m-d H:i:s');
        $status = in_array($d['status'] ?? 'publish', ['publish', 'draft', 'private']) ? $d['status'] : 'publish';
        $slug = $this->uniqueSlug($d['slug'] ?: $d['name'], $id);
        $post = ['post_title' => $d['name'], 'post_name' => $slug, 'post_status' => $status, 'post_content' => (string) ($d['description'] ?? ''), 'post_excerpt' => (string) ($d['short'] ?? ''), 'post_modified' => $now, 'post_modified_gmt' => $now];
        if ($id) {
            $db->table('wp_posts')->where('ID', $id)->update($post);
        } else {
            $id = $db->table('wp_posts')->insertGetId($post + ['post_author' => 1, 'post_date' => $now, 'post_date_gmt' => $now, 'post_type' => 'product', 'comment_status' => 'closed', 'ping_status' => 'closed',
                'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '', 'guid' => config('app.url') . '/?post_type=product&p=0', 'menu_order' => 0]);
            $db->table('wp_posts')->where('ID', $id)->update(['guid' => config('app.url') . '/product/' . $slug]);
        }
        $regular = $d['regular_price'] !== '' ? round((float) $d['regular_price'], 2) : null;
        $sale = ($d['sale_price'] ?? '') !== '' ? round((float) $d['sale_price'], 2) : null;
        $price = $sale !== null && ($regular === null || $sale < $regular) ? $sale : $regular;
        $manage = !empty($d['manage_stock']);
        $stock = $manage ? (int) ($d['stock'] ?? 0) : null;
        $stockStatus = $manage ? ($stock > 0 ? 'instock' : (($d['backorders'] ?? 'no') !== 'no' ? 'onbackorder' : 'outofstock')) : ($d['stock_status'] ?? 'instock');
        foreach ([
            '_sku' => trim((string) $d['sku']), '_price' => $price, '_regular_price' => $regular, '_sale_price' => $sale, '_uds_cost' => ($d['cost'] ?? '') !== '' ? round((float) $d['cost'], 2) : null,
            '_weight' => ($d['weight'] ?? '') !== '' ? $d['weight'] : null, '_length' => ($d['length'] ?? '') !== '' ? $d['length'] : null, '_width' => ($d['width'] ?? '') !== '' ? $d['width'] : null, '_height' => ($d['height'] ?? '') !== '' ? $d['height'] : null,
            '_manage_stock' => $manage ? 'yes' : 'no', '_stock' => $stock, '_stock_status' => $stockStatus, '_backorders' => $d['backorders'] ?? 'no',
            '_uds_by_foot' => !empty($d['sold_by_foot']) ? 'yes' : null, '_uds_seo_title' => $d['seo_title'] ?: null, '_uds_seo_desc' => $d['seo_desc'] ?: null, '_uds_interchange' => $d['interchange'] ?: null,
            '_virtual' => 'no', '_downloadable' => 'no', '_tax_status' => 'taxable', '_visibility' => 'visible', '_product_version' => '11.0.0',
        ] as $k => $v) $this->setMeta($id, $k, $v);

        // categories
        $this->syncTerms($id, 'product_cat', array_values(array_filter((array) ($d['categories'] ?? []))), false);
        // attributes: {pa_series: "1310", pa_style: "Full round", …} — new values become terms
        $attrs = []; $order = [];
        $pos = 0;
        foreach ((array) ($d['attrs'] ?? []) as $tax => $val) {
            if (!preg_match('/^pa_[a-z0-9\-]+$/', $tax)) continue;
            $vals = array_values(array_filter(array_map('trim', is_array($val) ? $val : explode('|', (string) $val)), fn ($v) => $v !== ''));
            $attrs[$tax] = $vals;
            if ($vals) $order[$tax] = ['name' => $tax, 'value' => '', 'position' => $pos++, 'is_visible' => 1, 'is_variation' => 0, 'is_taxonomy' => 1];
        }
        foreach ($attrs as $tax => $vals) $this->syncTerms($id, $tax, $vals, true);
        // drop attribute taxonomies that were removed entirely
        $present = array_keys($attrs);
        $ttids = $db->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('tr.object_id', $id)->where('tt.taxonomy', 'like', 'pa_%')->whereNotIn('tt.taxonomy', $present ?: [''])->pluck('tr.term_taxonomy_id');
        if ($ttids->count()) { $db->table('wp_term_relationships')->where('object_id', $id)->whereIn('term_taxonomy_id', $ttids)->delete(); foreach ($ttids as $t) $this->recount($t); }
        $this->setMeta($id, '_product_attributes', serialize($order));

        // images: stored as absolute/local URLs on the live overlay (what every read path checks first), locked against the sync
        $images = array_values(array_filter(array_map('trim', (array) ($d['images'] ?? []))));
        $cat = app(\App\Services\CatalogService::class);
        $catSlugs = (array) ($d['categories'] ?? []);
        DB::table('catalog_live')->updateOrInsert(['wp_product_id' => $id], [
            'sku' => trim((string) $d['sku']) ?: null, 'name' => mb_substr($d['name'], 0, 255), 'slug' => $slug, 'price' => $price, 'in_stock' => $stockStatus !== 'outofstock',
            'image' => $images[0] ?? null, 'thumb' => $images[0] ?? null, 'gallery' => json_encode(array_slice($images, 1)), 'categories' => json_encode($catSlugs),
            'synced_at' => now(), 'locked_at' => now(),
        ]);
        // inventory row keeps the admin stock list in sync
        $inv = DB::table('inventory')->where('wp_product_id', $id)->first();
        $invData = ['sku' => trim((string) $d['sku']), 'name' => mb_substr($d['name'], 0, 255), 'category' => $catSlugs[0] ?? null, 'updated_at' => now()];
        if ($inv) { if ($manage && $inv->source === 'manual') $invData['stock'] = $stock; DB::table('inventory')->where('id', $inv->id)->update($invData); }
        elseif ($manage) DB::table('inventory')->insert($invData + ['wp_product_id' => $id, 'stock' => $stock, 'source' => 'manual', 'tracked' => 1, 'created_at' => now()]);
        self::invalidate();
        return $id;
    }

    /** Attach a product to exactly these terms of a taxonomy (creating missing ones when $create). */
    public function syncTerms(int $objectId, string $tax, array $names, bool $create): void
    {
        $db = $this->db();
        $want = [];
        foreach ($names as $n) {
            $n = trim((string) $n); if ($n === '') continue;
            $slug = Str::slug($n) ?: md5($n);
            $tt = $db->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', $tax)
                ->where(fn ($q) => $q->where('t.slug', $slug)->orWhere('t.name', $n))->value('tt.term_taxonomy_id');
            if (!$tt && $create) $tt = $this->createTerm($tax, $n, $slug);
            if ($tt) $want[] = (int) $tt;
        }
        $have = $db->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('tr.object_id', $objectId)->where('tt.taxonomy', $tax)->pluck('tr.term_taxonomy_id')->map(fn ($v) => (int) $v)->all();
        $add = array_diff($want, $have); $del = array_diff($have, $want);
        if ($del) $db->table('wp_term_relationships')->where('object_id', $objectId)->whereIn('term_taxonomy_id', $del)->delete();
        foreach ($add as $tt) $db->table('wp_term_relationships')->insert(['object_id' => $objectId, 'term_taxonomy_id' => $tt, 'term_order' => 0]);
        foreach (array_merge($add, $del) as $tt) $this->recount($tt);
    }

    public function createTerm(string $tax, string $name, ?string $slug = null, int $parent = 0, string $description = ''): int
    {
        $db = $this->db();
        $slug = $slug ?: (Str::slug($name) ?: md5($name));
        $base = $slug; $n = 2;
        while ($db->table('wp_terms as t')->join('wp_term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')->where('t.slug', $slug)->where('tt.taxonomy', $tax)->exists()) $slug = $base . '-' . $n++;
        $termId = $db->table('wp_terms')->insertGetId(['name' => $name, 'slug' => $slug, 'term_group' => 0]);
        $ttid = $db->table('wp_term_taxonomy')->insertGetId(['term_id' => $termId, 'taxonomy' => $tax, 'description' => $description, 'parent' => $parent, 'count' => 0]);
        if (str_starts_with($tax, 'pa_') && !$db->table('wp_woocommerce_attribute_taxonomies')->where('attribute_name', substr($tax, 3))->exists()) {
            $db->table('wp_woocommerce_attribute_taxonomies')->insert(['attribute_name' => substr($tax, 3), 'attribute_label' => ucwords(str_replace('-', ' ', substr($tax, 3))), 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => 0]);
        }
        return (int) $ttid;
    }

    public function recount(int $ttid): void
    {
        $n = $this->db()->table('wp_term_relationships as tr')->join('wp_posts as p', 'p.ID', '=', 'tr.object_id')->where('tr.term_taxonomy_id', $ttid)->where('p.post_type', 'product')->where('p.post_status', 'publish')->count();
        $this->db()->table('wp_term_taxonomy')->where('term_taxonomy_id', $ttid)->update(['count' => $n]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db()->table('wp_posts')->where('ID', $id)->where('post_type', 'product')->update(['post_status' => $status, 'post_modified' => now()]);
        foreach ($this->db()->table('wp_term_relationships')->where('object_id', $id)->pluck('term_taxonomy_id') as $tt) $this->recount((int) $tt);
        self::invalidate();
    }

    public function deleteProduct(int $id): void
    {
        $db = $this->db();
        $tts = $db->table('wp_term_relationships')->where('object_id', $id)->pluck('term_taxonomy_id');
        $db->table('wp_term_relationships')->where('object_id', $id)->delete();
        $db->table('wp_postmeta')->where('post_id', $id)->delete();
        $db->table('wp_posts')->where('ID', $id)->delete();
        DB::table('catalog_live')->where('wp_product_id', $id)->delete();
        DB::table('inventory')->where('wp_product_id', $id)->delete();
        foreach ($tts as $tt) $this->recount((int) $tt);
        self::invalidate();
    }

    public function duplicate(int $id): ?int
    {
        $p = $this->product($id); if (!$p) return null;
        $d = $p; $d['name'] = $p['name'] . ' (copy)'; $d['slug'] = ''; $d['sku'] = $p['sku'] ? $p['sku'] . '-COPY' : ''; $d['status'] = 'draft';
        $d['attrs'] = array_map(fn ($v) => implode('|', $v), $p['attrs']);
        $d['manage_stock'] = $p['manage_stock'];
        return $this->saveProduct(null, $d);
    }

    /**
     * Stock-only write (inventory edits, stock quick-edits). Unlike saveProduct() it never sets
     * catalog_live.locked_at, so a stock count does not freeze the product's price/name/images
     * against the hourly live sync.
     */
    public function setStock(int $id, int $stock): bool
    {
        if (!$this->db()->table('wp_posts')->where('ID', $id)->where('post_type', 'product')->exists()) return false;
        $stock = max(0, $stock);
        $back = (string) ($this->db()->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_backorders')->value('meta_value') ?: 'no');
        $status = $stock > 0 ? 'instock' : ($back !== 'no' ? 'onbackorder' : 'outofstock');
        $this->setMeta($id, '_manage_stock', 'yes');
        $this->setMeta($id, '_stock', $stock);
        $this->setMeta($id, '_stock_status', $status);
        DB::table('catalog_live')->where('wp_product_id', $id)->update(['in_stock' => $status !== 'outofstock']);
        self::invalidate();
        return true;
    }

    /** Quick inline edits from the product list / bulk bar. */
    public function quick(int $id, array $fields): void
    {
        $p = $this->product($id); if (!$p) return;
        $d = $p; $d['attrs'] = array_map(fn ($v) => implode('|', $v), $p['attrs']);
        foreach ($fields as $k => $v) $d[$k] = $v;
        $this->saveProduct($id, $d);
    }

    /* ---------- attribute vocabulary ---------- */

    public function attributeTaxonomies(): array
    {
        return Cache::remember('adm_attr_tax', 600, function () {
            $labels = $this->db()->table('wp_woocommerce_attribute_taxonomies')->pluck('attribute_label', 'attribute_name')->all();
            $counts = $this->db()->table('wp_term_taxonomy')->where('taxonomy', 'like', 'pa_%')->selectRaw('taxonomy, SUM(count) n, COUNT(*) terms')->groupBy('taxonomy')->get()->keyBy('taxonomy');
            $out = [];
            foreach ($counts as $tax => $c) $out[$tax] = ['label' => $labels[substr($tax, 3)] ?? ucwords(str_replace('-', ' ', substr($tax, 3))), 'products' => (int) $c->n, 'terms' => (int) $c->terms];
            uasort($out, fn ($a, $b) => $b['products'] <=> $a['products']);
            return $out;
        });
    }

    public function termValues(string $tax, string $q = '', int $limit = 30): array
    {
        return $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', $tax)
            ->when($q !== '', fn ($w) => $w->where('t.name', 'like', "%$q%"))->orderByDesc('tt.count')->orderBy('t.name')->limit($limit)->pluck('t.name')->map(fn ($n) => html_entity_decode($n))->all();
    }

    /** Attributes most used by the products of a category (so the form shows the right fields first). */
    public function categoryAttributes(string $catSlug, int $limit = 14): array
    {
        $term = app(\App\Services\CatalogService::class)->term($catSlug); if (!$term) return [];
        return $this->db()->table('wp_term_relationships as ptr')->join('wp_term_relationships as tr', 'tr.object_id', '=', 'ptr.object_id')
            ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')->where('ptr.term_taxonomy_id', $term->term_taxonomy_id)
            ->where('tt.taxonomy', 'like', 'pa_%')->groupBy('tt.taxonomy')->orderByRaw('COUNT(*) DESC')->limit($limit)->pluck('tt.taxonomy')->all();
    }

    /* ---------- categories ---------- */

    public function categories(): array
    {
        $rows = $this->db()->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'product_cat')
            ->orderBy('t.name')->get(['t.term_id', 't.name', 't.slug', 'tt.count', 'tt.parent', 'tt.description', 'tt.term_taxonomy_id']);
        $meta = $this->db()->table('wp_termmeta')->whereIn('term_id', $rows->pluck('term_id'))->whereIn('meta_key', ['uds_image', 'order', 'uds_seo_title', 'uds_seo_desc', 'uds_blurb'])->get()->groupBy('term_id');
        $groupOf = app(\App\Services\CatalogService::class)->allCategorySlugs();
        $hidden = (array) \App\Support\Settings::get('catalog.hidden_categories', []);
        $out = [];
        foreach ($rows as $r) {
            $m = ($meta[$r->term_id] ?? collect())->pluck('meta_value', 'meta_key');
            $out[] = ['term_id' => (int) $r->term_id, 'ttid' => (int) $r->term_taxonomy_id, 'name' => html_entity_decode($r->name), 'slug' => $r->slug, 'count' => (int) $r->count, 'parent' => (int) $r->parent,
                'description' => $r->description, 'image' => $m['uds_image'] ?? '', 'order' => (int) ($m['order'] ?? 0), 'seo_title' => $m['uds_seo_title'] ?? '', 'seo_desc' => $m['uds_seo_desc'] ?? '', 'blurb' => $m['uds_blurb'] ?? '',
                'group' => $groupOf[$r->slug] ?? null, 'hidden' => in_array($r->slug, $hidden, true)];
        }
        return $out;
    }

    public function category(string $slug): ?array
    {
        foreach ($this->categories() as $c) if ($c['slug'] === $slug) return $c;
        return null;
    }

    private function setTermMeta(int $termId, string $key, $value): void
    {
        $this->db()->table('wp_termmeta')->where('term_id', $termId)->where('meta_key', $key)->delete();
        if ($value !== null && $value !== '') $this->db()->table('wp_termmeta')->insert(['term_id' => $termId, 'meta_key' => $key, 'meta_value' => (string) $value]);
    }

    public function saveCategory(?string $slug, array $d): string
    {
        $db = $this->db();
        if ($slug) {
            $c = $this->category($slug); if (!$c) throw new \RuntimeException('Category not found');
            $termId = $c['term_id'];
            $db->table('wp_terms')->where('term_id', $termId)->update(['name' => $d['name']]);
            $db->table('wp_term_taxonomy')->where('term_taxonomy_id', $c['ttid'])->update(['description' => (string) ($d['description'] ?? ''), 'parent' => (int) ($d['parent'] ?? $c['parent'])]);
        } else {
            $parent = (int) ($d['parent'] ?? 0);
            $ttid = $this->createTerm('product_cat', $d['name'], ($d['slug'] ?? null) ?: null, $parent, (string) ($d['description'] ?? ''));
            $row = $db->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.term_taxonomy_id', $ttid)->first(['t.term_id', 't.slug']);
            $termId = (int) $row->term_id; $slug = $row->slug;
        }
        foreach (['uds_image' => $d['image'] ?? null, 'uds_seo_title' => $d['seo_title'] ?? null, 'uds_seo_desc' => $d['seo_desc'] ?? null, 'uds_blurb' => $d['blurb'] ?? null, 'order' => $d['order'] ?? null] as $k => $v) $this->setTermMeta($termId, $k, $v);
        // group membership (storefront navigation) + hidden flag live in settings
        $groups = (array) \App\Support\Settings::get('catalog.groups', []);
        foreach ($groups as $g => $list) $groups[$g] = array_values(array_diff((array) $list, [$slug]));
        if (!empty($d['group'])) { $groups[$d['group']][] = $slug; $groups[$d['group']] = array_values(array_unique($groups[$d['group']])); }
        $hidden = (array) \App\Support\Settings::get('catalog.hidden_categories', []);
        $hidden = array_values(array_diff($hidden, [$slug])); if (!empty($d['hidden'])) $hidden[] = $slug;
        \App\Support\Settings::setMany(['catalog.groups' => $groups, 'catalog.hidden_categories' => $hidden]);
        self::invalidate();
        return $slug;
    }

    public function deleteCategory(string $slug): bool
    {
        $c = $this->category($slug); if (!$c) return false;
        if ($c['count'] > 0) return false;
        $this->db()->table('wp_term_relationships')->where('term_taxonomy_id', $c['ttid'])->delete();
        $this->db()->table('wp_term_taxonomy')->where('term_taxonomy_id', $c['ttid'])->delete();
        $this->db()->table('wp_termmeta')->where('term_id', $c['term_id'])->delete();
        $this->db()->table('wp_terms')->where('term_id', $c['term_id'])->delete();
        $groups = (array) \App\Support\Settings::get('catalog.groups', []);
        foreach ($groups as $g => $list) $groups[$g] = array_values(array_diff((array) $list, [$slug]));
        \App\Support\Settings::set('catalog.groups', $groups);
        self::invalidate();
        return true;
    }
}
