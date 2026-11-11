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

    /**
     * hex nut or bolt head from the side, axis horizontal, seen across its corners ($R = half across corners): three
     * faces, each ending in the curve the chamfer leaves on it ($ends: -1 chamfered left, 1 right, 0 both)
     */
    private static function hexSide(Sketch $s, float $x1, float $x2, float $cy, float $R, int $ends = 0): void
    {
        $c = min(6, ($x2 - $x1) / 5);
        [$l, $r] = [$ends <= 0 ? $c : 0, $ends >= 0 ? $c : 0];
        $s->shape([['M', $x1, $cy - $R + 1.5 * $l], ['L', $x1 + $l, $cy - $R], ['L', $x2 - $r, $cy - $R], ['L', $x2, $cy - $R + 1.5 * $r],
            ['L', $x2, $cy + $R - 1.5 * $r], ['L', $x2 - $r, $cy + $R], ['L', $x1 + $l, $cy + $R], ['L', $x1, $cy + $R - 1.5 * $l], ['Z']], 'md-part', null, 'cyh');
        $s->line($x1 + $l, $cy - $R / 2, $x2 - $r, $cy - $R / 2, 'md-edge')->line($x1 + $l, $cy + $R / 2, $x2 - $r, $cy + $R / 2, 'md-edge');
        foreach ([[$x1, $l, -1], [$x2, $r, 1]] as [$x, $d, $k]) {
            foreach ($d ? [[-1, -.5], [-.5, .5], [.5, 1]] : [] as [$a, $b]) {
                $s->shape([['M', $x - $k * $d, $cy + $a * $R], ['Q', $x + $k * $d, $cy + ($a + $b) / 2 * $R, $x - $k * $d, $cy + $b * $R]], 'md-edge');
            }
        }
    }

    /** outline of a strip $t wide laid along the open path $pts (wire and stamped rings) */
    private static function band(array $pts, float $t): array
    {
        $n = count($pts);
        $l = $r = [];
        foreach ($pts as $i => [$x, $y]) {
            [$ax, $ay] = $pts[max($i - 1, 0)];
            [$bx, $by] = $pts[min($i + 1, $n - 1)];
            $d = hypot($bx - $ax, $by - $ay) ?: 1;
            [$nx, $ny] = [($ay - $by) / $d * $t / 2, ($bx - $ax) / $d * $t / 2];
            $l[] = [$x + $nx, $y + $ny];
            $r[] = [$x - $nx, $y - $ny];
        }
        $cmds = [];
        foreach (array_merge($l, array_reverse($r)) as $i => [$x, $y]) {
            $cmds[] = [$i ? 'L' : 'M', round($x, 2), round($y, 2)];
        }
        $cmds[] = ['Z'];

        return $cmds;
    }

    /**
     * points along a path of straight runs joined by rounded corners: [x, y, radius?] corners, the path starts and ends
     * on one. A radius larger than the runs allow rounds the corner as far as half the shorter run.
     */
    private static function bent(array $corners, float $rad = 9): array
    {
        $pts = [$corners[0]];
        for ($i = 1; $i < count($corners) - 1; $i++) {
            [$px, $py] = $corners[$i - 1];
            [$x, $y] = $corners[$i];
            [$qx, $qy] = $corners[$i + 1];
            [$d1, $d2] = [hypot($x - $px, $y - $py), hypot($qx - $x, $qy - $y)];
            $r = min($corners[$i][2] ?? $rad, $d1 / 2, $d2 / 2);
            [$ax, $ay] = [$x + ($px - $x) / $d1 * $r, $y + ($py - $y) / $d1 * $r];
            [$bx, $by] = [$x + ($qx - $x) / $d2 * $r, $y + ($qy - $y) / $d2 * $r];
            for ($t = 0; $t <= 1.001; $t += .125) {
                $pts[] = [(1 - $t) ** 2 * $ax + 2 * $t * (1 - $t) * $x + $t ** 2 * $bx, (1 - $t) ** 2 * $ay + 2 * $t * (1 - $t) * $y + $t ** 2 * $by];
            }
        }
        $pts[] = $corners[count($corners) - 1];

        return $pts;
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

    /** u-bolt over a bearing cap, through the yoke (phantom), with its lock washers and nuts */
    public static function uBolt(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 200;
        $cy = 100;
        $s->circle($cx, $cy, 44, 'md-part', 'face')->circle($cx, $cy, 40, 'md-edge')->circle($cx, $cy, 16, 'md-edge');
        $s->shape(array_merge([['M', $cx - 56, $cy + 126], ['L', $cx - 56, $cy]], Sketch::arc($cx, $cy, 56, 180, 0, true),
            [['L', $cx + 56, $cy + 126], ['L', $cx + 46, $cy + 126], ['L', $cx + 46, $cy]], Sketch::arc($cx, $cy, 46, 0, 180, true),
            [['L', $cx - 46, $cy + 126], ['Z']]), 'md-part', null, 'cast');
        $s->shape([['M', $cx - 58, $cy + 4], ['L', $cx - 84, $cy + 4], ['L', $cx - 84, $cy + 78], ['L', $cx + 84, $cy + 78], ['L', $cx + 84, $cy + 4], ['L', $cx + 58, $cy + 4]], 'md-ph');
        foreach ([-1, 1] as $k) {
            $x = $cx + 51 * $k;
            self::thread($s, $x, $cy + 60, $x, $cy + 126, 5);
            $s->rect($x - 12, $cy + 78, 24, 5, 'md-part', 1, 'cyv');
            self::headV($s, $x, $cy + 83, $cy + 100, 11);
            $s->cl($x, $cy + 20, $x, $cy + 138);
        }
        $s->cl($cx - 70, $cy, $cx + 70, $cy)->cl($cx, $cy - 70, $cx, $cy + 60);
        $s->dimH(['pa_center-to-center', 'pa_ctr-to-ctr'], $cx - 51, $cx + 51, 256, $cy + 140, $cy + 140);
        $s->tag(['pa_thread-dia', 'pa_thread-size'], $cx + 56, $cy + 114, $cx + 122, $cy + 132);
        $s->tag(['pa_series', 'pa_style-series'], $cx - 31, $cy - 31, $cx - 100, $cy - 72);
        $s->tag(['pa_fits-cap-dia', 'pa_cap-dia-1'], $cx + 31, $cy - 31, $cx + 100, $cy - 72);

        return $s;
    }

    /** hex bolt: side view + head end */
    public static function bolt(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 128;
        self::turned($s, [[86, 17], [318, 17], [322, 13]], $cy);
        self::hexSide($s, 50, 85, $cy, 40, -1);
        $s->rect(85, $cy - 33, 3, 66, 'md-part', 0, 'cyh');   // washer face under the head
        self::thread($s, 196, $cy, 318, $cy, 17);
        $s->cl(40, $cy, 334, $cy);
        $s->dimH(['pa_length-under-head', 'pa_length', 'pa_bolt-length'], 88, 322, 212, $cy + 42, $cy + 19);
        $s->tag(['pa_thread-size', 'pa_thread-dia'], 270, $cy - 17, 290, $cy - 72);

        $ex = 414;
        self::hex($s, $ex, $cy, 44);
        for ($a = 30; $a < 360; $a += 60) {   // grade marks on the head (six lines: grade 8)
            $s->line($ex + 13 * cos(deg2rad($a)), $cy - 13 * sin(deg2rad($a)), $ex + 25 * cos(deg2rad($a)), $cy - 25 * sin(deg2rad($a)), 'md-line');
        }
        $s->tag(['pa_grade', 'pa_type-sp'], $ex + 19 * cos(deg2rad(150)), $cy - 19 * sin(deg2rad(150)), $ex - 56, $cy - 76);
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
        self::hexSide($s, $x1, $x2, $cy, 82);
        $s->hid($x1, $cy - 36, $x2, $cy - 36)->hid($x1, $cy + 36, $x2, $cy + 36);
        $s->cl($x1 - 12, $cy, $x2 + 12, $cy);
        $s->dimH(['pa_thickness', 'pa_height'], $x1, $x2, 248, $cy + 82, $cy + 82);
        $s->tag('pa_self-locking', $x2, $cy - 60, $x2 + 44, $cy - 104);

        return $s;
    }

    /** grease fitting: thread, hex, and the nipple with its check ball + the view on the nipple */
    public static function zerk(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 132;
        self::turned($s, [[104, 12], [107, 14], [168, 14]], $cy);
        self::thread($s, 106, $cy, 168, $cy, 14);
        $s->circle(259, $cy, 5, 'md-ball', 'ball');   // check ball, proud of the tip
        $s->shape(self::sym([['M', 0, 206], ['L', 19, 206], ['L', 10, 220], ['L', 10, 227], ['L', 15, 233], ['L', 15, 238], ['C', 15, 249, 12, 256, 8, 260], ['L', 0, 260]]),
            'md-part', fn ($x, $y) => [$y, $cy + $x], 'cyh');
        $s->line(220, $cy - 10, 220, $cy + 10, 'md-edge')->line(227, $cy - 10, 227, $cy + 10, 'md-edge')
            ->line(233, $cy - 15, 233, $cy + 15, 'md-edge')->line(238, $cy - 15, 238, $cy + 15, 'md-edge');
        self::hexSide($s, 168, 206, $cy, 34);
        $s->cl(90, $cy, 284, $cy);
        $s->dimH(['pa_overall-len', 'pa_overall-length', 'pa_length'], 104, 264, 214, $cy + 16, $cy + 6);
        $s->tag(['pa_thread-size', 'pa_thread-dia'], 136, $cy - 14, 116, $cy - 64);

        $ex = 392;
        self::hex($s, $ex, $cy, 48);
        $s->circle($ex, $cy, 21, 'md-part', 'face')->circle($ex, $cy, 11, 'md-edge')->circle($ex, $cy, 5, 'md-ball', 'ball');
        $s->cl($ex - 58, $cy, $ex + 58, $cy)->cl($ex, $cy - 58, $ex, $cy + 58);
        $f = 48 * sqrt(3) / 2;
        $s->dimV(['pa_hex', 'pa_hex-size'], $cy - $f, $cy + $f, $ex + 62, $ex + 24, $ex + 24);

        return $s;
    }

    /**
     * U-joint snap rings on the bearing they fit (phantom) + edge. The two kinds the table calls "Out" and "In": the
     * outside ring is a stamped clip that springs into the yoke bore beyond the cap (two lobes, squeezed by the tab
     * across its notch), the inside one a plain ring in the groove around the cap. A part's own page draws its kind
     * ($vals = its specs).
     */
    public static function snapRing(array $codes, array $vals = []): Sketch
    {
        $s = new Sketch($codes);
        $cy = 112;
        $fits = ['pa_fits-brg-dia', 'pa_brg-dia', 'pa_inside-dia'];
        $style = ['pa_style', 'pa_type-sp'];
        $ring = function (float $cx, bool $out) use ($s, $cy, $fits, $style) {
            $R = $out ? 46 : 54;   // the bearing
            $s->circle($cx, $cy, $R, 'md-ph');
            if ($out) {   // from the tab's free end round both lobes to the end that stops above it
                $ctrl = [[-17, -6], [15, -6, 5], [15, -54, 13], [40, -54], [64, -21.5], [64, 21.5], [26.5, 52], [-26.5, 52],
                    [-64, 21.5], [-64, -21.5], [-40, -54], [-15, -54, 13], [-14, -19]];
                $pts = self::bent(array_map(fn ($p) => [$cx + $p[0], $cy + $p[1], $p[2] ?? 1e3], $ctrl));
                $s->shape(self::band($pts, 8), 'md-part', null, 'face');
                $s->tag($style, $cx + 4, $cy - 10, $cx + 66, $cy - 86);
            } else {
                $pts = [];
                for ($a = 250; $a >= -70; $a -= 4) {
                    $pts[] = [$cx + ($R + 1) * cos(deg2rad($a)), $cy - ($R + 1) * sin(deg2rad($a))];
                }
                $s->shape(self::band($pts, 8), 'md-part', null, 'face');
                $s->tag($style, $cx + ($R + 5) * M_SQRT1_2, $cy - ($R + 5) * M_SQRT1_2, $cx + 66, $cy - 86);
            }
            $s->cl($cx - 76, $cy, $cx + 76, $cy)->cl($cx, $cy - 66, $cx, $cy + 66);
            $s->dimH($fits, $cx - $R, $cx + $R, $cy + 86, $cy, $cy);
            $s->note($cx, 252, $out ? 'Outside (Out)' : 'Inside (In)');
        };
        $kind = strtoupper(trim((string) ($vals['pa_style'] ?? '')));
        $kind = str_starts_with($kind, 'OUT') ? 'out' : (str_starts_with($kind, 'IN') ? 'in' : null);
        if ($kind) {
            $ring(170, $kind === 'out');
        } else {
            $ring(90, true);
            $ring(262, false);
        }

        $x1 = $kind ? 340 : 388;
        $x2 = $x1 + 9;
        $s->rect($x1, $cy - 56, $x2 - $x1, 112, 'md-part', 1, 'cyh');
        $s->cl(($x1 + $x2) / 2, $cy - 66, ($x1 + $x2) / 2, $cy + 66);
        $s->dimH('pa_thickness', $x1, $x2, 28, $cy - 56, $cy - 56);
        $s->note(($x1 + $x2) / 2, 252, 'edge');

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

    /** dust cap kit laid out: cap, splined seal, splined washer */
    public static function dustCap(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 116;
        $s->circle(110, $cy, 76, 'md-part', 'face')->circle(110, $cy, 66, 'md-edge')->circle(110, $cy, 44, 'md-edge')->circle(110, $cy, 40, 'md-hole');
        $s->cl(24, $cy, 196, $cy)->cl(110, $cy - 86, 110, $cy + 86);
        $s->dimH(['pa_diameter', 'pa_outer-dia', 'pa_od'], 34, 186, 222, $cy, $cy);
        $s->dimH(['pa_inside-dia', 'pa_id'], 70, 150, $cy, null, null, ['t' => .5]);
        $s->note(110, 254, 'cap');

        $s->circle(284, $cy, 54, 'md-part', 'rubber');   // seal and washer ride on the splines: toothed inside
        self::gear($s, 284, $cy, 31, 37, 18);
        $s->cl(222, $cy, 346, $cy)->cl(284, $cy - 62, 284, $cy + 62);
        $s->tag('pa_seal-od-id', 284 + 38, $cy + 38, 330, $cy + 92);
        $s->tag('pa_seal-type', 284 - 45, $cy - 30, 240, $cy - 84);
        $s->note(284, 254, 'seal');

        $s->circle(416, $cy, 50, 'md-part', 'face')->circle(416, $cy, 47, 'md-edge');
        self::gear($s, 416, $cy, 32, 39, 18);
        $s->cl(358, $cy, 474, $cy)->cl(416, $cy - 58, 416, $cy + 58);
        $s->tag('pa_washer-od-id', 416 + 31, $cy + 31, 456, $cy + 92);
        $s->tag(['pa_series', 'pa_type-sp'], 416 - 31, $cy - 31, 380, $cy - 84);
        $s->note(416, 254, 'washer');

        return $s;
    }

    /** ear clamp seen along the axis: thin band, the ear that is pinched shut, the band end tucked under it */
    public static function clamp(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 220;
        $cy = 146;
        $R = 88;    // inside
        $t = 4;     // band
        $e = 12;    // half ear
        $h = 22;    // ear height above the band
        $a = rad2deg(asin($e / ($R + $t)));
        $b = rad2deg(asin(($e - $t) / $R));
        $s->shape(array_merge(Sketch::arc($cx, $cy, $R + $t, 90 + $a, 450 - $a), [['L', $cx + $e, $cy - $R - $h + 4],
            ['Q', $cx + $e, $cy - $R - $h, $cx + $e - 4, $cy - $R - $h], ['L', $cx - $e + 4, $cy - $R - $h],
            ['Q', $cx - $e, $cy - $R - $h, $cx - $e, $cy - $R - $h + 4], ['Z']]), 'md-part', null, 'face');
        $s->shape(array_merge(Sketch::arc($cx, $cy, $R, 90 + $b, 450 - $b), [['L', $cx + $e - $t, $cy - $R - $h + $t],
            ['L', $cx - $e + $t, $cy - $R - $h + $t], ['Z']]), 'md-hole');
        $s->shape(array_merge(Sketch::arc($cx, $cy, $R, 64, 102), Sketch::arc($cx, $cy, $R - 3, 102, 64, true), [['Z']]), 'md-part', null, 'face');
        $s->cl($cx - 108, $cy, $cx + 108, $cy)->cl($cx, $cy - $R - $h - 12, $cx, $cy + 108);
        $s->dimH(['pa_diameter', 'pa_inside-dia'], $cx - $R, $cx + $R, $cy, null, null, ['t' => .3]);
        $s->tag(['pa_type-sp', 'pa_style'], $cx + $e, $cy - $R - $h + 8, $cx + 84, $cy - $R - $h - 4);

        return $s;
    }

    /** seal guard: plastic ring seen along the bearing cap it slips over + its section */
    public static function sealGuard(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cx = 176;
        $cy = 134;
        $s->circle($cx, $cy, 92, 'md-part', 'rubber')->circle($cx, $cy, 87, 'md-edge')->circle($cx, $cy, 70, 'md-hole');
        $s->cl($cx - 104, $cy, $cx + 104, $cy)->cl($cx, $cy - 104, $cx, $cy + 104);
        $s->dimH(['pa_brg-dia', 'pa_fits-brg-dia', 'pa_inside-dia'], $cx - 70, $cx + 70, $cy, null, null, ['t' => .3]);
        $s->tag(['pa_ujoint-number', 'pa_series'], $cx + 57, $cy - 57, $cx + 112, $cy - 112);

        $x = 372;   // section: flat ring with a skirt round its rim
        foreach ([-1, 1] as $k) {
            $s->shape([['M', $x, $cy + 70 * $k], ['L', $x + 5, $cy + 70 * $k], ['L', $x + 5, $cy + 87 * $k], ['L', $x + 22, $cy + 87 * $k],
                ['L', $x + 22, $cy + 92 * $k], ['L', $x + 2, $cy + 92 * $k], ['L', $x, $cy + 90 * $k], ['Z']], 'md-part', null, 'rubber');
        }
        $s->line($x, $cy - 70, $x, $cy + 70, 'md-thin')->line($x + 5, $cy - 70, $x + 5, $cy + 70, 'md-thin');
        $s->line($x + 22, $cy - 87, $x + 22, $cy + 87, 'md-thin');
        $s->cl($x - 16, $cy, $x + 40, $cy);
        $s->note($x + 11, 254, 'section');

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
