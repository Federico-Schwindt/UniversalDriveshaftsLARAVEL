<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Local image cache for the catalog pictures that still live on the WordPress site.
 * /media/{w}/{key}.webp  → fetched once, resized to {w}px wide, written as WebP under public/media
 * so every later hit is a static file served by nginx. Only universaldriveshafts.com uploads are proxied.
 */
class MediaController extends Controller
{
    public const WIDTHS = [160, 320, 640, 1200];
    private const HOST = 'universaldriveshafts.com';

    /** Public URL for a remote catalog image at a given width (unknown or local sources are returned as-is). */
    /** Local folders that may also be served resized (finder photos and make logos are large PNGs). */
    private const LOCAL = ['/img/driveshafts/', '/img/makes/', '/uploads/', '/img/pti/'];

    public static function url(?string $src, int $w = 640): string
    {
        $src = (string) $src;
        // WordPress uploads that are mirrored locally are served from the copy, never from the old site
        $src = \App\Support\WpUploads::localPath($src) ?? $src;
        $local = $src !== '' && $src[0] === '/' && array_filter(self::LOCAL, fn ($d) => str_starts_with($src, $d)) && preg_match('/\.(png|jpe?g|webp|avif)$/i', $src);
        if ($src === '' || (!$local && !preg_match('#^https?://(www\.)?' . preg_quote(self::HOST, '#') . '/wp-content/uploads/#i', $src))) return $src;
        if (!in_array($w, self::WIDTHS, true)) $w = 640;
        $key = rtrim(strtr(base64_encode($src), '+/', '-_'), '=');
        // the .webp is cached a year as immutable: the source file's version is in the URL, so a photo edited
        // in place (pti:dewatermark, a new upload under the same name) gets a new URL and every browser refetches it
        return "/media/$w/$key.webp" . ($local ? '?v=' . self::version($src) : '');
    }

    /** Short version tag of a local public file (its modification time), '' when it does not exist. */
    public static function version(string $path): string
    {
        static $memo = [];
        $path = rawurldecode((string) parse_url($path, PHP_URL_PATH));
        return $memo[$path] ??= (($t = @filemtime(public_path(ltrim($path, '/')))) ? base_convert((string) $t, 10, 36) : '');
    }

    public function show(int $w, string $key)
    {
        abort_unless(in_array($w, self::WIDTHS, true) && preg_match('/^[A-Za-z0-9_-]{8,700}$/', $key), 404);
        $src = base64_decode(strtr($key, '-_', '+/'), true);
        if ($src) $src = \App\Support\WpUploads::localPath($src) ?? $src;   // keys minted before the mirror existed
        $local =$src && $src[0] === '/' && !str_contains($src, '..') && array_filter(self::LOCAL, fn ($d) => str_starts_with($src, $d)) && is_file(public_path($src));
        abort_unless($src && ($local || preg_match('#^https?://(www\.)?' . preg_quote(self::HOST, '#') . '/wp-content/uploads/[^\s"<>]+$#i', $src)), 404);

        $dir = public_path("media/$w"); $file = "$dir/$key.webp";
        if (!is_file($file)) {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            try {
                if ($local) {
                    $blob = file_get_contents(public_path($src));
                } else {
                    $resp = Http::withOptions(\App\Support\LiveOrigin::httpOptions())->timeout(15)->withHeaders(['User-Agent' => 'UDS media cache'])->get($src);
                    if (!$resp->successful()) abort(404);
                    $blob = $resp->body();
                }
                if (strlen($blob) < 100 || strlen($blob) > 25 * 1024 * 1024) abort(404);
                $img = new \Imagick();
                $img->readImageBlob($blob);
                $img->setIteratorIndex(0);
                $img = $img->coalesceImages()->current();
                $img->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                if ($img->getImageWidth() > $w) $img->resizeImage($w, 0, \Imagick::FILTER_LANCZOS, 1);
                $img->setImageFormat('webp');
                $img->setOption('webp:method', '6');
                $img->setImageCompressionQuality(80);
                $img->stripImage();
                $tmp = "$file." . getmypid() . '.tmp';
                $img->writeImage($tmp);
                @rename($tmp, $file);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('media cache failed for ' . $src . ': ' . $e->getMessage());
                return $local ? response()->file(public_path($src)) : redirect()->away($src, 302);   // degrade to the original picture
            }
        }
        return response()->file($file, ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
