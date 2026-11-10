<?php

namespace App\Support\Measure;

/** Hardware and small parts: straps, u-bolts, bolts, nuts, zerks, snap rings, washers, dust caps, clamps, ball kits. */
trait SmallParts
{
    /** threaded length seen from the side: crests as slanted lines plus the root lines */
    private static function thread(Sketch $s, float $x1, float $y1, float $x2, float $y2, float $r): void
    {
        $vertical = $x1 === $x2;
        $len = $vertical ? $y2 - $y1 : $x2 - $x1;
        for ($t = 4; $t < $len - 2; $t += 5) {
            $vertical
                ? $s->line($x1 - $r, $y1 + $t, $x1 + $r, $y1 + $t + 2.5, 'md-edge')
                : $s->line($x1 + $t, $y1 - $r, $x1 + $t + 2.5, $y1 + $r, 'md-edge');
        }
        foreach ([-1, 1] as $k) {
            $vertical
                ? $s->line($x1 + $k * $r * .78, $y1 + 2, $x1 + $k * $r * .78, $y2 - 1, 'md-thin')
                : $s->line($x1 + 2, $y1 + $k * $r * .78, $x2 - 1, $y1 + $k * $r * .78, 'md-thin');
        }
    }

    /** hex head seen across its corners, axis vertical: three faces */
    private static function headV(Sketch $s, float $x, float $y1, float $y2, float $w): void
    {
        $s->shape([['M', $x - $w, $y2], ['L', $x - $w, $y1 + 2], ['Q', $x - $w, $y1, $x - $w + 3, $y1], ['L', $x + $w - 3, $y1], ['Q', $x + $w, $y1, $x + $w, $y1 + 2], ['L', $x + $w, $y2], ['Z']], 'md-part', null, 'cyv');
        $s->line($x - $w / 2, $y1, $x - $w / 2, $y2, 'md-edge')->line($x + $w / 2, $y1, $x + $w / 2, $y2, 'md-edge');
    }

    /** u-joint strap over a bearing cap, with its two bolts */
    public static function strapKit(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 196;
        $cy = 152;
        $bx = 64;   // bolt offset from the cap centre
        $s->circle($cx, $cy, 44, 'md-part', 'face')->circle($cx, $cy, 40, 'md-edge')->circle($cx, $cy, 16, 'md-edge');
        foreach ([-1, 1] as $k) {
            $x = $cx + $bx * $k;
            $s->rect($x - 7, $cy, 14, 56, 'md-part', 0, 'cyv');
            self::thread($s, $x, $cy + 18, $x, $cy + 56, 7);
        }
        $s->shape(array_merge([['M', $cx - $bx - 20, $cy], ['L', $cx - 44, $cy]], Sketch::arc($cx, $cy, 44, 180, 0, true),
            [['L', $cx + $bx + 20, $cy], ['L', $cx + $bx + 20, $cy - 8], ['L', $cx + 52, $cy - 8]], Sketch::arc($cx, $cy, 52, 9, 171, true),
            [['L', $cx - $bx - 20, $cy - 8], ['Z']]), 'md-part', null, 'cast');
        foreach ([-1, 1] as $k) {
            $x = $cx + $bx * $k;
            self::headV($s, $x, $cy - 22, $cy - 8, 13);
            $s->cl($x, $cy - 34, $x, $cy + 66);
        }
        $s->cl($cx - 60, $cy, $cx + 60, $cy)->cl($cx, $cy - 66, $cx, $cy + 58);
        $s->dimH(['pa_center-to-center', 'pa_ctr-to-ctr'], $cx - $bx, $cx + $bx, 44, $cy - 36, $cy - 36);
        $s->dimH(['pa_fits-cap-dia', 'pa_cap-dia-1', 'pa_bearing-dia'], $cx - 44, $cx + 44, $cy, null, null, ['t' => .5]);
        $s->dimV('pa_bolt-length', $cy - 8, $cy + 56, $cx + $bx + 44, $cx + $bx + 22, $cx + $bx + 8);
        $s->tag(['pa_thread-dia', 'pa_thread-size'], $cx - $bx - 7, $cy + 40, $cx - $bx - 64, $cy + 90);
        $s->tag(['pa_series', 'pa_style-series'], $cx - 31, $cy - 31, $cx - 96, $cy - 108);

        return $s;
    }

