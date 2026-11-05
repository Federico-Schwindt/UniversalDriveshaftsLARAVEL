<?php

namespace App\Http\Middleware;

use App\Http\Controllers\MediaController;
use Closure;
use Illuminate\Http\Request;

/**
 * Photos under /img/pti/ and /img/driveshafts/ are rewritten in place (pti:dewatermark) but served with a
 * one-year immutable cache, so a browser that saw the old file would never ask again. Every page and JSON
 * response gets ?v={modification time} appended to those URLs, the same version MediaController::url() adds.
 */
class VersionEditedImages
{
    private const RX = '#((?:\\\\?/)img\\\\?/(?:pti|driveshafts)\\\\?/[^\s"\'<>()?\\\\]+(?:\\\\/[^\s"\'<>()?\\\\]+)?\.(?:jpe?g|png|webp|gif))(?![\w?])#i';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $type = (string) $response->headers->get('Content-Type');
        if (!str_contains($type, 'html') && !str_contains($type, 'json')) return $response;
        if (method_exists($response, 'getFile') || !method_exists($response, 'setContent')) return $response;
        $body = $response->getContent();
        if (!is_string($body) || $body === '' || strlen($body) > 8_000_000 || !preg_match('#img\\\\?/(?:pti|driveshafts)#', $body)) return $response;
        $out = preg_replace_callback(self::RX, function ($m) {
            $v = MediaController::version(str_replace('\\/', '/', $m[1]));
            return $v === '' ? $m[1] : $m[1] . '?v=' . $v;
        }, $body);
        if (is_string($out)) $response->setContent($out);
        return $response;
    }
}
