<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Key/value store edited from the admin; values override config('uds.*') at boot. */
class Settings
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all !== null) return self::$all;
        return self::$all = Cache::remember('settings_all', 3600, function () {
            try {
                return DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();
            } catch (\Throwable $e) { return []; }
        });
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now()]);
        self::$all = null;
        Cache::forget('settings_all');
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => json_encode($v), 'updated_at' => now()]);
        self::$all = null;
        Cache::forget('settings_all');
    }

    /** Settings that mirror a config key: [setting key => config path]. */
    public const CONFIG_MAP = [
        'store.email' => 'uds.email', 'store.phone' => 'uds.phone', 'store.phone_href' => 'uds.phone_href',
        'store.address' => 'uds.address', 'store.hours' => 'uds.hours',
        'shipping.free_min' => 'uds.free_shipping_min',
        'alerts.email' => 'uds.inventory_alert_email',
        'pickup.hours' => 'uds.pickup.hours',
        'pickup.enabled' => 'uds.pickup.enabled',
        'mail.from_name' => 'mail.from.name',
        'mail.from_address' => 'mail.from.address',
        'brevo.from_address' => 'services.brevo.from',
        'brevo.from_name' => 'services.brevo.from_name',
    ];

    public static function applyToConfig(): void
    {
        foreach (self::all() as $k => $v) {
            if (isset(self::CONFIG_MAP[$k]) && $v !== null && $v !== '') config([self::CONFIG_MAP[$k] => $v]);
        }
    }
}