    /** u-bolt over a bearing cap */
    public static function uBolt(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 200;
        $cy = 112;
        $s->circle($cx, $cy, 44, 'md-part', 'face')->circle($cx, $cy, 40, 'md-edge')->circle($cx, $cy, 16, 'md-edge');
        $s->shape(array_merge([['M', $cx - 56, $cy + 118], ['L', $cx - 56, $cy]], Sketch::arc($cx, $cy, 56, 180, 0, true),
            [['L', $cx + 56, $cy + 118], ['L', $cx + 46, $cy + 118], ['L', $cx + 46, $cy]], Sketch::arc($cx, $cy, 46, 0, 180, true),
            [['L', $cx - 46, $cy + 118], ['Z']]), 'md-part', null, 'cast');
        foreach ([-1, 1] as $k) {
            self::thread($s, $cx + 51 * $k, $cy + 64, $cx + 51 * $k, $cy + 118, 5);
            $s->cl($cx + 51 * $k, $cy + 20, $cx + 51 * $k, $cy + 130);
        }
        $s->cl($cx - 70, $cy, $cx + 70, $cy)->cl($cx, $cy - 70, $cx, $cy + 60);
        $s->dimH(['pa_center-to-center', 'pa_ctr-to-ctr'], $cx - 51, $cx + 51, 250, $cy + 132, $cy + 132);
        $s->tag(['pa_thread-dia', 'pa_thread-size'], $cx + 56, $cy + 96, $cx + 120, $cy + 120);
        $s->tag(['pa_series', 'pa_style-series'], $cx - 31, $cy - 31, $cx - 100, $cy - 82);
        $s->tag(['pa_fits-cap-dia', 'pa_cap-dia-1'], $cx + 31, $cy - 31, $cx + 100, $cy - 82);

        return $s;
    }

    /** hex bolt: side view + head end */
    public static function bolt(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 128;
        $s->shape([['M', 88, $cy - 40], ['L', 54, $cy - 40], ['Q', 50, $cy - 30, 50, $cy], ['Q', 50, $cy + 30, 54, $cy + 40], ['L', 88, $cy + 40], ['Z']], 'md-part', null, 'cyh');
        $s->line(52, $cy - 20, 88, $cy - 20, 'md-edge')->line(52, $cy + 20, 88, $cy + 20, 'md-edge');
        self::turned($s, [[88, 17], [318, 17], [322, 13]], $cy);
        self::thread($s, 196, $cy, 318, $cy, 17);
        $s->cl(40, $cy, 334, $cy);
        $s->dimH(['pa_length-under-head', 'pa_length', 'pa_bolt-length'], 88, 322, 212, $cy + 42, $cy + 19);
        $s->tag(['pa_thread-size', 'pa_thread-dia'], 270, $cy - 17, 290, $cy - 72);
        $s->tag(['pa_grade', 'pa_type-sp'], 70, $cy - 40, 110, $cy - 84);

        $ex = 414;
        self::hex($s, $ex, $cy, 44);
        $s->cl($ex - 54, $cy, $ex + 54, $cy)->cl($ex, $cy - 54, $ex, $cy + 54);
        $f = 44 * sqrt(3) / 2;
        $s->dimV(['pa_hex-size', 'pa_hex'], $cy - $f, $cy + $f, $ex + 58, $ex + 22, $ex + 22);

        return $s;
    }

