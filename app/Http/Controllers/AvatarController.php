<?php

namespace App\Http\Controllers;

use App\Support\Avatar;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    /** GET /avatar/{style}/{seed} — a DiceBear picture served from our own cache. */
    public function show(string $style, string $seed)
    {
        $kept = is_file(storage_path('app/avatars/' . $style . '/' . $seed . '.svg')) || Avatar::keeps($seed);
        $svg = Avatar::svg($style, $seed, $kept);
        if ($svg === null) {
            // DiceBear unreachable or a bad style/seed: a neutral circle rather than a broken image
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#dbe3ee"/><circle cx="20" cy="16" r="7" fill="#9aa8bb"/><path d="M6 38c2-8 8-11 14-11s12 3 14 11z" fill="#9aa8bb"/></svg>';
            return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=300']);
        }
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            // a picker preview (not on disk) may be re-fetched; a kept seed never changes
            'Cache-Control' => $kept ? 'public, max-age=31536000, immutable' : 'private, max-age=600',
            // opened directly, the SVG still can't run anything
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
