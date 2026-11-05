<?php
// usage: php track.php framesDir out.json  (frames are 360x640 PNGs of a 1080x1920 clip)
[$_, $dir, $out] = $argv; $S = 4; $files = glob("$dir/*.png"); sort($files); $res = [];
foreach ($files as $f) {
  $im = new Imagick($f); $W = $im->getImageWidth(); $H = $im->getImageHeight(); $px = $im->exportImagePixels(0, 0, $W, $H, 'RGB', Imagick::PIXEL_CHAR);
  $pts = []; $n = 0; $sx = 0; $sy = 0;
  for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x++) { $i = ($y*$W+$x)*3; $r = $px[$i]; $g = $px[$i+1]; $b = $px[$i+2];
    if ($g > 90 && $g > $r + 40 && $g > $b + 30) { $pts[] = $x; $pts[] = $y; $sx += $x; $sy += $y; $n++; } }
  if ($n < 300) { $res[] = null; continue; }
  $cx = $sx/$n; $cy = $sy/$n; $a = $b2 = $c = 0;
  for ($k = 0; $k < count($pts); $k += 2) { $dx = $pts[$k]-$cx; $dy = $pts[$k+1]-$cy; $a += $dx*$dx; $b2 += $dx*$dy; $c += $dy*$dy; }
  $th = 0.5*atan2(2*$b2, $a-$c); $vx = cos($th); $vy = sin($th); $ux = -$vy; $uy = $vx;
  $best = array_fill(0, 4, [-1e9, 0, 0]);
  for ($k = 0; $k < count($pts); $k += 2) { $dx = $pts[$k]-$cx; $dy = $pts[$k+1]-$cy; $u = $dx*$ux+$dy*$uy; $v = $dx*$vx+$dy*$vy;
    $sc = [-$u-$v, $u-$v, $u+$v, -$u+$v]; for ($q = 0; $q < 4; $q++) if ($sc[$q] > $best[$q][0]) $best[$q] = [$sc[$q], $pts[$k], $pts[$k+1]]; }
  $cs = array_map(fn($p) => [($p[1]+0.5)*$S, ($p[2]+0.5)*$S], $best); $C = [$cx*$S, $cy*$S];
  usort($cs, fn($p, $q) => atan2($p[1]-$C[1], $p[0]-$C[0]) <=> atan2($q[1]-$C[1], $q[0]-$C[0]));
  $i0 = 0; foreach ($cs as $i => $p) if ($p[0]+$p[1] < $cs[$i0][0]+$cs[$i0][1]) $i0 = $i;
  $cs = array_merge(array_slice($cs, $i0), array_slice($cs, 0, $i0)); $res[] = array_merge(...$cs);
}
for ($i = 0; $i < count($res); $i++) if (!$res[$i]) $res[$i] = $res[$i-1] ?? current(array_filter($res));
$sm = []; foreach ($res as $i => $f) { $row = []; foreach ($f as $j => $_) { $s = $w = 0; for ($k = -3; $k <= 3; $k++) if (isset($res[$i+$k])) { $ww = 4-abs($k); $s += $res[$i+$k][$j]*$ww; $w += $ww; } $row[] = round($s/$w, 1); } $sm[] = $row; }
file_put_contents($out, json_encode($sm)); echo basename($out), " frames=", count($sm), " f0=", implode(',', array_map('intval', $sm[0])), "\n";
