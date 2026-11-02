<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Driveshaft Designer: a port of the supplier's (Powertrain Industries) designer tool,
 * reproduced with their permission. Pick a style (one or two piece), then a front,
 * (middle,) rear section; the composed drawing comes back as an SVG whose dimension
 * labels can be clicked, filled in and printed.
 *
 * The section catalog and section drawings are self-hosted (resources/designer-sections.json,
 * public/img/designer/sections). The composed drawing for a given combination is fetched once
 * from the supplier's builder and cached on disk forever after.
 */
class DesignerController extends Controller
{
    private const BUILDER = 'https://cdn.powertrainindustries.com/driveshaft-builder/';
    private const STYLES = [1, 2]; // one piece and two piece only

    /** Strings designer.js shows, translated for the page into window.UDS_T. */
    public const JS_STRINGS = [
        'Overall length, front end to rear end', 'Front end to center bearing', 'Center bearing to rear end',
        'Front shaft, end to joint center', 'Rear shaft, joint center to rear end', 'Front tube length, weld to weld',
        'Rear tube length, weld to weld', 'Front tube outside diameter', 'Rear tube outside diameter',
        'Overall length, end to end', 'Tube length, weld to weld', 'Tube outside diameter', 'Overall length',
        'End to center bearing', 'Shaft length', 'Dimension', 'Saved', 'my designs', 'Could not save the drawing.',
        'The drawing could not be loaded. Please try again or call (813) 524-6491.', 'Driveshaft drawing with dimensions', 'in',
    ];

    /** Middle sections that are not a two-piece center: end pieces, boots and a broken image (owner, 2026-10-25). */
    private const HIDDEN_CENTER = ['71Q', '71RQ', '72', '72R', '73'];

    private function catalog(): array
    {
        static $c = null;
        if ($c === null) {
            $c = json_decode(file_get_contents(resource_path('designer-sections.json')), true)['data']['sections_by_location'];
            $c['center'] = array_values(array_filter($c['center'], fn ($f) => !in_array(basename($f, '.svg'), self::HIDDEN_CENTER, true)));
        }
        return $c;
    }

    /** Which location comes next for a style and the sections chosen so far (same rule as the original). */
    public static function nextLocation(int $style, int $chosen): ?string
    {
        if ($chosen === 0) return 'left';
        if ($chosen < $style) return 'center';
        if ($chosen === $style) return 'right';
        return null;
    }

    /**
     * A finished combination is exactly front (+ middle for two piece) + rear, each a real section for its slot —
     * the same rule page() enforces. Anything else is refused before the supplier is ever asked for a drawing
     * (each new combination is a remote fetch + a file on disk forever).
     */
    public static function validSections(string $sections): bool
    {
        $codes = explode('+', $sections);
        $style = count($codes) - 1;
        if (!in_array($style, self::STYLES, true)) return false;
        $catalog = (new self)->catalog();
        foreach ($codes as $i => $code) {
            $loc = self::nextLocation($style, $i);
            if (!$loc || !in_array($code . '.svg', $catalog[$loc] ?? [], true)) return false;
        }
        return true;
    }

    public function page(Request $r, int $style = 0, string $sections = '')
    {
        abort_unless($style === 0 || in_array($style, self::STYLES, true), 404);
        $catalog = $this->catalog();
        $codes = array_values(array_filter(explode('+', $sections)));
        // every chosen code must be a real section for its slot, in order
        foreach ($codes as $i => $code) {
            $loc = self::nextLocation($style, $i);
            abort_unless($loc && in_array($code . '.svg', $catalog[$loc], true), 404);
        }
        $complete = $style > 0 && count($codes) === $style + 1;
        $next = $style > 0 && !$complete ? self::nextLocation($style, count($codes)) : null;
        return view('designer-tool', [
            'style' => $style, 'codes' => $codes, 'sections' => implode('+', $codes), 'complete' => $complete,
            'nextLocation' => $next,
            'options' => $next ? array_map(fn ($f) => basename($f, '.svg'), $catalog[$next]) : [],
            'locationLabel' => ['left' => 'Front', 'center' => 'Middle', 'right' => 'Rear'][$next] ?? null,
            'store' => config('uds.stores')[0],
        ]);
    }

