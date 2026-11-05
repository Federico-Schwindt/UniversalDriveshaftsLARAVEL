<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin → SEO → Performance from Search Console, with Google faked (a throwaway key, no network). */
class SearchConsoleTest extends TestCase
{
    use DatabaseTransactions;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pk, $pem);
        $this->key = tempnam(sys_get_temp_dir(), 'gsc') . '.json';
        file_put_contents($this->key, json_encode(['type' => 'service_account', 'client_email' => 'claude-seo@brave-sonar-498116-s0.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.gsc.key' => $this->key, 'services.gsc.site' => 'sc-domain:universaldriveshafts.com']);
        Cache::forget('google:token:' . md5('https://www.googleapis.com/auth/webmasters.readonly'));
    }

    protected function tearDown(): void
    {
        @unlink($this->key);
        Cache::forget('google:token:' . md5('https://www.googleapis.com/auth/webmasters.readonly'));
        parent::tearDown();
    }

    public function test_sync_fills_days_and_monthly_queries_and_the_tab_shows_average_position(): void
    {
        DB::table('seo_gsc_daily')->delete(); DB::table('seo_gsc_rows')->delete();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'searchconsole.googleapis.com/*' => function ($req) {
                $dims = $req->data()['dimensions'] ?? [];
                if ($dims === ['date']) return Http::response(['rows' => [
                    ['keys' => [now('America/Los_Angeles')->subDays(3)->toDateString()], 'clicks' => 30, 'impressions' => 1000, 'ctr' => 0.03, 'position' => 12.0],
                    ['keys' => [now('America/Los_Angeles')->subDays(2)->toDateString()], 'clicks' => 10, 'impressions' => 3000, 'ctr' => 0.003, 'position' => 20.0],
                ]]);
                $k = $dims[0] === 'query' ? 'driveshaft shop near me' : 'https://universaldriveshafts.com/parts/5-1310-1X';
                return Http::response(['rows' => [['keys' => [$k], 'clicks' => 7, 'impressions' => 90, 'ctr' => 0.08, 'position' => 6.4]]]);
            },
        ]);
        $this->artisan('seo:gsc-sync', ['--months' => 2])->assertSuccessful();
        $this->assertSame(2, DB::table('seo_gsc_daily')->count());
        $this->assertSame(4, DB::table('seo_gsc_rows')->count());   // 2 months × query + page
        Http::assertSent(fn ($req) => str_contains($req->url(), rawurlencode('sc-domain:universaldriveshafts.com')) && $req->hasHeader('Authorization', 'Bearer fake-token'));

        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        // (12×1000 + 20×3000) / 4000 = 18.0: weighted by impressions, as Search Console averages it
        $this->actingAs($admin)->get('/admin/seo?tab=performance')->assertOk()->assertSee('Average position')->assertSee('18.0')->assertSee('driveshaft shop near me');
    }

    public function test_www_answers_with_a_301_to_the_one_canonical_host(): void
    {
        config(['app.url' => 'https://universaldriveshafts.com']);
        $this->get('http://www.universaldriveshafts.com/shop?page=2')->assertStatus(301)->assertRedirect('https://universaldriveshafts.com/shop?page=2');
        $this->get('https://universaldriveshafts.com/shop')->assertOk();
    }

    public function test_a_property_the_account_cannot_read_is_explained(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token']),
            'searchconsole.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'User does not have sufficient permission for site']], 403),
        ]);
        $this->artisan('seo:gsc-sync', ['--months' => 1])->assertFailed();
        $this->assertStringContainsString('Users and permissions', \App\Support\Settings::get('seo.gsc_status')['message']);
    }
}
