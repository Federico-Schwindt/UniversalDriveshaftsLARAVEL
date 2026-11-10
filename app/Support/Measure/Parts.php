<?php

namespace App\Support\Measure;

/**
 * The drawings themselves, one method per part family: our own line work, built from the part geometry in the
 * standard side / end views. Each takes the callout letters of the category (attribute key => letter, see
 * Diagram) and returns a Sketch. Sheet is 480 × 270; nothing is to scale.
 */
class Parts
{
    use YokeParts, ShaftParts, FlangeParts, SmallParts;

    /** maps a yoke's own coordinates (cross centre at 0,0, ears towards -y) onto the sheet with the ears pointing $dir */
    private static function tf(string $dir, float $cx, float $cy): \Closure
    {
        return match ($dir) {
            'up' => fn ($x, $y) => [$cx + $x, $cy + $y],
            'down' => fn ($x, $y) => [$cx + $x, $cy - $y],
            'right' => fn ($x, $y) => [$cx - $y, $cy + $x],
            'left' => fn ($x, $y) => [$cx + $y, $cy + $x],
        };
    }

    /** closed outline from its +x half (centre line to centre line): the half, then its mirror image run backwards */
    private static function sym(array $half): array
    {
        $out = $half;
        for ($i = count($half) - 1; $i > 0; $i--) {
            $c = $half[$i];
            $p = $half[$i - 1];
            [$x, $y] = [$p[count($p) - 2], $p[count($p) - 1]];
            $out[] = match ($c[0]) {
                'L' => ['L', -$x, $y],
                'Q' => ['Q', -$c[1], $c[2], -$x, $y],
                'C' => ['C', -$c[3], $c[4], -$c[1], $c[2], -$x, $y],
            };
        }
        $out[] = ['Z'];

        return $out;
    }

