<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Bot filter for the anonymous contact / fitment forms (owner, 2026-11-14: "/admin/leads a lot of spam, fix that").
 * Two traps every real browser passes (a hidden field people never see, and a signed time the form was shown, at least
 * a few seconds before the submit) plus a score on what the bots send: XRumer names ("HenryWawQF"), random-letter
 * messages, Cyrillic, links, SEO / marketing pitches. A caught submission is kept as status "spam" (no e-mail, out of
 * the open list) so a false positive can still be found under Leads → Spam.
 */
class SpamGuard
{
    public const FIELD = 'website_url';   // the honeypot
    private const MIN_SECONDS = 3;

    /** Hidden inputs for the form: the honeypot and the signed render time. */
    public static function fields(): string
    {
        $t = now()->timestamp;
        return '<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">'
            . '<label>Leave this empty<input type="text" name="' . self::FIELD . '" value="" tabindex="-1" autocomplete="off"></label></div>'
            . '<input type="hidden" name="_ft" value="' . $t . '.' . self::sign($t) . '">';
    }

    private static function sign(int $t): string
    {
        return substr(hash_hmac('sha256', 'form-time:' . $t, (string) config('app.key')), 0, 16);
    }

    /** Why this submission is spam ('honeypot', 'too-fast', 'no-token', 'content:…'), or null for a person. */
    public static function reason(Request $r, string $name, string $message): ?string
    {
        if (trim((string) $r->input(self::FIELD, '')) !== '') return 'honeypot';
        $ft = (string) $r->input('_ft', '');
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{16})$/', $ft, $m) || !hash_equals(self::sign((int) $m[1]), $m[2])) return 'no-token';
        if (now()->timestamp - (int) $m[1] < self::MIN_SECONDS) return 'too-fast';
        $score = self::score($name, $message);
        return $score >= 3 ? 'content:' . $score : null;
    }

    /** What the leads table's spam looked like (2026-08 → 2026-11): 3 or more is spam. */
    public static function score(string $name, string $message): int
    {
        $s = 0;
        // XRumer names: a word with two capitals stuck on the end ("HenryWawQF", "RogerzeshyPP", "ivan-VipOO", "AustinRoxGM")
        if (preg_match('/\p{Ll}{2,}\p{Lu}{2,}\b/u', $name)) $s += 3;
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) === 2 && strcasecmp($words[0], $words[1]) === 0) $s += 1;
        if (preg_match('/\.(com|net|ru|xyz|info)(?![a-z])|https?:/', $name)) $s += 2;
        // a message that is one run of random mixed-case letters ("cnKntiTScFcCEWAvo")
        $msg = trim($message);
        if (preg_match('/^[A-Za-z]{12,}$/', $msg) && preg_match_all('/[A-Z]/', $msg) >= 3 && preg_match_all('/[a-z]/', $msg) >= 3) $s += 3;
        // the shop sells in English, Spanish, French, Chinese and Japanese: Cyrillic is a bot
        if (preg_match_all('/\p{Cyrillic}/u', $msg . ' ' . $name) >= 8) $s += 3;
        // links to anywhere but this site
        $links = preg_match_all('#(https?://|www\.)(?!(www\.)?(universaldriveshafts|udshafts|ushafts)\.com)#i', $msg);
        if ($links) $s += min(3, 1 + $links);
        // marketing pitches
        if (preg_match('/\b(seo|backlinks?|guest post|freelance writer|web ?design(er)?|rank(ing)? (on|in) google|more (leads|traffic|sales)|website owner|com owner|crypto|bitcoin|casino|viagra|loan offer|AI generates|nude)\b/iu', $msg)) $s += 3;
        return $s;
    }
}
