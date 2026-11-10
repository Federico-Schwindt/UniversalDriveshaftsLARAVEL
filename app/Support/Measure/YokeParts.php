<?php

namespace App\Support\Measure;

/** Yokes: flange, tube (weld), end, slip, transmission, yoke shafts, H, CV link, CV heads and prong yokes. */
trait YokeParts
{
    /** side view (ears up, flange below) + flange face */
    public static function flangeYoke(array $codes, bool $cv = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 128;
        $cy = 76;
        self::yoke($s, 'up', $cx, $cy, 74, 100, ['series' => [34, 34]]);
        $s->rect($cx - 86, 150, 172, 15)->rect($cx - 36, 165, 72, 8);
        $s->cl($cx, $cy - 46, $cx, 190);
        if ($cv) {   // centring ball stud standing in the throat
            $s->rect($cx - 6, $cy + 10, 12, 24, 'md-part', 3);
            $s->tag(['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], $cx + 6, $cy + 16, $cx + 44, $cy - 50);
        }
        $s->dimV(['pa_cj-to-flat', 'pa_c-l-to-face'], $cy, 165, $cx + 112, $cx + 78, $cx + 88);
        $s->dimH(['pa_pilot-dia', 'pa_bolt-hole-pilot-dia'], $cx - 36, $cx + 36, 200, 173, 173);

        $fx = 362;
        $fy = 132;
        $s->circle($fx, $fy, 94)->circle($fx, $fy, 36, 'md-line')->circle($fx, $fy, 20, 'md-hole');
        $s->cl($fx - 104, $fy, $fx + 104, $fy)->cl($fx, $fy - 104, $fx, $fy + 104);
        self::bolts($s, $fx, $fy, 68, 8, 4, ['bc' => [1, 3], 't' => .2, 'dia' => [0, 45], 'count' => [288, 30], 'countHole' => 1]);

        return $s;
    }

    public static function cvFlangeYoke(array $codes): Sketch
    {
        return self::flangeYoke($codes, true);
    }

    /** side view of a tube yoke with the tube shown in phantom over the butt + the tube end */
    public static function weldYoke(array $codes, bool $stud = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 136;
        $cy = 70;
        self::yoke($s, 'up', $cx, $cy, 96, 92, ['series' => [34, 32]]);
        $s->rect($cx - 40, 166, 80, 28);
        $s->line($cx - 46, 166, $cx - 46, 214, 'md-ph')->line($cx + 46, 166, $cx + 46, 214, 'md-ph');
        $s->cl($cx, $cy - 46, $cx, 252);
        if ($stud) {
            $s->rect($cx - 6, $cy + 8, 12, 26, 'md-part', 3);
            $s->tag(['pa_ball-stud-length-dia', 'pa_ball-stud-dia'], $cx + 6, $cy + 14, $cx + 40, $cy - 52);
        }
        $s->dimV(['pa_center-u-j-to-weld', 'pa_c-l-to-weld'], $cy, 166, $cx + 104, $cx + 78, $cx + 48);
        $s->dimH('pa_butt-dia', $cx - 40, $cx + 40, 238, 196, 196);
        self::tube($s, 370, 118, 62, 53, 216);

        return $s;
    }

    public static function ballStudYoke(array $codes): Sketch
    {
        return self::weldYoke($codes, true);
    }

    /** side view (ears left, hub right) + the hub end: splined bore, or a round bore with keyway for PTO yokes */
    public static function endYoke(array $codes, bool $pto = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 92;
        $cy = 120;
        $x1 = $cx + 50;
        $x2 = $cx + 146;
        $hr = 36;
        self::yoke($s, 'left', $cx, $cy, 50, 80, ['series' => [26, 26]]);
        $s->rect($x1, $cy - $hr, $x2 - $x1, 2 * $hr);
        $step = $x1 + 16;
        foreach ([-1, 1] as $k) {
            $s->hid($cx + 34, $cy + 28 * $k, $step, $cy + 28 * $k)->hid($step, $cy + 28 * $k, $step, $cy + 21 * $k)->hid($step, $cy + 21 * $k, $x2, $cy + 21 * $k);
        }
        $s->cl($cx - 44, $cy, $x2 + 14, $cy);
        $s->dimH('pa_cj-to-end', $cx, $x2, 220, $cy + 78, $cy + $hr);
        $s->dimV('pa_hub-dia', $cy - $hr, $cy + $hr, $x2 + 26, $x2, $x2);

        $ex = 392;
        $s->circle($ex, $cy, 72)->circle($ex, $cy, 55, 'md-hid');
        $s->cl($ex - 84, $cy, $ex + 84, $cy)->cl($ex, $cy - 84, $ex, $cy + 84);
        $k = M_SQRT1_2;
        $s->dim('pa_through-hole', $ex - 55 * $k, $cy + 55 * $k, $ex + 55 * $k, $cy - 55 * $k, ['t' => .86]);
        if ($pto) {
            $a = rad2deg(asin(7 / 34));
            $s->shape(array_merge(Sketch::arc($ex, $cy, 34, 90 + $a, 450 - $a), [['L', $ex + 7, $cy - 43], ['L', $ex - 7, $cy - 43], ['Z']]), 'md-hole');
            $s->dimH('pa_bore-dia', $ex - 34, $ex + 34, $cy, null, null, ['t' => .3]);
            $s->tag('pa_keyway', $ex - 7, $cy - 40, $ex - 52, $cy - 92);
            $s->tag('pa_bore-type', $ex - 24, $cy + 24, $ex - 76, $cy + 96);
        } else {
            self::gear($s, $ex, $cy, 32, 38, 18);
            $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $ex - 38, $ex + 38, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 26, $cy - 26, $ex - 60, $cy - 96);
            $s->tag('pa_bore-type', $ex - 22, $cy + 26, $ex - 76, $cy + 96);
        }

        return $s;
    }

    public static function ptoYoke(array $codes): Sketch
    {
        return self::endYoke($codes, true);
    }

    /** ears left, internally splined sleeve right + sleeve end; the transmission version has a counterbore and seal hub */
    public static function slipYoke(array $codes, bool $trans = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 78;
        $cy = 116;
        $x1 = $cx + 46;
        $x2 = $cx + 232;
        $r = $trans ? 30 : 34;
        self::yoke($s, 'left', $cx, $cy, 46, 2 * $r + 6, ['series' => [24, 24]]);
        $s->rect($x1, $cy - $r, $x2 - $x1, 2 * $r);
        $s->hid($x1 + 14, $cy - 18, $x1 + 14, $cy + 18);   // closed end (welch plug)
        if ($trans) {
            foreach ([-1, 1] as $k) {
                $s->hid($x1 + 14, $cy + 18 * $k, $x2 - 18, $cy + 18 * $k)->hid($x2 - 18, $cy + 18 * $k, $x2 - 18, $cy + 23 * $k)->hid($x2 - 18, $cy + 23 * $k, $x2, $cy + 23 * $k);
            }
            $s->dimV('pa_hub-dia', $cy - $r, $cy + $r, $x2 + 24, $x2, $x2);
        } else {
            $s->hid($x1 + 14, $cy - 22, $x2, $cy - 22)->hid($x1 + 14, $cy + 22, $x2, $cy + 22);
            $s->line($x2 - 18, $cy - $r, $x2 - 18, $cy + $r, 'md-thin')->line($x2 - 12, $cy - $r, $x2 - 12, $cy + $r, 'md-thin');
        }
        $s->cl($cx - 44, $cy, $x2 + 12, $cy);
        $s->dimH(['pa_center-u-joint-to-end', 'pa_c-l-to-end', 'pa_cj-to-end'], $cx, $x2, 214, $cy + 78, $cy + $r);

        $ex = 410;
        $s->circle($ex, $cy, 60);
        $s->cl($ex - 70, $cy, $ex + 70, $cy)->cl($ex, $cy - 70, $ex, $cy + 70);
        if ($trans) {
            $k = M_SQRT1_2;
            $s->circle($ex, $cy, 46, 'md-line');
            self::gear($s, $ex, $cy, 26, 31, 18);
            $s->dim('pa_counter-bore-dia', $ex - 46 * $k, $cy + 46 * $k, $ex + 46 * $k, $cy - 46 * $k, ['t' => .88]);
            $s->dimH(['pa_splines-count-dia', 'pa_splines-dia'], $ex - 31, $ex + 31, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 22, $cy + 22, $ex - 52, $cy + 92);
        } else {
            self::gear($s, $ex, $cy, 32, 38, 18);
            $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $ex - 38, $ex + 38, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 27, $cy + 27, $ex - 52, $cy + 92);
        }

        return $s;
    }

