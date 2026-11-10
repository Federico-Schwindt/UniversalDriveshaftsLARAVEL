<?php

namespace App\Support\Measure;

/** Yokes: flange, tube (weld), end, slip, transmission, yoke shafts, H, CV link, CV heads and prong yokes. */
trait YokeParts
{
    /** half section across the ears with the flange below + the flange face */
    public static function flangeYoke(array $codes, bool $cv = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 124;
        $cy = 76;
        $ft = 134;   // flange back
        $fb = 150;   // mating face
        $fr = 86;    // flange radius
        $pr = 46;    // pilot radius
        $flange = [['M', $cx - $fr, $ft + 2], ['L', $cx - $fr + 2, $ft], ['L', $cx + $fr - 2, $ft], ['L', $cx + $fr, $ft + 2],
            ['L', $cx + $fr, $fb - 2], ['L', $cx + $fr - 2, $fb], ['L', $cx - $fr + 2, $fb], ['L', $cx - $fr, $fb - 2], ['Z']];
        $pilot = [['M', $cx - $pr, $fb - 1], ['L', $cx + $pr, $fb - 1], ['L', $cx + $pr, $fb + 3], ['L', $cx + $pr - 2, $fb + 5],
            ['L', $cx - $pr + 2, $fb + 5], ['L', $cx - $pr, $fb + 3], ['Z']];
        $clip = $s->clip($cx - $fr - 4, $ft - 1, $cx, $fb + 7);
        foreach ([$pilot, $flange] as $part) {
            $s->shape($part, 'md-part', null, 'cyv')->shape($part, 'md-cut', null, 'hatch', $clip)->shape($part, 'md-outline');
        }
        foreach ([37, 53] as $x) {   // a bolt hole through the flange, on the uncut half
            $s->hid($cx + $x, $ft, $cx + $x, $fb);
        }
        self::yoke($s, 'up', $cx, $cy, $ft - $cy + 1, 92, ['series' => [30, 32], 'cut' => true, 'fillet' => 8]);
        if ($cv) {   // centring ball stud standing in the throat
            $s->shape([['M', $cx - 6, $cy + 47], ['L', $cx - 6, $cy + 22], ['Q', $cx - 6, $cy + 16, $cx, $cy + 16], ['Q', $cx + 6, $cy + 16, $cx + 6, $cy + 22], ['L', $cx + 6, $cy + 47]], 'md-part', null, 'cyv');
            $s->tag(['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], $cx + 6, $cy + 26, $cx + 46, $cy - 52);
        }
        $s->cl($cx, $cy - 52, $cx, $fb + 18);
        $s->dimV(['pa_cj-to-flat', 'pa_c-l-to-face'], $cy, $fb, $cx + 114, $cx + 84, $cx + $fr + 2);
        $s->dimH(['pa_pilot-dia', 'pa_bolt-hole-pilot-dia'], $cx - $pr, $cx + $pr, 186, $fb + 6, $fb + 6);

        $fx = 362;
        $fy = 128;
        $s->circle($fx, $fy, 86, 'md-part', 'face')->circle($fx, $fy, 83, 'md-edge');
        $s->circle($fx, $fy, $pr, 'md-part', 'face')->circle($fx, $fy, $pr - 3, 'md-edge')->circle($fx, $fy, 15, 'md-hole');
        $s->cl($fx - 98, $fy, $fx + 98, $fy)->cl($fx, $fy - 98, $fx, $fy + 98);
        self::bolts($s, $fx, $fy, 66, 8, 4, ['bc' => [1, 3], 't' => .12, 'dia' => [0, 45], 'count' => [284, 30], 'countHole' => 1]);

        return $s;
    }

    public static function cvFlangeYoke(array $codes): Sketch
    {
        return self::flangeYoke($codes, true);
    }

    /** tube yoke in half section with the tube in phantom over the butt + the tube end */
    public static function weldYoke(array $codes, bool $stud = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 130;
        $cy = 64;
        $w = 136;    // weld shoulder: where the tube end sits
        $tr = 60;    // tube outside radius
        $br = 55;    // butt radius (tube inside)
        $bl = 24;    // butt length
        $butt = [['M', $cx - $br, $w - 1], ['L', $cx + $br, $w - 1], ['L', $cx + $br, $w + $bl - 2], ['L', $cx + $br - 2, $w + $bl],
            ['L', $cx - $br + 2, $w + $bl], ['L', $cx - $br, $w + $bl - 2], ['Z']];
        $s->shape($butt, 'md-part', null, 'cyv');
        $s->shape($butt, 'md-cut', null, 'hatch', $s->clip($cx - $br - 4, $w - 2, $cx, $w + $bl + 2));
        $s->rect($cx - $br + 7, $w - 2, $br - 7, $bl + 3, 'md-void');
        $s->line($cx - $br + 7, $w, $cx - $br + 7, $w + $bl, 'md-line');
        $s->shape($butt, 'md-outline');
        self::yoke($s, 'up', $cx, $cy, $w - $cy + 1, 2 * $tr, ['series' => [28, 28], 'cut' => true, 'bore' => [$br - 7, $cy + 60]]);
        $s->line($cx - $tr, $w + 1, $cx - $br, $w + 1, 'md-line')->line($cx + $br, $w + 1, $cx + $tr, $w + 1, 'md-line');
        foreach ([-1, 1] as $k) {   // the tube it welds into, in phantom
            $s->line($cx + $tr * $k, $w + 1, $cx + $tr * $k, 236, 'md-ph')->line($cx + $br * $k, $w + $bl, $cx + $br * $k, 236, 'md-ph');
        }
        $s->cl($cx, $cy - 52, $cx, 246);
        if ($stud) {
            $s->shape([['M', $cx - 6, $cy + 47], ['L', $cx - 6, $cy + 22], ['Q', $cx - 6, $cy + 16, $cx, $cy + 16], ['Q', $cx + 6, $cy + 16, $cx + 6, $cy + 22], ['L', $cx + 6, $cy + 47]], 'md-part', null, 'cyv');
            $s->tag(['pa_ball-stud-length-dia', 'pa_ball-stud-dia'], $cx + 6, $cy + 26, $cx + 42, $cy - 52);
        }
        $s->dimV(['pa_center-u-j-to-weld', 'pa_c-l-to-weld'], $cy, $w + 1, $cx + 112, $cx + 84, $cx + $tr + 2);
        $s->dimH('pa_butt-dia', $cx - $br, $cx + $br, $w + $bl + 30, $w + $bl, $w + $bl);
        self::tube($s, 372, 118, 62, 55, 216);

        return $s;
    }

    public static function ballStudYoke(array $codes): Sketch
    {
        return self::weldYoke($codes, true);
    }

    /** half section (ears left, hub right) + the hub end: splined bore, or a round bore with keyway for PTO yokes */
    public static function endYoke(array $codes, bool $pto = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 84;
        $cy = 124;
        $x1 = $cx + 60;
        $x2 = $cx + 152;
        $hr = 36;
        $th = $pto ? 24 : 28;   // through hole from the throat
        $br = 21;               // bore
        $step = $x1 + 20;
        self::turned($s, [[$x1 - 2, $hr], [$x2 - 3, $hr], [$x2, $hr - 3]], $cy, ['cut' => true, 'bores' => [[[$x1 - 2, $th], [$step, $th], [$step, $br], [$x2, $br]]]]);
        if (!$pto) {
            $s->line($step, $cy - $br * .84, $x2, $cy - $br * .84, 'md-edge');
        }
        self::yoke($s, 'left', $cx, $cy, $x1 - $cx + 1, 2 * $hr, ['series' => [24, 26], 'cut' => true, 'bore' => [$th, 46]]);
        $s->cl($cx - 50, $cy, $x2 + 14, $cy);
        $s->dimH(['pa_cj-to-end', 'pa_center-u-joint-to-end'], $cx, $x2, 228, $cy + 84, $cy + $hr);
        $s->dimV('pa_hub-dia', $cy - $hr, $cy + $hr, $x2 + 26, $x2, $x2);

        $ex = 396;
        $s->circle($ex, $cy, 70, 'md-part', 'face')->circle($ex, $cy, 66, 'md-edge')->circle($ex, $cy, 53, 'md-hid');
        $s->cl($ex - 82, $cy, $ex + 82, $cy)->cl($ex, $cy - 82, $ex, $cy + 82);
        $k = M_SQRT1_2;
        $s->dim('pa_through-hole', $ex - 53 * $k, $cy + 53 * $k, $ex + 53 * $k, $cy - 53 * $k, ['t' => .86]);
        if ($pto) {
            $a = rad2deg(asin(7 / 36));
            $s->shape(array_merge(Sketch::arc($ex, $cy, 36, 90 + $a, 450 - $a), [['L', $ex + 7, $cy - 45], ['L', $ex - 7, $cy - 45], ['Z']]), 'md-hole');
            $s->circle($ex, $cy, 39, 'md-edge');
            $s->dimH('pa_bore-dia', $ex - 36, $ex + 36, $cy, null, null, ['t' => .3]);
            $s->tag('pa_keyway', $ex - 7, $cy - 42, $ex - 52, $cy - 94);
            $s->tag('pa_bore-type', $ex - 25, $cy + 25, $ex - 76, $cy + 98);
        } else {
            self::gear($s, $ex, $cy, 35, 41, 20);
            $s->circle($ex, $cy, 44, 'md-edge');
            $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $ex - 41, $ex + 41, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 28, $cy - 28, $ex - 60, $cy - 98);
            $s->tag('pa_bore-type', $ex - 24, $cy + 27, $ex - 76, $cy + 98);
        }

        return $s;
    }

    public static function ptoYoke(array $codes): Sketch
    {
        return self::endYoke($codes, true);
    }

    /** half section: ears left, internally splined sleeve right + the sleeve end; the transmission version has a seal hub and counterbore */
    public static function slipYoke(array $codes, bool $trans = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 74;
        $cy = 118;
        $x1 = $cx + 58;
        $x2 = $cx + 244;
        if ($trans) {
            $r = 30;
            self::turned($s, [[$x1 - 2, $r], [$x2 - 3, $r], [$x2, $r - 3]], $cy, ['cut' => true, 'bores' => [[[$x1 + 14, 19], [$x2 - 20, 19], [$x2 - 20, 24], [$x2, 24]]]]);
            $s->line($x1 + 14, $cy - 16, $x2 - 20, $cy - 16, 'md-edge');
            $s->dimV('pa_hub-dia', $cy - $r, $cy + $r, $x2 + 24, $x2, $x2);
        } else {
            $r = 34;
            self::turned($s, [[$x1 - 2, $r], [$x2 - 22, $r], [$x2 - 22, $r - 2], [$x2 - 16, $r - 2], [$x2 - 16, $r], [$x2 - 3, $r], [$x2, $r - 3]], $cy,
                ['cut' => true, 'bores' => [[[$x1 + 12, 26], [$x1 + 17, 26], [$x1 + 17, 23], [$x2, 23]]]]);
            $s->line($x1 + 17, $cy - 19.5, $x2, $cy - 19.5, 'md-edge');
        }
        self::yoke($s, 'left', $cx, $cy, $x1 - $cx + 1, 2 * $r, ['series' => [22, 24], 'cut' => true]);
        $s->cl($cx - 50, $cy, $x2 + 12, $cy);
        $s->dimH(['pa_center-u-joint-to-end', 'pa_c-l-to-end', 'pa_cj-to-end'], $cx, $x2, 222, $cy + 84, $cy + $r);

        $ex = 410;
        $s->circle($ex, $cy, 60, 'md-part', 'face')->circle($ex, $cy, 56, 'md-edge');
        $s->cl($ex - 70, $cy, $ex + 70, $cy)->cl($ex, $cy - 70, $ex, $cy + 70);
        if ($trans) {
            $k = M_SQRT1_2;
            $s->circle($ex, $cy, 47, 'md-line');
            self::gear($s, $ex, $cy, 32, 37, 20);
            $s->dim('pa_counter-bore-dia', $ex - 47 * $k, $cy + 47 * $k, $ex + 47 * $k, $cy - 47 * $k, ['t' => .88]);
            $s->dimH(['pa_splines-count-dia', 'pa_splines-dia'], $ex - 37, $ex + 37, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 26, $cy + 26, $ex - 52, $cy + 94);
        } else {
            self::gear($s, $ex, $cy, 35, 41, 20);
            $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $ex - 41, $ex + 41, $cy, null, null, ['t' => .3]);
            $s->tag('pa_splines-count', $ex - 29, $cy + 29, $ex - 52, $cy + 94);
        }

        return $s;
    }

