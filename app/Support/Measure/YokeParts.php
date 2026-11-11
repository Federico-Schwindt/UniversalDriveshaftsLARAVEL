<?php

namespace App\Support\Measure;

/** Yokes: flange, tube (weld), end, slip, transmission, yoke shafts, H, CV link, CV heads and prong yokes. */
trait YokeParts
{
    /**
     * what centres a double cardan joint, standing on the bridge of a CV yoke at ($x, $y): the ball stud, or the
     * socket that takes it (half in section)
     */
    private static function centring(Sketch $s, float $x, float $y, bool $socket, array $keys, array $at): void
    {
        if ($socket) {
            $cup = [['M', $x - 15, $y + 1], ['L', $x - 15, $y - 22], ['L', $x - 13, $y - 24], ['L', $x + 13, $y - 24], ['L', $x + 15, $y - 22], ['L', $x + 15, $y + 1], ['Z']];
            $s->shape($cup, 'md-part', null, 'cyv')->shape($cup, 'md-cut', null, 'hatch', $s->clip($x - 16, $y - 25, $x, $y + 2));
            $s->rect($x - 8, $y - 24, 8, 18, 'md-void');
            $s->shape($cup, 'md-outline');
            $s->rect($x - 7.3, $y - 25, 7.3, 2, 'md-void');   // open mouth
            $s->shape([['M', $x - 8, $y - 24], ['L', $x - 8, $y - 6], ['L', $x, $y - 6]], 'md-line');
            $s->hid($x + 8, $y - 24, $x + 8, $y - 6)->hid($x, $y - 6, $x + 8, $y - 6);
            $s->tag($keys, $x + 8, $y - 14, $at[0], $at[1]);

            return;
        }
        $s->rect($x - 5.5, $y - 16, 11, 14, 'md-part', 0, 'cyv');
        $s->rect($x - 10, $y - 5, 20, 7, 'md-part', 1.5, 'cyv');
        $s->circle($x, $y - 21, 9, 'md-part', 'ball');
        $s->tag($keys, $x + 7.5, $y - 26, $at[0], $at[1]);
    }

