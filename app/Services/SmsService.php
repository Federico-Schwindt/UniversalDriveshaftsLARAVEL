<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * The site's own switch for texts (Settings → Notifications). Owner, 2026-11-14: "disable sms on the website dont show
     * the sms checkboxes ... only notifications via mail" — off by default: nothing is texted and no SMS option is shown.
     */
    public static function on(): bool
    {
        return (bool) \App\Support\Settings::get('notify.sms', false);
    }

    public function enabled(): bool
    {
        return self::on() && (bool) config('services.twilio.enabled') && config('services.twilio.sid') && config('services.twilio.token') && config('services.twilio.from');
    }

    /** Send an SMS. No-ops (returns false) unless fully configured + enabled. */
    public function send(string $to, string $body): bool
    {
        // in the language the customer used the site in; callers write English
        $body = (string) \App\Support\MailLocale::tr($body, \App\Support\MailLocale::forPhone($to));
        if (!$this->enabled()) {
            // Twilio is off on this environment: keep the call site working and leave a trace
            Log::info('SMS (disabled, not sent) to ' . self::mask($to) . ' (' . mb_strlen($body) . ' chars)');   // no full number / body in logs
            return false;
        }
        $to = self::e164($to);
        if (!$to) { Log::warning('SMS skipped: no usable phone number'); return false; }
        if (!\App\Support\MailGuard::allowsPhone($to)) { Log::info('SMS guard (pre-launch): not sent to ' . self::mask($to)); return false; }
        try {
            $sid = config('services.twilio.sid');
            $resp = Http::asForm()->withBasicAuth($sid, config('services.twilio.token'))->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => config('services.twilio.from'),
                    'To' => $to,
                    'Body' => $body,
                ]);
            // Twilio answers a queued message with 201 Created — ok() only accepts 200, so every
            // successful text used to be logged as a failure and reported as not sent
            if (!$resp->successful()) { Log::warning('Twilio send failed to ' . self::mask($to) . ': HTTP ' . $resp->status() . ' ' . ($resp->json('code') ?? '') . ' ' . mb_substr((string) $resp->json('message'), 0, 160)); return false; }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Twilio exception (to ' . self::mask($to) . '): ' . $e->getMessage());
            return false;
        }
    }

    /** US E.164 normalisation: 10 digits, 11 starting with 1, or an explicit +country number; anything else is unusable (null). */
    public static function e164(string $phone): ?string
    {
        $d = preg_replace('/[^0-9]/', '', $phone);
        if ($d === '') return null;
        if (str_starts_with(trim($phone), '+')) return strlen($d) >= 8 && strlen($d) <= 15 ? '+' . $d : null;
        if (strlen($d) === 10) return '+1' . $d;
        if (strlen($d) === 11 && $d[0] === '1') return '+' . $d;
        return null;
    }

    /** Area codes inside +1 that are other countries (Caribbean, Bermuda): a classic target for SMS pumping. */
    private const NANP_ABROAD = ['242', '246', '264', '268', '284', '345', '441', '473', '649', '658', '664', '721', '758', '767', '784', '809', '829', '849', '868', '869', '876'];

    /** A U.S. (territories included) or Canadian number: texts nobody has paid for go only to these. */
    public static function domestic(string $phone): bool
    {
        $to = self::e164($phone);
        return $to !== null && preg_match('/^\+1(\d{3})\d{7}$/', $to, $m) === 1 && !in_array($m[1], self::NANP_ABROAD, true);
    }

    /** "…6491" for logs. */
    public static function mask(string $phone): string
    {
        $d = preg_replace('/[^0-9]/', '', $phone);
        return $d === '' ? '(none)' : '…' . substr($d, -4);
    }
}