    /**
     * Forged yoke seen across its ears: two lugs around the cross centre (machined pads on the outer faces, bearing
     * bores with the snap-ring groove at the outer end), an arched throat and a bell that runs into a neck $L from
     * the centre line and $N wide. Options: 'S' span over the ears, 'cut' => true draws the -x half in section
     * (hatched, bores open), 'bore' => [radius, from y] hollows the neck in that section, 'fillet' rounds the neck
     * into the part it meets, 'bridge' => true joins the ear tips over the cross (CV yokes: the bridge carries the
     * centring stud or socket), 'series' => [x,y] puts the series letter there. Returns the geometry (ear tops,
     * throat floor, half span, ear thickness, bore radius, half throat, bridge top) for the parts drawn around it.
     */
    private static function yoke(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, array $o = []): array
    {
        $S = ($o['S'] ?? 136) / 2;   // half span over the ears
        $T = 26;                     // ear thickness
        $c = 20;                     // bearing bore radius
        $top = -$c - 15;             // ear tips
        $D = 46;                     // throat floor
        $in = $S - $T;
        $n = $N / 2;
        $r = $o['fillet'] ?? 0;      // fillet into whatever the neck meets
        $tf = self::tf($dir, $cx, $cy);
        $y0 = $c - 2;                // the throat starts rounding just under the bores
        $b = $D - $y0;
        $q = .5523;                  // quarter ellipse as a cubic
        $k = $L - $r - $c - 8;
        $bridge = !empty($o['bridge']);
        $wt = -$c - 9;               // top of the window under a bridge
        $bt = $wt - 15;              // top of the bridge
        $throat = [['M', 0, $D], ['C', $q * $in, $D, $in, $y0 + $q * $b, $in, $y0]];
        $half = $bridge
            ? [['M', 0, $bt], ['L', 18, $bt], ['C', $S - 20, $bt, $S - 2, -$c - 18, $S - 2, -$c - 8]]
            : array_merge($throat, [['L', $in, $top + 4], ['Q', $in, $top, $in + 4, $top], ['L', $S - 9, $top], ['L', $S - 2, $top + 7], ['L', $S - 2, -$c - 8]]);
        array_push($half, ['L', $S, -$c - 6], ['L', $S, $c + 6], ['L', $S - 2, $c + 8],
            ['C', $S - 2, $c + 8 + .55 * $k, $n + .15 * ($S - 2 - $n), $L - $r - .22 * $k, $n, $L - $r]);
        // closed window the cross sits in, when the ears are bridged
        $window = self::sym(array_merge($throat, [['L', $in, $wt + 10], ['Q', $in, $wt, $in - 10, $wt], ['L', 0, $wt]]));
        if ($r) {
            $half[] = ['Q', $n, $L, $n + $r, $L];
        }
        $half[] = ['L', 0, $L];
        $outline = self::sym($half);
        $s->shape($outline, 'md-part', $tf, 'cast');
        $cut = !empty($o['cut']);
        // forging contours on the side(s) seen from outside: the rounded edge of the throat and the line where
        // the flat of the ears runs out on the round of the body
        $e = 4;
        $yU = $D + .55 * ($L - $r - $D);
        foreach ($cut ? [1] : [-1, 1] as $m) {
            if (!$bridge) {
                $s->shape([['M', $m * ($in + $e), $top], ['L', $m * ($in + $e), $y0],
                    ['C', $m * ($in + $e), $y0 + $q * ($b + $e), $m * $q * ($in + $e), $D + $e, 0, $D + $e]], 'md-edge', $tf);
            }
            if ($L - $r - $D >= 22) {
                $s->shape([['M', $m * ($S - 8), $c + 12], ['C', $m * ($S - 8), $c + 12 + .6 * ($yU - $c - 12), $m * .55 * $S, $yU, 0, $yU]], 'md-edge', $tf);
            }
        }
        $bores = [];
        if ($cut) {
            [$x1, $y1] = $tf(-$S - 6, $top - 6);
            [$x2, $y2] = $tf(0, $L + 1);
            $clip = $s->clip($x1, $y1, $x2, $y2);
            $s->shape($outline, 'md-cut', $tf, 'hatch', $clip);
            // bearing bore through the cut ear, with the snap-ring groove
            $void = [['M', -$S - 1, -$c], ['L', -$S + 4, -$c], ['L', -$S + 4, -$c - 3], ['L', -$S + 7, -$c - 3], ['L', -$S + 7, -$c], ['L', -$in + 1, -$c],
                ['L', -$in + 1, $c], ['L', -$S + 7, $c], ['L', -$S + 7, $c + 3], ['L', -$S + 4, $c + 3], ['L', -$S + 4, $c], ['L', -$S - 1, $c], ['Z']];
            $s->shape($void, 'md-void', $tf);
            foreach ([-1, 1] as $m) {
                $s->shape([['M', -$S, $m * $c], ['L', -$S + 4, $m * $c], ['L', -$S + 4, $m * ($c + 3)], ['L', -$S + 7, $m * ($c + 3)], ['L', -$S + 7, $m * $c], ['L', -$in, $m * $c]], 'md-line', $tf);
            }
            if ($b = $o['bore'] ?? null) {
                $s->shape([['M', -$b[0], $b[1]], ['L', 0, $b[1]], ['L', 0, $L + 1], ['L', -$b[0], $L + 1], ['Z']], 'md-void', $tf);
                $s->shape([['M', -$b[0], $L], ['L', -$b[0], $b[1]], ['L', 0, $b[1]]], 'md-line', $tf);
            }
            $s->shape($outline, 'md-outline', $tf);
        }
        if ($bridge) {
            $s->shape($window, 'md-hole', $tf);
        }
        foreach ([-1, 1] as $side) {
            $visible = $cut && $side < 0;
            foreach ([-$c, $c] as $y) {
                $a = $tf($side * $S, $y);
                $z = $tf($side * $in, $y);
                if (!$visible) {
                    $s->hid($a[0], $a[1], $z[0], $z[1]);
                }
                $bores[] = [$a[0], $a[1], $z[0], $z[1]];
            }
            if (!$visible) {   // snap-ring groove near the outer face
                foreach ([4, 7] as $g) {
                    $s->hid($side * ($S - $g), -$c - 3, $side * ($S - $g), $c + 3, $tf);
                }
            }
        }
        $s->cl(-$S - 14, 0, $S + 14, 0, $tf);
        if ($at = $o['series'] ?? null) {
            $l = $tf(-$S + $T / 2, -$c);
            $z = $tf($S - $T / 2, -$c);
            $p = hypot($at[0] - $l[0], $at[1] - $l[1]) < hypot($at[0] - $z[0], $at[1] - $z[1]) ? $l : $z;
            $s->tag(['pa_series', 'pa_style-series'], $p[0], $p[1], $at[0], $at[1])->hi(['pa_series', 'pa_style-series'], $bores);
        }

        return ['top' => $top, 'D' => $D, 'S' => $S, 'T' => $T, 'c' => $c, 'in' => $in, 'bt' => $bt];
    }

