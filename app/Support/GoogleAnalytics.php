<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Google Analytics 4 for the admin dashboard (owner, 2026-11-05: "ver el tráfico global en un globo 3D e información
 * importante"): the last 28 days against the 28 before, and who is on the site right now. Read with the store's
 * service account (Data API, read only), cached so the dashboard never waits on Google more than once in a while.
 * Until launch GA4 measures the old WordPress store (its tag); afterwards this site (partials/analytics in production).
 */
class GoogleAnalytics
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';
    private const API = 'https://analyticsdata.googleapis.com/v1beta/properties/';

    public static function property(): string
    {
        return (string) config('services.ga4.property', '472848382');
    }

    public static function configured(): bool
    {
        return GoogleServiceAccount::configured();
    }

    private static function call(string $method, array $body): array
    {
        $r = Http::withToken(GoogleServiceAccount::token(self::SCOPE))->timeout(25)->retry(1, 1500, throw: false)
            ->post(self::API . self::property() . ':' . $method, $body);
        if (!$r->successful()) {
            $msg = (string) ($r->json('error.message') ?? $r->status());
            if ($r->status() === 403 && (str_contains($msg, 'has not been used') || str_contains($msg, 'disabled'))) $msg = 'The Google Analytics Data API is not enabled in the Google Cloud project.';
            elseif ($r->status() === 403) $msg = 'The service account cannot read GA4 property ' . self::property() . ': add it as a Viewer under Admin → Property access management.';
            throw new \RuntimeException($msg);
        }
        return $r->json();
    }

    /** Rows as [dim1, dim2…, metric1, metric2…] (metrics as floats). */
    private static function rows(array $res): array
    {
        return array_map(fn ($r) => array_merge(array_column($r['dimensionValues'] ?? [], 'value'), array_map('floatval', array_column($r['metricValues'] ?? [], 'value'))), $res['rows'] ?? []);
    }

    /** Last 28 days (and the 28 before for the deltas). Cached 30 minutes. */
    public static function overview(): array
    {
        return Cache::remember('ga4:overview', 1800, function () {
            $range = [['startDate' => '27daysAgo', 'endDate' => 'today', 'name' => 'cur'], ['startDate' => '55daysAgo', 'endDate' => '28daysAgo', 'name' => 'prev']];
            $metrics = ['activeUsers', 'newUsers', 'sessions', 'engagementRate', 'averageSessionDuration', 'screenPageViews', 'ecommercePurchases', 'purchaseRevenue'];
            $tot = self::call('runReport', ['dateRanges' => $range, 'metrics' => array_map(fn ($m) => ['name' => $m], $metrics)]);
            $k = ['cur' => array_fill_keys($metrics, 0.0), 'prev' => array_fill_keys($metrics, 0.0)];
            foreach ($tot['rows'] ?? [] as $row) {
                $name = $row['dimensionValues'][0]['value'] ?? 'cur';
                foreach ($metrics as $i => $m) $k[$name === 'prev' ? 'prev' : 'cur'][$m] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }
            $cur = [['startDate' => '27daysAgo', 'endDate' => 'today']];
            $report = fn (array $dims, string $metric, int $limit, array $extraMetrics = []) => self::rows(self::call('runReport', [
                'dateRanges' => $cur, 'dimensions' => array_map(fn ($d) => ['name' => $d], $dims),
                'metrics' => array_map(fn ($m) => ['name' => $m], array_merge([$metric], $extraMetrics)),
                'orderBys' => [['metric' => ['metricName' => $metric], 'desc' => true]], 'limit' => $limit,
            ]));
            $daily = self::rows(self::call('runReport', ['dateRanges' => $cur, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]]]));
            return [
                'kpi' => $k,
                'channels' => $report(['sessionDefaultChannelGroup'], 'sessions', 8),
                'countries' => $report(['country', 'countryId'], 'activeUsers', 40),
                'cities' => $report(['city', 'region', 'countryId', 'country'], 'activeUsers', 150),
                'pages' => $report(['pagePath', 'pageTitle'], 'screenPageViews', 8),
                'devices' => $report(['deviceCategory'], 'sessions', 4),
                'daily' => $daily,
                'at' => now()->toIso8601String(),
            ];
        });
    }

    /** Who is on the site in the last 30 minutes. Cached 45 seconds. */
    public static function live(): array
    {
        return Cache::remember('ga4:live', 45, function () {
            $cities = self::rows(self::call('runRealtimeReport', ['dimensions' => [['name' => 'city'], ['name' => 'countryId'], ['name' => 'country']], 'metrics' => [['name' => 'activeUsers']], 'limit' => 100]));
            $pages = self::rows(self::call('runRealtimeReport', ['dimensions' => [['name' => 'unifiedScreenName']], 'metrics' => [['name' => 'activeUsers']], 'limit' => 6]));
            $devices = self::rows(self::call('runRealtimeReport', ['dimensions' => [['name' => 'deviceCategory']], 'metrics' => [['name' => 'activeUsers']]]));
            return ['total' => (int) array_sum(array_map(fn ($r) => end($r), $cities)), 'cities' => $cities, 'pages' => $pages, 'devices' => $devices, 'at' => now()->toIso8601String()];
        });
    }

    /* ---------------- the globe ---------------- */

    public static function cityKey(string $city, string $region, string $cc): string
    {
        return mb_substr('city|' . mb_strtolower(trim($city)) . '|' . mb_strtolower(trim($region)) . '|' . strtoupper($cc), 0, 190);
    }

    /** [lat, lng] of a country: the centre computed from the map (resources/data), else a geocoded one, else null. */
    public static function countryPoint(string $cc): ?array
    {
        static $c = null;
        $c ??= json_decode((string) @file_get_contents(resource_path('data/country-centroids.json')), true) ?: [];
        if (isset($c[strtoupper($cc)])) return $c[strtoupper($cc)];
        $g = DB::table('geo_places')->where('key', 'country|' . strtoupper($cc))->where('found', 1)->first(['lat', 'lng']);
        return $g ? [(float) $g->lat, (float) $g->lng] : null;
    }

    /**
     * Globe points: [{lat, lng, label, users}] for cities (geocoded), falling back to the country centre for cities not
     * geocoded yet. $rows: [city, (region,) countryId, (country,) …, users].
     */
    public static function points(array $rows, bool $withRegion): array
    {
        $keys = [];
        foreach ($rows as $r) $keys[] = self::cityKey($r[0], $withRegion ? $r[1] : '', $withRegion ? $r[2] : $r[1]);
        $geo = DB::table('geo_places')->whereIn('key', $keys)->where('found', 1)->get(['key', 'lat', 'lng'])->keyBy('key');
        $out = [];
        foreach ($rows as $i => $r) {
            $cc = $withRegion ? $r[2] : $r[1];
            $country = $withRegion ? $r[3] : $r[2];
            $users = (float) end($r);
            $city = $r[0] === '(not set)' ? '' : $r[0];
            $g = $geo[$keys[$i]] ?? null;
            $pt = $g ? [(float) $g->lat, (float) $g->lng] : self::countryPoint($cc);
            if (!$pt) continue;
            $out[] = ['lat' => $pt[0], 'lng' => $pt[1], 'label' => trim($city !== '' ? "$city, $country" : $country, ', '), 'users' => $users, 'exact' => (bool) $g];
        }
        return $out;
    }

    /** Places (cities, small countries) not geocoded yet: [key => [city, region, country name, cc]]. */
    public static function missingPlaces(): array
    {
        $want = [];
        $ov = Cache::get('ga4:overview'); $lv = Cache::get('ga4:live');
        foreach ($ov['cities'] ?? [] as $r) if ($r[0] !== '(not set)') $want[self::cityKey($r[0], $r[1], $r[2])] = [$r[0], $r[1], $r[3], $r[2]];
        foreach ($lv['cities'] ?? [] as $r) if ($r[0] !== '(not set)') $want[self::cityKey($r[0], '', $r[1])] = [$r[0], '', $r[2], $r[1]];
        foreach (array_merge($ov['countries'] ?? [], array_map(fn ($r) => [$r[2], $r[1]], $lv['cities'] ?? [])) as $r) {
            if (!self::countryPoint($r[1]) && $r[1] !== '' && $r[1] !== '(not set)') $want['country|' . strtoupper($r[1])] = ['', '', $r[0], $r[1]];
        }
        $have = DB::table('geo_places')->whereIn('key', array_keys($want))->pluck('key')->flip();
        return array_diff_key($want, $have->all());
    }
}