    public static function transYoke(array $codes): Sketch
    {
        return self::slipYoke($codes, true);
    }

    /** yoke with an externally splined shaft + spline end view */
    public static function yokeShaft(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 76;
        $cy = 128;
        self::yoke($s, 'left', $cx, $cy, 48, 64, ['series' => [24, 238]]);
        $s->shape([['M', 124, $cy - 32], ['L', 188, $cy - 32], ['L', 204, $cy - 23], ['L', 238, $cy - 23], ['L', 238, $cy + 23], ['L', 204, $cy + 23], ['L', 188, $cy + 32], ['L', 124, $cy + 32], ['Z']]);
        self::splines($s, 238, 344, $cy, 28);
        $s->cl($cx - 44, $cy, 358, $cy);
        $s->dimH(['pa_c-l-to-end', 'pa_center-u-joint-to-end', 'pa_cj-to-end'], $cx, 344, 32, $cy - 78, $cy - 28);
        $s->dimH('pa_splines-length', 238, 344, 196, $cy + 28, $cy + 28);
        $s->dimV(['pa_splines-dia', 'pa_splines-count-dia'], $cy - 28, $cy + 28, 368, 344, 344);

        $ex = 432;
        self::gear($s, $ex, $cy, 27, 33, 16, 'md-part');
        $s->circle($ex, $cy, 4, 'md-thin');
        $s->tag('pa_splines-count', $ex + 23, $cy - 23, $ex + 24, $cy - 76);

        return $s;
    }

