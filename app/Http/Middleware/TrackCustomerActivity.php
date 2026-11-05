<?php

namespace App\Http\Middleware;

use App\Support\CustomerActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs what signed-in customers do (pages, form posts, errors) for the admin chat page. Written after the
 * response is sent (terminate), never for staff, never request bodies.
 */
class TrackCustomerActivity
{
    /** Requests that are plumbing, not something the customer did. */
    private const SKIP = ['assistant/*', 'admin/*', 'media/*', 'avatar/*', 'api/search', 'api/catalog/*', 'geo', 'health', 'up', 'cart/count', 'sanctum/*', 'shop/alternates/*'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $u = $request->user();
        if (!$u || $u->is_admin || $request->is(...self::SKIP)) return;
        $status = $response->getStatusCode();
        $get = $request->isMethod('GET') || $request->isMethod('HEAD');
        $html = str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        $kind = $status >= 400 ? 'error' : ($get ? ($html ? 'page' : null) : 'action');
        if (!$kind) return;   // JSON lookups, images, feeds
        $path = '/' . ltrim($request->path(), '/') . ($request->getQueryString() ? '?' . $request->getQueryString() : '');
        CustomerActivity::record((int) $u->id, $kind, $path, [
            'method' => $request->method(), 'status' => $status, 'ip' => $request->ip(), 'ua' => (string) $request->userAgent(),
            'ms' => defined('LARAVEL_START') ? (int) round((microtime(true) - LARAVEL_START) * 1000) : null,
            'detail' => $kind === 'error' && $status >= 500 ? 'Server error' : null,
            'title' => $kind === 'page' && is_string($c = $response->getContent()) && preg_match('#<title>(.*?)</title>#is', substr($c, 0, 8000), $m) ? html_entity_decode(trim($m[1])) : null,
        ]);
    }
}
