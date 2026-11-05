<?php
use App\Services\CatalogService;
$cat = app(CatalogService::class);
$data = $cat->products('short-couple-driveshafts', [], 1, 1000, 'price_asc', '', []);
$items = $data['items'];
echo "total reported: {$data['total']}, fetched: ".count($items)."\n";
$noimg=0;$withimg=0;$bySeries=[];
foreach($items as $it){
  $s=$it['series']?:'(none)'; $has=$it['image']!==''&&$it['image']!==null;
  $bySeries[$s]['total']=($bySeries[$s]['total']??0)+1;
  $bySeries[$s]['img']=($bySeries[$s]['img']??0)+($has?1:0);
  $has?$withimg++:$noimg++;
}
echo "with image: $withimg | without: $noimg\n\nseries | items | with_image\n";
foreach($bySeries as $s=>$c) printf("  %-18s %4d %4d\n",$s,$c['total'],$c['img']);
$fill=0;$orph=0;
foreach($items as $it){ $has=$it['image']!==''&&$it['image']!==null; if($has)continue;
  $s=$it['series']?:'(none)'; (($bySeries[$s]['img']??0)>0 && $s!=='(none)')?$fill++:$orph++; }
echo "\nfillable from same-series sibling: $fill\nstay blank (no series / no sibling img): $orph\n";