    /** two yokes back to back: side view + end view with the swing circle */
    public static function hYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 126;
        self::yoke($s, 'up', $cx, 82, 55, 92, ['series' => [32, 34]]);
        self::yoke($s, 'down', $cx, 192, 55, 92);
        $s->cl($cx, 40, $cx, 236);
        $s->dimV(['pa_cj-to-cj', 'pa_cl-to-cl'], 82, 192, $cx + 106, $cx + 78, $cx + 78);

        $ex = 362;
        $ey = 136;
        $s->circle($ex, $ey, 92, 'md-ph');
        $s->rect($ex - 78, $ey - 32, 156, 64, 'md-part', 10);
        foreach ([-1, 1] as $k) {
            $s->hid($ex - 78, $ey + 15 * $k, $ex - 50, $ey + 15 * $k)->hid($ex + 50, $ey + 15 * $k, $ex + 78, $ey + 15 * $k);
        }
        $s->rect($ex - 50, $ey - 32, 100, 64, 'md-line');
        $s->cl($ex - 100, $ey, $ex + 100, $ey)->cl($ex, $ey - 100, $ex, $ey + 100);
        $a = deg2rad(36);
        $s->dim('pa_swing-dia', $ex - 92 * cos($a), $ey + 92 * sin($a), $ex + 92 * cos($a), $ey - 92 * sin($a), ['t' => .84]);

