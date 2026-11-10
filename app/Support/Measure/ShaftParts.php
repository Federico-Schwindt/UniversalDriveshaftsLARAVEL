<?php

namespace App\Support\Measure;

/** Shafts and tube: spline stubs, splined sleeves, tubing, assemblies, centre bearings, u-joints. */
trait ShaftParts
{
    /** weld-in body shared by stubs and sleeves: neck at $x0 (radius $r0) flaring to the weld shoulder, then the butt */
    private static function weldEnd(Sketch $s, float $x0, float $r0, float $cy, float $flare): float
    {
        $sh = $flare + 32;
        $s->shape([['M', $x0, $cy - $r0], ['L', $flare, $cy - $r0], ['L', $sh - 10, $cy - 48], ['L', $sh - 10, $cy - 52], ['L', $sh, $cy - 52],
            ['L', $sh, $cy - 45], ['L', $sh + 32, $cy - 45], ['L', $sh + 32, $cy + 45], ['L', $sh, $cy + 45], ['L', $sh, $cy + 52],
            ['L', $sh - 10, $cy + 52], ['L', $sh - 10, $cy + 48], ['L', $flare, $cy + $r0], ['L', $x0, $cy + $r0], ['Z']]);
        $s->hid($sh - 8, $cy - 33, $sh + 32, $cy - 33)->hid($sh - 8, $cy + 33, $sh + 32, $cy + 33);
        $s->line($sh, $cy - 52, $sh + 88, $cy - 52, 'md-ph')->line($sh, $cy + 52, $sh + 88, $cy + 52, 'md-ph');
        $s->line($sh + 32, $cy - 45, $sh + 88, $cy - 45, 'md-ph')->line($sh + 32, $cy + 45, $sh + 88, $cy + 45, 'md-ph');
        $s->dimV('pa_butt-dia', $cy - 45, $cy + 45, $sh + 16, null, null, ['t' => .26]);

        return $sh;
    }

    /** externally splined stub: side view with the tube in phantom + spline end view */
    public static function splineStub(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 126;
        self::splines($s, 30, 132, $cy, 28);
        $sh = self::weldEnd($s, 132, 20, $cy, 206);
        $s->cl(20, $cy, $sh + 96, $cy);
        $s->dimH('pa_splines-length', 30, 132, 42, $cy - 28, $cy - 28);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 30, $sh, 222, $cy + 28, $cy + 54);
        $s->dimV(['pa_tube-dia', 'pa_tube-dia-wall', 'pa_fits-to-tube'], $cy - 52, $cy + 52, $sh + 106, $sh + 88, $sh + 88);
        $s->dim('pa_tube-wall', $sh + 70, $cy - 45, $sh + 70, $cy - 52);

        $ex = 432;
        self::gear($s, $ex, $cy, 30, 36, 16, 'md-part');
        $s->circle($ex, $cy, 4, 'md-thin');
        $s->cl($ex - 46, $cy, $ex + 46, $cy)->cl($ex, $cy - 46, $ex, $cy + 46);
        $s->dimV(['pa_splines-count-dia', 'pa_splines-dia'], $cy - 36, $cy + 36, $ex, null, null, ['t' => .3]);
        $s->tag('pa_splines-count', $ex + 26, $cy + 26, $ex + 20, $cy + 84);

