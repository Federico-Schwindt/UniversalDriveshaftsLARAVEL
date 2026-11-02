<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Which language a customer's e-mail or text goes out in, and the translation of the
 * English the callers build ("Order :order confirmed — …", "Thanks, :name — …").
 *
 * The language is what the customer last used the site in: their most recent order's
 * locale, else their account's, else English. Mail to the shop itself is always English.
 *
 * Callers keep writing English. Text that arrives already assembled is matched against
 * the keys of lang/i18n/emails/es.json — keys with :placeholders become patterns — so
 * every Spanish string lives in that one file and anything unmatched simply stays English.
 */
class MailLocale
{
    private const FILE = 'i18n/emails/%s.json';

    /** Placeholders that are always one token (an order number, a SKU, a price, a link). */
    private const TOKENS = ['order', 'ref', 'sku', 'count', 'amount', 'n', 'pct', 'url', 'link', 'status', 'tracking_no'];

    private const DATE_ES = [
        'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo',
        'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio',
        'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
        'today' => 'hoy',
    ];
    private static array $patterns = [];

    public static function forRecipient(string|array|null $to): string
    {
        $email = strtolower(trim((string) (is_array($to) ? (array_values($to)[0] ?? '') : $to)));
        if ($email === '' || in_array($email, self::shopAddresses(), true)) return 'en';
        try {
            $loc = DB::table('orders')->where('email', $email)->whereNotNull('locale')->orderByDesc('id')->value('locale')
                ?: DB::table('users')->where('email', $email)->value('locale');
        } catch (\Throwable $e) {
            $loc = null;
        }
        return self::valid($loc);
    }

    public static function forPhone(?string $phone): string
    {
        if (!$phone) return 'en';
        try {
            $loc = DB::table('orders')->where('phone', $phone)->whereNotNull('locale')->orderByDesc('id')->value('locale')
                ?: DB::table('users')->where('phone', $phone)->value('locale');
        } catch (\Throwable $e) {
            $loc = null;
        }
        return self::valid($loc);
    }

    public static function valid(?string $loc): string
    {
        return in_array($loc, \App\Http\Middleware\SetLocale::SUPPORTED, true) ? $loc : 'en';
    }

    /** One string: an exact key first, then the :placeholder patterns. Unknown text stays as it is. */
    public static function tr(?string $s, string $loc): ?string
    {
        if ($s === null || trim($s) === '' || $loc === 'en') return $s;
        // words that mean something else on the storefront ("Cancel" the button vs "Cancel" the
        // request type) carry an @mail: prefix, so the shared translator never sees them as one
        $scoped = trans()->get('@mail:' . $s, [], $loc);
        if (is_string($scoped) && $scoped !== '@mail:' . $s) return $scoped;
        $exact = trans()->get($s, [], $loc);
        if (is_string($exact) && $exact !== $s) return $exact;
        foreach (self::patterns($loc) as [$re, $names, $target]) {
            if (!preg_match($re, $s, $m)) continue;
            $vals = [];
            foreach ($names as $i => $n) {
                $v = $m[$i + 1];
                // descriptive pieces are phrases of ours ("Monday to Friday, 3:00 to 5:00 pm") and get
                // translated; names, numbers and links never do
                if ($n === 'hours') { $t = trans()->get($v, [], $loc); $v = is_string($t) ? $t : $v; }
                elseif ($n === 'when' && $loc === 'es') $v = self::dateEs($v);
                $vals[$n] = $v;
            }
            return self::fill($target, $vals);
        }
        return $s;
    }

