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

    /**
     * Yoke seen across its ears: two ears around the cross centre (machined faces, bearing bores, snap-ring
     * grooves) on a cast body that flows into a neck $L from the centre line and $N wide. Options: 'S' span over
     * the ears, 'cut' => true draws the -x half in section (hatched, bores open), 'bore' => [radius, from y]
     * hollows the neck in that section, 'fillet' rounds the neck into the part it meets, 'series' => [x,y] puts
     * the series letter there. Returns the geometry
     * (top, throat, half span, ear thickness, bore radius) for the parts drawn around it.
     */
    private static function yoke(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, array $o = []): array
    {
        $S = ($o['S'] ?? 136) / 2;   // half span over the ears
        $T = 26;                     // ear thickness
        $c = 20;                     // bearing bore radius
        $top = -$c - 15;             // ear tops
        $D = 46;                     // throat floor
        $f = 15;                     // throat fillet
        $in = $S - $T;
        $n = $N / 2;
        $r = $o['fillet'] ?? 0;      // fillet into whatever the neck meets
        $tf = self::tf($dir, $cx, $cy);
        $k = $L - $c - 9 - $r;
        $outline = [
            ['M', -$S, $top + 8], ['Q', -$S, $top, -$S + 8, $top], ['L', -$in - 5, $top], ['Q', -$in, $top, -$in, $top + 5],
            ['L', -$in, $D - $f], ['Q', -$in, $D, -$in + $f, $D], ['L', $in - $f, $D], ['Q', $in, $D, $in, $D - $f],
            ['L', $in, $top + 5], ['Q', $in, $top, $in + 5, $top], ['L', $S - 8, $top], ['Q', $S, $top, $S, $top + 8],
            ['L', $S, $c + 6], ['L', $S - 3, $c + 9], ['C', $S - 3, $c + 9 + .55 * $k, $n + .15 * ($S - 3 - $n), $L - $r - .22 * $k, $n, $L - $r],
            ['Q', $n, $L, $n + $r, $L], ['L', -$n - $r, $L], ['Q', -$n, $L, -$n, $L - $r],
            ['C', -$n - .15 * ($S - 3 - $n), $L - $r - .22 * $k, -$S + 3, $c + 9 + .55 * $k, -$S + 3, $c + 9], ['L', -$S, $c + 6], ['Z'],
        ];
        $s->shape($outline, 'md-part', $tf, 'cast');
        // machined ear faces end where the casting starts
        foreach ([-1, 1] as $side) {
            $a = $tf($side * ($S - 3), $c + 9);
            $b = $tf($side * $S, $c + 6);
            $s->line($a[0], $a[1], $b[0], $b[1], 'md-edge');
        }
        $bores = [];
        $cut = !empty($o['cut']);
        if ($cut) {
            [$x1, $y1] = $tf(-$S - 6, $top - 6);
            [$x2, $y2] = $tf(0, $L + 1);
            $clip = $s->clip($x1, $y1, $x2, $y2);
            $s->shape($outline, 'md-cut', $tf, 'hatch', $clip);
            $void = [['M', -$S - 1, -$c], ['L', -$in + 1, -$c], ['L', -$in + 1, $c], ['L', -$S - 1, $c], ['Z']];
            $s->shape($void, 'md-void', $tf);
            if ($b = $o['bore'] ?? null) {
                $s->shape([['M', -$b[0], $b[1]], ['L', 0, $b[1]], ['L', 0, $L + 1], ['L', -$b[0], $L + 1], ['Z']], 'md-void', $tf);
                $s->shape([['M', -$b[0], $L], ['L', -$b[0], $b[1]], ['L', 0, $b[1]]], 'md-line', $tf);
            }
            $s->shape($outline, 'md-outline', $tf);
        }
        foreach ([-1, 1] as $side) {
            $visible = $cut && $side < 0;
            foreach ([-$c, $c] as $y) {
                $a = $tf($side * $S, $y);
                $b = $tf($side * $in, $y);
                $visible ? $s->line($a[0], $a[1], $b[0], $b[1], 'md-line') : $s->hid($a[0], $a[1], $b[0], $b[1]);
                $bores[] = [$a[0], $a[1], $b[0], $b[1]];
            }
            // snap-ring groove near the outer face
            $g1 = $tf($side * ($S - 5), -$c - 2.5);
            $g2 = $tf($side * ($S - 5), $c + 2.5);
            $visible ? $s->line($g1[0], $g1[1], $g2[0], $g2[1], 'md-edge') : $s->hid($g1[0], $g1[1], $g2[0], $g2[1]);
        }
        $s->cl(-$S - 14, 0, $S + 14, 0, $tf);
        if ($at = $o['series'] ?? null) {
            $l = $tf(-$S + $T / 2, -$c);
            $r = $tf($S - $T / 2, -$c);
            $p = hypot($at[0] - $l[0], $at[1] - $l[1]) < hypot($at[0] - $r[0], $at[1] - $r[1]) ? $l : $r;
            $s->tag(['pa_series', 'pa_style-series'], $p[0], $p[1], $at[0], $at[1])->hi(['pa_series', 'pa_style-series'], $bores);
        }

        return ['top' => $top, 'D' => $D, 'S' => $S, 'T' => $T, 'c' => $c];
    }

    /**
     * Yoke turned a quarter turn from yoke(): the ear faces the viewer, so it reads as a block with the bearing cap
     * in the middle, tapering to a neck $L from the cross centre and $N wide. Same $dir convention as yoke().
     */
    private static function earOn(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, float $W = 54): void
    {
        $tf = self::tf($dir, $cx, $cy);
        $w = $W / 2;
        $n = $N / 2;
        $s->shape([
            ['M', $n, $L], ['C', $n, ($L + 22) / 2, $w, ($L + 22) / 2, $w, 22], ['L', $w, -24], ['Q', $w, -34, $w - 10, -34],
            ['L', -$w + 10, -34], ['Q', -$w, -34, -$w, -24], ['L', -$w, 22], ['C', -$w, ($L + 22) / 2, -$n, ($L + 22) / 2, -$n, $L],
        ], 'md-part', $tf, 'cast');
        $s->circle($cx, $cy, 19, 'md-part', 'face')->circle($cx, $cy, 16, 'md-edge')->circle($cx, $cy, 6, 'md-edge');
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

    /** hub with $n capsule-shaped arms reaching $d out with $lr end radius (prong and spider faces) */
    private static function lobes(Sketch $s, float $cx, float $cy, int $n, float $hub, float $d, float $lr, float $start, string $fill = 'face'): void
    {
        $cmds = [];
        for ($i = 0; $i < 240; $i++) {
            $t = $i * 1.5;
            $r = $hub;
            for ($j = 0; $j < $n; $j++) {
                $phi = deg2rad($t - $start - $j * 360 / $n);
                $sin = abs(sin($phi));
                $cos = cos($phi);
                if ($cos <= 0) {
                    continue;
                }
                $side = $sin > 1e-6 ? $lr / $sin : INF;
                if ($side * $cos <= $d) {
                    $r = max($r, $side);
                } elseif ($d * $sin <= $lr) {
                    $r = max($r, $d * $cos + sqrt($lr * $lr - $d * $d * $sin * $sin));
                }
            }
            $cmds[] = [$i ? 'L' : 'M', $cx + $r * cos(deg2rad($t)), $cy - $r * sin(deg2rad($t))];
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
