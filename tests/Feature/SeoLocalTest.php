<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The store's own SEO: imported redirects, 410s, the 404 log and title/description overrides (admin → SEO). */
class SeoLocalTest extends TestCase
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

    private function redirect(string $source, ?string $target, int $code = 301): int
    {
        return DB::table('seo_redirects')->insertGetId(['source' => $source, 'target' => $target, 'code' => $code, 'origin' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_old_url_follows_its_redirect_and_counts_the_hit(): void
    {
        $id = $this->redirect('product/zz-seo-test-old', '/shop/driveshaft-components');
        $this->get('/product/zz-seo-test-old?utm_source=x')->assertRedirect('/shop/driveshaft-components?utm_source=x')->assertStatus(301);
        $this->get('/PRODUCT/ZZ-SEO-TEST-OLD')->assertStatus(301);
        $this->assertSame(2, (int) DB::table('seo_redirects')->where('id', $id)->value('hits'));
    }

    public function test_gone_rule_answers_410(): void
    {
        $this->redirect('category/zz-seo-test-gone', null, 410);
        $this->get('/category/zz-seo-test-gone')->assertStatus(410);
    }

    public function test_a_real_page_always_wins_over_a_redirect(): void
    {
        $this->redirect('contact', '/shop');
        $this->get('/contact')->assertOk();
    }

    public function test_unmatched_404_is_logged_but_probes_are_not(): void
    {
        $this->get('/zz-seo-test-missing-page')->assertNotFound();
        $this->get('/zz-seo-test-missing-page')->assertNotFound();
        $this->get('/wp-admin/zz-seo-test.php')->assertNotFound();
        $this->assertSame(2, (int) DB::table('seo_404s')->where('path', 'zz-seo-test-missing-page')->value('hits'));
        $this->assertFalse(DB::table('seo_404s')->where('path', 'like', 'wp-admin/zz-seo-test%')->exists());
    }

    public function test_page_override_sets_title_description_and_robots(): void
    {
        DB::table('seo_pages')->insert(['path' => 'contact', 'title' => 'ZZ Contact <Title>', 'description' => 'ZZ custom description.', 'robots' => 'noindex,follow', 'created_at' => now(), 'updated_at' => now()]);
        $html = $this->get('/contact')->assertOk()->getContent();
        $this->assertStringContainsString('<title>ZZ Contact &lt;Title&gt;</title>', $html);
        $this->assertStringContainsString('content="ZZ custom description."', $html);
    }

    public function test_product_seo_fields_from_the_admin_reach_the_product_page(): void
    {
        $row = DB::connection('wp')->table('wp_posts as p')->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
            ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', 'like', '5-%')->first(['p.ID', 's.meta_value as sku']);
        $this->assertNotNull($row);
        $wp = DB::connection('wp')->table('wp_postmeta');
        $wp->where('post_id', $row->ID)->whereIn('meta_key', ['_uds_seo_title', '_uds_seo_desc'])->delete();
        $wp->insert([['post_id' => $row->ID, 'meta_key' => '_uds_seo_title', 'meta_value' => 'ZZ Own Product Title'], ['post_id' => $row->ID, 'meta_key' => '_uds_seo_desc', 'meta_value' => 'ZZ own product description.']]);
        $html = $this->get('/parts/' . rawurlencode($row->sku))->assertOk()->getContent();
        $this->assertStringContainsString('<title>ZZ Own Product Title</title>', $html);
        $this->assertStringContainsString('content="ZZ own product description."', $html);
    }

    public function test_admin_saves_a_redirect_from_the_404_log(): void
    {
        $admin = User::query()->where('is_admin', 1)->where(fn ($q) => $q->whereNull('admin_role')->orWhere('admin_role', 'owner'))->firstOrFail();
        $this->get('/zz-seo-test-old-page')->assertNotFound();
        $logId = (int) DB::table('seo_404s')->where('path', 'zz-seo-test-old-page')->value('id');
        $this->actingAs($admin)->get('/admin/seo?tab=notfound&q=zz-seo-test-old-page')->assertOk()->assertSee('/zz-seo-test-old-page');
        $this->actingAs($admin)->post('/admin/seo/redirects', ['source' => '/zz-seo-test-old-page/', 'target' => 'https://universaldriveshafts.com/shop/', 'code' => '301', 'from_404' => $logId])->assertRedirect();
        $this->assertSame('/shop/', DB::table('seo_redirects')->where('source', 'zz-seo-test-old-page')->value('target'));
        $this->assertFalse(DB::table('seo_404s')->where('id', $logId)->exists());
        $this->get('/zz-seo-test-old-page')->assertRedirect('/shop/');
        $this->actingAs($admin)->get('/admin/seo')->assertOk();
        $this->actingAs($admin)->get('/admin/seo?tab=pages')->assertOk();
    }
}
