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
        self::turned($s, [[30, 25], [33, 28], [132, 28], [138, 20], [204, 20], [228, 48], [228, 52], [$sh, 52], [$sh, 45], [$sh + 30, 45], [$sh + 32, 43]], $cy,
            ['cut' => [196, $sh + 32], 'bores' => [[[218, 33], [$sh + 32, 33]]]]);
        self::teeth($s, 30, 132, $cy, 28, 'right');
        self::phantomTube($s, $sh, $sh + 90, $cy, 52, 45, $sh + 32);
        $s->cl(20, $cy, $sh + 98, $cy);
        $s->dimH('pa_splines-length', 30, 132, 44, $cy - 28, $cy - 28);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 30, $sh, 222, $cy + 28, $cy + 54);
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
        self::shaftTube($s, 122, 296, $cy, 34);
        self::turned($s, [[296, 34], [312, 20], [372, 20], [372, 17], [436, 17], [439, 14]], $cy);
        self::teeth($s, 372, 439, $cy, 17, 'left');
        $s->rect($cb - 38, $cy - 66, 76, 7, 'md-part', 1.5, 'cyh');
        $s->shape([['M', $cb - 29, $cy - 59], ['L', $cb - 29, $cy + 18], ['Q', $cb - 29, $cy + 46, $cb, $cy + 46], ['Q', $cb + 29, $cy + 46, $cb + 29, $cy + 18], ['L', $cb + 29, $cy - 59], ['Z']], 'md-part', null, 'cast');
        $s->rect($cb - 23, $cy - 52, 46, 92, 'md-part', 8, 'rubber');
        $s->rect($cb - 12, $cy - 27, 24, 54, 'md-part', 2, 'cyh');
        self::shaftEnd($s, 'left', 64, $cy, 59, 68, ['series' => [22, 40]]);
        $s->cl(14, $cy, 450, $cy)->cl($cb, $cy - 80, $cb, $cy + 62);
        $s->dimH(['pa_cl-to-cb', 'pa_center-to-center'], 64, $cb, 236, $cy + 84, $cy + 48);

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

    /** cross with its four bearing caps, seals and grease fitting */
    public static function uJoint(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 212;
        $cy = 136;
        $a = 14;    // trunnion half width
        $e = 50;    // cross shoulder
        $r = 22;    // cap radius
        $o = 102;   // centre to cap end
        $s->shape([['M', -$a, -$e], ['L', $a, -$e], ['L', $a, -28], ['Q', $a, -$a, 28, -$a], ['L', $e, -$a], ['L', $e, $a], ['L', 28, $a], ['Q', $a, $a, $a, 28],
            ['L', $a, $e], ['L', -$a, $e], ['L', -$a, 28], ['Q', -$a, $a, -28, $a], ['L', -$e, $a], ['L', -$e, -$a], ['L', -28, -$a], ['Q', -$a, -$a, -$a, -28], ['Z']],
            'md-part', fn ($x, $y) => [$cx + $x, $cy + $y], 'cast');
        foreach ([[0, -1], [0, 1], [-1, 0], [1, 0]] as [$dx, $dy]) {
            $at = fn ($u, $v) => [$cx + $dx * $u - $dy * $v, $cy + $dy * $u + $dx * $v];   // u along the arm, v across
            $fill = $dx ? 'cyh' : 'cyv';
            $s->shape([['M', $e - 4, -$r + 4], ['L', $e + 3, -$r + 4], ['L', $e + 3, $r - 4], ['L', $e - 4, $r - 4], ['Z']], 'md-part', $at, 'rubber');
            $s->shape([['M', $e + 2, -$r], ['L', $o - 5, -$r], ['Q', $o, -$r, $o, -$r + 5], ['L', $o, $r - 5], ['Q', $o, $r, $o - 5, $r], ['L', $e + 2, $r], ['Z']], 'md-part', $at, $fill);
            foreach ([$o - 11, $o - 8] as $u) {   // snap-ring groove
                [$x1, $y1] = $at($u, -$r);
                [$x2, $y2] = $at($u, $r);
                $s->line($x1, $y1, $x2, $y2, 'md-edge');
            }
            [$x1, $y1] = $at($e + 7, -$r);
            [$x2, $y2] = $at($e + 7, $r);
            $s->line($x1, $y1, $x2, $y2, 'md-edge');
        }
        $s->circle($cx, $cy, 13, 'md-part', 'face');
        self::hex($s, $cx, $cy, 7);
        $s->circle($cx, $cy, 3.5, 'md-ball', 'ball');
        $s->cl($cx - $o - 14, $cy, $cx + $o + 14, $cy)->cl($cx, $cy - $o - 14, $cx, $cy + $o + 14);
        $s->dimH(['pa_cap-dia-1', 'pa_cap-dia', 'pa_bearing-dia'], $cx - $r, $cx + $r, 16, $cy - $o, $cy - $o);
        $s->dimV(['pa_width-1', 'pa_width', 'pa_overall-length'], $cy - $o, $cy + $o, $cx + 144, $cx + $r, $cx + $r);
        $s->tag('pa_lube', $cx - 5, $cy + 5, $cx - 96, $cy + 96);
        $s->tag(['pa_series', 'pa_style-series'], $cx - $o + 20, $cy - $r, $cx - 112, $cy - 92);
        $s->tag('pa_cap-dia-2', $cx + $o - 20, $cy - $r, $cx + 100, $cy - 92);

        return $s;
    }
}