    /** hex nut: face + side */
    public static function nut(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 168;
        $cy = 134;
        self::hex($s, $cx, $cy, 82);
        $s->circle($cx, $cy, 36, 'md-hole');
        $s->shape(Sketch::arc($cx, $cy, 42, 100, 370), 'md-thin');
        $s->cl($cx - 94, $cy, $cx + 94, $cy)->cl($cx, $cy - 94, $cx, $cy + 94);
        $f = 82 * sqrt(3) / 2;
        $s->dimV(['pa_hex', 'pa_hex-size'], $cy - $f, $cy + $f, $cx + 112, $cx + 41, $cx + 41);
        $s->dimH(['pa_thread-size', 'pa_thread-dia'], $cx - 36, $cx + 36, $cy, null, null, ['t' => .5]);

        $x1 = 352;
        $x2 = 402;
        $s->shape([['M', $x1, $cy - 79], ['Q', $x1, $cy - 82, $x1 + 3, $cy - 82], ['L', $x2 - 3, $cy - 82], ['Q', $x2, $cy - 82, $x2, $cy - 79],
            ['L', $x2, $cy + 79], ['Q', $x2, $cy + 82, $x2 - 3, $cy + 82], ['L', $x1 + 3, $cy + 82], ['Q', $x1, $cy + 82, $x1, $cy + 79], ['Z']], 'md-part', null, 'cyh');
        $s->line($x1, $cy - 41, $x2, $cy - 41, 'md-edge')->line($x1, $cy + 41, $x2, $cy + 41, 'md-edge');
        $s->hid($x1, $cy - 36, $x2, $cy - 36)->hid($x1, $cy + 36, $x2, $cy + 36);
        $s->cl($x1 - 12, $cy, $x2 + 12, $cy);
        $s->dimH(['pa_thickness', 'pa_height'], $x1, $x2, 248, $cy + 82, $cy + 82);
        $s->tag('pa_self-locking', $x2, $cy - 60, $x2 + 44, $cy - 104);

        return $s;
    }

    /** grease fitting: side view + hex end */
    public static function zerk(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        self::turned($s, [[110, 12], [113, 14], [172, 14]], $cy);
        self::thread($s, 112, $cy, 172, $cy, 14);
        $s->shape([['M', 172, $cy - 34], ['L', 216, $cy - 34], ['Q', 219, $cy - 34, 219, $cy - 30], ['L', 219, $cy + 30], ['Q', 219, $cy + 34, 216, $cy + 34], ['L', 172, $cy + 34], ['Z']], 'md-part', null, 'cyh');
        $s->line(172, $cy - 17, 219, $cy - 17, 'md-edge')->line(172, $cy + 17, 219, $cy + 17, 'md-edge');
        self::turned($s, [[219, 10], [238, 10], [240, 12]], $cy);
        $s->circle(256, $cy, 19, 'md-ball', 'ball');
        $s->cl(96, $cy, 290, $cy);
        $s->dimH(['pa_overall-len', 'pa_overall-length', 'pa_length'], 110, 275, 214, $cy + 16, $cy + 21);
        $s->tag(['pa_thread-size', 'pa_thread-dia'], 140, $cy + 14, 120, $cy + 64);

        $ex = 392;
        self::hex($s, $ex, $cy, 48);
        $s->circle($ex, $cy, 19, 'md-ball', 'ball');
        $s->cl($ex - 58, $cy, $ex + 58, $cy)->cl($ex, $cy - 58, $ex, $cy + 58);
        $f = 48 * sqrt(3) / 2;
        $s->dimV(['pa_hex', 'pa_hex-size'], $cy - $f, $cy + $f, $ex + 62, $ex + 24, $ex + 24);

        return $s;
    }

