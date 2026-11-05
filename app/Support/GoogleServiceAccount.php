<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The store's Google service account (universaldriveshafts@claude-seo-513723.iam.gserviceaccount.com): one JSON key
 * outside the web root, read-only access to Search Console and Google Analytics. A signed JWT is exchanged for a
 * one-hour token per scope (no Google SDK).
 */
class GoogleServiceAccount
{
    public static function keyPath(): string
    {
        return (string) config('services.gsc.key', storage_path('app/google/search-console.json'));
    }

    public static function configured(): bool
    {
        return is_file(self::keyPath());
    }

    public static function key(): array
    {
        return self::configured() ? (json_decode((string) file_get_contents(self::keyPath()), true) ?: []) : [];
    }

    public static function token(string $scope): string
    {
        return Cache::remember('google:token:' . md5($scope), 3000, function () use ($scope) {
            $k = self::key();
            if (empty($k['client_email']) || empty($k['private_key'])) throw new \RuntimeException('The Google key file is missing or not a service-account JSON key.');
            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
                'iss' => $k['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            if (!openssl_sign($jwt, $sig, $k['private_key'], OPENSSL_ALGO_SHA256)) throw new \RuntimeException('Could not sign with the Google key.');
            $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $b64($sig)]);
            if (!$r->successful()) throw new \RuntimeException('Google refused the key: ' . ($r->json('error_description') ?: $r->status()));
            return (string) $r->json('access_token');
        });
    }
}
