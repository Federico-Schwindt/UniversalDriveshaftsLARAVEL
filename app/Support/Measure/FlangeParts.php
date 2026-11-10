<?php

namespace App\Support\Measure;

/** Flanges and discs: companion flanges, flex discs, CV joints, bushings, boot cans and boots. */
trait FlangeParts
{
    /** companion flange: half section (hub left, flange, pilot) + the face with its splined bore */
    public static function companionFlange(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        self::turned($s, [[54, 33], [57, 36], [146, 36], [150, 40], [150, 88], [152, 90], [164, 90], [166, 88], [166, 46], [172, 46], [175, 43]], $cy,
            ['cut' => true, 'bores' => [[[54, 26], [62, 26], [62, 22], [175, 22]]]]);
        $s->line(62, $cy - 18.5, 175, $cy - 18.5, 'md-edge');
        foreach ([50, 66] as $y) {   // a bolt hole through the flange, on the uncut half
            $s->hid(150, $cy + $y, 166, $cy + $y);
        }
        $s->cl(40, $cy, 188, $cy);
        $s->dimV('pa_hub-dia', $cy - 36, $cy + 36, 30, 54, 54);
        $s->dimV(['pa_pilot-dia', 'pa_bolt-hole-pilot-dia'], $cy - 46, $cy + 46, 202, 175, 175);
        $s->dimH(['pa_overall-length', 'pa_length'], 54, 175, 250, $cy + 38, $cy + 92);

        $fx = 354;
        $s->circle($fx, $cy, 90, 'md-part', 'face')->circle($fx, $cy, 87, 'md-edge')->circle($fx, $cy, 46, 'md-part', 'face')->circle($fx, $cy, 43, 'md-edge');
        self::gear($s, $fx, $cy, 21, 26, 20);
        $s->cl($fx - 100, $cy, $fx + 100, $cy)->cl($fx, $cy - 100, $fx, $cy + 100);
        self::bolts($s, $fx, $cy, 66, 8, 4, ['bc' => [1, 3], 't' => .12, 'dia' => [0, 45], 'count' => [264, 238], 'countHole' => 2]);
        $s->dimH(['pa_through-hole', 'pa_splines-dia'], $fx - 26, $fx + 26, $cy, null, null, ['out' => true]);
        $s->tag('pa_splines-count', $fx - 19, $cy - 19, $fx - 62, $cy - 106);

        return $s;
    }

    /** rubber flex disc (guibo): face with its steel sleeves + edge */
    public static function flexDisc(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 160;
        $cy = 135;
        self::rpoly($s, $cx, $cy, 112, 6, 0, .3, 'md-part', 'rubber');
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad($i * 60);
            $s->circle($cx + 76 * cos($a), $cy - 76 * sin($a), 17, 'md-part', 'face')->circle($cx + 76 * cos($a), $cy - 76 * sin($a), 14, 'md-edge');
        }
        $s->circle($cx, $cy, 27, 'md-part', 'face')->circle($cx, $cy, 19, 'md-hole');
        $s->cl($cx - 124, $cy, $cx + 124, $cy)->cl($cx, $cy - 112, $cx, $cy + 112);
        self::bolts($s, $cx, $cy, 76, 10, 6, ['start' => 0, 'bc' => [1, 4], 't' => .16, 'dia' => [0, 0], 'count' => [44, 30], 'countHole' => 2]);
        $s->dimH(['pa_pilot-dia', 'pa_nose-bushing-dia'], $cx - 19, $cx + 19, $cy + 1, null, null, ['out' => true]);

        $x1 = 360;
        $x2 = 400;
        $s->rect($x1, 40, $x2 - $x1, 190, 'md-part', 12, 'rubber');
        foreach ([69, 135, 201] as $i => $y) {   // sleeves stand proud on alternate sides
            $s->rect($i % 2 ? $x1 - 5 : $x1, $y - 17, $x2 - $x1 + 5, 34, 'md-part', 2, 'cyh');
        }
        $s->cl(($x1 + $x2) / 2, 26, ($x1 + $x2) / 2, 244);
        $s->dimH(['pa_disc-width', 'pa_thickness'], $x1, $x2, 18, 40, 40, ['out' => true]);
        $s->note(($x1 + $x2) / 2, 260, 'edge');

