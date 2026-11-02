<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * JSON / fragment / asset endpoints must never land in a search index, even when a
 * crawler reaches them through a link. Belt and braces with the robots.txt disallows:
 * robots.txt stops crawling, X-Robots-Tag stops indexing of anything already fetched.
 */
class NoIndexApi
{
    /** Request paths (Laravel pattern syntax) that get X-Robots-Tag: noindex. HTML pages are never listed here. */
    public const PATHS = [
        'api/*', 'shop/quick-view/*', 'shop/alternates/*', 'dsb/*', 'geo', 'vehicles/models',
        'health', 'up', 'driveshaft-designer/svg/*', 'search', 'media/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (method_exists($response, 'header') && $request->is(...self::PATHS)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}