    /** snap ring on the bearing cap it fits (phantom) + edge */
    public static function snapRing(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 170;
        $cy = 134;
        $s->circle($cx, $cy, 64, 'md-ph');
        $s->shape(array_merge(Sketch::arc($cx, $cy, 84, 280, 620), Sketch::arc($cx, $cy, 70, 620, 280, true), [['Z']]), 'md-part', null, 'face');
        $s->cl($cx - 98, $cy, $cx + 98, $cy)->cl($cx, $cy - 98, $cx, $cy + 98);
        $s->dimH(['pa_fits-brg-dia', 'pa_brg-dia', 'pa_inside-dia'], $cx - 64, $cx + 64, $cy, null, null, ['t' => .3]);
        $s->tag(['pa_style', 'pa_type-sp'], $cx + 12, $cy + 76, $cx + 76, $cy + 112);

        $x1 = 354;
        $x2 = 368;
        $s->rect($x1, $cy - 84, $x2 - $x1, 168, 'md-part', 1, 'cyh');
        $s->cl(($x1 + $x2) / 2, $cy - 98, ($x1 + $x2) / 2, $cy + 98);
        $s->dimH('pa_thickness', $x1, $x2, 30, $cy - 84, $cy - 84);
        $s->note(($x1 + $x2) / 2, $cy + 116, 'edge');

        return $s;
    }

    /** flat washer: face + edge */
    public static function washer(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 166;
        $cy = 128;
        $s->circle($cx, $cy, 92, 'md-part', 'face')->circle($cx, $cy, 89, 'md-edge')->circle($cx, $cy, 44, 'md-hole');
        $s->cl($cx - 104, $cy, $cx + 104, $cy)->cl($cx, $cy - 104, $cx, $cy + 104);
        $s->dimH(['pa_outside-dia', 'pa_od', 'pa_outer-dia'], $cx - 92, $cx + 92, 248, $cy, $cy);
        $s->dimH(['pa_inside-dia', 'pa_id'], $cx - 44, $cx + 44, $cy, null, null, ['t' => .5]);

        $x1 = 352;
        $x2 = 366;
        $s->rect($x1, $cy - 92, $x2 - $x1, 184, 'md-part', 1, 'cyh');
        $s->hid($x1, $cy - 44, $x2, $cy - 44)->hid($x1, $cy + 44, $x2, $cy + 44);
        $s->cl(($x1 + $x2) / 2, $cy - 104, ($x1 + $x2) / 2, $cy + 104);
        $s->dimH('pa_thickness', $x1, $x2, 20, $cy - 92, $cy - 92);

        return $s;
    }

    /** dust cap kit laid out: cap, seal, washer */
    public static function dustCap(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 116;
        $s->circle(110, $cy, 76, 'md-part', 'face')->circle(110, $cy, 66, 'md-edge')->circle(110, $cy, 44, 'md-edge')->circle(110, $cy, 40, 'md-hole');
        $s->cl(24, $cy, 196, $cy)->cl(110, $cy - 86, 110, $cy + 86);
        $s->dimH(['pa_diameter', 'pa_outer-dia', 'pa_od'], 34, 186, 222, $cy, $cy);
        $s->dimH(['pa_inside-dia', 'pa_id'], 70, 150, $cy, null, null, ['t' => .5]);
        $s->note(110, 254, 'cap');

        $s->circle(284, $cy, 54, 'md-part', 'rubber')->circle(284, $cy, 36, 'md-hole');
        $s->cl(222, $cy, 346, $cy)->cl(284, $cy - 62, 284, $cy + 62);
        $s->tag('pa_seal-od-id', 284 + 38, $cy + 38, 330, $cy + 92);
        $s->tag('pa_seal-type', 284 - 45, $cy - 30, 240, $cy - 84);
        $s->note(284, 254, 'seal');

        $s->circle(416, $cy, 50, 'md-part', 'face')->circle(416, $cy, 47, 'md-edge')->circle(416, $cy, 38, 'md-hole');
        $s->cl(358, $cy, 474, $cy)->cl(416, $cy - 58, 416, $cy + 58);
        $s->tag('pa_washer-od-id', 416 + 31, $cy + 31, 456, $cy + 92);
        $s->tag(['pa_series', 'pa_type-sp'], 416 - 31, $cy - 31, 380, $cy - 84);
        $s->note(416, 254, 'washer');

        return $s;
    }