    public static function transYoke(array $codes): Sketch
    {
        return self::slipYoke($codes, true);
    }

    /** yoke with an externally splined shaft + the spline end */
    public static function yokeShaft(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 72;
        $cy = 130;
        $x1 = $cx + 56;
        self::turned($s, [[$x1 - 2, 33], [186, 33], [200, 24], [232, 24], [236, 30], [340, 30], [344, 26]], $cy);
        self::teeth($s, 236, 344, $cy, 30, 'left');
        self::yoke($s, 'left', $cx, $cy, $x1 - $cx + 1, 66, ['series' => [22, 238]]);
        $s->cl($cx - 50, $cy, 358, $cy);
        $s->dimH(['pa_c-l-to-end', 'pa_center-u-joint-to-end', 'pa_cj-to-end'], $cx, 344, 32, $cy - 84, $cy - 30);
        $s->dimH('pa_splines-length', 236, 344, 204, $cy + 30, $cy + 30);
        $s->dimV(['pa_splines-dia', 'pa_splines-count-dia'], $cy - 30, $cy + 30, 370, 344, 344);

        $ex = 432;
        self::gear($s, $ex, $cy, 27, 33, 18, 'md-part', 'face');
        $s->circle($ex, $cy, 5, 'md-edge');
        $s->tag('pa_splines-count', $ex + 23, $cy - 23, $ex + 24, $cy - 78);

        return $s;
    }

