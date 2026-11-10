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
     * Yoke seen from the side: two ears around the cross centre, tapering to a neck $L from the centre line and
     * $N wide. Draws the bearing bores as hidden lines and the cross centre line. 'series' => [x,y] puts the
     * series letter there, pointing at the nearest ear.
     */
    private static function yoke(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, array $o = []): void
    {
        $S = ($o['S'] ?? 124) / 2;   // half span over the ears
        $T = 26;                     // ear thickness
        $R = 30;                     // ear reach past the centre line
        $D = 34;                     // throat depth
        $c = 17;                     // bearing cap radius
        $n = $N / 2;
        $m = $D + ($L - $D) * .5;
        $tf = self::tf($dir, $cx, $cy);
        $s->shape([
            ['M', -$S, -$R + 8], ['Q', -$S, -$R, -$S + 8, -$R], ['L', -$S + $T - 8, -$R], ['Q', -$S + $T, -$R, -$S + $T, -$R + 8],
            ['L', -$S + $T, $D - 12], ['Q', -$S + $T, $D, -$S + $T + 12, $D], ['L', $S - $T - 12, $D], ['Q', $S - $T, $D, $S - $T, $D - 12],
            ['L', $S - $T, -$R + 8], ['Q', $S - $T, -$R, $S - $T + 8, -$R], ['L', $S - 8, -$R], ['Q', $S, -$R, $S, -$R + 8],
            ['L', $S, $D - 6], ['C', $S, $m, $n, $m, $n, $L], ['L', -$n, $L], ['C', -$n, $m, -$S, $m, -$S, $D - 6], ['Z'],
        ], 'md-part', $tf);
        $bores = [];
        foreach ([-1, 1] as $side) {
            foreach ([-$c, $c] as $y) {
                $a = $tf($side * $S, $y);
                $b = $tf($side * ($S - $T), $y);
                $s->hid($a[0], $a[1], $b[0], $b[1]);
                $bores[] = [$a[0], $a[1], $b[0], $b[1]];
            }
        }
        $s->cl(-$S - 14, 0, $S + 14, 0, $tf);
        if ($at = $o['series'] ?? null) {
            $l = $tf(-$S + $T / 2, -$c);
            $r = $tf($S - $T / 2, -$c);
            $p = hypot($at[0] - $l[0], $at[1] - $l[1]) < hypot($at[0] - $r[0], $at[1] - $r[1]) ? $l : $r;
            $s->tag(['pa_series', 'pa_style-series'], $p[0], $p[1], $at[0], $at[1])->hi(['pa_series', 'pa_style-series'], $bores);
        }
    }

    /**
     * Yoke turned a quarter turn from yoke(): the ear faces the viewer, so it reads as a block with the bearing cap
     * in the middle, tapering to a neck $L from the cross centre and $N wide. Same $dir convention as yoke().
     */
    private static function earOn(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, float $W = 52): void
    {
        $tf = self::tf($dir, $cx, $cy);
        $w = $W / 2;
        $n = $N / 2;
        $s->shape([
            ['M', -$w, -22], ['Q', -$w, -30, -$w + 8, -30], ['L', $w - 8, -30], ['Q', $w, -30, $w, -22],
            ['L', $w, 20], ['C', $w, ($L + 20) / 2, $n, ($L + 20) / 2, $n, $L], ['L', -$n, $L],
            ['C', -$n, ($L + 20) / 2, -$w, ($L + 20) / 2, -$w, 20], ['Z'],
        ], 'md-part', $tf);
        $s->circle($cx, $cy, 16, 'md-line')->circle($cx, $cy, 7, 'md-thin');
    }

    /** regular polygon with rounded corners: circumradius $R, $n corners from $start°, corner cut back $k (0–.5 of a side) */
    private static function rpoly(Sketch $s, float $cx, float $cy, float $R, int $n, float $start, float $k, string $cls = 'md-part'): void
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
        $s->shape($cmds, $cls);
    }

    /** hexagon with flats top and bottom (across flats = $r·√3) */
    private static function hex(Sketch $s, float $cx, float $cy, float $r, string $cls = 'md-part'): void
    {
        self::rpoly($s, $cx, $cy, $r, 6, 0, 0, $cls);
    }

    /** hub with $n capsule-shaped arms reaching $d out with $lr end radius (prong and spider faces) */
    private static function lobes(Sketch $s, float $cx, float $cy, int $n, float $hub, float $d, float $lr, float $start): void
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
        $s->shape($cmds);
    }

    /** spline teeth around a circle (end views): $n teeth between radii $r1 and $r2 */
    private static function gear(Sketch $s, float $cx, float $cy, float $r1, float $r2, int $n = 16, string $cls = 'md-hole'): void
    {
        $cmds = [];
        $step = 2 * M_PI / $n;
        for ($i = 0; $i < $n; $i++) {
            foreach ([[0, $r1], [.18, $r2], [.5, $r2], [.68, $r1]] as [$f, $r]) {
                $a = ($i + $f) * $step;
                $cmds[] = [$cmds ? 'L' : 'M', $cx + $r * cos($a), $cy + $r * sin($a)];
            }
        }
        $cmds[] = ['Z'];
        $s->shape($cmds, $cls);
    }

    /** splined length of a shaft seen from the side */
    private static function splines(Sketch $s, float $x1, float $x2, float $cy, float $h): void
    {
        $s->rect($x1, $cy - $h, $x2 - $x1, 2 * $h);
        foreach ([-.5, 0, .5] as $f) {
            $s->line($x1, $cy + $f * $h, $x2, $cy + $f * $h, 'md-thin');
        }
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
            $s->circle($p[0], $p[1], $r, 'md-hole');
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
        $s->circle($cx, $cy, $ro)->circle($cx, $cy, $ri, 'md-hole');
        $s->cl($cx - $ro - 10, $cy, $cx + $ro + 10, $cy)->cl($cx, $cy - $ro - 10, $cx, $cy + $ro + 10);
        $s->dimH(['pa_tube-dia-wall', 'pa_tube-dia'], $cx - $ro, $cx + $ro, $dimY, $cy, $cy);
        $k = M_SQRT1_2;
        $s->dim(['pa_tube-dia-wall', 'pa_tube-wall'], $cx + $ri * $k, $cy - $ri * $k, $cx + $ro * $k, $cy - $ro * $k);
    }
}