    /** ear clamp: band seen along the axis */
    public static function clamp(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 220;
        $cy = 142;
        $s->shape(array_merge(Sketch::arc($cx, $cy, 96, 99, 441), [['L', $cx + 12, $cy - 104], ['Q', $cx + 12, $cy - 114, $cx, $cy - 114], ['Q', $cx - 12, $cy - 114, $cx - 12, $cy - 104], ['Z']]), 'md-part', null, 'face');
        $s->circle($cx, $cy, 88, 'md-hole');
        $s->cl($cx - 108, $cy, $cx + 108, $cy)->cl($cx, $cy - 124, $cx, $cy + 108);
        $s->dimH(['pa_diameter', 'pa_inside-dia'], $cx - 88, $cx + 88, $cy, null, null, ['t' => .3]);
        $s->tag(['pa_type-sp', 'pa_style'], $cx + 12, $cy - 108, $cx + 76, $cy - 124);

        return $s;
    }

    /** seal guard ring seen along the cap it slips over */
    public static function sealGuard(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 236;
        $cy = 134;
        $s->circle($cx, $cy, 92, 'md-part', 'face')->circle($cx, $cy, 88, 'md-edge')->circle($cx, $cy, 74, 'md-edge')->circle($cx, $cy, 70, 'md-hole');
        $s->cl($cx - 104, $cy, $cx + 104, $cy)->cl($cx, $cy - 104, $cx, $cy + 104);
        $s->dimH(['pa_brg-dia', 'pa_fits-brg-dia', 'pa_inside-dia'], $cx - 70, $cx + 70, $cy, null, null, ['t' => .3]);
        $s->tag(['pa_ujoint-number', 'pa_series'], $cx + 65, $cy - 65, $cx + 120, $cy - 108);

        return $s;
    }

    /** CV centring ball (with its stud bore) + seal in section */
    public static function cvBall(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 150;
        $cy = 134;
        $h = 52;   // flats top and bottom
        $w = sqrt(70 * 70 - $h * $h);
        $a = rad2deg(asin($h / 70));
        $s->shape(array_merge([['M', $cx - $w, $cy - $h], ['L', $cx + $w, $cy - $h]], Sketch::arc($cx, $cy, 70, $a, -$a, true),
            [['L', $cx - $w, $cy + $h]], Sketch::arc($cx, $cy, 70, 180 + $a, 180 - $a, true), [['Z']]), 'md-ball', null, 'ball');
        $s->hid($cx - 16, $cy - $h, $cx - 16, $cy + $h)->hid($cx + 16, $cy - $h, $cx + 16, $cy + $h);
        $s->cl($cx - 84, $cy, $cx + 84, $cy)->cl($cx, $cy - $h - 16, $cx, $cy + $h + 16);
        $s->dimH('pa_ball-dia', $cx - 70, $cx + 70, 240, $cy, $cy);
        $s->dimH(['pa_stud-bore-dia', 'pa_bore-dia'], $cx - 16, $cx + 16, $cy - $h - 22, $cy - $h, $cy - $h, ['out' => true]);
        $s->tag(['pa_series', 'pa_style-series'], $cx - 50, $cy - 30, $cx - 110, $cy - 104);

        $x1 = 352;
        $x2 = 378;
        foreach ([-1, 1] as $k) {
            $s->rect($x1, $k < 0 ? $cy - 62 : $cy + 40, $x2 - $x1, 22, 'md-part', 3, $k < 0 ? 'hatch' : 'rubber');
        }
        $s->cl($x1 - 14, $cy, $x2 + 14, $cy);
        $s->dimV(['pa_seal-o-d', 'pa_seal-od'], $cy - 62, $cy + 62, $x2 + 38, $x2, $x2);
        $s->dimH('pa_seal-height', $x1, $x2, $cy - 88, $cy - 62, $cy - 62, ['out' => true]);
        $s->note(($x1 + $x2) / 2, $cy + 100, 'seal');

        return $s;
    }
}
