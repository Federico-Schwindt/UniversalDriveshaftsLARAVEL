<?php
$ss=app(App\Services\ShipStationService::class);
foreach ([['FL','33605',151,[]],['FL','33605',70,[]],['FL','00000',15,[]],['TX','33605',15,[]],['FL','33605',0.4,[]],['FL','33605',15,[['weight'=>60],['weight'=>12]]]] as [$st,$z,$w,$b]) {
  $r=$ss->rates($st,$z,'US',$w,$b); echo "$st $z w=$w b=".count($b)." => ".count($r)."\n";
}
