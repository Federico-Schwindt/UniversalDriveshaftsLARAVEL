<?php

namespace App\Support\Measure;

/**
 * A small SVG drafting board for the "how to measure" drawings: part outlines, hidden and centre lines, and
 * lettered dimension callouts. Every callout is a <g class="md-dim" data-dim="pa_…">, so the page can light up
 * the one that belongs to a table column. Colours and line weights live in CSS (.md-* in uds-2026.css).
 *
 * A callout is only drawn when its attribute has a letter in $codes — one drawing serves categories whose
 * tables carry different columns.
 *
 * Surfaces can be painted with the shared shading in <defs> ($fill: cyh / cyv = turned surface with its axis
 * horizontal / vertical, cast, face = end face, hatch = cut material) and clipped to a rectangle (half sections).
 * Ids carry the token "mdU_"; print the markup through uniq() so the same drawing can appear twice on a page.
 */
class Sketch
{
    private const TOKEN = 'mdU_';

    private array $base = [];
    private array $dims = [];
    private array $clips = [];

    /** @param array<string,string> $codes attribute key => callout letter */
    public function __construct(private array $codes, private float $w = 480, private float $h = 270) {}

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    /** a fresh copy of the markup with its own ids */
    public static function uniq(string $svg): string
    {
        return str_replace(self::TOKEN, 'md' . bin2hex(random_bytes(3)) . '_', $svg);
    }

    private static function paint(?string $fill, ?string $clip = null): string
    {
        return ($fill ? ' style="fill:url(#' . self::TOKEN . $fill . ')"' : '') . ($clip ? ' clip-path="url(#' . self::TOKEN . $clip . ')"' : '');
    }

    /** clip rectangle for half sections; returns the name to pass as $clip */
    public function clip(float $x1, float $y1, float $x2, float $y2): string
    {
        $id = 'c' . count($this->clips);
        $this->clips[] = '<clipPath id="' . self::TOKEN . $id . '"><rect x="' . self::n(min($x1, $x2)) . '" y="' . self::n(min($y1, $y2))
            . '" width="' . self::n(abs($x2 - $x1)) . '" height="' . self::n(abs($y2 - $y1)) . '"/></clipPath>';

        return $id;
    }

    /** path data from absolute commands: [['M',x,y],['L',x,y],['Q',x1,y1,x,y],['C',…],['Z']] */
    public static function d(array $cmds, ?\Closure $tf = null): string
    {
        $d = '';
        foreach ($cmds as $c) {
            $d .= array_shift($c);
            foreach (array_chunk($c, 2) as $p) {
                [$x, $y] = $tf ? $tf($p[0], $p[1]) : $p;
                $d .= self::n($x) . ' ' . self::n($y) . ' ';
            }
        }

        return trim($d);
    }

    /** closed or open outline */
    public function shape(array $cmds, string $cls = 'md-part', ?\Closure $tf = null, ?string $fill = null, ?string $clip = null): static
    {
        $this->base[] = '<path class="' . $cls . '"' . self::paint($fill, $clip) . ' d="' . self::d($cmds, $tf) . '"/>';

        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, string $cls = 'md-part', float $rx = 0, ?string $fill = null): static
    {
        $this->base[] = '<rect class="' . $cls . '"' . self::paint($fill) . ' x="' . self::n($x) . '" y="' . self::n($y) . '" width="' . self::n($w)
            . '" height="' . self::n($h) . '"' . ($rx ? ' rx="' . self::n($rx) . '"' : '') . '/>';

        return $this;
    }

    public function circle(float $cx, float $cy, float $r, string $cls = 'md-part', ?string $fill = null): static
    {
        $this->base[] = '<circle class="' . $cls . '"' . self::paint($fill) . ' cx="' . self::n($cx) . '" cy="' . self::n($cy) . '" r="' . self::n($r) . '"/>';

        return $this;
    }