    /**
     * "Max. joint angle" view in a band under the drawing, only when the part lists the angle: this yoke across its
     * ears holding the cross, the mating yoke in phantom swung about the cross to the angle (drawn at the part's own
     * value, kept between 12° and 40° so the view stays readable) and the angle between the two shaft axes.
     * $double: a double cardan head, flange yoke to tube yoke with the centre yoke between, half the angle at each cross.
     * $from: the lowest point the drawing's own views use, so the band starts right under them.
     */
    private static function jointAngle(Sketch $s, array $vals, bool $double, float $from): void
    {
        if (!$s->has('pa_joint-angle')) {
            return;
        }
        $deg = (float) preg_replace('/[^\d.]/', '', (string) ($vals['pa_joint-angle'] ?? ''));
        $deg = max(12, min(40, $deg ?: 25));
        // a yoke across its ears, cross centre at 0,0, opening towards +x; $neck = where its shaft ends
        $fork = fn (float $neck) => [['M', 14, -30], ['L', -24, -30], ['C', -34, -30, -40, -14, -48, -12], ['L', $neck, -12], ['L', $neck, 12], ['L', -48, 12],
            ['C', -40, 14, -34, 30, -24, 30], ['L', 14, 30], ['Q', 20, 30, 20, 24], ['Q', 20, 18, 14, 18], ['L', -4, 18], ['C', -14, 18, -18, 10, -18, 0],
            ['C', -18, -10, -14, -18, -4, -18], ['L', 14, -18], ['Q', 20, -18, 20, -24], ['Q', 20, -30, 14, -30], ['Z']];
        // drawn $z times the size of these figures, turned $a degrees counter-clockwise on screen about $ox,$oy;
        // $flip mirrors the part first (opening towards -x)
        $rot = fn (float $ox, float $oy, float $a, float $z, bool $flip = false) => function ($x, $y) use ($ox, $oy, $a, $z, $flip) {
            $x = ($flip ? -$x : $x) * $z;
            $y *= $z;
            $c = cos(deg2rad($a));
            $n = sin(deg2rad($a));

            return [$ox + $x * $c + $y * $n, $oy - $x * $n + $y * $c];
        };
        $sin = sin(deg2rad($deg));

        if (!$double) {
            $z = 1.35;
            $up = max(30, 134 * $sin + 12) * $z;      // centre line of the swung shaft above the cross
            $top = $s->extend(16 + $up + 30 * $z + 34, $from);
            $ox = 214;
            $oy = $top + 16 + $up;
            $tf = $rot($ox, $oy, 0, $z);
            $s->shape($fork(-100), 'md-part', $tf, 'cast');
            // the cross: trunnions in the ear bores, body at the centre
            $s->shape([['M', -5, -18], ['L', 5, -18], ['L', 5, 18], ['L', -5, 18], ['Z']], 'md-part', $tf, 'cyv');
            foreach ([-1, 1] as $m) {
                $s->hid(-7, $m * 18, -7, $m * 30, $tf)->hid(7, $m * 18, 7, $m * 30, $tf);
            }
            $s->circle($ox, $oy, 9 * $z, 'md-part', 'cast')->circle($ox, $oy, 4 * $z, 'md-edge');
            // the mating yoke, ear on: the ear round the cross and its shaft, in phantom
            $mate = array_merge([['M', 30, -15], ['L', 0, -15]], array_slice(Sketch::arc(0, 0, 15, 90, 270, true), 1),
                [['L', 30, 15], ['C', 38, 15, 42, 12, 48, 12], ['L', 104, 12], ['M', 104, -12], ['L', 48, -12], ['C', 42, -12, 38, -15, 30, -15]]);
            $s->shape($mate, 'md-ph', $rot($ox, $oy, $deg, $z));
            $s->cl(-110, 0, 134, 0, $tf)->cl(0, -40, 0, 40, $tf)->cl(0, 0, 134, 0, $rot($ox, $oy, $deg, $z));
            $s->angle('pa_joint-angle', $ox, $oy, 118 * $z, 0, $deg);
            $s->note($ox + 12 * $z, $oy + 30 * $z + 26, 'Max. joint angle');

            return;
        }
        // double cardan: flange yoke level, centre yoke at half the angle, tube yoke (and tube) at the full angle
        $d = 30;
        $phi = $deg / 2;
        $up = max(44, 190 * $sin + 8);
        $top = $s->extend(14 + $up + 44 + 30, $from);
        $ox = 196;
        $oy = $top + 14 + $up;
        $u = fn (float $a) => [cos(deg2rad($a)), -sin(deg2rad($a))];
        [$ux, $uy] = $u($phi);
        [$c1x, $c1y] = [$ox - $d * $ux, $oy - $d * $uy];
        [$c2x, $c2y] = [$ox + $d * $ux, $oy + $d * $uy];
        $s->shape($fork(-66), 'md-part', $rot($c1x, $c1y, 0, 1), 'cast');
        $s->shape([['M', -78, -44], ['L', -66, -44], ['L', -66, 44], ['L', -78, 44], ['Z']], 'md-part', $rot($c1x, $c1y, 0, 1), 'cyh');   // the flange
        $s->shape($fork(-64), 'md-part', $rot($c2x, $c2y, $deg, 1, true), 'cast');
        foreach ([-1, 1] as $m) {   // the tube on the tube yoke, in phantom
            $s->line(64, $m * 16, 116, $m * 16, 'md-ph', $rot($c2x, $c2y, $deg, 1));
        }
        $s->shape([['M', -$d - 6, -22], ['L', $d + 6, -22], ['Q', $d + 22, -22, $d + 22, 0], ['Q', $d + 22, 22, $d + 6, 22], ['L', -$d - 6, 22],
            ['Q', -$d - 22, 22, -$d - 22, 0], ['Q', -$d - 22, -22, -$d - 6, -22], ['Z']], 'md-part', $rot($ox, $oy, $phi, 1), 'cast');
        foreach ([[$c1x, $c1y], [$c2x, $c2y]] as [$x, $y]) {
            $s->circle($x, $y, 11, 'md-part', 'face')->circle($x, $y, 7.5, 'md-edge');
        }
        // the two shaft axes meet at $p, d / cos(half angle) from each cross
        [$tx, $ty] = $u($deg);
        $k = $d / cos(deg2rad($phi));
        [$px, $py] = [$c2x - $k * $tx, $c2y - $k * $ty];
        $s->cl($c1x - 84, $c1y, $px + 190, $py)->cl($px, $py, $px + 190 * $tx, $py + 190 * $ty);
        $s->angle('pa_joint-angle', $px, $py, 172, 0, $deg);
        $s->note($ox + 40, $oy + 44 + 22, 'Max. joint angle');
    }

