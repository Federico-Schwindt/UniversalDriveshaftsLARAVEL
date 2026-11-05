<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'mailer=', config('mail.default'), "\n";
if (config('mail.default') !== 'smtp') exit("not smtp\n");
Illuminate\Support\Facades\Mail::raw("Attached is the UDS vs DriveshaftParts.com price comparison: 416 identical parts (UDS cheaper on 152, higher on 264; median difference +8%).\n\nPublic prices as of Nov 1, 2026.", function ($m) use ($argv) {
    $m->to('owner@example.com')->subject('UDS vs DriveshaftParts.com — price comparison')
      ->attach($argv[1], ['as' => 'UDS-vs-DriveshaftParts-price-comparison.pdf', 'mime' => 'application/pdf']);
});
echo "sent\n";
