<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Admin sign-in needs a 6-digit code e-mailed to the admin's own address (owner, 2026-11-14: "2FA por mail para entrar
 * al admin"). Checked by AdminMiddleware on every admin request, so password, Google and remember-me sign-ins all pass
 * through it. "Trust this computer" keeps a signed cookie for 30 days; it dies with a password or e-mail change.
 */
class AdminTwoFactor
{
    public const COOKIE = 'uds_admin_device';
    public const DAYS = 30;

    public static function enabled(): bool
    {
        return (bool) config('uds.admin_2fa', true);
    }

    public static function passed(Request $r): bool
    {
        $u = $r->user();
        if (!$u) return false;
        if ((int) $r->session()->get('admin_2fa') === (int) $u->id) return true;
        $c = (string) $r->cookie(self::COOKIE, '');
        if ($c !== '' && hash_equals(self::token($u, (string) strstr($c, '.', true)), $c)) {
            $r->session()->put('admin_2fa', $u->id);
            return true;
        }
        return false;
    }

    /** "expiry.signature" for this admin, or "!" (never matches) when the expiry is malformed or past. */
    private static function token(object $u, string $exp): string
    {
        if (!ctype_digit($exp) || (int) $exp < now()->timestamp) return '!';
        return $exp . '.' . hash_hmac('sha256', $u->id . '|' . $exp . '|' . strtolower((string) $u->email) . '|' . substr((string) $u->password, -24), (string) config('app.key'));
    }

    public static function trustCookie(object $u): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(self::COOKIE, self::token($u, (string) now()->addDays(self::DAYS)->timestamp), self::DAYS * 24 * 60, '/', null, null, true, false, 'lax');
    }
}