    /**
     * half section across the ears with the flange below + the flange face. The CV version has its ears bridged
     * over the cross, with the socket for the centring ball on top.
     */
    public static function flangeYoke(array $codes, array $vals = [], bool $cv = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 124;
        $cy = $cv ? 100 : 76;
        $ft = $cy + 58;   // flange back
        $fb = $cy + 74;   // mating face
        $fr = 86;    // flange radius
        $pr = 46;    // pilot radius
        $bc = 66;    // bolt circle radius
        $flange = [['M', $cx - $fr, $ft + 2], ['L', $cx - $fr + 2, $ft], ['L', $cx + $fr - 2, $ft], ['L', $cx + $fr, $ft + 2],
            ['L', $cx + $fr, $fb - 2], ['L', $cx + $fr - 2, $fb], ['L', $cx - $fr + 2, $fb], ['L', $cx - $fr, $fb - 2], ['Z']];
        $pilot = [['M', $cx - $pr, $fb - 1], ['L', $cx + $pr, $fb - 1], ['L', $cx + $pr, $fb + 3], ['L', $cx + $pr - 2, $fb + 5],
            ['L', $cx - $pr + 2, $fb + 5], ['L', $cx - $pr, $fb + 3], ['Z']];
        $clip = $s->clip($cx - $fr - 4, $ft - 1, $cx, $fb + 7);
        foreach ([$pilot, $flange] as $part) {
            $s->shape($part, 'md-part', null, 'cyv')->shape($part, 'md-cut', null, 'hatch', $clip)->shape($part, 'md-outline');
        }
        foreach ([-1, 1] as $m) {   // bolt holes: 45° off the cut plane, so hidden
            $x = $cx + $m * $bc * .7071;
            $s->hid($x - 8, $ft, $x - 8, $fb)->hid($x + 8, $ft, $x + 8, $fb)->cl($x, $ft - 7, $x, $fb + 7);
        }
        $g = self::yoke($s, 'up', $cx, $cy, $ft - $cy + 1, 92, ['series' => [30, 32], 'cut' => true, 'fillet' => 8, 'bridge' => $cv]);
        if ($cv) {
            self::centring($s, $cx, $cy + $g['bt'], true, ['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], [$cx + 52, $cy - 80]);
        }
        $s->cl($cx, $cv ? 18 : $cy - 52, $cx, $fb + 18);
        $s->dimV(['pa_cj-to-flat', 'pa_c-l-to-face'], $cy, $fb, $cx + 114, $cx + 84, $cx + $fr + 2);
        $s->dimH(['pa_pilot-dia', 'pa_bolt-hole-pilot-dia'], $cx - $pr, $cx + $pr, $fb + 36, $fb + 6, $fb + 6);

        $fx = 362;
        $fy = $cv ? 136 : 128;
        $s->circle($fx, $fy, 86, 'md-part', 'face')->circle($fx, $fy, 83, 'md-edge');
        $s->circle($fx, $fy, $pr, 'md-part', 'face')->circle($fx, $fy, $pr - 3, 'md-edge')->circle($fx, $fy, 26, 'md-edge');
        foreach ([-68, 42] as $x) {   // the ears behind the flange
            $s->rect($fx + $x, $fy - 27, 26, 54, 'md-hid', 4);
        }
        $s->cl($fx - 98, $fy, $fx + 98, $fy)->cl($fx, $fy - 98, $fx, $fy + 98);
        self::bolts($s, $fx, $fy, $bc, 8, 4, ['bc' => [1, 3], 't' => .12, 'dia' => [0, 45], 'count' => [284, $fy - 98], 'countHole' => 1]);

        self::jointAngle($s, $vals, false, $cv ? 270 : 228);
        return $s;
    }

    public static function cvFlangeYoke(array $codes): Sketch
    {
        return self::flangeYoke($codes, [], true);
    }

    /**
     * tube yoke in half section with the tube in phantom over the butt + the tube end. The ball stud version has its
     * ears bridged over the cross, with the centring stud on top.
     */
    public static function weldYoke(array $codes, array $vals = [], bool $stud = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 130;
        $cy = $stud ? 92 : 64;
        $w = $cy + 72;   // weld shoulder: where the tube end sits
        $tr = 60;    // tube outside radius
        $br = 55;    // butt radius (tube inside)
        $bl = 24;    // butt length
        $end = $w + ($stud ? 84 : 100);
        $butt = [['M', $cx - $br, $w - 1], ['L', $cx + $br, $w - 1], ['L', $cx + $br, $w + $bl - 2], ['L', $cx + $br - 2, $w + $bl],
            ['L', $cx - $br + 2, $w + $bl], ['L', $cx - $br, $w + $bl - 2], ['Z']];
        $s->shape($butt, 'md-part', null, 'cyv');
        $s->shape($butt, 'md-cut', null, 'hatch', $s->clip($cx - $br - 4, $w - 2, $cx, $w + $bl + 2));
        $s->rect($cx - $br + 7, $w - 2, $br - 7, $bl + 3, 'md-void');
        $s->line($cx - $br + 7, $w, $cx - $br + 7, $w + $bl, 'md-line');
        $s->shape($butt, 'md-outline');
        $s->rect($cx - $br + 7.6, $w + $bl - 1, $br - 7.6, 2, 'md-void');   // the butt is hollow: open end
        $s->hid($cx + $br - 7, $w, $cx + $br - 7, $w + $bl);
        $g = self::yoke($s, 'up', $cx, $cy, $w - $cy + 1, 2 * $tr, ['series' => [28, 28], 'cut' => true, 'bore' => [$br - 7, 58], 'bridge' => $stud]);
        $s->hid($cx, $cy + 58, $cx + $br - 7, $cy + 58)->hid($cx + $br - 7, $cy + 58, $cx + $br - 7, $w);
        $s->line($cx - $tr, $w + 1, $cx - $br, $w + 1, 'md-line')->line($cx + $br, $w + 1, $cx + $tr, $w + 1, 'md-line');
        foreach ([-1, 1] as $k) {   // the tube it welds into, in phantom
            $s->line($cx + $tr * $k, $w + 1, $cx + $tr * $k, $end, 'md-ph')->line($cx + $br * $k, $w + $bl, $cx + $br * $k, $end, 'md-ph');
        }
        $s->cl($cx, $stud ? 12 : $cy - 52, $cx, $end + 10);
        if ($stud) {
            self::centring($s, $cx, $cy + $g['bt'], false, ['pa_ball-stud-length-dia', 'pa_ball-stud-dia'], [$cx + 50, $cy - 74]);
        }
        $s->dimV(['pa_center-u-j-to-weld', 'pa_c-l-to-weld'], $cy, $w + 1, $cx + 112, $cx + 84, $cx + $tr + 2);
        $s->dimH('pa_butt-dia', $cx - $br, $cx + $br, $w + $bl + 30, $w + $bl, $w + $bl);
        $ty = $stud ? 130 : 118;
        self::tube($s, 372, $ty, 62, 55, $ty + 98);

        self::jointAngle($s, $vals, false, $stud ? 252 : 240);
        return $s;
    }

    public static function ballStudYoke(array $codes, array $vals = []): Sketch
    {
        return self::weldYoke($codes, $vals, true);
    }

    /** half section (ears left, hub right) + the hub end: splined bore, or a round bore with keyway for PTO yokes */
    public static function endYoke(array $codes, array $vals = [], bool $pto = false): Sketch
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
        return self::endYoke($codes, [], true);
    }

    /** half section: ears left, internally splined sleeve right + the sleeve end; the transmission version has a seal hub and counterbore */
    public static function slipYoke(array $codes, array $vals = [], bool $trans = false): Sketch
    {
        $s = new Sketch($codes);
        $cx = 74;
        $cy = 118;
        $x1 = $cx + 58;
        $x2 = $cx + 244;
        if ($trans) {
            // the spline runs through to the throat floor, where the nut's washer bears
            $r = 30;
            self::turned($s, [[$x1 - 2, $r], [$x2 - 3, $r], [$x2, $r - 3]], $cy, ['cut' => true, 'bores' => [[[$x1 - 2, 19], [$x2 - 20, 19], [$x2 - 20, 24], [$x2, 24]]]]);
            $s->line($x1 - 2, $cy - 16, $x2 - 20, $cy - 16, 'md-edge');
            $s->dimV('pa_hub-dia', $cy - $r, $cy + $r, $x2 + 24, $x2, $x2);
            $s->dimH('pa_hub-length', $x1, $x2, $cy - $r - 36, $cy - $r, $cy - $r);
            $s->dimH('pa_counter-bore-depth', $x2 - 20, $x2, $cy - $r - 14, $cy - 24, $cy - 24);
        } else {
            $r = 34;
            self::turned($s, [[$x1 - 2, $r], [$x2 - 22, $r], [$x2 - 22, $r - 2], [$x2 - 16, $r - 2], [$x2 - 16, $r], [$x2 - 3, $r], [$x2, $r - 3]], $cy,
                ['cut' => true, 'bores' => [[[$x1 - 2, 26], [$x1 + 17, 26], [$x1 + 17, 23], [$x2, 23]]]]);
            $s->line($x1 + 17, $cy - 19.5, $x2, $cy - 19.5, 'md-edge');
        }
        self::yoke($s, 'left', $cx, $cy, $x1 - $cx + 1, 2 * $r, ['series' => [22, 24], 'cut' => true, 'bore' => $trans ? [19, 47] : [26, 47]]);
        if (!$trans) {   // the spline bore runs through to the throat, closed there by a plug
            $s->rect($cx + 47, $cy - 26, 3.5, 26, 'md-part', 0, 'cyv');
            $s->hid($cx + 47, $cy, $cx + 47, $cy + 26)->hid($cx + 50.5, $cy, $cx + 50.5, $cy + 26)->hid($cx + 47, $cy + 26, $x1 - 2, $cy + 26);
        }
        $s->cl($cx - 50, $cy, $x2 + 12, $cy);
        $s->dimH(['pa_center-u-joint-to-end', 'pa_c-l-to-end', 'pa_cj-to-end'], $cx, $x2, 222, $cy + 84, $cy + $r);
        // cross centre to the washer face: the machined seat on the throat floor (the nut's washer, or a slip yoke's plug)
        $s->dimH('pa_cl-to-washer', $cx, $cx + 47, $cy - 10);

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

        self::jointAngle($s, $vals, false, 240);
        return $s;
    }

    public static function transYoke(array $codes, array $vals = []): Sketch
    {
        return self::slipYoke($codes, $vals, true);
    }

    /** yoke with an externally splined shaft + the spline end */
    public static function yokeShaft(array $codes, array $vals = []): Sketch
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
        // spline end back to the radius where the shaft runs into the yoke
        $s->dimH('pa_end-to-radius', $x1, 344, 64, $cy - 33, $cy - 30);
        $s->dimV(['pa_splines-dia', 'pa_splines-count-dia'], $cy - 30, $cy + 30, 370, 344, 344);

        $ex = 432;
        self::gear($s, $ex, $cy, 27, 33, 18, 'md-part', 'face');
        $s->circle($ex, $cy, 5, 'md-edge');
        $s->tag('pa_splines-count', $ex + 23, $cy - 23, $ex + 24, $cy - 78);

        self::jointAngle($s, $vals, false, 254);
        return $s;
    }

