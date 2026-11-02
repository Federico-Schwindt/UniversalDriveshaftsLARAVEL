<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Baseline response hardening. A full CSP is not set yet: several pages still rely on inline scripts. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!method_exists($response, 'header')) return $response;
        if (function_exists('header_remove')) @header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=(self "https://js.stripe.com")');
        if (!$response->headers->has('Content-Security-Policy')) $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        return $response;
    }
}
