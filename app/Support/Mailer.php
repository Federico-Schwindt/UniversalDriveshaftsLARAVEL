<?php

namespace App\Support;

use App\Mail\Branded;
use Illuminate\Support\Facades\Mail;

/** One-liners for the branded templates. $opts: replyTo [email,name], attachments [[name,data,mime]], from. */
class Mailer
{
    /** $opts['locale'] forces a language; otherwise the recipient's own (MailLocale). */
    public static function send(string|array $to, string $subject, string $view, array $data = [], array $opts = []): void
    {
        $loc = MailLocale::valid($opts['locale'] ?? MailLocale::forRecipient($to));
        $data += ['title' => $subject];
        if ($loc !== 'en') {
            $subject = MailLocale::tr($subject, $loc);
            $data = MailLocale::data($data, $loc);
            if (($data['title'] ?? null) === null || $data['title'] === '') $data['title'] = $subject;
        }
        Mail::to($to)->send((new Branded($subject, $view, $data, $opts))->locale($loc));
    }

    /** Generic branded message: hero title + paragraphs (+ kv table, notice, button, pre block). */
    public static function simple(string|array $to, string $subject, array $c, array $opts = []): void
    {
        $data = ['hero' => ['kicker' => $c['kicker'] ?? null, 'title' => $c['title'] ?? $subject, 'text' => $c['intro'] ?? null], 'preheader' => $c['preheader'] ?? ($c['intro'] ?? '')]
            + array_intersect_key($c, array_flip(['paragraphs', 'notice', 'kv', 'kvTitle', 'button', 'html', 'pre', 'after', 'signoff']));
        self::send($to, $subject, 'emails.generic', $data, $opts);
    }

    /** Render a template to HTML (previews, Brevo sends). */
    public static function render(string $view, array $data): string
    {
        return view($view, $data)->render();
    }
}