    /** points of an arc (degrees, counter-clockwise on screen) as path commands; the first is an M unless $join */
    public static function arc(float $cx, float $cy, float $r, float $a0, float $a1, bool $join = false): array
    {
        $n = max(2, (int) ceil(abs($a1 - $a0) / 7.5));
        $out = [];
        for ($i = 0; $i <= $n; $i++) {
            $a = deg2rad($a0 + ($a1 - $a0) * $i / $n);
            $out[] = [$i || $join ? 'L' : 'M', $cx + $r * cos($a), $cy - $r * sin($a)];
        }

        return $out;
    }

    /** small caption (view names, part names in exploded views) */
    public function note(float $x, float $y, string $text, string $anchor = 'middle'): static
    {
        $this->base[] = '<text class="md-note" x="' . self::n($x) . '" y="' . self::n($y) . '" text-anchor="' . $anchor . '">' . e(__($text)) . '</text>';

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $cls = 'md-line', ?\Closure $tf = null): static
    {
        if ($tf) {
            [$x1, $y1] = $tf($x1, $y1);
            [$x2, $y2] = $tf($x2, $y2);
        }
        $this->base[] = self::seg($x1, $y1, $x2, $y2, $cls);

        return $this;
    }

    /** hidden edge (dashed) */
    public function hid(float $x1, float $y1, float $x2, float $y2, ?\Closure $tf = null): static
    {
        return $this->line($x1, $y1, $x2, $y2, 'md-hid', $tf);
    }

    /** centre line (dash-dot) */
    public function cl(float $x1, float $y1, float $x2, float $y2, ?\Closure $tf = null): static
    {
        return $this->line($x1, $y1, $x2, $y2, 'md-cl', $tf);
    }

    private static function circ(float $cx, float $cy, float $r, string $cls): string
    {
        return '<circle class="' . $cls . '" cx="' . self::n($cx) . '" cy="' . self::n($cy) . '" r="' . self::n($r) . '"/>';
    }

    private static function seg(float $x1, float $y1, float $x2, float $y2, string $cls): string
    {
        return '<line class="' . $cls . '" x1="' . self::n($x1) . '" y1="' . self::n($y1) . '" x2="' . self::n($x2) . '" y2="' . self::n($y2) . '"/>';
    }

    /** does this attribute get a callout letter in this drawing */
    public function has(string $key): bool
    {
        return isset($this->codes[$key]);
    }

    /** add a band of $dh for an extra view, from $from (the lowest point the views above use) or the bottom edge; returns its top */
    public function extend(float $dh, ?float $from = null): float
    {
        $top = $from ?? $this->h;
        $this->h = max($this->h, $top + $dh);

        return $top;
    }

    /** first of the given attribute keys that has a letter in this category, or null */
    private function pick(string|array $key): ?string
    {
        foreach ((array) $key as $k) {
            if (isset($this->codes[$k])) {
                return $k;
            }
        }

        return null;
    }

    /** arrowhead with its tip at x,y pointing along $ang (drawn as a polygon: SVG markers can't follow the stroke colour in Safari) */
    private static function arrow(float $x, float $y, float $ang): string
    {
        $bx = $x - 9 * cos($ang);
        $by = $y - 9 * sin($ang);
        $nx = -sin($ang) * 2.5;
        $ny = cos($ang) * 2.5;

        return '<polygon class="md-arw" points="' . self::n($x) . ',' . self::n($y) . ' ' . self::n($bx + $nx) . ',' . self::n($by + $ny)
            . ' ' . self::n($bx - $nx) . ',' . self::n($by - $ny) . '"/>';
    }

    private static function badge(float $x, float $y, string $code): string
    {
        return '<g class="md-badge">' . self::circ($x, $y, 10, 'md-badge-o')
            . '<text x="' . self::n($x) . '" y="' . self::n($y + 4.2) . '" text-anchor="middle">' . e($code) . '</text></g>';
    }