    /** two yokes back to back + the view along the shaft with the circle the ear tips swing through */
    public static function hYoke(array $codes, array $vals = []): Sketch
    {
        $s = new Sketch($codes);
        $cx = 126;
        self::yoke($s, 'down', $cx, 196, 58, 92);
        self::yoke($s, 'up', $cx, 82, 58, 92, ['series' => [30, 34]]);
        foreach ([-15, 15] as $x) {   // pocket the centring ball works in
            $s->hid($cx + $x, 128, $cx + $x, 150);
        }
        $s->cl($cx, 30, $cx, 246);
        $s->dimV(['pa_cj-to-cj', 'pa_cl-to-cl'], 82, 196, $cx + 108, $cx + 84, $cx + 84);

        $ex = 364;
        $ey = 130;
        $k = 1.26;
        [$S, $in, $hw, $c] = [68 * $k, 42 * $k, 27 * $k, 20 * $k];
        $R = hypot($S, $hw) - 1;
        $s->circle($ex, $ey, $R, 'md-ph');
        $s->rect($ex - $in - 1, $ey - $hw + 6, 2 * $in + 2, 2 * $hw - 12, 'md-part', 0, 'cast');   // throat floor, between the ears
        $s->line($ex - $in, $ey - $hw + 13, $ex + $in, $ey - $hw + 13, 'md-edge')->line($ex - $in, $ey + $hw - 13, $ex + $in, $ey + $hw - 13, 'md-edge');
        $s->circle($ex, $ey, 15 * $k, 'md-hole')->circle($ex, $ey, 15 * $k + 3, 'md-edge');
        foreach ([$ex - $S, $ex + $in] as $x) {   // ear tips, with the bearing bore across each
            $s->rect($x, $ey - $hw, $S - $in, 2 * $hw, 'md-part', 4, 'cast');
            $s->hid($x, $ey - $c, $x + $S - $in, $ey - $c)->hid($x, $ey + $c, $x + $S - $in, $ey + $c);
        }
        $s->cl($ex - $R - 10, $ey, $ex + $R + 10, $ey)->cl($ex, $ey - $R - 10, $ex, $ey + $R + 10);
        $s->dimH('pa_swing-dia', $ex - $R, $ex + $R, $ey + $R + 20, $ey, $ey);

        self::jointAngle($s, $vals, false, 256);
        return $s;
    }