    /** Save the finished drawing (sections + measurements) to the signed-in customer's designs. */
    public function save(Request $r)
    {
        if (!\Illuminate\Support\Facades\Auth::check()) return response()->json(['success' => false, 'login' => true, 'message' => __('Sign in to save this drawing to your account.')], 401);
        $sections = preg_replace('/[^A-Z0-9+]/', '', strtoupper((string) $r->input('sections')));
        $style = (int) $r->input('style');
        $codes = $sections === '' ? [] : explode('+', $sections);
        abort_unless(in_array($style, self::STYLES, true) && count($codes) === $style + 1 && self::validSections($sections), 422);
        $dims = self::parseDims((string) $r->input('dims', ''));
        $ref = 'DWG-' . now()->format('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        $title = ($style === 2 ? 'Two piece' : 'One piece') . ' drawing ' . $sections;
        $summary = $dims ? implode(', ', array_map(fn ($k, $v) => "$k $v", array_keys($dims), $dims)) : 'no measurements yet';
        \Illuminate\Support\Facades\DB::table('saved_designs')->insert([
            'user_id' => \Illuminate\Support\Facades\Auth::id(), 'ref' => $ref, 'title' => $title,
            'config' => json_encode(['kind' => 'designer', 'style' => $style, 'sections' => $sections, 'dims' => $dims, 'summary' => $summary]),
            'price' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'ref' => $ref]);
    }

    /** The UDS look, injected into a composed drawing so it renders styled even as a plain <img> or e-mail attachment. */
    public const STYLE = '<style>.tube-line{stroke:#0b1a33!important;stroke-width:1!important}.parts path,.parts line,.parts polyline,.parts ellipse,.parts polygon{stroke:#0b1a33!important;stroke-width:.45!important}[fill="#ffffff"]{fill:#f6f8fc!important}[fill="#9d9fa2"]{fill:#b7cdf0!important}.dim-line,.dim-ext,.dim-line-t,.radius-line{stroke:#1246ab!important;stroke-width:.6!important}.dim-arrow{fill:#1246ab!important}text{font-family:Poppins,Arial,sans-serif!important;font-weight:600!important;fill:#8392b0}text.dim-text{fill:#1246ab;font-size:8.5px!important}text.dim-text.dim-text-small{font-size:6.5px!important}text.dim-text.is-set{fill:#0b1a33;font-family:"IBM Plex Mono",Menlo,monospace!important}text.front{fill:#5c6879;font-family:Menlo,monospace!important;font-weight:500!important;font-size:7px!important;letter-spacing:.1em}</style>';

    /** Composed drawing with the customer's measurements written in and the UDS style applied. */
    public static function render(string $sections, array $dims = [], bool $styled = true): ?string
    {
        if (!self::validSections($sections)) return null;
        $file = storage_path('app/designer/' . $sections . '.svg');
        if (!is_file($file)) {
            $resp = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Accept' => 'image/svg+xml,*/*'])->timeout(20)->get(self::BUILDER . $sections);
            if (!$resp->successful() || !str_contains($resp->body(), '<svg')) return null;
            file_put_contents($file, self::sanitize($resp->body()));
        }
        $svg = self::normalize(self::sanitize(file_get_contents($file)));
        foreach ($dims as $name => $value) {
            $name = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $name)); $value = trim(strip_tags((string) $value));
            if ($name === '' || $value === '') continue;
            $svg = preg_replace('/(<text class="dim-text)([^"]*" id="dim-text-' . strtolower($name) . '" name="' . $name . '"[^>]*>)\{' . $name . '\}/', '${1} is-set${2}' . htmlspecialchars($value, ENT_QUOTES), $svg, 1);
        }
        if ($styled) $svg = preg_replace('/(<svg\b[^>]*>)/', '$1' . self::STYLE, $svg, 1);
        return $svg;
    }

    /** Supplier drawing quirks fixed before the sheet uses it: the small detail-view labels (X2, Y2, Z2, N2…)
     *  lack the base dim-text class, and the FRONT / REAR caption sits on top of the detail views. */
    public static function normalize(string $svg): string
    {
        $svg = preg_replace('/<text class="(dim-text-[^"]*)"/', '<text class="dim-text $1"', $svg);
        preg_match_all('/<text class="dim-text dim-text-small[^"]*"[^>]*\bx="([-\d.]+)"[^>]*\by="([-\d.]+)"/', $svg, $m, PREG_SET_ORDER);
        $small = array_map(fn ($r) => [(float) $r[1], (float) $r[2]], $m);
        if ($small) {
            $svg = preg_replace_callback('/(<text class="front"[^>]*\bx=")([-\d.]+)("[^>]*\by=")([-\d.]+)(")/', function ($t) use ($small) {
                $x = (float) $t[2]; $y = (float) $t[4]; $left = $right = false;
                foreach ($small as [$lx, $ly]) { if (abs($ly - $y) < 18 && abs($lx - $x) < 60) { if ($lx >= $x) $right = true; else $left = true; } }
                if ($right && !$left) $x = max(30, $x - 48); elseif ($left && !$right) $x += 48; elseif ($left && $right) $y -= 22;
                return $t[1] . $x . $t[3] . $y . $t[5];
            }, $svg);
        }
        return $svg;
    }

    /** Third-party SVG hardening: no scripts, no event handlers, no foreign objects, no external references. */
    public static function sanitize(string $svg): string
    {
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
        $svg = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svg);
        $svg = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
        // only in-document references, whatever the quoting: "…", '…' or bare
        $svg = preg_replace('/\s+(xlink:href|href)\s*=\s*("(?!#)[^"]*"|\'(?!#)[^\']*\'|(?![#"\'])[^\s>]+)/i', '', $svg);
        return $svg;
    }

    /** Dimension values from the designer link hash ("A1=52.25&C1=22") into [name => value]. */
    public static function parseDims(string $hash): array
    {
        $out = [];
        foreach (explode('&', ltrim($hash, '#')) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
            $k = preg_replace('/[^A-Z0-9]/', '', strtoupper($k)); $v = trim(urldecode($v));
            if ($k !== '' && $v !== '' && strlen($v) <= 12) $out[$k] = $v;
        }
        return $out;
    }

    /** The composed drawing, fetched once from the supplier and cached locally. ?style=uds returns it in the UDS look. */
    public function svg(string $sections, Request $r)
    {
        abort_unless(self::validSections($sections), 404);   // slot order checked like page(), not just "known codes"

        $file = storage_path('app/designer/' . $sections . '.svg');
        if (!is_file($file)) {
            $resp = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Accept' => 'image/svg+xml,*/*', 'Referer' => 'https://c2.powertrainindustries.com/resources/driveshaft-designer/'])->timeout(20)->get(self::BUILDER . $sections);
            if (!$resp->successful() || !str_contains($resp->body(), '<svg')) {
                \Illuminate\Support\Facades\Log::warning('Designer drawing fetch failed for [' . $sections . '] -> ' . $resp->status() . ' ' . substr($resp->body(), 0, 120));
                abort(502, 'Drawing service unavailable');
            }
            file_put_contents($file, self::sanitize($resp->body()));
        }
        $body = self::normalize(self::sanitize(file_get_contents($file)));
        if ($r->query('style') === 'uds') $body = self::render($sections, self::parseDims((string) $r->query('dims', '')), true) ?? $body;
        return response($body, 200, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:", 'Content-Disposition' => 'inline',
            'Cache-Control' => $r->query('dims') ? 'no-store' : 'public, max-age=2592000']);
    }
}
