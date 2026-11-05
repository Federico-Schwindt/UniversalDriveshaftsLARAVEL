<?php
// Runs the (edited) clean() + stamp + write on the transparent originals into a scratch folder. Touches nothing public.
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out = $argv[1];
$cmd = new App\Console\Commands\PtiDewatermark();
$r = new ReflectionClass($cmd);
$call = function ($m, ...$a) use ($r, $cmd) { $x = $r->getMethod($m); $x->setAccessible(true); return $x->invoke($cmd, ...$a); };
$base = storage_path('app/pti/watermark'); $cfg = json_decode(file_get_contents("$base/parts.json"), true);
[$M, $band] = $call('template', "$base/parts.png", (float) $cfg['scale']);
$p = $r->getProperty('opt'); $p->setAccessible(true);
$p->setValue($cmd, ['white' => (int) ($cfg['white'] ?? 247), 'fit' => (bool) ($cfg['fit_strength'] ?? false), 'min_k' => (float) ($cfg['min_k'] ?? 0.02), 'polish' => (bool) ($cfg['polish'] ?? true)]);
$call('loadBrand', "$base/brand.json");
foreach (glob(storage_path('app/pti/originals/img/pti/*')) as $f) {
    $im = new Imagick($f);
    if (!$im->getImageAlphaChannel() || $im->getImageChannelRange(Imagick::CHANNEL_ALPHA)['minima'] >= 65000) continue;
    [$state, $clean, $score, $k] = $call('clean', $f, $M, $band, (float) $cfg['grey'], (float) $cfg['min_score']);
    echo basename($f), " $state ", round($score, 3), "\n";
    if ($clean) { $call('write', clone $clean, "$out/clean-" . basename($f)); $call('write', $call('stamp', clone $clean), "$out/" . basename($f)); }
}
