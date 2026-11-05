<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * One URL per page for crawlers: /index.php/anything is a duplicate of /anything, and www.<site> of <site>
 * (Search Console, 2026-11-04: Google ranked http://www.universaldriveshafts.com/ apart from the https home page).
 */
class CanonicalPath
{
    public function handle(Request $request, Closure $next)
    {
        $canon = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($canon !== '' && strcasecmp($request->getHost(), 'www.' . $canon) === 0) {
            return redirect()->away(rtrim((string) config('app.url'), '/') . $request->getRequestUri(), 301);
        }
        if ($request->isMethod('GET') && str_contains($request->getBaseUrl(), 'index.php')) {
            $qs = $request->getQueryString();
            return redirect()->away($request->getSchemeAndHttpHost() . $request->getPathInfo() . ($qs ? '?' . $qs : ''), 301);
        }
        return $next($request);
    }
}
