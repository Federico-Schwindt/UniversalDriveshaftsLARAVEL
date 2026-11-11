<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Signed client for the shop station's integration API (uds-checkin, src/lib/integration.ts). The store holds a shared
 * secret only — no database credentials — and the station writes its own rows with its own validation and audit trail.
 *
 * Each request carries X-UDS-Timestamp (unix seconds) and X-UDS-Signature = "sha256=" + HMAC-SHA256 of
 * "{timestamp}.{METHOD}.{path with query}.{raw body}" with CHECKIN_API_SECRET (= the station's INTEGRATION_SECRET).
 */
class CheckinApi
{
    public static function configured(): bool
    {
        return (string) config('services.checkin.url') !== '' && strlen((string) config('services.checkin.secret')) >= 32;
    }

    /** PUT the work order for a job number; created once, a repeat returns the existing one. [id, created, deleted] */
    public static function put(string $job, array $payload): array
    {
        return self::call('PUT', '/api/integrations/web-orders/' . rawurlencode($job), $payload);
    }

    /** PATCH what changed on the order since (payment / cancel / complete). [id, changed, status, payment] */
    public static function patch(string $job, array $update): array
    {
        return self::call('PATCH', '/api/integrations/web-orders/' . rawurlencode($job), $update);
    }

    /** job number => [id, status, payment, cancelled, deleted] for the given jobs (200 per request). */
    public static function statuses(array $jobs): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($jobs)), 200) as $chunk) {
            $res = self::call('GET', '/api/integrations/web-orders?jobs=' . implode(',', array_map('rawurlencode', $chunk)));
            foreach ((array) ($res['entries'] ?? []) as $e) $out[(string) $e['job']] = $e;
        }
        return $out;
    }

    /** Throws on anything but a 2xx answer: the caller records the failure and checkin:push retries. */
    private static function call(string $method, string $path, ?array $body = null): array
    {
        if (!self::configured()) throw new \RuntimeException('The shop station API is not configured (CHECKIN_API_URL / CHECKIN_API_SECRET).');
        $raw = $body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ts = (string) time();
        $sig = 'sha256=' . hash_hmac('sha256', "{$ts}.{$method}.{$path}.{$raw}", (string) config('services.checkin.secret'));
        $req = Http::withHeaders(['X-UDS-Timestamp' => $ts, 'X-UDS-Signature' => $sig, 'Accept' => 'application/json', 'User-Agent' => 'UDS online store'])
            ->timeout((int) config('services.checkin.timeout', 20))->connectTimeout(10);
        // a server whose own DNS points the station's name elsewhere (this staging box maps it to itself in /etc/hosts):
        // CHECKIN_API_RESOLVE="driveshaftsindustries.com:443:<real ip>" pins it, like WC_ORIGIN_IP does for the old store
        if ($pin = (string) config('services.checkin.resolve')) $req = $req->withOptions(['curl' => [CURLOPT_RESOLVE => [$pin]]]);
        if ($raw !== '') $req = $req->withBody($raw, 'application/json');
        $res = $req->send($method, rtrim((string) config('services.checkin.url'), '/') . $path);
        if (!$res->successful()) {
            throw new \RuntimeException("Station API {$method} {$path}: HTTP {$res->status()} " . mb_substr((string) ($res->json('error') ?? $res->body()), 0, 200));
        }
        return (array) $res->json();
    }
}