        return $s;
    }

    /** half-round link yoke seen on the ear face (cap seat, strap bolt holes, ball stud) + top view */
    public static function linkYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 140;
        $cy = 92;
        $s->shape(array_merge(
            [['M', $cx - 58, $cy], ['L', $cx - 21, $cy]],
            Sketch::arc($cx, $cy, 21, 180, 360, true),
            [['L', $cx + 58, $cy], ['L', $cx + 58, $cy + 34], ['C', $cx + 58, $cy + 56, $cx + 34, $cy + 56, $cx + 34, $cy + 70],
                ['L', $cx - 34, $cy + 70], ['C', $cx - 34, $cy + 56, $cx - 58, $cy + 56, $cx - 58, $cy + 34], ['Z']],
        ));
        foreach ([-1, 1] as $k) {
            $s->hid($cx + 36 * $k - 5, $cy, $cx + 36 * $k - 5, $cy + 26)->hid($cx + 36 * $k + 5, $cy, $cx + 36 * $k + 5, $cy + 26);
        }
        $s->circle($cx, $cy, 16, 'md-ph');
        $s->rect($cx - 7, $cy + 70, 14, 28, 'md-part', 3);
        $s->cl($cx - 72, $cy, $cx + 72, $cy)->cl($cx, $cy - 34, $cx, $cy + 112);
        $s->dimV(['pa_center-u-joint-to-end', 'pa_c-l-to-end'], $cy, $cy + 70, $cx + 96, $cx + 74, $cx + 34);
        $s->dimH(['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], $cx - 7, $cx + 7, $cy + 128, $cy + 98, $cy + 98);

        $tx = 370;
        $ty = 128;
        $s->rect($tx - 70, $ty - 30, 140, 60, 'md-part', 8);
        $s->rect($tx - 24, $ty - 30, 48, 60, 'md-line');
        foreach ([-1, 1] as $i) {
            foreach ([-1, 1] as $j) {
                $s->circle($tx + 47 * $i, $ty + 17 * $j, 6, 'md-hole');
            }
        }
        $s->cl($tx - 84, $ty, $tx + 84, $ty)->cl($tx, $ty - 44, $tx, $ty + 44);
        $s->dim(['pa_bolt-hole-dia', 'pa_thread-dia'], $tx + 41, $ty - 17, $tx + 53, $ty - 17, ['out' => true]);
        $s->tag(['pa_series', 'pa_style-series'], $tx - 47, $ty - 6, $tx - 76, $ty + 72);
        $s->note($tx, $ty - 46, 'top');

        return $s;
    }

    /** double cardan head: flange yoke, centre yoke, tube yoke and tube in phantom + flange face */
    public static function cvHead(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 118;
        $c1 = 98;
        $c2 = 172;
        $s->rect(34, $cy - 64, 12, 128);
        $s->rect($c1 - 2, $cy - 30, $c2 - $c1 + 4, 60, 'md-part', 8);
        $s->circle($c1, $cy, 15, 'md-line')->circle($c2, $cy, 15, 'md-line')->circle($c1, $cy, 6, 'md-thin')->circle($c2, $cy, 6, 'md-thin');
        self::yoke($s, 'right', $c1, $cy, 52, 84, ['series' => [22, 26]]);
        self::yoke($s, 'left', $c2, $cy, 62, 84);
        $s->rect($c2 + 62, $cy - 36, 26, 72);
        $s->line($c2 + 62, $cy - 42, 306, $cy - 42, 'md-ph')->line($c2 + 62, $cy + 42, 306, $cy + 42, 'md-ph');
        $s->cl(24, $cy, 316, $cy);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 34, $c2 + 62, 226, $cy + 64, $cy + 44);
        $s->dimV('pa_butt-dia', $cy - 36, $cy + 36, $c2 + 75, null, null, ['t' => .28]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 42, $cy + 42, 326, 306, 306);

        $fx = 410;
        $s->circle($fx, $cy, 58)->circle($fx, $cy, 22, 'md-line');
        $s->cl($fx - 68, $cy, $fx + 68, $cy)->cl($fx, $cy - 68, $fx, $cy + 68);
        self::bolts($s, $fx, $cy, 42, 6, 4, ['bc' => [1, 3], 't' => .2, 'dia' => [0, 45]]);

        return $s;
    }

    /** tri / quad prong yoke for flex discs: side view with the tube in phantom + prong face */
    public static function prongYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 130;
        $s->shape([['M', 48, $cy - 30], ['L', 76, $cy - 30], ['L', 76, $cy - 36], ['L', 92, $cy - 36], ['L', 124, $cy - 60], ['L', 124, $cy - 92],
            ['L', 152, $cy - 92], ['L', 152, $cy + 92], ['L', 124, $cy + 92], ['L', 124, $cy + 60], ['L', 92, $cy + 36], ['L', 76, $cy + 36],
            ['L', 76, $cy + 30], ['L', 48, $cy + 30], ['Z']]);
        $s->hid(130, $cy - 15, 152, $cy - 15)->hid(130, $cy + 15, 152, $cy + 15)->hid(130, $cy - 15, 130, $cy + 15);
        $s->line(14, $cy - 36, 76, $cy - 36, 'md-ph')->line(14, $cy + 36, 76, $cy + 36, 'md-ph');
        $s->cl(6, $cy, 166, $cy);
        $s->dimH('pa_face-to-weld', 76, 152, 244, $cy + 38, $cy + 94);
        $s->dimV('pa_butt-dia', $cy - 30, $cy + 30, 62, null, null, ['t' => .26]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 36, $cy + 36, 24, null, null, ['t' => .26]);

        $fx = 344;
        self::lobes($s, $fx, $cy, 3, 44, 72, 22, 90);
        $s->circle($fx, $cy, 15, 'md-hole');
        $s->cl($fx - 104, $cy, $fx + 104, $cy)->cl($fx, $cy - 104, $fx, $cy + 104);
        $p = self::bolts($s, $fx, $cy, 72, 9, 3, ['start' => 90, 'dia' => [0, 0]]);
        $a = deg2rad(150);
        $s->dim('pa_bolt-circle-dia', $fx + 72 * cos($a), $cy - 72 * sin($a), $p[2][0], $p[2][1], ['t' => .22]);
        $s->dimH(['pa_nose-bushing-dia', 'pa_pilot-dia'], $fx - 15, $fx + 15, $cy, null, null, ['out' => true]);

        return $s;
    }
}
