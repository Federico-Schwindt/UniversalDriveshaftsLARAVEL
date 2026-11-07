<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The real universaldriveshafts.com (the live WooCommerce store) lives on another host, but this
 * server's /etc/hosts points the domain at itself, where an old copy of the WordPress site is served.
 * Anything that must read the live store resolves the domain through public DNS instead, so it reaches
 * the real host without depending on (or needing root to change) the hosts file.
 */
class LiveOrigin
{
    public const HOST = 'universaldriveshafts.com';

    /** Real A record of the live store, from public DNS (public DNS, never /etc/hosts); null if DNS fails. */
    public static function ip(): ?string
    {
        if ($fixed = config('services.woocommerce.origin_ip')) return $fixed;
        // the local resolver (systemd-resolved) also serves /etc/hosts, so ask public DNS over HTTPS
        return Cache::remember('live_origin_ip', now()->addMinutes(30), function () {
            foreach (['https://cloudflare-dns.com/dns-query', 'https://dns.google/resolve'] as $doh) {
                try {
                    $r = \Illuminate\Support\Facades\Http::timeout(8)->withHeaders(['Accept' => 'application/dns-json'])->get($doh, ['name' => self::HOST, 'type' => 'A']);
                    foreach ((array) $r->json('Answer') as $a) if (($a['type'] ?? 0) === 1 && filter_var($a['data'] ?? '', FILTER_VALIDATE_IP)) return $a['data'];
                } catch (\Throwable $e) {}
            }
            return null;
        }) ?: null;
    }

    /**
     * True once this app IS universaldriveshafts.com (launch day: APP_URL on the real domain, or public DNS
     * already pointing the domain at this server). From then on there is no old store to read from:
     * catalog:sync-live and catalog:localize-images stop instead of reading the new site back into itself.
     */
    public static function isSelf(): bool
    {
        $appHost = strtolower(preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)));
        if ($appHost === self::HOST) return true;
        $ip = self::ip();
        return $ip !== null && $ip === config('services.woocommerce.self_ip', '178.156.163.237');
    }

    /**
     * True once the catalog was taken off the old store for good (catalog:detach): products, prices, photos and stock
     * live only here, edited in the admin, and nothing reads the catalog from WooCommerce any more.
     */
    public static function retired(): bool
    {
        return (bool) Settings::get('catalog.detached_at');
    }

    /** Options for Http::withOptions() that pin the live host (and www) to its real address. */
    public static function httpOptions(): array
    {
        $ip = self::ip();
        if (!$ip) return [];
        return ['curl' => [CURLOPT_RESOLVE => [self::HOST . ":443:$ip", 'www.' . self::HOST . ":443:$ip", self::HOST . ":80:$ip", 'www.' . self::HOST . ":80:$ip"]]];
    }

    /** WooCommerce REST (wc/v3) client for the live store, with the read-only keys from .env. */
    public static function wc(): \Illuminate\Http\Client\PendingRequest
    {
        return \Illuminate\Support\Facades\Http::withOptions(self::httpOptions())
            ->withBasicAuth((string) config('services.woocommerce.key'), (string) config('services.woocommerce.secret'))
            ->baseUrl(rtrim((string) config('services.woocommerce.url', 'https://' . self::HOST), '/') . '/wp-json/wc/v3')
            ->timeout(60)->retry(2, 2000);
    }
}
