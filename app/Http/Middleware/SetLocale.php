<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Storefront language: English, Spanish, French, Chinese (Simplified) or Japanese. Chosen with ?lang=, remembered in a cookie,
 * guessed from the browser on a first visit. The admin stays in English.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'es', 'fr', 'zh', 'ja'];
    /** Each language in its own words (the switcher, the e-mail language setting). */
    public const NAMES = ['en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'zh' => '简体中文', 'ja' => '日本語'];
    public const COOKIE = 'uds_lang';

    /** BCP 47 tag for <html lang> and hreflang: zh is Simplified Chinese, so browsers pick Simplified glyphs and fonts. */
    public static function tag(string $locale): string
    {
        return ['zh' => 'zh-Hans'][$locale] ?? $locale;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('admin', 'admin/*')) return $next($request);

        $asked = strtolower((string) $request->query('lang', ''));
        $locale = in_array($asked, self::SUPPORTED, true) ? $asked
            : (in_array($c = (string) $request->cookie(self::COOKIE), self::SUPPORTED, true) ? $c
            : ($request->getPreferredLanguage(self::SUPPORTED) ?: 'en'));

        App::setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        $response = $next($request);
        if ($asked !== '' && in_array($asked, self::SUPPORTED, true) && method_exists($response, 'withCookie')) {
            $response->withCookie(cookie()->forever(self::COOKIE, $locale, null, null, null, false));
        }
        return $response;
    }
}
