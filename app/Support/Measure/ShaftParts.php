<?php

namespace App\Support\Measure;

/** Shafts and tube: spline stubs, splined sleeves, tubing, assemblies, centre bearings, u-joints. */
trait ShaftParts
{
    /** the tube a weld end goes into, in phantom, from the shoulder at $sh */
    private static function phantomTube(Sketch $s, float $sh, float $x2, float $cy, float $ro, float $ri, float $from): void
    {
        foreach ([-1, 1] as $k) {
            $s->line($sh, $cy + $ro * $k, $x2, $cy + $ro * $k, 'md-ph')->line($from, $cy + $ri * $k, $x2, $cy + $ri * $k, 'md-ph');
        }
    }

    /** externally splined stub: side view, weld end in section, tube in phantom + spline end */
    public static function splineStub(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 128;
        $sh = 238;   // weld shoulder
        // splines, relief, the ground seat a bearing or seal rides on, shoulder, weld butt
        self::turned($s, [[30, 23], [33, 26], [120, 26], [123, 22], [129, 22], [132, 27], [184, 27], [184, 33], [196, 33], [226, 48], [226, 52], [$sh, 52],
            [$sh, 45], [$sh + 30, 45], [$sh + 32, 43]], $cy, ['cut' => [196, $sh + 32], 'bores' => [[[216, 33], [$sh + 32, 33]]]]);
        self::teeth($s, 30, 122, $cy, 26, 'right');
        self::phantomTube($s, $sh, $sh + 90, $cy, 52, 45, $sh + 32);
        $s->cl(20, $cy, $sh + 98, $cy);
        $s->dimH('pa_splines-length', 30, 122, 44, $cy - 26, $cy - 26);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 30, $sh, 222, $cy + 26, $cy + 54);
        $s->dimV('pa_butt-dia', $cy - 45, $cy + 45, $sh + 16, null, null, ['t' => .74]);
        $s->dimV(['pa_tube-dia', 'pa_tube-dia-wall', 'pa_fits-to-tube'], $cy - 52, $cy + 52, $sh + 108, $sh + 90, $sh + 90);
        $s->dim('pa_tube-wall', $sh + 70, $cy - 45, $sh + 70, $cy - 52);

        $ex = 434;
        self::gear($s, $ex, $cy, 30, 36, 18, 'md-part', 'face');
        $s->circle($ex, $cy, 5, 'md-edge');
        $s->cl($ex - 46, $cy, $ex + 46, $cy)->cl($ex, $cy - 46, $ex, $cy + 46);
        $s->dimV(['pa_splines-count-dia', 'pa_splines-dia'], $cy - 36, $cy + 36, $ex, null, null, ['t' => .3]);
        $s->tag('pa_splines-count', $ex + 26, $cy + 26, $ex + 20, $cy + 86);