    /**
     * CV link yoke: a bridge that bolts over two bearing caps (half-round seats, tapped holes either side) and
     * carries the socket for the centring ball. Seen across the bridge + along the caps.
     */
    public static function linkYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        [$S, $in, $c, $q] = [68, 42, 20, .5523];
        $h = 82;     // cap centre line to the open end of the socket
        $cy = 156;   // cap centre line = seat face of the feet
        $socket = function (float $x) use ($s, $cy, $h) {
            $s->rect($x - 17, $cy - $h, 34, 36, 'md-part', 2, 'cyv');
            $s->hid($x - 9, $cy - $h, $x - 9, $cy - $h + 24)->hid($x + 9, $cy - $h, $x + 9, $cy - $h + 24)->hid($x - 9, $cy - $h + 24, $x + 9, $cy - $h + 24);
        };

        $cx = 122;
        $socket($cx);
        $s->shape(self::sym([
            ['M', 0, -56], ['L', 22, -56], ['C', 44, -56, $S, -46, $S, -26], ['L', $S, -2], ['L', $S - 2, 0], ['L', $in + 2, 0], ['L', $in, -2],
            ['L', $in, -14], ['C', $in, -27, 28, -31, 0, -31],
        ]), 'md-part', self::tf('up', $cx, $cy), 'cast');
        foreach ([-1, 1] as $m) {
            $x = $cx + $m * ($S + $in) / 2;
            $s->hid($cx + $m * $in, $cy - $c, $cx + $m * $S, $cy - $c);   // top of the cap seat
            $s->hid($x - 4, $cy, $x - 4, $cy - 17)->hid($x + 4, $cy, $x + 4, $cy - 17);
            $s->shape([['M', $x - 11, $cy], ['L', $x - 11, $cy + $c], ['L', $x + 11, $cy + $c], ['L', $x + 11, $cy]], 'md-ph');   // the cap it clamps
        }
        $s->cl($cx - $S - 14, $cy, $cx + $S + 14, $cy)->cl($cx, $cy - $h - 10, $cx, $cy + 30);
        $s->dimV(['pa_center-u-joint-to-end', 'pa_c-l-to-end', 'pa_cj-to-end'], $cy - $h, $cy, $cx + 102, $cx + 17, $cx + $S + 14);
        $s->dimH(['pa_ball-stud-dia', 'pa_ball-stud-length-dia'], $cx - 9, $cx + 9, $cy - $h - 22, $cy - $h, $cy - $h);
        $s->tag(['pa_series', 'pa_style-series'], $cx - 55, $cy + 12, $cx - 88, $cy + 54)
            ->hi(['pa_series', 'pa_style-series'], [[$cx - 66, $cy + $c, $cx - 44, $cy + $c], [$cx + 44, $cy + $c, $cx + 66, $cy + $c]]);

