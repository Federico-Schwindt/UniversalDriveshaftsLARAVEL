<?php

namespace Tests\Feature;

use App\Support\Seo;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Owner, 2026-11-12: "urls viejas: arreglala no quiero perder nada de seo". Every URL of the old WordPress store
 * (Search Console, its sitemap, its database) answers with ONE 301 to its page here: closing slash, WooCommerce
 * paging and filters included. Only campaign parameters follow the visitor.
 */
class LegacyUrlsTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql', 'wp'];

    protected function setUp(): void
    {
        parent::setUp();
        Seo::flush();
    }

    protected function tearDown(): void
    {
        Seo::flush();
        parent::tearDown();
    }

    /** A request as a browser sends it: the test client would trim the closing slash every old URL has. */
    private function old(string $uri): TestResponse
    {
        return TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle(Request::create($uri, 'GET', [], [], [], ['HTTP_USER_AGENT' => 'UDS seo test'])));
    }

    private function assertGoes(string $from, string $to): void
    {
        $res = $this->old($from);
        $this->assertSame(301, $res->getStatusCode(), $from);
        $this->assertSame($to, preg_replace('#^https?://[^/]+#', '', (string) $res->headers->get('Location')) ?: '/', $from);
    }

    /** A published u-joint: [id, slug, sku, the slug form of its part number]. */
    private function part(): array
    {
        $row = DB::connection('wp')->table('wp_posts as p')->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', 'like', '5-%')->where('p.post_title', 'like', '%universal joint%')
            ->orderBy('p.ID')->first(['p.ID', 'p.post_name', 's.meta_value as sku']);
        $this->assertNotNull($row);
        return [(int) $row->ID, $row->post_name, $row->sku, trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($row->sku)), '-')];
    }

    public function test_an_old_product_url_answers_in_one_hop_and_keeps_only_the_campaign(): void
    {
        [, $slug, $sku] = $this->part();
        $page = '/parts/' . rawurlencode($sku);
        $this->assertGoes("/product/{$slug}/", $page);
        $this->assertGoes("/product/{$slug}/?add-to-cart=13812&gclid=abc&utm_source=google", $page . '?gclid=abc&utm_source=google');
        $this->assertGoes("/product/{$slug}/feed/", $page);
        $this->get($page)->assertOk();
    }

    public function test_a_part_number_with_a_slash_or_a_space_has_a_page_the_old_url_reaches(): void
    {
        $wp = DB::connection('wp');
        foreach (['%/%' => fn ($sku) => str_replace('%2F', '/', rawurlencode($sku)), '% %' => fn ($sku) => rawurlencode($sku)] as $like => $path) {
            $row = $wp->table('wp_posts as p')->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
                ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', 'like', $like)->orderBy('p.ID')->first(['p.post_name', 's.meta_value as sku']);
            if (!$row) continue;
            $this->assertGoes("/product/{$row->post_name}/", '/parts/' . $path($row->sku));
            $this->get('/parts/' . $path($row->sku))->assertOk();
        }
        $this->get('/parts/zz-legacy/no/such/part')->assertNotFound();
    }

    public function test_a_slug_the_product_had_before_still_finds_it(): void
    {
        [$id, , $sku] = $this->part();
        DB::connection('wp')->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => '_wp_old_slug', 'meta_value' => 'zz-legacy-former-slug']);
        $this->assertGoes('/product/zz-legacy-former-slug/', '/parts/' . rawurlencode($sku));

        // a redirect kept in admin → SEO knows better than the catalog
        DB::table('seo_redirects')->insert(['source' => 'product/zz-legacy-former-slug', 'target' => '/shop/driveshaft-components', 'code' => 301, 'origin' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        Seo::flush();
        $this->assertGoes('/product/zz-legacy-former-slug/?per_page=24&fbclid=f1', '/shop/driveshaft-components?fbclid=f1');
    }

    public function test_an_imported_redirect_that_gave_up_on_a_product_is_corrected_by_its_former_slug(): void
    {
        [$id, , $sku] = $this->part();
        DB::connection('wp')->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => '_wp_old_slug', 'meta_value' => 'zz-legacy-former-slug']);
        $row = ['target' => '/shop', 'code' => 301, 'note' => 'no close match: sent to the shop', 'created_at' => now(), 'updated_at' => now()];
        DB::table('seo_redirects')->insert([
            ['source' => 'product/zz-legacy-former-slug', 'origin' => 'rank_math'] + $row,
            ['source' => 'product/zz-legacy-no-such-part', 'origin' => 'rank_math'] + $row,
            ['source' => 'product/zz-legacy-set-in-admin', 'origin' => 'manual'] + $row,
        ]);
        DB::connection('wp')->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => '_wp_old_slug', 'meta_value' => 'zz-legacy-set-in-admin']);
        Seo::flush();
        $this->assertGoes('/product/zz-legacy-former-slug/', '/shop');

        (require database_path('migrations/2026_11_12_000001_seo_redirects_former_slugs.php'))->up();
        $this->assertGoes('/product/zz-legacy-former-slug/', '/parts/' . rawurlencode($sku));
        $this->assertGoes('/product/zz-legacy-no-such-part/', '/shop');
        $this->assertGoes('/product/zz-legacy-set-in-admin/', '/shop');
    }

    public function test_an_unrecorded_slug_is_found_by_its_part_number_only_when_the_name_agrees(): void
    {
        [, , $sku, $number] = $this->part();
        $this->assertGoes("/product/{$number}-universal-joint-zz-legacy-old-title/", '/parts/' . rawurlencode($sku));
        $this->old("/product/{$number}-zz-legacy-other-thing/")->assertNotFound();
        $this->old('/product/zz-legacy-no-such-part/')->assertNotFound();
    }

    public function test_the_old_shop_and_its_categories_with_paging_and_filters(): void
    {
        $this->assertGoes('/driveshaft-shop/', '/shop');
        $this->assertGoes('/driveshaft-shop/page/45/?per_page=24&shop_view=list&add-to-cart=11390', '/shop');
        $this->assertGoes('/product-category/tubing/', '/shop/driveshaft-tubing/tubing');
        $this->assertGoes('/product-category/driveshaft-components/u-joints/page/3/?filter_brand=spicer&utm_campaign=fall', '/shop/driveshaft-components/u-joints?utm_campaign=fall');
        $this->assertGoes('/product-table/end-yokes/', '/shop/driveshaft-components/end-yokes');
        $this->assertGoes('/product-tag/zz-legacy-tag/', '/shop');
        $this->old('/product-category/zz-legacy-no-category/page/2/')->assertNotFound();
        // a category asked for without its group, or under another one
        $this->assertGoes('/shop/tubing/', '/shop/driveshaft-tubing/tubing');
        $this->assertGoes('/shop/zz-legacy-group/u-joints', '/shop/driveshaft-components/u-joints');
        $this->old('/shop/driveshaft-components/zz-legacy-no-category')->assertNotFound();
        $this->get('/shop/driveshaft-components/u-joints')->assertOk();
    }

    public function test_links_in_the_old_stores_emails_reach_the_account(): void
    {
        $this->assertGoes('/my-account/', '/account');
        $this->assertGoes('/my-account/orders/', '/account/orders');
        $this->assertGoes('/my-account/view-order/70619/', '/account/orders');
        $this->assertGoes('/my-account/edit-address/billing/', '/account/addresses');
        $this->assertGoes('/my-account/lost-password/?key=zz&login=someone', '/forgot-password');
        $this->assertGoes('/checkout/order-received/70619/?key=wc_order_zz', '/account/orders');
    }

    public function test_blog_archives_feeds_and_published_form_posts(): void
    {
        $this->assertGoes('/blog/', '/guides');
        $this->assertGoes('/category/zz-legacy-topic/', '/guides');
        $this->assertGoes('/feed/', '/');
        // a page of ours asked for the WordPress way
        $this->assertGoes('/guides/page/2/', '/guides');
        $this->assertGoes('/contact/feed/?utm_medium=rss', '/contact?utm_medium=rss');
        $this->old('/zz-legacy-missing-page/feed/')->assertNotFound();
        $this->assertSame(410, $this->old('/form-sumbission-post/custom-request-97/')->getStatusCode());
    }

    public function test_a_closing_slash_is_the_same_page_in_one_hop(): void
    {
        $this->assertGoes('/contact/', '/contact');
        $this->assertGoes('/shop/?sort=price&utm_source=x', '/shop?sort=price&utm_source=x');
        $this->old('/contact')->assertOk();
        $this->old('/')->assertOk();
        $this->old('/zz-legacy-missing-page/')->assertNotFound();
    }

    public function test_a_moved_page_keeps_the_campaign_and_a_kept_redirect_wins_over_the_shape(): void
    {
        $this->assertGoes('/about/?gclid=abc&v=2', '/about-us?gclid=abc');
        DB::table('seo_redirects')->insert(['source' => 'my-account/orders', 'target' => '/track-order', 'code' => 301, 'origin' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        Seo::flush();
        $this->assertGoes('/my-account/orders/', '/track-order');
    }
}
