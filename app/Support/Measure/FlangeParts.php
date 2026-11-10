<?php

namespace App\Support\Measure;

/** Flanges and discs: companion flanges, flex discs, CV joints, bushings, boot cans and boots. */
trait FlangeParts
{
    /** companion flange: side view (hub left, flange, pilot) + face with the splined bore */
    public static function companionFlange(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        $s->rect(54, $cy - 36, 96, 72)->rect(150, $cy - 90, 16, 180)->rect(166, $cy - 44, 9, 88);
        $s->hid(54, $cy - 22, 175, $cy - 22)->hid(54, $cy + 22, 175, $cy + 22);
        $s->cl(40, $cy, 188, $cy);
        $s->dimV('pa_hub-dia', $cy - 36, $cy + 36, 30, 54, 54);
        $s->dimV(['pa_pilot-dia', 'pa_bolt-hole-pilot-dia'], $cy - 44, $cy + 44, 202, 175, 175);
        $s->dimH(['pa_overall-length', 'pa_length'], 54, 175, 248, $cy + 38, $cy + 92);

        $fx = 352;
        $s->circle($fx, $cy, 90)->circle($fx, $cy, 44, 'md-line');
        self::gear($s, $fx, $cy, 21, 26, 18);
        $s->cl($fx - 100, $cy, $fx + 100, $cy)->cl($fx, $cy - 100, $fx, $cy + 100);
        self::bolts($s, $fx, $cy, 66, 8, 4, ['bc' => [1, 3], 't' => .12, 'dia' => [0, 45], 'count' => [262, 236], 'countHole' => 2]);
        $s->dimH(['pa_through-hole', 'pa_splines-dia'], $fx - 26, $fx + 26, $cy, null, null, ['out' => true]);
        $s->tag('pa_splines-count', $fx - 19, $cy - 19, $fx - 62, $cy - 104);

        return $s;
    }

    /** rubber flex disc (guibo): face + edge */
    public static function flexDisc(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 160;
        $cy = 135;
        self::rpoly($s, $cx, $cy, 112, 6, 0, .3);
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad($i * 60);
            $s->circle($cx + 76 * cos($a), $cy - 76 * sin($a), 16, 'md-line');
        }
        $s->circle($cx, $cy, 26, 'md-line')->circle($cx, $cy, 19, 'md-hole');
        $s->cl($cx - 124, $cy, $cx + 124, $cy)->cl($cx, $cy - 112, $cx, $cy + 112);
        self::bolts($s, $cx, $cy, 76, 10, 6, ['start' => 0, 'bc' => [1, 4], 't' => .16, 'dia' => [0, 0], 'count' => [44, 30], 'countHole' => 2]);
        $s->dimH(['pa_pilot-dia', 'pa_nose-bushing-dia'], $cx - 19, $cx + 19, $cy + 1, null, null, ['out' => true]);

        $x1 = 360;
        $x2 = 400;
        $s->rect($x1, 40, $x2 - $x1, 190, 'md-part', 10);
        foreach ([69, 135, 201] as $y) {
            $s->hid($x1 - 4, $y - 16, $x2 + 4, $y - 16)->hid($x1 - 4, $y + 16, $x2 + 4, $y + 16);
            $s->rect($x1 - 4, $y - 16, $x2 - $x1 + 8, 32, 'md-line', 2);
        }
        $s->cl(($x1 + $x2) / 2, 26, ($x1 + $x2) / 2, 244);
        $s->dimH(['pa_disc-width', 'pa_thickness'], $x1, $x2, 18, 40, 40, ['out' => true]);
        $s->note(($x1 + $x2) / 2, 260, 'edge');

