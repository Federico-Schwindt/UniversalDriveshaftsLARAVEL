<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Six-digit e-mail codes. Used to prove a mailbox is real before an account exists (registration),
 * before an account's e-mail changes, and to confirm an older unverified account.
 *
 * The code is stored hashed, per purpose and address, for 10 minutes from when it was sent (wrong tries
 * never extend it); 5 wrong tries burn it, a new one can be requested after 45 seconds, one address gets
 * at most 6 codes an hour and 12 a day per purpose, and 3 burned codes in a day pause that purpose for 24 h.
 */
class EmailOtp
{
    public const TTL_MIN = 10;
    public const MAX_TRIES = 5;
    public const COOLDOWN_S = 45;
    public const MAX_PER_HOUR = 6;
    public const MAX_PER_DAY = 12;     // per address and purpose
    public const MAX_BURNED = 3;       // burned codes in 24 h before the address is locked for 24 h

    private static function key(string $purpose, string $email): string
    {
        return 'otp:' . $purpose . ':' . sha1(strtolower(trim($email)));
    }

    private static function lockKey(string $purpose, string $email): string
    {
        return 'otp-lock:' . $purpose . ':' . sha1(strtolower(trim($email)));
    }

    private static function lockedMsg(): string
    {
        return __('Too many wrong codes. For your security, codes to this address are paused for 24 hours. Call (813) 524-6491 if you need help.');
    }

    /** Seconds until another code may be sent (0 = now). */
    public static function cooldown(string $purpose, string $email): int
    {
        $s = Cache::get(self::key($purpose, $email));
        return $s ? max(0, self::COOLDOWN_S - (now()->getTimestamp() - $s['sent'])) : 0;
    }

    /** Send a fresh code. Returns null on success, or a user-facing reason it was not sent. */
    public static function send(string $purpose, string $email, ?string $locale = null): ?string
    {
        $email = strtolower(trim($email));
        if (Cache::has(self::lockKey($purpose, $email))) return self::lockedMsg();
        if ($wait = self::cooldown($purpose, $email)) return __('Please wait :s seconds before asking for another code.', ['s' => $wait]);
        $dayKey = 'otp-day:' . $purpose . ':' . sha1($email);
        if ((int) Cache::get($dayKey, 0) >= self::MAX_PER_DAY) return __('Too many codes were sent to this address today. Try again tomorrow.');
        $hourKey = 'otp-hour:' . sha1($email);
        if ((int) Cache::get($hourKey, 0) >= self::MAX_PER_HOUR) return __('Too many codes were sent to this address. Try again in an hour.');
        Cache::add($hourKey, 0, now()->addHour());
        Cache::increment($hourKey);
        Cache::add($dayKey, 0, now()->addDay());
        Cache::increment($dayKey);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // exp travels with the code: check() re-stores it after a wrong try without resetting the clock
        Cache::put(self::key($purpose, $email), ['hash' => hash('sha256', $code . config('app.key')), 'tries' => 0, 'sent' => now()->getTimestamp(), 'exp' => now()->getTimestamp() + self::TTL_MIN * 60], now()->addMinutes(self::TTL_MIN));

        $intro = [
            'register' => 'Enter this code to finish creating your Universal Driveshafts account.',
            'email' => 'Enter this code to confirm the new e-mail address on your Universal Driveshafts account.',
            'verify' => 'Enter this code to confirm the e-mail address on your Universal Driveshafts account.',
            'reauth' => 'Enter this code to confirm it is you before changing the sign-in details of your Universal Driveshafts account.',
        ][$purpose] ?? 'Enter this code to continue.';
        if ($locale && $locale !== 'en') $intro = __($intro, [], $locale);   // newer intros live in lang/i18n/account; Mailer leaves translated text alone
        $digits = implode('', array_map(fn ($d) => '<span style="display:inline-block;width:38px;margin:0 3px;padding:10px 0;border:1px solid #d4dae6;border-radius:8px;background:#f6f8fc;font:700 26px/1 Menlo,Consolas,monospace;color:#0b2455;text-align:center;">' . $d . '</span>', str_split($code)));
        try {
            Mailer::simple($email, 'Your verification code: ' . $code, [
                'kicker' => 'Verification', 'title' => 'Your verification code', 'intro' => $intro,
                'preheader' => 'Your code is ' . $code . '. It expires in 10 minutes.',   // matched by the ':code' patterns in lang/i18n/emails
                'html' => '<div style="margin:6px 0 18px;text-align:center;white-space:nowrap;">' . $digits . '</div>',
                'after' => ['The code expires in 10 minutes. Never share it — Universal Driveshafts will never ask you for it by phone.', 'If you did not request this, you can ignore this e-mail.'],
                'signoff' => false,
            ], $locale ? ['locale' => $locale] : []);
        } catch (\Throwable $e) {
            report($e);
            Cache::forget(self::key($purpose, $email));
            return __('We could not send the code right now. Please try again in a minute.');
        }
        return null;
    }

    /** Check a code. Returns null when it matches (and burns it), or the reason it did not. */
    public static function check(string $purpose, string $email, string $code): ?string
    {
        $k = self::key($purpose, $email);
        if (Cache::has(self::lockKey($purpose, $email))) return self::lockedMsg();
        $s = Cache::get($k);
        $left = $s ? ($s['exp'] ?? ($s['sent'] + self::TTL_MIN * 60)) - now()->getTimestamp() : 0;
        if (!$s || $left <= 0) { Cache::forget($k); return __('That code has expired. Ask for a new one.'); }
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) === 6 && hash_equals($s['hash'], hash('sha256', $code . config('app.key')))) { Cache::forget($k); return null; }
        $s['tries']++;
        if ($s['tries'] >= self::MAX_TRIES) {
            Cache::forget($k);
            // a burned code counts toward the 24 h lock: 3 x 5 guesses is as far as anyone gets
            $burnKey = 'otp-burn:' . $purpose . ':' . sha1(strtolower(trim($email)));
            Cache::add($burnKey, 0, now()->addDay());
            if (Cache::increment($burnKey) >= self::MAX_BURNED) { Cache::put(self::lockKey($purpose, $email), 1, now()->addDay()); Cache::forget($burnKey); return self::lockedMsg(); }
            return __('Too many wrong codes. Ask for a new one.');
        }
        Cache::put($k, $s, $left);   // the original expiry, not a fresh 10 minutes
        return trans_choice('That code is not right. :n try left.|That code is not right. :n tries left.', self::MAX_TRIES - $s['tries'], ['n' => self::MAX_TRIES - $s['tries']]);
    }

    /** Mask an address for display: carlos@gmail.com -> c•••s@gmail.com */
    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return (mb_strlen($u) <= 2 ? mb_substr($u, 0, 1) . '•' : mb_substr($u, 0, 1) . '•••' . mb_substr($u, -1)) . '@' . $d;
    }
}
