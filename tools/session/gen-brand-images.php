<?php
$pub = '/var/www/vhosts/dev.udshafts.com/uds-app/public';
$navy = '#0b1a33';
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

function logo(): Imagick { $l = new Imagick('/var/www/vhosts/dev.udshafts.com/uds-app/public/img/uds-logo.webp'); $l->setImageFormat('png'); return $l; }
function mark(): Imagick { $m = logo(); $m->cropImage(268, 125, 0, 0); $m->setImagePage(0, 0, 0, 0); return $m; }

/* ---- og-default.jpg 1200x630 ---- */
$og = new Imagick(); $og->newImage(1200, 630, new ImagickPixel($navy)); $og->setImageFormat('jpeg');
// subtle diagonal accent band bottom-right
$d = new ImagickDraw(); $d->setFillColor('#12284d'); $d->polygon([['x'=>760,'y'=>630],['x'=>1200,'y'=>630],['x'=>1200,'y'=>250]]); $og->drawImage($d);
$d = new ImagickDraw(); $d->setFillColor('#d1232a'); $d->rectangle(0, 0, 1200, 14); $og->drawImage($d);   // red top rule
$l = logo(); $l->resizeImage(900, 0, Imagick::FILTER_LANCZOS, 1);
$og->compositeImage($l, Imagick::COMPOSITE_OVER, (int)((1200 - $l->getImageWidth()) / 2), 170);
$t = new ImagickDraw(); $t->setFont($font); $t->setFillColor('#ffffff'); $t->setTextAlignment(Imagick::ALIGN_CENTER); $t->setFontSize(40);
$og->annotateImage($t, 600, 400, 0, 'Driveshaft parts & complete driveshafts');
$t2 = new ImagickDraw(); $t2->setFont($font); $t2->setFillColor('#9fb3d1'); $t2->setTextAlignment(Imagick::ALIGN_CENTER); $t2->setFontSize(28);
$og->annotateImage($t2, 600, 460, 0, 'U-joints · yokes · center support bearings · built in Odessa, Florida');
$t3 = new ImagickDraw(); $t3->setFont($font); $t3->setFillColor('#ffffff'); $t3->setTextAlignment(Imagick::ALIGN_CENTER); $t3->setFontSize(30);
$og->annotateImage($t3, 600, 545, 0, '(813) 524-6491  ·  universaldriveshafts.com');
$og->setImageCompressionQuality(82); $og->stripImage(); $og->writeImage("$pub/img/og-default.jpg");

/* ---- apple-touch-icon 180x180 + favicon PNGs/ICO from the UDS mark ---- */
function iconAt(int $size): Imagick {
    global $navy; $c = new Imagick(); $c->newImage($size, $size, new ImagickPixel($navy)); $c->setImageFormat('png');
    $m = mark(); $w = (int) round($size * 0.86); $m->resizeImage($w, 0, Imagick::FILTER_LANCZOS, 1);
    $c->compositeImage($m, Imagick::COMPOSITE_OVER, (int)(($size - $m->getImageWidth()) / 2), (int)(($size - $m->getImageHeight()) / 2));
    return $c;
}
iconAt(180)->writeImage("$pub/apple-touch-icon.png");
iconAt(32)->writeImage("$pub/favicon-32.png");
iconAt(192)->writeImage("$pub/icon-192.png");
$ico = new Imagick();
foreach ([16, 32, 48] as $s) { $i = iconAt($s); $i->setImageFormat('png'); $ico->addImage($i); }
$ico->setFormat('ico'); $ico->writeImages("$pub/favicon.ico", true);
foreach (['img/og-default.jpg','apple-touch-icon.png','favicon-32.png','icon-192.png','favicon.ico'] as $f) echo $f, ' ', filesize("$pub/$f"), " bytes\n";