    /**
     * Dimension between two points of the dimension line. Options: 'ext' => [[x,y],[x,y]] points on the part the
     * extension lines come from, 't' => badge position along the line (0–1), 'out' => arrows outside pointing in
     * (automatic when the span is too short to hold the letter).
     */
    public function dim(string|array $key, float $x1, float $y1, float $x2, float $y2, array $o = []): static
    {
        if (!$k = $this->pick($key)) {
            return $this;
        }
        $len = hypot($x2 - $x1, $y2 - $y1) ?: 1;
        $ux = ($x2 - $x1) / $len;
        $uy = ($y2 - $y1) / $len;
        $ang = atan2($uy, $ux);
        $m = '';
        foreach ($o['ext'] ?? [] as $i => $from) {
            if (!$from) {
                continue;
            }
            [$tx, $ty] = $i ? [$x2, $y2] : [$x1, $y1];
            $el = hypot($tx - $from[0], $ty - $from[1]) ?: 1;
            $ex = ($tx - $from[0]) / $el;
            $ey = ($ty - $from[1]) / $el;
            $m .= self::seg($from[0] + 3 * $ex, $from[1] + 3 * $ey, $tx + 4 * $ex, $ty + 4 * $ey, 'md-ext');
        }
        if ($o['out'] ?? $len < 36) {
            $m .= self::seg($x1 - 15 * $ux, $y1 - 15 * $uy, $x2 + 15 * $ux, $y2 + 15 * $uy, 'md-dl')
                . self::arrow($x1, $y1, $ang) . self::arrow($x2, $y2, $ang + M_PI)
                . self::badge($x2 + 27 * $ux, $y2 + 27 * $uy, $this->codes[$k]);
        } else {
            $t = $o['t'] ?? .5;
            $m .= self::seg($x1, $y1, $x2, $y2, 'md-dl') . self::arrow($x1, $y1, $ang + M_PI) . self::arrow($x2, $y2, $ang)
                . self::badge($x1 + ($x2 - $x1) * $t, $y1 + ($y2 - $y1) * $t, $this->codes[$k]);
        }
        $this->dims[] = [$k, $m];

        return $this;
    }

    /**
     * Angular dimension: an arc of radius $r about $cx,$cy from $a0 to $a1 (degrees, counter-clockwise on screen) with
     * the letter at mid-arc; on an arc too short to hold the letter the arrows sit outside pointing in and the letter
     * follows past the $a1 end.
     */
    public function angle(string|array $key, float $cx, float $cy, float $r, float $a0, float $a1): static
    {
        if (!$k = $this->pick($key)) {
            return $this;
        }
        $pt = fn (float $a) => [$cx + $r * cos(deg2rad($a)), $cy - $r * sin(deg2rad($a))];
        $ccw = fn (float $a) => atan2(-cos(deg2rad($a)), -sin(deg2rad($a)));   // screen heading when travelling counter-clockwise
        [$x0, $y0] = $pt($a0);
        [$x1, $y1] = $pt($a1);
        if (deg2rad(abs($a1 - $a0)) * $r < 40) {
            $m = '<path class="md-dl" d="' . self::d(self::arc($cx, $cy, $r, $a0 - rad2deg(15 / $r), $a1 + rad2deg(17 / $r))) . '"/>'
                . self::arrow($x0, $y0, $ccw($a0)) . self::arrow($x1, $y1, $ccw($a1) + M_PI);
            [$bx, $by] = $pt($a1 + rad2deg(29 / $r));
        } else {
            $m = '<path class="md-dl" d="' . self::d(self::arc($cx, $cy, $r, $a0, $a1)) . '"/>'
                . self::arrow($x0, $y0, $ccw($a0) + M_PI) . self::arrow($x1, $y1, $ccw($a1));
            [$bx, $by] = $pt(($a0 + $a1) / 2);
        }
        $this->dims[] = [$k, $m . self::badge($bx, $by, $this->codes[$k])];

        return $this;
    }

    /** horizontal dimension at height $y; $f1/$f2 = y on the part where each extension line starts */
    public function dimH(string|array $key, float $x1, float $x2, float $y, ?float $f1 = null, ?float $f2 = null, array $o = []): static
    {
        return $this->dim($key, $x1, $y, $x2, $y, $o + ['ext' => [$f1 === null ? null : [$x1, $f1], $f2 === null ? null : [$x2, $f2]]]);
    }

