<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Search Console API (read only) with a service account: universaldriveshafts@claude-seo-513723.iam.gserviceaccount.com
 * (Google Cloud project claude-seo-513723), added as a user of the domain property by the owner (2026-11-04). The JSON key lives outside the web root
 * (services.gsc.key, default storage/app/google/search-console.json, 0600). No Google SDK: a signed JWT is exchanged
 * for a one-hour token, then searchAnalytics.query.
 */
class SearchConsole
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
    private const API = 'https://searchconsole.googleapis.com/webmasters/v3';

    public static function keyPath(): string
    {
        return GoogleServiceAccount::keyPath();
    }

    public static function site(): string
    {
        return (string) config('services.gsc.site', 'sc-domain:universaldriveshafts.com');
    }

    public static function configured(): bool
    {
        return is_file(self::keyPath());
    }

    /** The service account's e-mail (shown in the admin so the owner can check it is the one added in Search Console). */
    public static function account(): ?string
    {
        $k = self::key();
        return $k['client_email'] ?? null;
    }

    private static function key(): array
    {
        return GoogleServiceAccount::key();
    }

    public static function token(): string
    {
        return GoogleServiceAccount::token(self::SCOPE);
    }

    /** Properties the service account can read (to tell "not added in Search Console" from other problems). */
    public static function sites(): array
    {
        $r = Http::withToken(self::token())->timeout(20)->get(self::API . '/sites');
        if (!$r->successful()) throw new \RuntimeException(self::why($r));
        return array_column($r->json('siteEntry') ?? [], 'permissionLevel', 'siteUrl');
    }

    /** searchAnalytics.query, all pages of results. Rows: ['keys' => [...], 'clicks', 'impressions', 'ctr', 'position']. */
    public static function query(string $from, string $to, array $dimensions, int $limit = 25000): array
    {
        $out = []; $start = 0;
        do {
            $r = Http::withToken(self::token())->timeout(60)->retry(2, 2000, throw: false)
                ->post(self::API . '/sites/' . rawurlencode(self::site()) . '/searchAnalytics/query', [
                    'startDate' => $from, 'endDate' => $to, 'dimensions' => $dimensions, 'rowLimit' => min(25000, $limit - $start), 'startRow' => $start, 'dataState' => 'all',
                ]);
            if (!$r->successful()) throw new \RuntimeException(self::why($r));
            $rows = $r->json('rows') ?? [];
            $out = array_merge($out, $rows);
            $start += count($rows);
        } while (count($rows) === 25000 && $start < $limit);
        return $out;
    }

    private static function why($r): string
    {
        $msg = (string) ($r->json('error.message') ?? $r->status());
        if ($r->status() === 403 && (str_contains($msg, 'has not been used') || str_contains($msg, 'disabled'))) return 'The Search Console API is not enabled in the Google Cloud project: APIs & Services → Library → "Google Search Console API" → Enable.';
        if ($r->status() === 403) return 'The service account cannot read ' . self::site() . ': add ' . (self::account() ?? 'it') . ' as a user in Search Console → Settings → Users and permissions.';
        return 'Search Console: ' . $msg;
    }
}
