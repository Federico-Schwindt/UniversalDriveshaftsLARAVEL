<?php

namespace App\Http\Middleware;

use App\Support\LegacyUrls;
use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Old URLs keep their rankings: when a GET would answer 404, a matching row in seo_redirects
 * (imported from Rank Math / Redirection / ThinkRank, or added in /admin/seo) sends it on with a
 * 301 (or answers 410 Gone); with no row, LegacyUrls knows the shapes of the old WordPress store
 * (former product slugs, paging, account and blog pages). Real pages always win; anything still
 * unmatched lands in the 404 log. A redirect carries the visitor's campaign parameters and no others.
 */
class SeoRedirects
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) return $response;
        // a page that moved (a legacy route's 301) keeps the ad click or campaign that brought the visitor
        if (in_array($response->getStatusCode(), [301, 308], true)) return $this->carried($request, $response);
        if ($response->getStatusCode() !== 404) return $response;

        $hit = Seo::redirectFor($request->getPathInfo());
        if (!$hit) {
            if ($old = LegacyUrls::resolve($request->getPathInfo())) {
                return $old[0] === null ? response(view('errors.404')->render(), 410) : redirect()->to(LegacyUrls::carry($request, $old[0]), 301);
            }
            if ($request->method() === 'GET' && !str_starts_with((string) $request->userAgent(), 'UDS seo')) Seo::log404($request->getPathInfo(), $request->headers->get('referer'));
            return $response;
        }
        Seo::countHit($hit['id']);
        if ($hit['code'] === 410 || !$hit['target']) return response(view('errors.404')->render(), 410);

        $target = $hit['target'];
        if (!preg_match('#^https?://#i', $target)) $target = '/' . ltrim($target, '/');
        $target = LegacyUrls::carry($request, $target);
        $code = in_array($hit['code'], [301, 302, 307, 308], true) ? $hit['code'] : 301;
        return $target[0] === '/' ? redirect()->to($target, $code) : redirect()->away($target, $code);
    }

    /** The same redirect with the campaign parameters on its target, when it stays on this site. */
    private function carried(Request $request, $response)
    {
        $to = (string) $response->headers->get('Location');
        if ($to === '' || !($to[0] === '/' || str_starts_with($to, $request->getSchemeAndHttpHost() . '/') || $to === $request->getSchemeAndHttpHost())) return $response;
        if (($with = LegacyUrls::carry($request, $to)) === $to) return $response;
        $response instanceof RedirectResponse ? $response->setTargetUrl($with) : $response->headers->set('Location', $with);
        return $response;
    }
}