        return $s;
    }

    /** internally splined weld-in sleeve in half section, tube in phantom + sleeve end */
    public static function splinedSleeve(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 128;
        $sh = 226;
        self::turned($s, [[30, 35], [33, 38], [190, 38], [214, 48], [214, 52], [$sh, 52], [$sh, 45], [$sh + 30, 45], [$sh + 32, 43]], $cy,
            ['cut' => true, 'bores' => [[[30, 25], [176, 25]], [[204, 33], [$sh + 32, 33]]]]);
        $s->line(30, $cy - 21, 176, $cy - 21, 'md-edge');
        self::phantomTube($s, $sh, $sh + 90, $cy, 52, 45, $sh + 32);
        $s->cl(20, $cy, $sh + 98, $cy);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 30, $sh, 222, $cy + 40, $cy + 54);
        $s->dimV('pa_butt-dia', $cy - 45, $cy + 45, $sh + 16, null, null, ['t' => .74]);
        $s->dimV(['pa_fits-to-tube', 'pa_tube-dia-wall', 'pa_tube-dia'], $cy - 52, $cy + 52, $sh + 108, $sh + 90, $sh + 90);

        $ex = 426;
        $s->circle($ex, $cy, 48, 'md-part', 'face')->circle($ex, $cy, 45, 'md-edge');
        self::gear($s, $ex, $cy, 27, 32, 18);
        $s->cl($ex - 58, $cy, $ex + 58, $cy)->cl($ex, $cy - 58, $ex, $cy + 58);
        $s->dimV(['pa_splines-dia', 'pa_splines-count-dia'], $cy - 32, $cy + 32, $ex, null, null, ['t' => .3]);
        $s->tag('pa_splines-count', $ex + 23, $cy + 23, $ex + 20, $cy + 86);
        $s->tag(['pa_series', 'pa_style-series'], $ex - 40, $cy - 27, $ex - 30, $cy - 86);

        return $s;
    }

    /** length of tube with a break, wall in section + tube end */
    public static function tubing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 120;
        $ro = 40;
        $ri = 33;
        $pieces = [
            [['M', 30, $cy - $ro], ['L', 150, $cy - $ro], ['Q', 162, $cy - $ro / 2, 150, $cy], ['Q', 138, $cy + $ro / 2, 150, $cy + $ro], ['L', 30, $cy + $ro], ['Z']],
            [['M', 172, $cy - $ro], ['Q', 184, $cy - $ro / 2, 172, $cy], ['Q', 160, $cy + $ro / 2, 172, $cy + $ro], ['L', 296, $cy + $ro], ['L', 296, $cy - $ro], ['Z']],
        ];
        $clip = $s->clip(20, $cy - $ro - 2, 310, $cy);
        foreach ($pieces as $i => $p) {
            $s->shape($p, 'md-part', null, 'cyh')->shape($p, 'md-cut', null, 'hatch', $clip);
            [$xa, $xb] = $i ? [167, 296] : [30, 154];
            $s->rect($xa, $cy - $ri, $xb - $xa, $ri, 'md-void');
            $s->line($xa + ($i ? 3 : 0), $cy - $ri, $xb - ($i ? 0 : 3), $cy - $ri, 'md-line');
            $s->hid($xa + ($i ? 4 : 0), $cy + $ri, $xb - ($i ? 0 : 4), $cy + $ri);
            $s->shape($p, 'md-outline');
        }
        $s->cl(20, $cy, 306, $cy);
        $s->dimH(['pa_tube-length', 'pa_length'], 30, 296, $cy + 88, $cy + $ro, $cy + $ro);
        self::tube($s, 396, $cy, 62, 51, $cy + 96);

        return $s;
    }

    /** u-joint end in profile with the cross cap facing the viewer */
    private static function shaftEnd(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, array $o = []): void
    {
        self::yoke($s, $dir, $cx, $cy, $L, $N, $o);
        $s->circle($cx, $cy, 19, 'md-part', 'face')->circle($cx, $cy, 16, 'md-edge')->circle($cx, $cy, 6, 'md-edge');
    }

    /** tube between two weld beads */
    private static function shaftTube(Sketch $s, float $x1, float $x2, float $cy, float $r): void
    {
        $s->rect($x1, $cy - $r, $x2 - $x1, 2 * $r, 'md-part', 0, 'cyh');
        foreach ([$x1, $x2 - 5] as $x) {
            $s->rect($x, $cy - $r - 1.5, 5, 2 * $r + 3, 'md-part', 2, 'cast');
            for ($y = $cy - $r + 4; $y < $cy + $r - 2; $y += 6) {
                $s->line($x + 1, $y, $x + 4, $y + 3, 'md-edge');
            }
        }
    }

    /** complete one-piece shaft */
    public static function driveshaft(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 130;
        self::shaftTube($s, 128, 352, $cy, 34);
        $s->rect(306, $cy - 37, 22, 4, 'md-part', 1, 'cast');   // balance weight
        self::shaftEnd($s, 'left', 70, $cy, 59, 68, ['series' => [26, 36]]);
        self::shaftEnd($s, 'right', 410, $cy, 59, 68);
        $s->cl(20, $cy, 460, $cy);
        $s->dimV(['pa_tube-diameter', 'pa_tube-dia', 'pa_tube-dia-wall'], $cy - 34, $cy + 34, 240, null, null, ['t' => .3]);
        $s->dimH(['pa_overall-length', 'pa_length', 'pa_cl-to-cl'], 70, 410, 236, $cy + 84, $cy + 84);

        return $s;
    }

    /** front shaft of a two-piece set: u-joint, tube, centre bearing on its stub, splined end */
    public static function twoPiece(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 136;
        $cb = 340;
        self::shaftTube($s, 122, 290, $cy, 34);
        // the centre bearing cut through its middle: mounting plate, bracket, rubber cushion; the stub runs through it
        $s->rect($cb - 29, $cy - 71, 58, 7, 'md-part', 1.5, 'cyh');
        $s->rect($cb - 23, $cy - 64, 46, 122, 'md-part', 5, 'cast');
        $s->rect($cb - 19, $cy - 58, 38, 112, 'md-part', 7, 'rubber');
        self::turned($s, [[290, 34], [304, 21], [378, 21], [378, 17], [436, 17], [439, 14]], $cy);
        self::teeth($s, 378, 439, $cy, 17, 'left');
        foreach ([-1, 1] as $k) {   // ball bearing on the stub, a slinger either side of it
            $s->rect($cb - 11, $cy + 35.5 * $k - 4.5, 22, 9, 'md-part', 1.5, 'cyh')->rect($cb - 11, $cy + 25 * $k - 4, 22, 8, 'md-part', 1.5, 'cyh');
            $s->circle($cb, $cy + 30 * $k, 5.5, 'md-ball', 'ball');
            $s->rect($cb + 27 * $k - 1.5, $cy - 31, 3, 62, 'md-part', 1, 'cyh');
        }
        self::shaftEnd($s, 'left', 64, $cy, 59, 68, ['series' => [22, 40]]);
        $s->cl(14, $cy, 450, $cy)->cl($cb, $cy - 84, $cb, $cy + 70);
        $s->dimH(['pa_cl-to-cb', 'pa_center-to-center'], 64, $cb, 236, $cy + 84, $cy + 60);

        return $s;
    }

    /** short coupled shaft: flange yoke, joint, coupled centre, joint, flange yoke */
    public static function shortCouple(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 134;
        $f1 = 96;
        $c1 = 160;
        $c2 = 320;
        $f2 = 384;
        $s->rect($c1 + 30, $cy - 26, $c2 - $c1 - 60, 52, 'md-part', 0, 'cyh');
        self::earOn($s, 'left', $c1, $cy, 31, 52);
        self::earOn($s, 'right', $c2, $cy, 31, 52);
        self::yoke($s, 'right', $c1, $cy, $c1 - $f1 + 1, 96, ['series' => [40, 34]]);
        self::yoke($s, 'left', $c2, $cy, $f2 - $c2 + 1, 96);
        $s->rect($f1 - 14, $cy - 72, 14, 144, 'md-part', 2, 'cyh')->rect($f2, $cy - 72, 14, 144, 'md-part', 2, 'cyh');
        foreach ([$f1 - 14, $f2] as $x) {   // bolt holes through the flanges
            foreach ([-56, 56] as $y) {
                $s->hid($x, $cy + $y - 6, $x + 14, $cy + $y - 6)->hid($x, $cy + $y + 6, $x + 14, $cy + $y + 6)->cl($x - 6, $cy + $y, $x + 20, $cy + $y);
            }
        }
        $s->cl($f1 - 26, $cy, $f2 + 26, $cy);
        $s->dimH(['pa_cl-to-cl', 'pa_cj-to-cj'], $c1, $c2, 30, $cy - 84, $cy - 84);
        $s->dimH(['pa_flange-to-flange', 'pa_overall-length'], $f1 - 14, $f2 + 14, 238, $cy + 72, $cy + 72);

        return $s;
    }

    /** hanger bearing seen along the shaft: mounting plate, strap, rubber cushion, ball bearing */
    public static function centerBearing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 236;
        $cy = 148;
        $base = 64;
        $s->rect($cx - 152, $base, 304, 8, 'md-part', 1.5, 'cyh');
        foreach ([-1, 1] as $k) {   // mounting holes through the plate
            $s->hid($cx + 128 * $k - 6, $base, $cx + 128 * $k - 6, $base + 8)->hid($cx + 128 * $k + 6, $base, $cx + 128 * $k + 6, $base + 8);
            $s->cl($cx + 128 * $k, $base - 14, $cx + 128 * $k, $base + 20);
        }
        $s->shape(array_merge([['M', $cx - 93, $base + 7], ['L', $cx - 93, $cy]], Sketch::arc($cx, $cy, 93, 180, 360, true),
            [['L', $cx + 93, $base + 7], ['L', $cx + 86, $base + 7], ['L', $cx + 86, $cy]], Sketch::arc($cx, $cy, 86, 360, 180, true),
            [['L', $cx - 86, $base + 7], ['Z']]), 'md-part', null, 'cast');
        $s->circle($cx, $cy, 85, 'md-part', 'rubber');
        foreach ([[28, 152], [208, 332]] as [$a0, $a1]) {   // the cushion's relief slots
            $s->shape(array_merge(Sketch::arc($cx, $cy, 71, $a0, $a1), Sketch::arc($cx, $cy, 59, $a1, $a0, true), [['Z']]), 'md-hole');
        }
        $s->circle($cx, $cy, 49, 'md-part', 'face')->circle($cx, $cy, 45, 'md-edge');
        for ($i = 0; $i < 13; $i++) {
            $a = deg2rad($i * 360 / 13);
            $s->circle($cx + 38 * cos($a), $cy - 38 * sin($a), 5.2, 'md-ball', 'ball');
        }
        $s->circle($cx, $cy, 31, 'md-part', 'face')->circle($cx, $cy, 25, 'md-hole');
        $s->cl($cx - 104, $cy, $cx + 104, $cy)->cl($cx, $base - 24, $cx, $cy + 104);
        $s->dimH(['pa_ctr-to-ctr-of-mount-holes', 'pa_center-to-center'], $cx - 128, $cx + 128, 30, $base - 16, $base - 16);
        $s->dimV(['pa_center-bearing-to-base', 'pa_c-l-to-base'], $base, $cy, $cx + 178, $cx + 154, $cx + 106);
        $k = M_SQRT1_2;
        $s->dim(['pa_bearing-id', 'pa_inside-dia'], $cx - 25 * $k, $cy + 25 * $k, $cx + 25 * $k, $cy - 25 * $k, ['t' => .5]);

        return $s;
    }

    /**
     * U-joint cross with its bearings, seen along the shaft, at $k pixels per unit (a snap-ring cap ends 100 units from
     * the centre). $ax = [vertical, horizontal] axis, each [bearing, cap radius]; bearings: osr / isr snap ring, plain
     * cap, bp bearing plate, wb wing bearing.
     * Returns per axis, in pixels: 'r' cap radius, 'end' outermost point, 'm' half the width as it is measured, 'w' half
     * width of the face it is measured from, 'tag' a point on what retains the bearing (u along the arm, v across) or null.
     */
    private static function cross(Sketch $s, float $cx, float $cy, float $k, array $ax, bool $zerk): array
    {
        $w = 27;   // forged arm, half width
        $body = [];
        foreach ([[1, 0], [0, 1], [-1, 0], [0, -1]] as $i => [$dx, $dy]) {
            foreach ([[40, -$w], [50, -$w], [50, $w], [40, $w]] as $j => [$u, $v]) {
                $body[] = [$i + $j ? 'L' : 'M', $dx * $u - $dy * $v, $dy * $u + $dx * $v];
            }
            $body[] = ['Q', ($dx - $dy) * $w, ($dy + $dx) * $w, $dx * $w - $dy * 40, $dy * $w + $dx * 40];   // crotch fillet
        }
        $s->shape(array_merge($body, [['Z']]), 'md-part', fn ($x, $y) => [$cx + $k * $x, $cy + $k * $y], 'cast');
        $out = [];
        foreach ([[0, -1], [0, 1], [-1, 0], [1, 0]] as [$dx, $dy]) {
            [$type, $r] = $ax[$dx ? 1 : 0];
            $at = fn ($u, $v) => [$cx + $k * ($dx * $u - $dy * $v), $cy + $k * ($dy * $u + $dx * $v)];   // u along the arm, v across
            $box = fn ($u1, $u2, $v, $c = 0) => [['M', $u1, $c - $v], ['L', $u2, $c - $v], ['L', $u2, $c + $v], ['L', $u1, $c + $v], ['Z']];
            $fill = $dx ? 'cyh' : 'cyv';
            $end = $type === 'isr' ? 106 : 100;
            $s->shape([['M', 41, -$w], ['Q', 35, 0, 41, $w]], 'md-edge', $at);   // arm blending into the body
            $s->shape($box(50, 56, 24), 'md-part', $at, $fill);                 // machined seal seat
            $s->shape([['M', 56, -$r + 6], ['L', 62, -$r + 3], ['L', 62, $r - 3], ['L', 56, $r - 6], ['Z']], 'md-part', $at, 'rubber');
            if ($type === 'wb') {   // block bolted to the yoke face: the holes run along the shaft
                $g = $r + 26;
                $s->shape($box(62, 72, $r + 2), 'md-part', $at, $fill);
                $s->shape([['M', 70, -$g], ['L', 94, -$g], ['Q', 100, -$g, 100, -$g + 6], ['L', 100, $g - 6], ['Q', 100, $g, 94, $g], ['L', 70, $g], ['Z']], 'md-part', $at, 'cast');
                foreach ([-1, 1] as $sg) {
                    $s->hid(70, $sg * $r, 100, $sg * $r, $at);
                    [$hx, $hy] = $at(85, $sg * ($r + 13));
                    $s->circle($hx, $hy, 6.5 * $k, 'md-hole');
                }
            } else {
                $s->shape([['M', 62, -$r], ['L', $end - 5, -$r], ['Q', $end, -$r, $end, -$r + 5], ['L', $end, $r - 5], ['Q', $end, $r, $end - 5, $r],
                    ['L', 62, $r], ['Z']], 'md-part', $at, $fill);
                $s->line(65.5, -$r, 65.5, $r, 'md-edge', $at)->line($end - 4.5, -$r + 1.5, $end - 4.5, $r - 1.5, 'md-edge', $at);
            }
            if ($type === 'isr') {         // clip in the groove of the cap, against the inside of the yoke ear
                $s->shape($box(69.5, 73.5, $r + 6), 'md-part', $at, 'rubber');
            } elseif ($type === 'osr') {   // ring in the yoke ear, against the end of the cap
                $s->shape($box($end + .5, $end + 4, $r + 4), 'md-part', $at, 'rubber');
            } elseif ($type === 'bp') {    // plate welded on the cap, bolted to the outside of the yoke ear
                $s->shape($box(105.5, 111.5, 5.5, -$r - 14), 'md-part', $at, $fill)->shape($box(105.5, 111.5, 5.5, $r + 14), 'md-part', $at, $fill);
                $s->shape($box(100, 106.5, $r + 24), 'md-part', $at, 'cast');
            }
            $out[$dx ? 1 : 0] = ['r' => $r * $k, 'end' => ($type === 'bp' ? 112 : $end) * $k, 'm' => ($type === 'isr' ? 73.5 : 100) * $k,
                'w' => ($r + (['isr' => 6, 'bp' => 24, 'wb' => 26][$type] ?? 0)) * $k,
                'tag' => ['isr' => [71.5 * $k, ($r + 6) * $k], 'osr' => [102.2 * $k, ($r + 4) * $k], 'bp' => [103.2 * $k, ($r + 24) * $k], 'wb' => [85 * $k, ($r + 19.5) * $k]][$type] ?? null];
        }
        $s->circle($cx, $cy, 16 * $k, 'md-edge');
        if ($zerk) {   // grease fitting in the cross, tilted towards a crotch so the gun reaches it between the yoke ears
            $z = fn ($a, $b) => [$cx + $k * ($b - $a) * M_SQRT1_2, $cy + $k * ($a + $b) * M_SQRT1_2];
            $s->shape([['M', 1, -8.5], ['L', 11, -8.5], ['L', 11, 8.5], ['L', 1, 8.5], ['Z']], 'md-part', $z, 'cyh');   // hex
            $s->line(1, -4.2, 11, -4.2, 'md-edge', $z)->line(1, 4.2, 11, 4.2, 'md-edge', $z);
            $s->shape([['M', 11, -3.6], ['L', 15, -3.6], ['L', 16.5, -5.8], ['L', 20.5, -5.8], ['L', 23.5, -3.2], ['L', 23.5, 3.2], ['L', 20.5, 5.8], ['L', 16.5, 5.8],
                ['L', 15, 3.6], ['L', 11, 3.6], ['Z']], 'md-part', $z, 'cyh');   // neck and head
            [$bx, $by] = $z(23.6, 0);
            $s->circle($bx, $by, 2.7 * $k, 'md-ball', 'ball');
        } else {
            $s->circle($cx, $cy, 6 * $k, 'md-edge');
        }
        $s->cl($cx - $out[1]['end'] - 12, $cy, $cx + $out[1]['end'] + 12, $cy)->cl($cx, $cy - $out[0]['end'] - 12, $cx, $cy + $out[0]['end'] + 12);

        return $out;
    }

    /**
     * Cross and bearings. The width is taken over the cap ends on an outside-snap-ring joint and between the clips on
     * an inside-snap-ring one, so the category sheet shows both; a part's own page draws its style ($vals = its specs).
     */
    public static function uJoint(array $codes, array $vals = []): Sketch
    {
        $s = new Sketch($codes);
        $num = fn (string $k) => (float) preg_replace('/[^0-9.]/', '', (string) ($vals[$k] ?? ''));
        $style = strtoupper(preg_replace('/\s+/', '', (string) ($vals['pa_style'] ?? '')));
        $is = fn (string ...$any) => (bool) array_filter($any, fn ($x) => str_contains($style, $x));
        $wing = $is('WB', 'LWT', 'LWD');
        $axes = match (true) {
            $is('OSR') && $is('ISR') => 'mixed',
            $wing => [$is('OSR') ? 'osr' : 'wb', 'wb'],
            $is('BP') => [$is('HR') ? 'plain' : 'bp', 'bp'],
            $is('OSR') => ['osr', 'osr'],
            $is('ISR') => ['isr', 'isr'],
            $is('STAKED', 'HR', 'SPRTAB') => ['plain', 'plain'],
            default => null,   // combination or unknown
        };
        $r2 = 33.0;
        if (isset($codes['pa_cap-dia-2']) || isset($codes['pa_width-2'])) {   // conversion joint: a second size on the other axis
            [$c1, $c2] = [$num('pa_cap-dia-1'), $num('pa_cap-dia-2')];
            $q = [$c1 ? $num('pa_width-1') / $c1 : 0, $c2 ? $num('pa_width-2') / $c2 : 0];   // an inside-lock axis is narrow for its cap
            if ($c1 && $c2) {
                $r2 = 33 * min(1.2, max(.85, $c2 / $c1));
            }
            if ($axes === 'mixed') {
                $axes = $q[1] && $q[1] < $q[0] ? ['osr', 'isr'] : ['isr', 'osr'];
            } elseif (!$axes) {
                $axes = array_map(fn ($x) => $x && $x < 2.45 ? 'isr' : 'osr', $q);
            }
        }
        $axes = is_array($axes) ? $axes : null;
        $cap = ['pa_cap-dia-1', 'pa_cap-dia', 'pa_bearing-dia'];
        $wid = ['pa_width-1', 'pa_width', 'pa_overall-length'];
        $names = ['osr' => 'Outside snap ring (OSR)', 'isr' => 'Inside snap ring (ISR)'];
        $view = function (float $cx, float $cy, float $k, array $t, bool $zerk, bool $full) use ($s, $cap, $wid, $r2): array {
            $g = self::cross($s, $cx, $cy, $k, [[$t[0], 33], [$t[1], $r2]], $zerk);
            [$v, $h] = $g;
            $top = $cy - ($t[0] === 'isr' ? 106 : 100) * $k;
            $s->dim($cap, $cx - $v['r'], $top + 15 * $k, $cx + $v['r'], $top + 15 * $k);
            $s->hi($cap, [[$cx - $v['r'], $top, $cx - $v['r'], $cy - 62 * $k], [$cx + $v['r'], $top, $cx + $v['r'], $cy - 62 * $k]]);
            $s->dimV($wid, $cy - $v['m'], $cy + $v['m'], $cx + $h['end'] + 28 * $k, $cx + $v['w'], $cx + $v['w']);
            $s->hi($wid, [[$cx - $v['w'], $cy - $v['m'], $cx + $v['w'], $cy - $v['m']], [$cx - $v['w'], $cy + $v['m'], $cx + $v['w'], $cy + $v['m']]]);
            $far = in_array('wb', $t, true) ? 78 : 74;   // badges clear of the wing blocks
            if ($t[1] === 'isr') {
                $s->tag('pa_style', $cx - $h['tag'][0], $cy + $h['tag'][1], $cx - 105 * $k, $cy + 68 * $k);
            } elseif ($h['tag'] && $t[0] === 'isr') {   // clear of the width's extension line
                $s->tag('pa_style', $cx - $h['tag'][0] - 1.8 * $k, $cy + $h['tag'][1], $cx - 128 * $k, $cy + 66 * $k);
            } elseif ($h['tag']) {
                $s->tag('pa_style', $cx + $h['tag'][0], $cy - $h['tag'][1], $cx + $far * $k, $cy - $far * $k);
            }
            if ($full) {
                $s->tag(['pa_series', 'pa_style-series'], $cx - 30 * $k, $cy - 30 * $k, $cx - ($far - 2) * $k, $cy - ($far - 2) * $k);
                $z = $zerk ? 18.5 : 11;
                $s->tag('pa_lube', $cx - $z * $k, $cy + $z * $k, $cx - ($far > 74 ? 80 : 54) * $k, $cy + 80 * $k);
            }

            return $g;
        };
        if (!$axes) {
            $view(112, 128, .78, ['osr', 'osr'], true, true);
            $view(346, 128, .78, ['isr', 'isr'], false, false);
            $s->note(112, 252, $names['osr'])->note(346, 252, $names['isr']);

            return $s;
        }
        $zerk = !in_array(strtoupper(trim((string) ($vals['pa_lube'] ?? ''))), ['N', 'NO'], true);
        $k = in_array('bp', $axes, true) ? .9 : (in_array($axes, [['osr', 'osr'], ['plain', 'plain']], true) ? 1.0 : .95);
        [$cx, $cy] = [226, 132];
        [$v, $h] = $view($cx, $cy, $k, $axes, $zerk, true);
        $x = $cx + ($axes[1] === 'isr' ? 91 : 85) * $k;
        $s->dim('pa_cap-dia-2', $x, $cy - $h['r'], $x, $cy + $h['r']);
        $s->dimH('pa_width-2', $cx - $h['m'], $cx + $h['m'], $cy + $v['end'] + 4 * $k + 20, $cy + $h['w'], $cy + $h['w']);
        if ($axes[0] === $axes[1] && isset($names[$axes[0]]) && !isset($codes['pa_width-2'])) {
            $s->note($cx, 262, $names[$axes[0]]);
        }

        return $s;
    }
}
