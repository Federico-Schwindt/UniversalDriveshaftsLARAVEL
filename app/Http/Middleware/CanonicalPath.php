<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * One URL per page for crawlers: /index.php/anything is a duplicate of /anything, /anything/ too, and www.<site>
 * of <site> (Search Console, 2026-11-04: Google ranked http://www.universaldriveshafts.com/ apart from the https home page).
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
        $response = $next($request);
        // WordPress wrote every address with a closing slash; here /contact/ is /contact. The page is asked first: an
        // old address that moved or is gone answers with its own redirect or 404 and that answer stands (one hop, not
        // two), and only a page that would be served twice is sent to its one address.
        $path = $request->getPathInfo();
        if (strlen($path) > 1 && str_ends_with($path, '/') && in_array($request->method(), ['GET', 'HEAD'], true) && $response->isSuccessful()) {
            $qs = $request->getQueryString();
            return redirect()->away($request->getSchemeAndHttpHost() . (rtrim($path, '/') ?: '/') . ($qs ? '?' . $qs : ''), 301);
        }
        return $response;
    }
}