        $tx = 364;
        $socket($tx);
        $s->shape(self::sym([
            ['M', 0, -$c], ['C', $q * $c, -$c, $c, -$q * $c, $c, 0], ['L', 50, 0], ['L', 52, -2], ['L', 52, -20], ['Q', 52, -28, 44, -28],
            ['L', 30, -28], ['Q', 24, -28, 24, -34], ['L', 24, -50], ['Q', 24, -56, 18, -56], ['L', 0, -56],
        ]), 'md-part', self::tf('up', $tx, $cy), 'cast');
        $s->circle($tx, $cy, $c, 'md-ph');
        foreach ([-38, 38] as $d) {   // tapped holes for the bolts, either side of the cap
            $s->hid($tx + $d - 5, $cy, $tx + $d - 5, $cy - 22)->hid($tx + $d + 5, $cy, $tx + $d + 5, $cy - 22)->hid($tx + $d - 5, $cy - 22, $tx + $d + 5, $cy - 22);
            $s->cl($tx + $d, $cy + 8, $tx + $d, $cy - 34);
        }
        $s->cl($tx - 66, $cy, $tx + 66, $cy)->cl($tx, $cy - $h - 10, $tx, $cy + 30);
        $s->dimH(['pa_bolt-hole-dia', 'pa_thread-dia'], $tx + 33, $tx + 43, $cy + 28, $cy, $cy);

