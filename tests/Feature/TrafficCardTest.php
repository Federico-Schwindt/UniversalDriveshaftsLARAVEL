<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Dashboard traffic card: Google Analytics faked, the payload the globe and side panel read. */
class TrafficCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_traffic_data_has_kpis_and_globe_points_and_the_dashboard_shows_the_card(): void
    {
        Cache::forget('ga4:overview'); Cache::forget('ga4:live');
        Cache::put('google:token:' . md5('https://www.googleapis.com/auth/analytics.readonly'), 'fake-token', 60);
        Http::fake(['analyticsdata.googleapis.com/*' => function ($req) {
            $b = $req->data(); $dims = array_column($b['dimensions'] ?? [], 'name');
            $row = fn (array $d, array $m) => ['dimensionValues' => array_map(fn ($v) => ['value' => $v], $d), 'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $m)];
            if (str_contains($req->url(), 'runRealtimeReport')) return Http::response(['rows' => $dims === ['city', 'countryId', 'country'] ? [$row(['Tampa', 'US', 'United States'], [3])] : [$row(['mobile'], [3])]]);
            if (!$dims) return Http::response(['rows' => [$row(['cur'], [100, 60, 120, 0.5, 90, 300, 4, 800]), $row(['prev'], [80, 50, 100, 0.45, 80, 250, 3, 600])]]);
            return Http::response(['rows' => match ($dims) {
                ['city', 'region', 'countryId', 'country'] => [$row(['Odessa', 'Florida', 'US', 'United States'], [40])],
                ['country', 'countryId'] => [$row(['United States', 'US'], [90])],
                ['date'] => [$row(['20261101'], [10, 12])],
                default => [$row(array_fill(0, count($dims), 'x'), [5])],
            }]);
        }]);
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $d = $this->actingAs($admin)->getJson('/admin/traffic/data')->assertOk()->json();
        $this->assertSame(100.0, (float) $d['kpi']['cur']['activeUsers']);
        $this->assertSame(3, $d['live']['total']);
        $this->assertSame('Odessa, United States', $d['points'][0]['label']);   // not geocoded yet: shown at the country centre
        $this->assertFalse($d['points'][0]['exact']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Website traffic')->assertSee('globe.gl-2.46.2.min.js', false);
        // the cache is the app's own: never leave the fake token or fake figures behind
        Cache::forget('ga4:overview'); Cache::forget('ga4:live'); Cache::forget('google:token:' . md5('https://www.googleapis.com/auth/analytics.readonly'));
    }
}
