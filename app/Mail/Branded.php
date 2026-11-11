<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Any branded HTML e-mail: a view that extends emails.layout, plus an auto-generated text part. */
class Branded extends Mailable
{
    public function __construct(public string $subjectLine, public string $tpl, public array $data = [], public array $opts = []) {}

    public function build()
    {
        $m = $this->subject($this->subjectLine)->view($this->tpl, $this->data);
        $html = view($this->tpl, $this->data)->render();
        $m->text('emails.text', ['body' => self::textFromHtml($html)]);
        if (!empty($this->opts['replyTo'])) $m->replyTo($this->opts['replyTo'][0], $this->opts['replyTo'][1] ?? null);
        if (!empty($this->opts['from'])) $m->from($this->opts['from'][0], $this->opts['from'][1] ?? null);
        foreach ($this->opts['attachments'] ?? [] as $a) $m->attachData($a['data'], $a['name'], ['mime' => $a['mime'] ?? 'application/octet-stream']);
        // a message the recipient asked for themselves (a verification code): the pre-launch MailGuard lets it through
        if (!empty($this->opts['requested'])) $m->withSymfonyMessage(fn ($msg) => $msg->getHeaders()->addTextHeader(\App\Support\MailGuard::REQUESTED_HEADER, '1'));
        return $m;
    }

    public static function textFromHtml(string $html): string
    {
        $html = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#si', '', $html);
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#si', '$2 ($1)', $html);
        $html = preg_replace('#</(p|div|tr|li|h1|h2|h3|table|ol|ul)>#i', "\n", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