        return $s;
    }

    /**
     * double cardan head from the side: flange yoke and tube yoke across their ears, the centre yoke in front of
     * them ear on (one bearing cap for each cross), the tube in phantom + the flange face
     */
    public static function cvHead(array $codes, array $vals = []): Sketch
    {
        $s = new Sketch($codes);
        $cy = 118;
        $c1 = 102;
        $c2 = 180;
        $s->rect($c2 + 64, $cy - 34, 24, 68, 'md-part', 2, 'cyh');
        $s->line($c2 + 64, $cy - 40, 308, $cy - 40, 'md-ph')->line($c2 + 64, $cy + 40, 308, $cy + 40, 'md-ph');
        self::yoke($s, 'left', $c2, $cy, 65, 80);
        self::yoke($s, 'right', $c1, $cy, 55, 78, ['series' => [20, 24], 'fillet' => 6]);
        $s->rect(34, $cy - 66, 14, 132, 'md-part', 2, 'cyh');
        $mid = ($c1 + $c2) / 2;
        $s->rect($mid - 11, $cy - 32, 22, 64, 'md-part', 3, 'cast');
        $s->rect($c1 - 35, $cy - 27, $c2 - $c1 + 70, 54, 'md-part', 27, 'cast');
        $s->line($mid - 11, $cy - 27, $mid - 11, $cy + 27, 'md-edge')->line($mid + 11, $cy - 27, $mid + 11, $cy + 27, 'md-edge');
        foreach ([$c1, $c2] as $x) {
            $s->circle($x, $cy, 24, 'md-edge')->circle($x, $cy, 20, 'md-part', 'face')->circle($x, $cy, 17, 'md-edge')->circle($x, $cy, 13.5, 'md-edge');
            $s->cl($x, $cy - 30, $x, $cy + 30);
        }
        $s->cl(24, $cy, 318, $cy);
        $s->dimH(['pa_end-to-weld', 'pa_face-to-weld'], 34, $c2 + 64, 228, $cy + 66, $cy + 42);
        $s->dimV('pa_butt-dia', $cy - 34, $cy + 34, $c2 + 76, null, null, ['t' => .28]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 40, $cy + 40, 328, 308, 308);

        $fx = 412;
        $s->circle($fx, $cy, 58, 'md-part', 'face')->circle($fx, $cy, 55, 'md-edge')->circle($fx, $cy, 22, 'md-part', 'face')->circle($fx, $cy, 19, 'md-edge');
        $s->cl($fx - 68, $cy, $fx + 68, $cy)->cl($fx, $cy - 68, $fx, $cy + 68);
        self::bolts($s, $fx, $cy, 41, 6, 4, ['bc' => [1, 3], 't' => .2, 'dia' => [0, 45]]);

        self::jointAngle($s, $vals, true, 246);
        return $s;
    }

    /** tri / quad prong yoke for flex discs: half section (one prong turned into the cut) with the tube in phantom + prong face */
    public static function prongYoke(array $codes): Sketch
    {
        $s = new Sketch($codes);
        $cy = 130;
        self::turned($s, [[48, 28], [50, 30], [76, 30], [76, 36], [92, 36], [122, 58], [122, 92], [150, 92], [152, 90]], $cy,
            ['cut' => true, 'bores' => [[[48, 22], [84, 22]], [[128, 15], [152, 15]]]]);
        // bolt holes through the arms
        foreach ([63, 81] as $y) {
            $s->hid(122, $cy - $y, 152, $cy - $y)->hid(122, $cy + $y, 152, $cy + $y);
        }
        $s->cl(112, $cy - 72, 162, $cy - 72)->cl(112, $cy + 72, 162, $cy + 72);
        $s->line(14, $cy - 36, 76, $cy - 36, 'md-ph')->line(14, $cy + 36, 76, $cy + 36, 'md-ph');
        $s->cl(6, $cy, 166, $cy);
        $s->dimH('pa_face-to-weld', 76, 152, 246, $cy + 38, $cy + 94);
        $s->dimV('pa_butt-dia', $cy - 30, $cy + 30, 62, null, null, ['t' => .74]);
        $s->dimV(['pa_tube-dia-wall', 'pa_tube-dia'], $cy - 36, $cy + 36, 24, null, null, ['t' => .74]);

        $fx = 344;
        self::spider($s, $fx, $cy, 3, 44, 72, 23, 90);
        $s->circle($fx, $cy, 36, 'md-edge')->circle($fx, $cy, 26, 'md-edge')->circle($fx, $cy, 15, 'md-hole');
        $s->cl($fx - 106, $cy, $fx + 106, $cy)->cl($fx, $cy - 106, $fx, $cy + 106);
        $p = self::bolts($s, $fx, $cy, 72, 9, 3, ['start' => 90, 'dia' => [0, 0]]);
        $a = deg2rad(150);
        $s->dim('pa_bolt-circle-dia', $fx + 72 * cos($a), $cy - 72 * sin($a), $p[2][0], $p[2][1], ['t' => .22]);
        $s->dimH(['pa_nose-bushing-dia', 'pa_pilot-dia'], $fx - 15, $fx + 15, $cy, null, null, ['out' => true]);
        // centre to centre of two neighbouring bolt holes (the chord of the bolt circle)
        $s->dimH('pa_hole-to-hole', $p[1][0], $p[2][0], $cy + 88, $p[1][1] + 9, $p[2][1] + 9);

        return $s;
    }
}
