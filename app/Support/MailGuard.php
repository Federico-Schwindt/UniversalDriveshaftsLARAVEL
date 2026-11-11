<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pre-launch safety net. Staging holds the real customers and orders imported from WooCommerce (customers:import-wp),
 * so outside production every e-mail and text goes only to the shop's own people: admin accounts, the shop inboxes and
 * MAIL_ALLOW / SMS_ALLOW. Anything else is dropped and logged (masked). Off by itself once APP_ENV=production.
 */
class MailGuard
{
    /** Set by Branded (opts 'requested') on mail the recipient asked for: verification codes. */
    public const REQUESTED_HEADER = 'X-UDS-Requested-By-Recipient';

    public static function on(): bool
    {
        return (bool) config('uds.mail_guard');
    }

    public static function allowsEmail(string $email): bool
    {
        if (!self::on()) return true;
        static $allow = null;
        $allow ??= collect(DB::table('users')->where('is_admin', 1)->pluck('email'))
            ->merge([config('uds.email'), config('uds.inventory_alert_email'), config('mail.from.address')])
            ->merge(explode(',', (string) config('uds.mail_allow')))
            ->map(fn ($e) => strtolower(trim((string) $e)))->filter()->unique()->all();
        return in_array(strtolower(trim($email)), $allow, true);
    }

    public static function allowsPhone(string $phone): bool
    {
        if (!self::on()) return true;
        $digits = fn ($p) => substr(preg_replace('/\D/', '', (string) $p), -10);
        $allow = collect(DB::table('users')->where('is_admin', 1)->whereNotNull('phone')->pluck('phone'))
            ->merge(explode(',', (string) config('uds.sms_allow')))
            ->map($digits)->filter(fn ($p) => strlen($p) === 10)->all();
        return in_array($digits($phone), $allow, true);
    }

    /** MessageSending listener: strips recipients outside the list; false cancels a message with nobody left. */
    public static function filter(MessageSending $event): ?bool
    {
        $msg = $event->message;
        // a verification code goes only to the address the visitor typed, because they asked for it: never held back
        // (owner, 2026-11-11: "im not receiving the otp code when creating an account on ushafts.com")
        $requested = $msg->getHeaders()->has(self::REQUESTED_HEADER);
        $msg->getHeaders()->remove(self::REQUESTED_HEADER);
        if (!self::on() || $requested) return null;
        $dropped = [];
        foreach (['To', 'Cc', 'Bcc'] as $field) {
            $all = $msg->{'get' . $field}();
            $keep = array_values(array_filter($all, fn ($a) => self::allowsEmail($a->getAddress())));
            if (count($keep) === count($all)) continue;
            foreach ($all as $a) if (!in_array($a, $keep, true)) $dropped[] = self::mask($a->getAddress());
            if ($keep) $msg->{strtolower($field)}(...$keep);
            else $msg->getHeaders()->remove($field);
        }
        if ($dropped) Log::info('Mail guard (pre-launch): not sent to ' . implode(', ', $dropped) . ' · "' . mb_substr((string) $msg->getSubject(), 0, 80) . '"');
        return $msg->getTo() ? null : false;
    }

    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 1) . '***@' . $d;
    }
}
