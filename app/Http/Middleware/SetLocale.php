<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Storefront language: English, Spanish or French. Chosen with ?lang=, remembered in a cookie,
 * guessed from the browser on a first visit. The admin stays in English.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'es', 'fr'];
    public const COOKIE = 'uds_lang';

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