    /** vertical dimension at $x; $f1/$f2 = x on the part where each extension line starts */
    public function dimV(string|array $key, float $y1, float $y2, float $x, ?float $f1 = null, ?float $f2 = null, array $o = []): static
    {
        return $this->dim($key, $x, $y1, $x, $y2, $o + ['ext' => [$f1 === null ? null : [$f1, $y1], $f2 === null ? null : [$f2, $y2]]]);
    }

    /** leader from a letter at bx,by to a feature at px,py (counts, sizes read off a feature) */
    public function tag(string|array $key, float $px, float $py, float $bx, float $by): static
    {
        if (!$k = $this->pick($key)) {
            return $this;
        }
        $this->dims[] = [$k, self::seg($bx, $by, $px, $py, 'md-dl') . self::arrow($px, $py, atan2($py - $by, $px - $bx))
            . self::badge($bx, $by, $this->codes[$k])];

        return $this;
    }

    /** feature outline shown only while its callout is active: circles [cx,cy,r] and lines [x1,y1,x2,y2] */
    public function hi(string|array $key, array $shapes): static
    {
        if (!$k = $this->pick($key)) {
            return $this;
        }
        $m = '';
        foreach ($shapes as $s) {
            $m .= count($s) === 3 ? self::circ($s[0], $s[1], $s[2], 'md-hi') : self::seg($s[0], $s[1], $s[2], $s[3], 'md-hi');
        }
        $this->dims[] = [$k, $m];

        return $this;
    }

    /** attribute keys that got a callout, in drawing order */
    public function keys(): array
    {
        return array_values(array_unique(array_column($this->dims, 0)));
    }

    public function svg(string $label = ''): string
    {
        $t = self::TOKEN;
        $metal = '<stop offset="0" stop-color="#aab4c4"/><stop offset=".2" stop-color="#f9fafc"/><stop offset=".42" stop-color="#e7ebf1"/>'
            . '<stop offset=".8" stop-color="#bcc5d2"/><stop offset="1" stop-color="#97a2b4"/>';
        $defs = '<defs><linearGradient id="' . $t . 'cyh" x1="0" y1="0" x2="0" y2="1">' . $metal . '</linearGradient>'
            . '<linearGradient id="' . $t . 'cyv" x1="0" y1="0" x2="1" y2="0">' . $metal . '</linearGradient>'
            . '<linearGradient id="' . $t . 'cast" x1="0" y1="0" x2=".6" y2="1"><stop offset="0" stop-color="#f4f6f9"/><stop offset="1" stop-color="#d3d9e3"/></linearGradient>'
            . '<radialGradient id="' . $t . 'face" cx=".4" cy=".36" r=".72"><stop offset="0" stop-color="#fbfcfd"/><stop offset=".75" stop-color="#e3e7ee"/><stop offset="1" stop-color="#c8cfda"/></radialGradient>'
            . '<radialGradient id="' . $t . 'ball" cx=".36" cy=".32" r=".7"><stop offset="0" stop-color="#ffffff"/><stop offset=".55" stop-color="#d9dfe8"/><stop offset="1" stop-color="#8f9bad"/></radialGradient>'
            . '<linearGradient id="' . $t . 'rubber" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b6372"/><stop offset="1" stop-color="#2c323d"/></linearGradient>'
            . '<pattern id="' . $t . 'hatch" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="5" height="5" fill="#e9edf3"/>'
            . '<line x1="0" y1="0" x2="0" y2="5" stroke="#76839a" stroke-width=".7"/></pattern>' . implode('', $this->clips) . '</defs>';
        $out = '<svg class="md-svg" viewBox="0 0 ' . self::n($this->w) . ' ' . self::n($this->h) . '" role="img" aria-label="' . e($label)
            . '" xmlns="http://www.w3.org/2000/svg">' . $defs . implode('', $this->base);
        foreach ($this->dims as [$k, $m]) {
            $out .= '<g class="md-dim" data-dim="' . e($k) . '">' . $m . '</g>';
        }

        return $out . '</svg>';
    }
}
