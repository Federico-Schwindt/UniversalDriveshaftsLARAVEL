<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A signed-in customer's trail for the admin chat page (owner, 2026-11-04: which page they are on, the server log
 * on their side): page views, actions and errors (TrackCustomerActivity middleware), JavaScript errors and a
 * presence heartbeat from the chat widget. Nothing is sent anywhere; rows older than 30 days are pruned.
 */
class CustomerActivity
{
    public const KEEP_DAYS = 30;
    public const ONLINE_SECONDS = 90;

    public static function record(int $userId, string $kind, string $path, array $extra = []): void
    {
        try {
            DB::table('customer_activity')->insert([
                'user_id' => $userId, 'kind' => $kind, 'path' => mb_substr($path, 0, 500),
                'method' => $extra['method'] ?? null, 'title' => isset($extra['title']) ? mb_substr((string) $extra['title'], 0, 190) : null,
                'status' => $extra['status'] ?? null, 'ms' => $extra['ms'] ?? null, 'ip' => $extra['ip'] ?? null,
                'ua' => isset($extra['ua']) ? mb_substr((string) $extra['ua'], 0, 255) : null,
                'detail' => isset($extra['detail']) ? mb_substr((string) $extra['detail'], 0, 2000) : null, 'created_at' => now(),
            ]);
        } catch (\Throwable $e) { report($e); }
    }

    /** The widget's heartbeat: where the customer is right now. */
    public static function ping(int $userId, string $url, string $title, string $ip, string $ua): void
    {
        Cache::put("presence:u{$userId}", ['url' => mb_substr($url, 0, 500), 'title' => mb_substr($title, 0, 190), 'at' => time(), 'ip' => $ip, 'ua' => mb_substr($ua, 0, 255)], 600);
    }

    /** ['online' => bool, 'url', 'title', 'at' (unix), 'ago', 'device', 'ip'] from the heartbeat, else from the last page view. */
    public static function presence(int $userId): ?array
    {
        $p = Cache::get("presence:u{$userId}");
        $page = DB::table('customer_activity')->where('user_id', $userId)->where('kind', 'page')->orderByDesc('id')->first(['path', 'title', 'ip', 'ua', 'created_at']);
        $pageAt = $page ? \Carbon\Carbon::parse($page->created_at)->timestamp : 0;
        if (!$p && !$page) return null;
        // a page view newer than the last heartbeat (or no heartbeat at all) says where they are
        if (!$p || $pageAt > $p['at']) $p = ['url' => $page->path, 'title' => $page->title, 'at' => $pageAt, 'ip' => $page->ip, 'ua' => $page->ua];
        return $p + ['online' => time() - $p['at'] <= self::ONLINE_SECONDS, 'ago' => \Carbon\Carbon::createFromTimestamp($p['at'])->diffForHumans(), 'device' => self::device((string) ($p['ua'] ?? ''))];
    }

    /** "Chrome on Windows", "Safari on iPhone"… good enough to picture the customer's screen. */
    public static function device(string $ua): string
    {
        if ($ua === '') return '';
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => (str_contains($ua, 'Mobile') ? 'Android phone' : 'Android tablet'),
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac', str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux', default => 'Unknown device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'CriOS') || (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium')) => 'Chrome', str_contains($ua, 'FxiOS') || str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari', default => 'Browser',
        };
        return "{$browser} on {$os}";
    }

    public static function prune(): int
    {
        return DB::table('customer_activity')->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
    }
}
