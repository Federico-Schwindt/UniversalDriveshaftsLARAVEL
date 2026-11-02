<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** /index.php/anything serves a duplicate of /anything: send crawlers to the one URL. */
class CanonicalPath
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && str_contains($request->getBaseUrl(), 'index.php')) {
            $qs = $request->getQueryString();
            return redirect()->away($request->getSchemeAndHttpHost() . $request->getPathInfo() . ($qs ? '?' . $qs : ''), 301);
        }
        return $next($request);
    }
}
