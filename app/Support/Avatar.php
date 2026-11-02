<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Profile pictures from DiceBear (dicebear.com, CC-licensed avatar styles). Each account keeps a style and a
 * random seed; the picture is fetched once from api.dicebear.com and served from our own disk by
 * /avatar/{style}/{seed}, so pages never call a third party and the seed never contains personal data.
 * Only seeds an account (or the assistant) uses are written to disk; picker previews are proxied.
 */
class Avatar
{
    /** style => label shown in the picker */
    public const STYLES = [
        'adventurer' => 'Adventurer', 'adventurer-neutral' => 'Adventurer (neutral)', 'avataaars' => 'Avataaars',
        'big-smile' => 'Big smile', 'bottts' => 'Robot', 'bottts-neutral' => 'Robot (neutral)', 'croodles' => 'Croodles',
        'fun-emoji' => 'Fun emoji', 'lorelei' => 'Lorelei', 'micah' => 'Micah', 'miniavs' => 'Miniavs',
        'notionists' => 'Notionists', 'open-peeps' => 'Open peeps', 'personas' => 'Personas', 'pixel-art' => 'Pixel art',
        'thumbs' => 'Thumbs', 'shapes' => 'Shapes', 'identicon' => 'Identicon',
    ];

    public const DEFAULT_STYLE = 'notionists';
    public const BOT_STYLE = 'bottts';
    public const BOT_SEED = 'Flange';   // yellow robot, on the brand blue

    private const BG = 'b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf,dbeafe';

    public static function validStyle(?string $style): bool
    {
        return is_string($style) && array_key_exists($style, self::STYLES);
    }

    public static function validSeed(?string $seed): bool
    {
        return is_string($seed) && (bool) preg_match('/^[A-Za-z0-9-]{1,40}$/', $seed);
    }

    public static function newSeed(): string
    {
        return Str::lower(Str::random(10));
    }

    public static function path(string $style, string $seed): string
    {
        return '/avatar/' . $style . '/' . $seed;
    }

    /** The account's picture; a seed is given (and stored) the first time it is needed. */
    public static function url(?object $user): string
    {
        if (!$user) return self::path(self::DEFAULT_STYLE, 'guest');
        $style = self::validStyle($user->avatar_style ?? null) ? $user->avatar_style : self::DEFAULT_STYLE;
        $seed = $user->avatar_seed ?? null;
        if (!self::validSeed($seed)) {
            $seed = self::newSeed();
            if (!empty($user->id)) DB::table('users')->where('id', $user->id)->update(['avatar_seed' => $seed]);
            $user->avatar_seed = $seed;
        }
        return self::path($style, $seed);
    }

    public static function botUrl(): string
    {
        return self::path(self::BOT_STYLE, self::BOT_SEED);
    }

    /**
     * Seeds worth a file on disk: an account's own, the assistant's and the guest picture. Anything else
     * (picker shuffles, made-up URLs) is fetched and served but never written, so the URL can't fill the disk.
     */
    public static function keeps(string $seed): bool
    {
        if ($seed === self::BOT_SEED || $seed === 'guest') return true;
        try { return DB::table('users')->where('avatar_seed', $seed)->exists(); } catch (\Throwable $e) { return false; }
    }

    /** SVG markup for a style + seed; kept on disk for good when keeps() says so. Null when DiceBear can't be reached. */
    public static function svg(string $style, string $seed, ?bool $keep = null): ?string
    {
        if (!self::validStyle($style) || !self::validSeed($seed)) return null;
        $file = storage_path('app/avatars/' . $style . '/' . $seed . '.svg');
        if (is_file($file)) return file_get_contents($file);
        $keep ??= self::keeps($seed);
        try {
            $bg = $style === self::BOT_STYLE && $seed === self::BOT_SEED ? '1246ab' : self::BG;
            $r = Http::timeout(8)->get('https://api.dicebear.com/9.x/' . $style . '/svg', ['seed' => $seed, 'backgroundColor' => $bg, 'backgroundType' => 'solid']);
        } catch (\Throwable $e) {
            return null;
        }
        $svg = $r->successful() ? $r->body() : '';
        // DiceBear returns plain vector art; refuse anything that could run in a browser
        if (!str_starts_with(ltrim($svg), '<svg') || preg_match('/<script|<foreignObject|\son[a-z]+\s*=|javascript:/i', $svg)) return null;
        if ($keep) {
            @mkdir(dirname($file), 0775, true);
            file_put_contents($file, $svg);
        }
        return $svg;
    }
}