    /**
     * Yoke turned a quarter turn from yoke(): the ear faces the viewer, a round-topped lug with the bearing cap and
     * its snap ring in the bore, running down into a neck $L from the cross centre and $N wide. Same $dir
     * convention as yoke().
     */
    private static function earOn(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, float $W = 54): void
    {
        $tf = self::tf($dir, $cx, $cy);
        $w = $W / 2;
        $n = $N / 2;
        $top = -35;
        $q = .5523;
        $k = $L - 20;
        $s->shape(self::sym([
            ['M', 0, $top], ['C', $q * $w, $top, $w, $top + $w - $q * $w, $w, $top + $w], ['L', $w, 20],
            ['C', $w, 20 + .5 * $k, $n, $L - .5 * $k, $n, $L], ['L', 0, $L],
        ]), 'md-part', $tf, 'cast');
        $s->circle($cx, $cy, 24, 'md-edge')->circle($cx, $cy, 20, 'md-part', 'face')->circle($cx, $cy, 17, 'md-edge')->circle($cx, $cy, 13.5, 'md-edge');
    }

    /**
     * Turned part seen from the side, axis horizontal at $cy. $outer = [[x, r], …] left to right, chamfers and
     * steps as their own points. Options: 'bores' => list of inner profiles [[x, r], …] (left to right; open where
     * they reach an end), 'cut' => true or [x1, x2] hatches the upper half (over that span) with the bores open,
     * 'fill' shading (cyh), 'edges' => false skips the lines at diameter changes.
     */
    private static function turned(Sketch $s, array $outer, float $cy, array $o = []): void
    {
        $path = [];
        foreach ($outer as $i => [$x, $r]) {
            $path[] = [$i ? 'L' : 'M', $x, $cy - $r];
        }
        foreach (array_reverse($outer) as [$x, $r]) {
            $path[] = ['L', $x, $cy + $r];
        }
        $path[] = ['Z'];
        $x0 = $outer[0][0];
        $x9 = $outer[count($outer) - 1][0];
        $bores = $o['bores'] ?? [];
        $s->shape($path, 'md-part', null, $o['fill'] ?? 'cyh');
        $cut = $o['cut'] ?? false;
        [$c1, $c2] = is_array($cut) ? $cut : [$x0, $x9];
        $inCut = fn ($x) => $cut && $x >= $c1 - .5 && $x <= $c2 + .5;
        if ($cut) {
            $s->shape($path, 'md-cut', null, 'hatch', $s->clip($c1, $cy - 200, $c2, $cy));
            foreach ($bores as $b) {
                $wall = [];
                foreach ($b as $i => [$x, $r]) {
                    $wall[] = [$i ? 'L' : 'M', $x, $cy - $r];
                }
                $s->shape(array_merge($wall, [['L', $b[count($b) - 1][0], $cy + .5], ['L', $b[0][0], $cy + .5], ['Z']]), 'md-void', null, null, $s->clip($c1, $cy - 200, $c2, $cy + 1));
                $s->shape($wall, 'md-line', null, null, $s->clip($c1, $cy - 200, $c2, $cy));
                foreach ($b as $i => [$x, $r]) {   // ends and steps of the bore, seen inside
                    if (!$inCut($x)) {
                        continue;
                    }
                    $end = ($i === 0 && $x > $x0 + .5) || ($i === count($b) - 1 && $x < $x9 - .5);
                    if ($end || ($i > 0 && $i < count($b) - 1)) {
                        $s->line($x, $cy - $r, $x, $cy, $end ? 'md-line' : 'md-edge');
                    }
                }
            }
            $s->shape($path, 'md-outline');
        }
        if ($o['edges'] ?? true) {
            for ($i = 1; $i < count($outer) - 1; $i++) {
                [$x, $r] = $outer[$i];
                $s->line($x, $inCut($x) ? $cy : $cy - $r, $x, $cy + $r, 'md-edge');
            }
        }
        foreach ($bores as $b) {   // hidden in the uncut half(s)
            for ($i = 0; $i < count($b) - 1; $i++) {
                [$xa, $ra] = $b[$i];
                [$xb, $rb] = $b[$i + 1];
                $s->hid($xa, $cy + $ra, $xb, $cy + $rb);
                if (!$inCut($xa) || !$inCut($xb)) {
                    $s->hid($xa, $cy - $ra, $xb, $cy - $rb);
                }
            }
            foreach ([0, count($b) - 1] as $i) {
                [$x, $r] = $b[$i];
                if (($i === 0 && $x > $x0 + .5) || ($i > 0 && $x < $x9 - .5)) {
                    $s->hid($x, $inCut($x) ? $cy : $cy - $r, $x, $cy + $r);
                }
            }
        }
    }