        return $s;
    }

    /** bolt-on CV joint: face (bolt holes, balls in the cage, splined inner race) + half section */
    public static function cvJoint(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 150;
        $cy = 132;
        $s->circle($cx, $cy, 100, 'md-part', 'face')->circle($cx, $cy, 97, 'md-edge')->circle($cx, $cy, 67, 'md-part', 'cast');
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad($i * 60);
            $s->circle($cx + 54 * cos($a), $cy - 54 * sin($a), 11, 'md-ball', 'ball');
        }
        $s->circle($cx, $cy, 40, 'md-part', 'face');
        self::gear($s, $cx, $cy, 20, 25, 20);
        $s->cl($cx - 112, $cy, $cx + 112, $cy)->cl($cx, $cy - 112, $cx, $cy + 112);
        self::bolts($s, $cx, $cy, 84, 6, 6, ['start' => 30, 'dia' => [0, 30], 'count' => [34, 26], 'countHole' => 2]);
        $s->dimH(['pa_outer-dia', 'pa_od'], $cx - 100, $cx + 100, 254, $cy, $cy);
        $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $cx - 25, $cx + 25, $cy, null, null, ['out' => true]);
        $s->tag('pa_splines-count', $cx - 18, $cy + 18, $cx - 104, $cy + 104);

        // half section, axis horizontal: outer race, balls, inner race on its splines
        $ax = 364;
        $outer = [['M', $ax - 30, $cy - 100], ['L', $ax + 30, $cy - 100], ['L', $ax + 30, $cy - 54], ['Q', $ax, $cy - 66, $ax - 30, $cy - 54], ['Z']];
        $s->shape($outer, 'md-part', null, 'hatch');
        $s->rect($ax - 30, $cy, 60, 100, 'md-part', 0, 'cyh');
        $s->circle($ax, $cy - 54, 11, 'md-ball', 'ball');
        $s->shape([['M', $ax - 24, $cy - 40], ['Q', $ax, $cy - 46, $ax + 24, $cy - 40], ['L', $ax + 24, $cy - 25], ['L', $ax - 24, $cy - 25], ['Z']], 'md-part', null, 'hatch');
        $s->line($ax - 24, $cy - 25, $ax + 24, $cy - 25, 'md-line');
        $s->hid($ax - 24, $cy + 40, $ax + 24, $cy + 40)->hid($ax - 24, $cy, $ax - 24, $cy + 40)->hid($ax + 24, $cy, $ax + 24, $cy + 40);
        foreach ([[$ax - 30, $cy - 100, $cy + 100], [$ax + 30, $cy - 100, $cy + 100]] as [$x, $y1, $y2]) {
            $s->line($x, $y1, $x, $y2, 'md-line');
        }
        $s->cl($ax - 44, $cy, $ax + 44, $cy);
        $s->dimH('pa_outer-race-width', $ax - 30, $ax + 30, 18, $cy - 100, $cy - 100);
        $s->dimH('pa_inner-race-width', $ax - 24, $ax + 24, 254, $cy + 40, $cy + 40);

        return $s;
    }

    /** increase bushing: face + half section with both tubes in phantom */
    public static function increaseBushing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 122;
        $cy = 132;
        $s->circle($cx, $cy, 96, 'md-part', 'face')->circle($cx, $cy, 86, 'md-edge')->circle($cx, $cy, 68, 'md-hole')->circle($cx, $cy, 71, 'md-edge');
        $s->cl($cx - 106, $cy, $cx + 106, $cy)->cl($cx, $cy - 106, $cx, $cy + 106);
        $s->dimH(['pa_outer-dia', 'pa_od'], $cx - 96, $cx + 96, 252, $cy, $cy);
        $s->dimH(['pa_inside-dia', 'pa_id'], $cx - 68, $cx + 68, $cy, null, null, ['t' => .3]);

        $x = 322;
        self::turned($s, [[$x, 94], [$x + 2, 96], [$x + 10, 96], [$x + 10, 86], [$x + 52, 86], [$x + 54, 84]], $cy, ['cut' => true, 'bores' => [[[$x, 68], [$x + 54, 68]]]]);
        foreach ([-1, 1] as $k) {
            $s->line($x + 10, $cy + 86 * $k, $x + 140, $cy + 86 * $k, 'md-ph')->line($x + 10, $cy + 94 * $k, $x + 140, $cy + 94 * $k, 'md-ph');
            $s->line($x - 60, $cy + 66 * $k, $x + 54, $cy + 66 * $k, 'md-ph')->line($x - 60, $cy + 58 * $k, $x + 40, $cy + 58 * $k, 'md-ph');
        }
        $s->cl($x - 70, $cy, $x + 150, $cy);
        $s->dimH(['pa_length', 'pa_height'], $x, $x + 54, 18, $cy - 96, $cy - 96);
        $s->tag('pa_to-tube', $x + 110, $cy - 94, $x + 124, $cy - 124);
        $s->tag('pa_from-tube', $x - 30, $cy + 66, $x - 44, $cy + 108);

        return $s;
    }

    /** plain bushing: half section + end view */
    public static function noseBushing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        self::turned($s, [[60, 39], [63, 42], [217, 42], [220, 39]], $cy, ['cut' => true, 'bores' => [[[60, 19], [220, 19]]]]);
        $s->cl(46, $cy, 234, $cy);
        $s->dimH(['pa_height', 'pa_length'], 60, 220, 222, $cy + 42, $cy + 42);
        $s->dimV(['pa_od', 'pa_outer-dia'], $cy - 42, $cy + 42, 246, 220, 220);

        $ex = 370;
        $s->circle($ex, $cy, 64, 'md-part', 'face')->circle($ex, $cy, 60, 'md-edge')->circle($ex, $cy, 32, 'md-edge')->circle($ex, $cy, 29, 'md-hole');
        $s->cl($ex - 76, $cy, $ex + 76, $cy)->cl($ex, $cy - 76, $ex, $cy + 76);
        $s->dimH(['pa_inside-dia', 'pa_id'], $ex - 29, $ex + 29, $cy, null, null, ['t' => .5]);

        return $s;
    }

    /**
     * boot can / bearing retainer: a stamped cup in half section (collar that grips the stub, flange round the open
     * end) + the view into it
     */
    public static function bootCan(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 124;
        $x0 = 96;    // closed end
        $x1 = 200;   // flange face
        $R = 64;     // body
        $r = 36;     // the hole that grips the stub
        $t = 3.5;    // sheet
        self::turned($s, [[$x0, $R - 6], [$x0 + 6, $R], [$x1 - $t - 4, $R], [$x1 - $t, $R + 4], [$x1 - $t, $R + 15], [$x1, $R + 15]], $cy, [
            'cut' => true,
            'bores' => [[[$x0, $r], [$x0 + $t, $r], [$x0 + $t, $R - $t - 3], [$x0 + $t + 3, $R - $t], [$x1 - 4, $R - $t], [$x1, $R + 1]]],
        ]);
        $c = $x0 + $t + 15;   // end of the collar
        $s->rect($x0 + $t, $cy - $r - $t, 15, $t, 'md-part', 0, 'cyh')->rect($x0 + $t, $cy - $r - $t, 15, $t, 'md-cut', 0, 'hatch');
        $s->line($x0 + $t, $cy - $r, $c, $cy - $r)->line($c, $cy - $r - $t, $c, $cy - $r)->line($x0 + $t, $cy - $r - $t, $c, $cy - $r - $t);
        $s->line($c, $cy - $r, $c, $cy, 'md-edge');
        $s->hid($x0 + $t, $cy + $r, $c, $cy + $r)->hid($x0 + $t, $cy + $r + $t, $c, $cy + $r + $t)->hid($c, $cy, $c, $cy + $r + $t);
        $s->cl($x0 - 16, $cy, $x1 + 18, $cy);
        $s->dimV(['pa_boot-diam', 'pa_outer-dia'], $cy - $R, $cy + $R, $x0 - 38, $x0 + 6, $x0 + 6);
        $s->dimH(['pa_height', 'pa_length'], $x0, $x1, $cy + $R + 46, $cy + $R - 6, $cy + $R + 15);

        $ex = 366;
        $s->circle($ex, $cy, $R + 15, 'md-part', 'face')->circle($ex, $cy, $R + 1, 'md-edge')->circle($ex, $cy, $R - $t, 'md-edge');
        $s->circle($ex, $cy, $r + $t, 'md-edge')->circle($ex, $cy, $r, 'md-hole');
        $s->cl($ex - $R - 27, $cy, $ex + $R + 27, $cy)->cl($ex, $cy - $R - 27, $ex, $cy + $R + 27);
        $s->dimH(['pa_stub-diam', 'pa_inside-dia'], $ex - $r, $ex + $r, $cy, null, null, ['t' => .5]);

        return $s;
    }

    /** CV boot: large cuff, bellows, small cuff, with its clamps */
    public static function boot(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        $x0 = 64;
        $x1 = 104;
        $x2 = 314;
        $x3 = 354;
        $top = [['M', $x0, $cy - 72], ['L', $x1, $cy - 72]];
        $n = 6;
        $w = ($x2 - $x1) / $n;
        for ($i = 0; $i < $n; $i++) {
            $peak = 72 - 40 * ($i + .5) / $n;
            $val = $peak - 16;
            $top[] = ['Q', $x1 + $w * ($i + .25), $cy - $peak - 6, $x1 + $w * ($i + .5), $cy - $peak];
            $top[] = ['Q', $x1 + $w * ($i + .75), $cy - $val + 6, $x1 + $w * ($i + 1), $cy - max($val, 26)];
        }
        $top[] = ['L', $x3, $cy - 26];
        // the lower outline is the upper one walked back and mirrored about the axis
        $bottom = [['L', $x3, $cy + 26]];
        for ($i = count($top) - 1; $i >= 1; $i--) {
            $prev = $top[$i - 1];
            [$ex, $ey] = array_slice($prev, -2);
            $bottom[] = $top[$i][0] === 'Q' ? ['Q', $top[$i][1], 2 * $cy - $top[$i][2], $ex, 2 * $cy - $ey] : ['L', $ex, 2 * $cy - $ey];
        }
        $s->shape(array_merge($top, $bottom, [['Z']]), 'md-part', null, 'rubber');
        $s->rect($x0 + 10, $cy - 76, 18, 152, 'md-part', 2, 'cyh')->rect($x3 - 26, $cy - 30, 16, 60, 'md-part', 2, 'cyh');
        $s->cl($x0 - 16, $cy, $x3 + 16, $cy);
        $s->dimH(['pa_length', 'pa_overall-length'], $x0, $x3, 238, $cy + 74, $cy + 28);
        $s->dimV(['pa_lg-dia-boot', 'pa_outer-dia'], $cy - 72, $cy + 72, 34, $x0, $x0);
        $s->dimV(['pa_sm-dia-boot', 'pa_inside-dia'], $cy - 26, $cy + 26, $x3 + 30, $x3, $x3);

        return $s;
    }
}
