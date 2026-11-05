<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;

/**
 * Old URLs keep their rankings: when a GET would answer 404, a matching row in seo_redirects
 * (imported from Rank Math / Redirection / ThinkRank, or added in /admin/seo) sends it on with a
 * 301 (or answers 410 Gone). Real pages always win; anything still unmatched lands in the 404 log.
 */
class SeoRedirects
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 404 || !in_array($request->method(), ['GET', 'HEAD'], true)) return $response;

        $hit = Seo::redirectFor($request->getPathInfo());
        if (!$hit) {
            if ($request->method() === 'GET' && !str_starts_with((string) $request->userAgent(), 'UDS seo')) Seo::log404($request->getPathInfo(), $request->headers->get('referer'));
            return $response;
        }
        Seo::countHit($hit['id']);
        if ($hit['code'] === 410 || !$hit['target']) return response(view('errors.404')->render(), 410);

        $target = $hit['target'];
        if (!preg_match('#^https?://#i', $target)) $target = '/' . ltrim($target, '/');
        if (($qs = $request->getQueryString()) && !str_contains($target, '?')) $target .= '?' . $qs;
        $code = in_array($hit['code'], [301, 302, 307, 308], true) ? $hit['code'] : 301;
        return $target[0] === '/' ? redirect()->to($target, $code) : redirect()->away($target, $code);
    }
}