    /** spline teeth on a turned length (side view): crest lines that run out in a curve at the $run end */
    private static function teeth(Sketch $s, float $x1, float $x2, float $cy, float $r, string $run = 'right', bool $lowerOnly = false): void
    {
        foreach ([-.66, -.25, .25, .66] as $f) {
            if ($lowerOnly && $f < 0) {
                continue;
            }
            $y = $cy + $f * $r;
            $sign = $f < 0 ? -1 : 1;
            if ($run === 'right') {
                $s->line($x1 + 2, $y, $x2 - 10, $y, 'md-edge');
                $s->shape([['M', $x2 - 10, $y], ['Q', $x2 - 2, $y, $x2, $y + $sign * ($r - abs($f) * $r) * .8]], 'md-edge');
            } else {
                $s->line($x1 + 10, $y, $x2 - 2, $y, 'md-edge');
                $s->shape([['M', $x1 + 10, $y], ['Q', $x1 + 2, $y, $x1, $y + $sign * ($r - abs($f) * $r) * .8]], 'md-edge');
            }
        }
        foreach ([-1, 1] as $k) {
            if (!$lowerOnly || $k > 0) {
                $s->line($x1 + 2, $cy + $k * $r * .88, $x2 - 2, $cy + $k * $r * .88, 'md-thin');
            }
        }
    }

