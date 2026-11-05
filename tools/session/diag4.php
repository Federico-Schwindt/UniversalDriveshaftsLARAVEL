<?php
chdir('/var/www/vhosts/dev.udshafts.com/uds-app');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\CatalogService;
$cat = app(CatalogService::class);
$data = $cat->products('short-couple-driveshafts', [], 1, 60, 'price_asc', '', []);
foreach($data['items'] as $it){
  printf("%-14s | series=%-10s | img=%s\n", $it['sku'], $it['series'] ?: '-', $it['image'] ?: '(EMPTY)');
}
