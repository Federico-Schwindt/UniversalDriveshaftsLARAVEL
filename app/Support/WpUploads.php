<?php

namespace App\Support;

/**
 * Local mirror of the WordPress uploads the catalog still references (public/uploads/wp/{yyyy}/{mm}/…).
 * The catalog rows keep their original https://universaldriveshafts.com/wp-content/uploads/… URLs as an id;
 * every page resolves them here, so nothing depends on the old site once a picture is mirrored
 * (catalog:localize-images downloads the missing ones, hourly after the live sync).
 */
class WpUploads
{
    public const DIR = 'uploads/wp';
    private const RX = '#^https?://(?:www\.)?universaldriveshafts\.com/wp-content/uploads/([^?\#\s"<>]+)$#i';

    /** "2024/04/x.jpg" for an old uploads URL, null for anything else. */
    public static function rel(?string $url): ?string
    {
        if (!$url || !preg_match(self::RX, trim($url), $m)) return null;
        $rel = rawurldecode($m[1]);
        return str_contains($rel, '..') || str_starts_with($rel, '/') ? null : $rel;
    }

    /** Absolute file path of the mirror copy (whether or not it exists yet). */
    public static function file(string $rel): string
    {
        return public_path(self::DIR . '/' . $rel);
    }

    /** "/uploads/wp/2024/04/x.jpg" (decoded, for MediaController) when mirrored, else null. */
    public static function localPath(?string $url): ?string
    {
        $rel = self::rel($url);
        return $rel !== null && is_file(self::file($rel)) ? '/' . self::DIR . '/' . $rel : null;
    }

    /** URL-safe local path when mirrored, otherwise the URL unchanged (for raw <img src> / feeds / e-mails). */
    public static function url(?string $url): string
    {
        $rel = self::rel($url);
        if ($rel === null || !is_file(self::file($rel))) return (string) $url;
        return '/' . self::DIR . '/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
    }
}
