<?php
chdir('/var/www/vhosts/dev.udshafts.com/uds-app');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\CatalogService;
$cat = app(CatalogService::class);
echo "series->image map for category:\n";
foreach($cat->categoryImageBySeries('short-couple-driveshafts') as $s=>$img) printf("  %-12s %s\n",$s, $img);
echo "\nlisting after fill:\n";
$data = $cat->products('short-couple-driveshafts', [], 1, 60, 'price_asc', '', []);
$miss=0;$fixed=0;
foreach($data['items'] as $it){
  $m = CatalogService::imageMissing($it['image']);
  if($m)$miss++;
  $tag = $m ? 'STILL MISSING' : 'ok';
  // mark which ones were filled (image token contains a different sku than own? just show series+ok)
  printf("  %-12s series=%-10s %-13s %s\n", $it['sku'], $it['series']?:'-', $tag, basename(parse_url($it['image'],PHP_URL_PATH)?:''));
}
echo "\nstill missing after fill: $miss / ".count($data['items'])."\n";