        return $s;
    }

    /** internally splined weld-in sleeve: side view with the tube in phantom + sleeve end */
    public static function splinedSleeve(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 126;
        $s->rect(30, $cy - 36, 140, 72);
        $s->hid(30, $cy - 22, 170, $cy - 22)->hid(30, $cy + 22, 170, $cy + 22);
        $sh = self::weldEnd($s, 170, 36, $cy, 196);
        $s->cl(20, $cy, $sh + 96, $cy);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 30, $sh, 222, $cy + 38, $cy + 54);
        $s->dimV(['pa_fits-to-tube', 'pa_tube-dia-wall', 'pa_tube-dia'], $cy - 52, $cy + 52, $sh + 106, $sh + 88, $sh + 88);

        $ex = 424;
        $s->circle($ex, $cy, 48);
        self::gear($s, $ex, $cy, 26, 31, 16);
        $s->cl($ex - 58, $cy, $ex + 58, $cy)->cl($ex, $cy - 58, $ex, $cy + 58);
        $s->dimV(['pa_splines-dia', 'pa_splines-count-dia'], $cy - 31, $cy + 31, $ex, null, null, ['t' => .3]);
        $s->tag('pa_splines-count', $ex + 22, $cy + 22, $ex + 20, $cy + 84);
        $s->tag(['pa_series', 'pa_style-series'], $ex - 40, $cy - 27, $ex - 30, $cy - 84);

        return $s;
    }

    /** length of tube with a break + tube end */
    public static function tubing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 120;
        $r = 40;
        $s->shape([['M', 30, $cy - $r], ['L', 150, $cy - $r], ['Q', 162, $cy - $r / 2, 150, $cy], ['Q', 138, $cy + $r / 2, 150, $cy + $r], ['L', 30, $cy + $r], ['Z']]);
        $s->shape([['M', 172, $cy - $r], ['Q', 184, $cy - $r / 2, 172, $cy], ['Q', 160, $cy + $r / 2, 172, $cy + $r], ['L', 296, $cy + $r], ['L', 296, $cy - $r], ['Z']]);
        foreach ([-1, 1] as $k) {
            $s->hid(30, $cy + 33 * $k, 146, $cy + 33 * $k)->hid(176, $cy + 33 * $k, 296, $cy + 33 * $k);
        }
        $s->cl(20, $cy, 306, $cy);
        $s->dimH(['pa_tube-length', 'pa_length'], 30, 296, $cy + 86, $cy + $r, $cy + $r);
        self::tube($s, 394, $cy, 62, 52, $cy + 94);

        return $s;
    }

    /** u-joint end in profile with the cross cap facing the viewer */
    private static function shaftEnd(Sketch $s, string $dir, float $cx, float $cy, float $L, float $N, array $o = []): void
    {
        self::yoke($s, $dir, $cx, $cy, $L, $N, $o);
        $s->circle($cx, $cy, 15, 'md-line')->circle($cx, $cy, 6, 'md-thin');
    }

    /** complete one-piece shaft */
    public static function driveshaft(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 130;
        self::shaftEnd($s, 'left', 74, $cy, 56, 70, ['series' => [30, 36]]);
        self::shaftEnd($s, 'right', 406, $cy, 56, 70);
        $s->rect(130, $cy - 34, 220, 68);
        $s->line(140, $cy - 34, 140, $cy + 34, 'md-thin')->line(340, $cy - 34, 340, $cy + 34, 'md-thin');
        $s->cl(28, $cy, 452, $cy);
        $s->dimV(['pa_tube-diameter', 'pa_tube-dia', 'pa_tube-dia-wall'], $cy - 34, $cy + 34, 240, null, null, ['t' => .3]);
        $s->dimH(['pa_overall-length', 'pa_length', 'pa_cl-to-cl'], 74, 406, 230, $cy + 78, $cy + 78);

        return $s;
    }

    /** front shaft of a two-piece set: u-joint, tube, centre bearing on its stub, splined end */
    public static function twoPiece(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 136;
        $cb = 336;
        self::shaftEnd($s, 'left', 66, $cy, 56, 70, ['series' => [24, 40]]);
        $s->rect(122, $cy - 34, 176, 68);
        $s->shape([['M', 298, $cy - 34], ['L', 312, $cy - 20], ['L', 360, $cy - 20], ['L', 360, $cy + 20], ['L', 312, $cy + 20], ['L', 298, $cy + 34], ['Z']]);
        self::splines($s, 360, 428, $cy, 17);
        $s->rect($cb - 34, $cy - 62, 68, 8);
        $s->shape([['M', $cb - 26, $cy - 54], ['L', $cb - 26, $cy + 20], ['Q', $cb - 26, $cy + 44, $cb, $cy + 44], ['Q', $cb + 26, $cy + 44, $cb + 26, $cy + 20], ['L', $cb + 26, $cy - 54], ['Z']]);
        $s->rect($cb - 13, $cy - 26, 26, 52, 'md-line', 3);
        $s->cl(20, $cy, 440, $cy)->cl($cb, $cy - 76, $cb, $cy + 60);
        $s->dimH(['pa_cl-to-cb', 'pa_center-to-center'], 66, $cb, 232, $cy + 78, $cy + 62);

        return $s;
    }

    /** short coupled shaft: flange yoke, joint, coupled centre, joint, flange yoke */
    public static function shortCouple(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 134;
        $f1 = 98;
        $c1 = 160;
        $c2 = 320;
        $f2 = 382;
        self::earOn($s, 'left', $c1, $cy, 30, 50);
        self::earOn($s, 'right', $c2, $cy, 30, 50);
        $s->rect($c1 + 30, $cy - 25, $c2 - $c1 - 60, 50);
        self::yoke($s, 'right', $c1, $cy, $c1 - $f1, 92, ['series' => [40, 34]]);
        self::yoke($s, 'left', $c2, $cy, $f2 - $c2, 92);
        $s->rect($f1 - 14, $cy - 70, 14, 140)->rect($f2, $cy - 70, 14, 140);
        $s->cl($f1 - 26, $cy, $f2 + 26, $cy);
        $s->dimH(['pa_cl-to-cl', 'pa_cj-to-cj'], $c1, $c2, 36, $cy - 78, $cy - 78);
        $s->dimH(['pa_flange-to-flange', 'pa_overall-length'], $f1 - 14, $f2 + 14, 236, $cy + 70, $cy + 70);

        return $s;
    }

    /** hanger bearing seen along the shaft: mounting feet, strap, cushion, bearing */
    public static function centerBearing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 236;
        $cy = 146;
        $base = 66;
        $s->shape(array_merge([['M', $cx - 90, $base + 6], ['L', $cx - 90, $cy]], Sketch::arc($cx, $cy, 90, 180, 360, true),
            [['L', $cx + 90, $base + 6], ['L', $cx + 83, $base + 6], ['L', $cx + 83, $cy]], Sketch::arc($cx, $cy, 83, 360, 180, true),
            [['L', $cx - 83, $base + 6], ['Z']]));
        $s->rect($cx - 150, $base, 300, 7);
        foreach ([-1, 1] as $k) {
            $s->hid($cx + 128 * $k - 6, $base, $cx + 128 * $k - 6, $base + 7)->hid($cx + 128 * $k + 6, $base, $cx + 128 * $k + 6, $base + 7);
        }
        $s->circle($cx, $cy, 82)->circle($cx, $cy, 46)->circle($cx, $cy, 38, 'md-thin')->circle($cx, $cy, 26, 'md-hole');
        $s->cl($cx - 100, $cy, $cx + 100, $cy)->cl($cx, $base - 24, $cx, $cy + 100);
        foreach ([-1, 1] as $k) {
            $s->cl($cx + 128 * $k, $base - 14, $cx + 128 * $k, $base + 20);
        }
        $s->dimH(['pa_ctr-to-ctr-of-mount-holes', 'pa_center-to-center'], $cx - 128, $cx + 128, 30, $base - 16, $base - 16);
        $s->dimV(['pa_center-bearing-to-base', 'pa_c-l-to-base'], $base, $cy, $cx + 176, $cx + 152, $cx + 102);
        $k = M_SQRT1_2;
        $s->dim(['pa_bearing-id', 'pa_inside-dia'], $cx - 26 * $k, $cy + 26 * $k, $cx + 26 * $k, $cy - 26 * $k, ['t' => .5]);

        return $s;
    }

    /** cross and bearing caps */
    public static function uJoint(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 214;
        $cy = 136;
        $a = 15;   // trunnion half width
        $f = 27;   // where the fillets start
        $e = 52;   // trunnion length to the cap
        $s->shape([['M', -$a, -$e], ['L', $a, -$e], ['L', $a, -$f], ['Q', $a, -$a, $f, -$a], ['L', $e, -$a], ['L', $e, $a], ['L', $f, $a], ['Q', $a, $a, $a, $f],
            ['L', $a, $e], ['L', -$a, $e], ['L', -$a, $f], ['Q', -$a, $a, -$f, $a], ['L', -$e, $a], ['L', -$e, -$a], ['L', -$f, -$a], ['Q', -$a, -$a, -$a, -$f], ['Z']],
            'md-part', fn ($x, $y) => [$cx + $x, $cy + $y]);
        $r = 22;
        $o = 100;
        foreach ([[0, -1], [0, 1], [-1, 0], [1, 0]] as [$dx, $dy]) {
            if ($dx) {
                $x = $dx < 0 ? $cx - $o : $cx + $e;
                $s->rect($x, $cy - $r, $o - $e, 2 * $r, 'md-part', 4);
                $g = $dx < 0 ? $cx - $o + 9 : $cx + $o - 9;
                $s->line($g, $cy - $r, $g, $cy + $r, 'md-thin');
            } else {
                $y = $dy < 0 ? $cy - $o : $cy + $e;
                $s->rect($cx - $r, $y, 2 * $r, $o - $e, 'md-part', 4);
                $g = $dy < 0 ? $cy - $o + 9 : $cy + $o - 9;
                $s->line($cx - $r, $g, $cx + $r, $g, 'md-thin');
            }
        }
        self::hex($s, $cx, $cy, 8);
        $s->circle($cx, $cy, 3, 'md-hole');
        $s->cl($cx - $o - 14, $cy, $cx + $o + 14, $cy)->cl($cx, $cy - $o - 14, $cx, $cy + $o + 14);
        $s->dimH(['pa_cap-dia-1', 'pa_cap-dia', 'pa_bearing-dia'], $cx - $r, $cx + $r, 16, $cy - $o, $cy - $o);
        $s->dimV(['pa_width-1', 'pa_width', 'pa_overall-length'], $cy - $o, $cy + $o, $cx + 142, $cx + $r, $cx + $r);
        $s->tag('pa_lube', $cx - 6, $cy + 6, $cx - 96, $cy + 96);
        $s->tag(['pa_series', 'pa_style-series'], $cx - $o + 20, $cy - $r, $cx - 112, $cy - 92);
        $s->tag('pa_cap-dia-2', $cx + $o - 20, $cy - $r, $cx + 100, $cy - 92);

        return $s;
    }
}
