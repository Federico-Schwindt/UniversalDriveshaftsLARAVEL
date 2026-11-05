<?php
$ss=app(App\Services\ShipStationService::class);
foreach ([['FL','33605',15,[]],['TX','75001',15,[]],['CA','90210',40,[['weight'=>40,'kind'=>'box']]],['FL','33605',0,[]]] as [$st,$z,$w,$b]) {
  $r=$ss->rates($st,$z,'US',$w,$b); echo "$st $z w=$w => ".count($r)." ".json_encode(array_slice($r,0,2))."\n";
}