        return $s;
    }

    /** bolt-on CV joint: face (bolt holes, balls, splined inner race) + edge (race widths) */
    public static function cvJoint(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 150;
        $cy = 132;
        $s->circle($cx, $cy, 100)->circle($cx, $cy, 66, 'md-thin');
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad($i * 60);
            $s->circle($cx + 54 * cos($a), $cy - 54 * sin($a), 11, 'md-line');
        }
        $s->circle($cx, $cy, 40);
        self::gear($s, $cx, $cy, 20, 25, 18);
        $s->cl($cx - 112, $cy, $cx + 112, $cy)->cl($cx, $cy - 112, $cx, $cy + 112);
        self::bolts($s, $cx, $cy, 84, 6, 6, ['start' => 30, 'dia' => [0, 30], 'count' => [34, 26], 'countHole' => 2]);
        $s->dimH(['pa_outer-dia', 'pa_od'], $cx - 100, $cx + 100, 254, $cy, $cy);
        $s->dimH(['pa_splines-dia', 'pa_splines-count-dia'], $cx - 25, $cx + 25, $cy, null, null, ['out' => true]);
        $s->tag('pa_splines-count', $cx - 18, $cy + 18, $cx - 104, $cy + 104);

        $s->rect(338, $cy - 100, 60, 200)->rect(328, $cy - 40, 48, 80, 'md-part', 3);
        $s->hid(328, $cy - 22, 376, $cy - 22)->hid(328, $cy + 22, 376, $cy + 22);
        $s->cl(314, $cy, 412, $cy);
        $s->dimH('pa_outer-race-width', 338, 398, 18, $cy - 100, $cy - 100);
        $s->dimH('pa_inner-race-width', 328, 376, 252, $cy + 40, $cy + 40);

        return $s;
    }

    /** increase bushing: face + half section with both tubes in phantom */
    public static function increaseBushing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 122;
        $cy = 132;
        $s->circle($cx, $cy, 96)->circle($cx, $cy, 86, 'md-thin')->circle($cx, $cy, 68, 'md-hole');
        $s->cl($cx - 106, $cy, $cx + 106, $cy)->cl($cx, $cy - 106, $cx, $cy + 106);
        $s->dimH(['pa_outer-dia', 'pa_od'], $cx - 96, $cx + 96, 252, $cy, $cy);
        $s->dimH(['pa_inside-dia', 'pa_id'], $cx - 68, $cx + 68, $cy, null, null, ['t' => .3]);

        $x = 318;
        foreach ([-1, 1] as $k) {
            $s->shape([['M', $x, $cy + 96 * $k], ['L', $x + 10, $cy + 96 * $k], ['L', $x + 10, $cy + 86 * $k], ['L', $x + 54, $cy + 86 * $k],
                ['L', $x + 54, $cy + 68 * $k], ['L', $x, $cy + 68 * $k], ['Z']]);
            $s->line($x + 10, $cy + 86 * $k, $x + 140, $cy + 86 * $k, 'md-ph')->line($x + 10, $cy + 94 * $k, $x + 140, $cy + 94 * $k, 'md-ph');
            $s->line($x - 60, $cy + 66 * $k, $x + 54, $cy + 66 * $k, 'md-ph')->line($x - 60, $cy + 58 * $k, $x + 40, $cy + 58 * $k, 'md-ph');
        }
        $s->cl($x - 70, $cy, $x + 150, $cy);
        $s->dimH(['pa_length', 'pa_height'], $x, $x + 54, 18, $cy - 96, $cy - 96);
        $s->tag('pa_to-tube', $x + 110, $cy - 94, $x + 124, $cy - 124);
        $s->tag('pa_from-tube', $x - 30, $cy + 66, $x - 44, $cy + 108);

        return $s;
    }

    /** plain bushing: side view with the bore hidden + end view */
    public static function noseBushing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        $s->rect(126, $cy - 80, 84, 160, 'md-part', 3);
        $s->hid(150, $cy - 80, 150, $cy + 80)->hid(186, $cy - 80, 186, $cy + 80);
        $s->cl(168, $cy - 94, 168, $cy + 94);
        $s->dimV(['pa_height', 'pa_length'], $cy - 80, $cy + 80, 96, 126, 126);
        $s->dimH(['pa_od', 'pa_outer-dia'], 126, 210, 246, $cy + 80, $cy + 80);

        $ex = 356;
        $s->circle($ex, $cy, 64)->circle($ex, $cy, 28, 'md-hole');
        $s->cl($ex - 76, $cy, $ex + 76, $cy)->cl($ex, $cy - 76, $ex, $cy + 76);
        $s->dimH(['pa_inside-dia', 'pa_id'], $ex - 28, $ex + 28, $cy, null, null, ['t' => .5]);

        return $s;
    }

    /** boot can / bearing retainer in half section */
    public static function bootCan(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 214;
        $top = 66;
        $bot = 190;
        foreach ([-1, 1] as $k) {
            $s->shape([['M', $cx + 88 * $k, $top - 6], ['L', $cx + 80 * $k, $top - 6], ['L', $cx + 80 * $k, $bot - 6], ['L', $cx + 40 * $k, $bot - 6],
                ['L', $cx + 40 * $k, $bot], ['L', $cx + 86 * $k, $bot], ['L', $cx + 86 * $k, $top], ['L', $cx + 88 * $k, $top], ['Z']]);
        }
        $s->line($cx - 80, $bot - 6, $cx + 80, $bot - 6, 'md-hid');
        $s->cl($cx, $top - 30, $cx, $bot + 30);
        $s->dimH(['pa_boot-diam', 'pa_outer-dia'], $cx - 86, $cx + 86, 32, $top - 8, $top - 8);
        $s->dimH(['pa_stub-diam', 'pa_inside-dia'], $cx - 40, $cx + 40, 226, $bot, $bot);
        $s->dimV(['pa_height', 'pa_length'], $top - 6, $bot, $cx + 130, $cx + 90, $cx + 88);

        return $s;
    }

    /** CV boot: large cuff, bellows, small cuff */
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
        $s->shape(array_merge($top, $bottom, [['Z']]));
        $s->rect($x0 + 10, $cy - 75, 18, 150, 'md-line', 2)->rect($x3 - 26, $cy - 29, 16, 58, 'md-line', 2);
        $s->cl($x0 - 16, $cy, $x3 + 16, $cy);
        $s->dimH(['pa_length', 'pa_overall-length'], $x0, $x3, 238, $cy + 74, $cy + 28);
        $s->dimV(['pa_lg-dia-boot', 'pa_outer-dia'], $cy - 72, $cy + 72, 34, $x0, $x0);
        $s->dimV(['pa_sm-dia-boot', 'pa_inside-dia'], $cy - 26, $cy + 26, $x3 + 30, $x3, $x3);

        return $s;
    }
}