    /** two yokes back to back + the end view with the circle the ears swing through */
    public static function hYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 126;
        self::yoke($s, 'down', $cx, 196, 58, 92);
        self::yoke($s, 'up', $cx, 82, 58, 92, ['series' => [30, 34]]);
        $s->cl($cx, 30, $cx, 246);
        $s->dimV(['pa_cj-to-cj', 'pa_cl-to-cl'], 82, 196, $cx + 108, $cx + 84, $cx + 84);

        $ex = 364;
        $ey = 138;
        $s->circle($ex, $ey, 94, 'md-ph');
        $s->rect($ex - 82, $ey - 30, 164, 60, 'md-part', 12, 'cast');
        $s->rect($ex - 54, $ey - 30, 108, 60, 'md-edge');
        foreach ([-1, 1] as $k) {
            $s->hid($ex - 82, $ey + 17 * $k, $ex - 54, $ey + 17 * $k)->hid($ex + 54, $ey + 17 * $k, $ex + 82, $ey + 17 * $k);
        }
        $s->cl($ex - 104, $ey, $ex + 104, $ey)->cl($ex, $ey - 104, $ex, $ey + 104);
        $a = deg2rad(36);
        $s->dim('pa_swing-dia', $ex - 94 * cos($a), $ey + 94 * sin($a), $ex + 94 * cos($a), $ey - 94 * sin($a), ['t' => .84]);

