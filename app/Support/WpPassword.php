<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Customers imported from WordPress keep their password: users.wp_password holds the WordPress hash
 * (WP 6.8+ "$wp$2y$…" bcrypt over an HMAC-SHA384, or the older phpass "$P$…"). The first sign-in that matches
 * it stores our own hash in users.password and drops the WordPress one.
 */
class WpPassword
{
    /** Before Auth::attempt(): turn a matching WordPress hash into ours. True when it did. */
    public static function upgrade(string $email, string $plain): bool
    {
        $u = DB::table('users')->where('email', $email)->whereNull('password')->whereNotNull('wp_password')->first(['id', 'wp_password']);
        if (!$u || !self::check($plain, $u->wp_password)) return false;
        DB::table('users')->where('id', $u->id)->update(['password' => Hash::make($plain), 'wp_password' => null, 'updated_at' => now()]);
        return true;
    }

    public static function check(string $plain, string $hash): bool
    {
        if ($plain === '' || $hash === '') return false;
        if (str_starts_with($hash, '$wp')) {
            // wp_hash_password() trims before hashing; wp_check_password() does not: accept either
            foreach (array_unique([$plain, trim($plain)]) as $p) {
                if (password_verify(base64_encode(hash_hmac('sha384', $p, 'wp-sha384', true)), substr($hash, 3))) return true;
            }
            return false;
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) return hash_equals($hash, self::phpass($plain, $hash));
        if (str_starts_with($hash, '$2y$')) return password_verify($plain, $hash);
        return strlen($hash) === 32 && hash_equals($hash, md5($plain));   // pre-2008 WordPress
    }

    /** phpass "portable" hash (WordPress up to 6.7): iterated salted MD5. */
    private static function phpass(string $password, string $setting): string
    {
        $itoa = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $log2 = strpos($itoa, $setting[3] ?? '');
        if ($log2 === false || $log2 < 7 || $log2 > 30) return '*';
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) return '*';
        $count = 1 << $log2;
        $hash = md5($salt . $password, true);
        do { $hash = md5($hash . $password, true); } while (--$count);
        return substr($setting, 0, 12) . self::encode64($hash, 16, $itoa);
    }

    private static function encode64(string $in, int $count, string $itoa): string
    {
        $out = ''; $i = 0;
        do {
            $v = ord($in[$i++]);
            $out .= $itoa[$v & 0x3f];
            if ($i < $count) $v |= ord($in[$i]) << 8;
            $out .= $itoa[($v >> 6) & 0x3f];
            if ($i++ >= $count) break;
            if ($i < $count) $v |= ord($in[$i]) << 16;
            $out .= $itoa[($v >> 12) & 0x3f];
            if ($i++ >= $count) break;
            $out .= $itoa[($v >> 18) & 0x3f];
        } while ($i < $count);
        return $out;
    }
}