    /** The parts of a template's data that are words for the reader (not names, prices or links). */
    public static function data(array $data, string $loc): array
    {
        if ($loc === 'en') return $data;
        foreach (['title', 'preheader', 'kicker', 'intro', 'kvTitle', 'signoff'] as $k) {
            if (isset($data[$k]) && is_string($data[$k])) $data[$k] = self::tr($data[$k], $loc);
        }
        if (isset($data['hero']) && is_array($data['hero'])) {
            foreach (['kicker', 'title', 'text'] as $k) if (isset($data['hero'][$k]) && is_string($data['hero'][$k])) $data['hero'][$k] = self::tr($data['hero'][$k], $loc);
        }
        foreach (['paragraphs', 'after'] as $k) {
            if (!isset($data[$k]) || !is_array($data[$k])) continue;
            $data[$k] = array_map(fn ($p) => is_string($p) ? self::tr($p, $loc) : (is_array($p) && isset($p['html']) ? ['html' => self::tr($p['html'], $loc)] + $p : $p), $data[$k]);
        }
        if (isset($data['notice']) && is_array($data['notice'])) {
            foreach (['title', 'text', 'html'] as $k) if (isset($data['notice'][$k]) && is_string($data['notice'][$k])) $data['notice'][$k] = self::tr($data['notice'][$k], $loc);
        }
        if (isset($data['button'][0]) && is_string($data['button'][0])) $data['button'][0] = self::tr($data['button'][0], $loc);
        if (isset($data['kv']) && is_array($data['kv'])) {
            $kv = [];
            // labels always; values only on an exact match ("Damaged in shipping"), never names or notes
            foreach ($data['kv'] as $label => $v) {
                if (is_string($v)) $v = self::tr($v, $loc);
                $kv[self::tr((string) $label, $loc)] = $v;
            }
            $data['kv'] = $kv;
        }
        return $data;
    }

    /** "Tuesday, October 20 at 3:30 PM" → "martes 20 de octubre a las 3:30 p. m." */
    private static function dateEs(string $v): string
    {
        if (preg_match('/^(\w+), (\w+) (\d{1,2})(?: at (\d{1,2}:\d{2}) (AM|PM))?$/', trim($v), $m)) {
            $out = strtr($m[1], self::DATE_ES) . ' ' . $m[3] . ' de ' . strtr($m[2], self::DATE_ES);
            if (!empty($m[4])) $out .= ' a las ' . $m[4] . ($m[5] === 'AM' ? ' a. m.' : ' p. m.');
            return $out;
        }
        return strtr($v, self::DATE_ES);
    }

    private static function patterns(string $loc): array
    {
        if (isset(self::$patterns[$loc])) return self::$patterns[$loc];
        $file = lang_path(sprintf(self::FILE, $loc));
        $map = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $out = [];
        foreach ($map as $en => $target) {
            if (!preg_match_all('/:([a-z_]+)/', $en, $mm)) continue;
            $re = preg_quote($en, '/');
            foreach ($mm[1] as $n) $re = str_replace(preg_quote(':' . $n, '/'), in_array($n, self::TOKENS, true) ? '(\\S+?)' : '(.+?)', $re);
            $out[] = ['/^' . $re . '$/su', $mm[1], $target, strlen($en)];
        }
        // the longest (most specific) pattern wins
        usort($out, fn ($a, $b) => $b[3] <=> $a[3]);
        return self::$patterns[$loc] = $out;
    }

    private static function fill(string $target, array $vals): string
    {
        // "there" is the English stand-in for a missing first name ("Thanks, there") — drop it,
        // and the comma that went with it ("Gracias, — …" → "Gracias — …")
        $blank = isset($vals['name']) && strtolower(trim($vals['name'])) === 'there';
        if ($blank) $target = preg_replace('/,?\s*:name\b/u', '', $target);
        foreach ($vals as $n => $v) $target = str_replace(':' . $n, $v, $target);
        // "p. m." already ends the sentence; "el hoy" reads wrong
        $target = str_replace(['m.. ', 'm..'], ['m. ', 'm.'], $target);
        if (isset($vals['when']) && $vals['when'] === 'hoy') $target = str_replace('el hoy', 'hoy', $target);
        return $blank ? trim(preg_replace('/\s{2,}/u', ' ', $target)) : $target;
    }

    private static function shopAddresses(): array
    {
        return array_values(array_filter(array_map(fn ($a) => strtolower(trim((string) $a)), [
            config('uds.email'), config('uds.inventory_alert_email'), config('mail.from.address'),
            config('services.anthropic.inbox'), Settings::get('alerts.email'), Settings::get('store.email'),
        ])));
    }
}
