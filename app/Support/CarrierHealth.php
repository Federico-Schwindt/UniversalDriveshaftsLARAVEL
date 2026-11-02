<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Notices when a rating provider (EasyPost, Warp) is failing, not when one address can't be
 * priced. Five failures inside an hour e-mail the shop — once per provider per hour — because
 * a quiet outage means every customer sees "call us" at checkout and nobody at the shop knows.
 */
class CarrierHealth
{
    private const THRESHOLD = 5;
    private const WINDOW_SECONDS = 3600;

    public static function failure(string $provider, string $detail): void
    {
        try {
            $key = 'carrier-fail:' . Str::slug($provider);
            $now = time();
            $hits = array_values(array_filter((array) Cache::get($key, []), fn ($t) => $t > $now - self::WINDOW_SECONDS));
            $hits[] = $now;
            Cache::put($key, $hits, self::WINDOW_SECONDS);

            if (count($hits) < self::THRESHOLD || !Cache::add($key . ':alerted', 1, self::WINDOW_SECONDS)) return;

            $to = (string) (Settings::get('alerts.email') ?: config('uds.email'));
            Mailer::simple($to, "{$provider} rates are failing", [
                'kicker' => 'Shipping alert',
                'title' => "{$provider} has failed " . count($hits) . ' times in the last hour',
                'intro' => 'Customers may be seeing no shipping rates at checkout (only local pickup and the "call us" note). Check the provider status and the API key.',
                'kvTitle' => 'Details',
                'kv' => ['Provider' => $provider, 'Failures (1 h)' => (string) count($hits), 'Last error' => Str::limit($detail, 300)],
            ]);
        } catch (\Throwable $e) {
            report($e);   // an alert must never break the checkout that triggered it
        }
    }
}
