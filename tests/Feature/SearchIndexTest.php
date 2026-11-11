<?php

namespace Tests\Feature;

use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner, 2026-11-11: "2s no es aceptable". A search was 17 SQL LIKE queries over a postmeta derived table; it now scans an
 * in-memory index. The index must find and order exactly what the SQL did.
 */
class SearchIndexTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql', 'wp'];

    private function product(string $title, string $sku, float $price, int $sales, int $ttid): int
    {
        $db = DB::connection('wp');
        $id = (int) $db->table('wp_posts')->insertGetId(['post_title' => $title, 'post_name' => 'zz-' . strtolower($sku), 'post_type' => 'product', 'post_status' => 'publish',
            'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '', 'post_date' => now(), 'post_date_gmt' => now(),
            'post_modified' => now(), 'post_modified_gmt' => now()]);
        foreach (['_sku' => $sku, '_price' => $price, '_regular_price' => $price, 'total_sales' => $sales] as $k => $v) $db->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        $db->table('wp_term_relationships')->insert(['object_id' => $id, 'term_taxonomy_id' => $ttid, 'term_order' => 0]);
        return $id;
    }

    public function test_the_index_finds_and_orders_what_the_sql_search_did(): void
    {
        $c = app(CatalogService::class);
        $term = $c->term('end-yokes');
        $ttid = (int) $term->term_taxonomy_id;
        $title = $this->product("ZZQ Yoke\u{a0}9310\u{a0}series – test", 'ZZQ-1', 10, 1, $ttid);
        $contains = $this->product('ZZQ test part', 'ZZQ-A9310B', 20, 500, $ttid);
        $starts = $this->product('ZZQ another', '9310-ZZQ', 30, 2, $ttid);
        $exact = $this->product('ZZQ exact', '9310', 40, 0, $ttid);
        $dash = $this->product('ZZQ dash', '5-9310XZ', 50, 9, $ttid);
        Cache::flush();

        // the part number itself, then a part number starting with it (or a dash-separated part of it), then containing it, then titles
        $this->assertSame([$exact, $dash, $starts, $contains, $title], array_values(array_intersect($c->searchIds('9310', $ttid), [$exact, $dash, $starts, $contains, $title])));
        // a title written with non-breaking spaces or an accent is found the way the database's LIKE found it
        $this->assertContains($title, $c->searchIds('9310 series', $ttid));
        $this->assertContains($title, $c->searchIds('YÓKE 9310', $ttid));
        // other categories do not see it, the category counts do
        $this->assertNotContains($exact, $c->searchIds('9310', (int) $c->term('flange-yokes')->term_taxonomy_id));
        $this->assertGreaterThanOrEqual(5, collect($c->searchCategories('zzq'))->firstWhere('slug', 'end-yokes')['n']);
        // the category listing searches through it
        $this->assertSame(5, $c->products('end-yokes', [], 1, 3, 'relevance', 'zzq', [])['total']);
        $this->assertSame('9310', $c->products('end-yokes', [], 1, 3, 'relevance', '9310', [])['items'][0]['sku']);
        // a query that folds to nothing is matched as typed, never as "everything"
        $this->assertSame([], $c->searchIds('日本', $ttid));
    }

    public function test_index_and_sql_agree_on_real_catalog_queries(): void
    {
        $c = app(CatalogService::class);
        $db = DB::connection('wp');
        foreach (['1310', 'yoke', 'spicer', '5-153'] as $q) {
            $rows = $db->table('wp_posts as p')
                ->leftJoinSub($db->table('wp_postmeta')->select('post_id', DB::raw('MAX(meta_value) as sv'))->where('meta_key', '_sku')->groupBy('post_id'), 'sk', 'sk.post_id', '=', 'p.ID')
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->where(fn ($w) => $w->where('p.post_title', 'like', "%$q%")->orWhere('sk.sv', 'like', "%$q%"))
                ->pluck('p.ID')->map(fn ($v) => (int) $v)->sort()->values()->all();
            $ids = $c->searchIds($q);
            sort($ids);
            $this->assertSame($rows, $ids, "query $q");
        }
    }

    public function test_the_vehicle_finder_search_reads_part_numbers_descriptions_and_fitment_notes(): void
    {
        DB::table('ds_parts')->insert(['part_number' => 'ZZQ-77', 'description' => 'ZZQ Fleet 2PC test', 'supplier' => 'PTI', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ds_parts')->insert(['part_number' => 'ZZQ-78', 'description' => 'ZZQ dropped', 'supplier' => 'PTI', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ds_fitments')->insert(['part_number' => 'ZZQ-77', 'make' => 'Test', 'year' => 2001, 'model' => 'Test', 'fkey' => 'zzq-77', 'notes' => 'Needs the zzqwidget carrier']);
        Cache::flush();
        $f = app(DriveshaftFinder::class);
        $this->assertSame(['ZZQ-77'], array_column($f->search('zzqwidget'), 'part_number'));
        $this->assertSame(['ZZQ-77'], array_column($f->search('zzq-77'), 'part_number'));
        $this->assertSame(['ZZQ-77'], array_column($f->search('zzq fleet'), 'part_number'));   // a dropped shaft never resurfaces
    }
}