        return $s;
    }

    /** half-round link yoke on its ear face (cap seat, strap bolt holes, ball stud) + top view */
    public static function linkYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 140;
        $cy = 92;
        $s->shape(array_merge(
            [['M', $cx - 58, $cy + 2], ['Q', $cx - 58, $cy, $cx - 56, $cy], ['L', $cx - 22, $cy]],
            Sketch::arc($cx, $cy, 22, 180, 360, true),
            [['L', $cx + 56, $cy], ['Q', $cx + 58, $cy, $cx + 58, $cy + 2], ['L', $cx + 58, $cy + 34], ['C', $cx + 58, $cy + 56, $cx + 36, $cy + 54, $cx + 34, $cy + 70],
                ['L', $cx - 34, $cy + 70], ['C', $cx - 36, $cy + 54, $cx - 58, $cy + 56, $cx - 58, $cy + 34], ['Z']],
        ), 'md-part', null, 'cast');
        foreach ([-1, 1] as $k) {
            $s->hid($cx + 38 * $k - 5, $cy, $cx + 38 * $k - 5, $cy + 26)->hid($cx + 38 * $k + 5, $cy, $cx + 38 * $k + 5, $cy + 26);
        }
        $s->circle($cx, $cy, 19, 'md-ph');
        $s->shape([['M', $cx - 7, $cy + 69], ['L', $cx - 7, $cy + 92], ['Q', $cx - 7, $cy + 99, $cx, $cy + 99], ['Q', $cx + 7, $cy + 99, $cx + 7, $cy + 92], ['L', $cx + 7, $cy + 69]], 'md-part', null, 'cyv');
        $s->cl($cx - 72, $cy, $cx + 72, $cy)->cl($cx, $cy - 34, $cx, $cy + 112);
        $s->dimV(['pa_center-u-joint-to-end', 'pa_c-l-to-end'], $cy, $cy + 70, $cx + 96, $cx + 74, $cx + 34);
        $s->dimH(['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], $cx - 7, $cx + 7, $cy + 130, $cy + 100, $cy + 100);

        $tx = 370;
        $ty = 128;
        $s->rect($tx - 70, $ty - 30, 140, 60, 'md-part', 8, 'cast');
        $s->rect($tx - 24, $ty - 30, 48, 60, 'md-part', 0, 'face');
        foreach ([-1, 1] as $i) {
            foreach ([-1, 1] as $j) {
                $s->circle($tx + 47 * $i, $ty + 17 * $j, 8.2, 'md-edge')->circle($tx + 47 * $i, $ty + 17 * $j, 6, 'md-hole');
            }
        }
        $s->cl($tx - 84, $ty, $tx + 84, $ty)->cl($tx, $ty - 44, $tx, $ty + 44);
        $s->dim(['pa_bolt-hole-dia', 'pa_thread-dia'], $tx + 41, $ty - 17, $tx + 53, $ty - 17, ['out' => true]);
        $s->tag(['pa_series', 'pa_style-series'], $tx - 12, $ty, $tx - 76, $ty + 74);
        $s->note($tx, $ty - 46, 'top');

        return $s;
    }

    /** double cardan head: flange yoke, centring yoke, tube yoke, tube in phantom + flange face */
    public static function cvHead(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 118;
        $c1 = 102;
        $c2 = 180;
        $s->rect($c1 - 6, $cy - 34, $c2 - $c1 + 12, 68, 'md-part', 10, 'cast');
        $s->circle($c1, $cy, 19, 'md-part', 'face')->circle($c2, $cy, 19, 'md-part', 'face')->circle($c1, $cy, 7, 'md-edge')->circle($c2, $cy, 7, 'md-edge');
        $s->rect($c2 + 64, $cy - 34, 24, 68, 'md-part', 2, 'cyh');
        $s->line($c2 + 64, $cy - 40, 308, $cy - 40, 'md-ph')->line($c2 + 64, $cy + 40, 308, $cy + 40, 'md-ph');
        self::yoke($s, 'left', $c2, $cy, 65, 80);
        self::yoke($s, 'right', $c1, $cy, 55, 78, ['series' => [20, 24], 'fillet' => 6]);
        $s->rect(34, $cy - 66, 14, 132, 'md-part', 2, 'cyh');
        $s->cl(24, $cy, 318, $cy);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 34, $c2 + 64, 228, $cy + 66, $cy + 42);
        $s->dimV('pa_butt-dia', $cy - 34, $cy + 34, $c2 + 76, null, null, ['t' => .28]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 40, $cy + 40, 328, 308, 308);

        $fx = 412;
        $s->circle($fx, $cy, 58, 'md-part', 'face')->circle($fx, $cy, 55, 'md-edge')->circle($fx, $cy, 22, 'md-part', 'face')->circle($fx, $cy, 10, 'md-hole');
        $s->cl($fx - 68, $cy, $fx + 68, $cy)->cl($fx, $cy - 68, $fx, $cy + 68);
        self::bolts($s, $fx, $cy, 41, 6, 4, ['bc' => [1, 3], 't' => .2, 'dia' => [0, 45]]);

        return $s;
    }

    /** tri / quad prong yoke for flex discs: half section with the tube in phantom + prong face */
    public static function prongYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 130;
        self::turned($s, [[48, 28], [50, 30], [76, 30], [76, 36], [92, 36], [122, 58], [122, 92], [150, 92], [152, 90]], $cy,
            ['cut' => true, 'bores' => [[[48, 22], [84, 22]], [[128, 15], [152, 15]]]]);
        $s->line(14, $cy - 36, 76, $cy - 36, 'md-ph')->line(14, $cy + 36, 76, $cy + 36, 'md-ph');
        $s->cl(6, $cy, 166, $cy);
        $s->dimH('pa_face-to-weld', 76, 152, 246, $cy + 38, $cy + 94);
        $s->dimV('pa_butt-dia', $cy - 30, $cy + 30, 62, null, null, ['t' => .74]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 36, $cy + 36, 24, null, null, ['t' => .74]);

        $fx = 344;
        self::lobes($s, $fx, $cy, 3, 46, 72, 23, 90);
        $s->circle($fx, $cy, 26, 'md-edge')->circle($fx, $cy, 15, 'md-hole');
        $s->cl($fx - 106, $cy, $fx + 106, $cy)->cl($fx, $cy - 106, $fx, $cy + 106);
        $p = self::bolts($s, $fx, $cy, 72, 9, 3, ['start' => 90, 'dia' => [0, 0]]);
        $a = deg2rad(150);
        $s->dim('pa_bolt-circle-dia', $fx + 72 * cos($a), $cy - 72 * sin($a), $p[2][0], $p[2][1], ['t' => .22]);
        $s->dimH(['pa_nose-bushing-dia', 'pa_pilot-dia'], $fx - 15, $fx + 15, $cy, null, null, ['out' => true]);

        return $s;
    }
}
