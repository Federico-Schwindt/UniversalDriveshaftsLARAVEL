<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner, 2026-11-13 (launch day): "necesito que mejores el seo! que el seo sea tan robusto como sea posible".
 * Part numbers and vehicles in the h1, categories titled the way people search them, share images, category lastmod.
 */
class SeoLaunchTest extends TestCase
{
    use DatabaseTransactions;

    private function h1(string $html): string
    {
        preg_match_all('#<h1[^>]*>(.*?)</h1>#s', $html, $m);
        $this->assertCount(1, $m[1], 'one h1');
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1][0]))));
    }

    public function test_a_part_page_h1_carries_the_part_number(): void
    {
        $sku = DB::connection('wp')->table('wp_posts as p')->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', 'like', '5-%')->orderBy('p.ID')->value('s.meta_value');
        $this->assertNotNull($sku);
        $this->assertStringContainsString($sku, $this->h1($this->get('/parts/' . rawurlencode($sku))->assertOk()->getContent()));
    }

    public function test_categories_are_titled_by_their_search_name_with_their_photo(): void
    {
        $html = $this->get('/shop/driveshaft-tubing/tubing')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<title>Driveshaft Tubing[^<]*\| UDS</title>#', $html);
        $html = $this->get('/shop/driveshaft-components/u-joints')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#<meta property="og:image" content="[^"]*og-default\.jpg"#', $html);
        // the logo that stands in for a missing photo is never a product's image
        $this->assertDoesNotMatchRegularExpression('#"@type":"Product"[^{}]*"image":"[^"]*uds-logo#', $html);
    }

    public function test_the_category_sitemap_dates_each_page_by_its_newest_product(): void
    {
        Cache::forget('uds_sitemap_categories');
        $xml = $this->get('/sitemap-categories.xml')->assertOk()->getContent();
        preg_match_all('#<url><loc>[^<]+</loc>(<lastmod>\d{4}-\d{2}-\d{2}</lastmod>)?#', $xml, $m);
        $this->assertGreaterThan(20, count($m[0]));
        $this->assertGreaterThan(count($m[0]) * 0.9, count(array_filter($m[1])));
        Cache::forget('uds_sitemap_categories');
    }
}