    /** regular polygon with rounded corners: circumradius $R, $n corners from $start°, corner cut back $k (0–.5 of a side) */
    private static function rpoly(Sketch $s, float $cx, float $cy, float $R, int $n, float $start, float $k, string $cls = 'md-part', ?string $fill = 'cast'): void
    {
        $v = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad($start + $i * 360 / $n);
            $v[] = [$cx + $R * cos($a), $cy - $R * sin($a)];
        }
        $cmds = [];
        foreach ($v as $i => $p) {
            $a = $v[($i + $n - 1) % $n];
            $b = $v[($i + 1) % $n];
            $cmds[] = [$i ? 'L' : 'M', $p[0] + ($a[0] - $p[0]) * $k, $p[1] + ($a[1] - $p[1]) * $k];
            $cmds[] = ['Q', $p[0], $p[1], $p[0] + ($b[0] - $p[0]) * $k, $p[1] + ($b[1] - $p[1]) * $k];
        }
        $cmds[] = ['Z'];
        $s->shape($cmds, $cls, null, $fill);
    }

    /** hexagon with flats top and bottom (across flats = $r·√3), with the chamfer circle */
    private static function hex(Sketch $s, float $cx, float $cy, float $r, ?string $fill = 'face'): void
    {
        self::rpoly($s, $cx, $cy, $r, 6, 0, 0, 'md-part', $fill);
        if ($r > 12) {
            $s->circle($cx, $cy, $r * .866 - 1.5, 'md-edge');
        }
    }

    /**
     * Prong face: $n bosses of radius $lr on a circle of radius $d (first at $start°), joined by concave webs that
     * pass $web from the centre half way between two bosses.
     */
    private static function spider(Sketch $s, float $cx, float $cy, int $n, float $web, float $d, float $lr, float $start, string $fill = 'face'): void
    {
        $step = 360 / $n;
        $w = 95;   // each boss is round this far either side of its arm
        $at = fn ($i, $da) => [
            $cx + $d * cos(deg2rad($start + $i * $step)) + $lr * cos(deg2rad($start + $i * $step + $da)),
            $cy - $d * sin(deg2rad($start + $i * $step)) - $lr * sin(deg2rad($start + $i * $step + $da)),
        ];
        $cmds = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $start + $i * $step;
            $cmds = array_merge($cmds, Sketch::arc($cx + $d * cos(deg2rad($a)), $cy - $d * sin(deg2rad($a)), $lr, $a - $w, $a + $w, (bool) $cmds));
            [$x1, $y1] = $at($i, $w);
            [$x2, $y2] = $at($i + 1, -$w);
            $m = deg2rad($a + $step / 2);
            $rc = 2 * $web - hypot(($x1 + $x2) / 2 - $cx, ($y1 + $y2) / 2 - $cy);
            $cmds[] = ['Q', $cx + $rc * cos($m), $cy - $rc * sin($m), $x2, $y2];
        }
        $cmds[] = ['Z'];
        $s->shape($cmds, 'md-part', null, $fill);
    }

    /** spline teeth around a circle (end views): $n teeth between radii $r1 and $r2; white = a splined hole */
    private static function gear(Sketch $s, float $cx, float $cy, float $r1, float $r2, int $n = 16, string $cls = 'md-hole', ?string $fill = null): void
    {
        $cmds = [];
        $step = 2 * M_PI / $n;
        for ($i = 0; $i < $n; $i++) {
            foreach ([[0, $r1], [.14, $r2], [.5, $r2], [.64, $r1]] as [$f, $r]) {
                $a = ($i + $f) * $step;
                $cmds[] = [$cmds ? 'L' : 'M', $cx + $r * cos($a), $cy + $r * sin($a)];
            }
        }
        $cmds[] = ['Z'];
        $s->shape($cmds, $cls, null, $fill);
    }

    /**
     * Bolt holes on a circle (face views) with their callouts: 'bc' => [i,j] holes the bolt circle is measured
     * between, 'dia' => [hole, angle°] where the hole diameter letter goes, 'count' => [x,y] of the count letter.
     * Returns the hole centres.
     */
    private static function bolts(Sketch $s, float $cx, float $cy, float $bc, float $r, int $n, array $o = []): array
    {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad(($o['start'] ?? 45) + $i * 360 / $n);
            $pts[] = [$cx + $bc * cos($a), $cy - $bc * sin($a)];
        }
        $s->circle($cx, $cy, $bc, 'md-cl');
        foreach ($pts as $p) {
            $s->circle($p[0], $p[1], $r + 2.2, 'md-edge')->circle($p[0], $p[1], $r, 'md-hole');
        }
        if ($b = $o['bc'] ?? null) {
            $s->dim('pa_bolt-circle-dia', $pts[$b[0]][0], $pts[$b[0]][1], $pts[$b[1]][0], $pts[$b[1]][1], ['t' => $o['t'] ?? .3]);
        }
        if ($d = $o['dia'] ?? null) {
            [$hx, $hy] = $pts[$d[0]];
            $ux = cos(deg2rad($d[1]));
            $uy = -sin(deg2rad($d[1]));
            $s->dim('pa_bolt-hole-dia', $hx - $r * $ux, $hy - $r * $uy, $hx + $r * $ux, $hy + $r * $uy, ['out' => true]);
        }
        if ($at = $o['count'] ?? null) {
            $h = $pts[$o['countHole'] ?? 0];
            $l = hypot($at[0] - $h[0], $at[1] - $h[1]) ?: 1;
            $keys = ['pa_bolt-holes', 'pa_bolt-hole-count'];
            $s->tag($keys, $h[0] + $r * ($at[0] - $h[0]) / $l, $h[1] + $r * ($at[1] - $h[1]) / $l, $at[0], $at[1])
                ->hi($keys, array_map(fn ($p) => [$p[0], $p[1], $r], $pts));
        }

        return $pts;
    }

    /** tube seen from the end, with its diameter and wall callouts */
    private static function tube(Sketch $s, float $cx, float $cy, float $ro, float $ri, float $dimY): void
    {
        $s->circle($cx, $cy, $ro, 'md-part', 'face')->circle($cx, $cy, $ro - 2, 'md-edge')->circle($cx, $cy, $ri, 'md-hole');
        $s->cl($cx - $ro - 10, $cy, $cx + $ro + 10, $cy)->cl($cx, $cy - $ro - 10, $cx, $cy + $ro + 10);
        $s->dimH(['pa_tube-dia-wall', 'pa_tube-dia'], $cx - $ro, $cx + $ro, $dimY, $cy, $cy);
        $k = M_SQRT1_2;
        $s->dim(['pa_tube-dia-wall', 'pa_tube-wall'], $cx + $ri * $k, $cy - $ri * $k, $cx + $ro * $k, $cy - $ro * $k);
    }
}
