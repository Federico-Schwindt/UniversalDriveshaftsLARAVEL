<?php
// read-only: compare the REAL live catalog with ours, matched by SKU. Price deliberately ignored.
$real=json_decode(file_get_contents('/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/real-all.json'),true);
$isPh=fn($u)=>!$u||stripos($u,'placeholder')!==false||stripos($u,'IMAGE-MISSING')!==false||stripos($u,'1312312312132')!==false;
$cl=DB::table('catalog_live')->get()->keyBy('wp_product_id'); $bySku=[]; foreach($cl as $r) if($r->sku) $bySku[$r->sku]=$r;
$out=['sku_changed'=>[],'title'=>[],'image'=>[],'gallery'=>[],'new'=>[],'locked'=>[]]; $realIds=[];
foreach($real as $p){ $realIds[$p['id']]=1;
  $row=$cl[$p['id']]??($p['sku']!==''?($bySku[$p['sku']]??null):null);
  if(!$row){ $out['new'][]=['id'=>$p['id'],'sku'=>$p['sku'],'name'=>$p['name']]; continue; }
  if($row->locked_at){ $out['locked'][]=$p['sku']; continue; }
  $base=['id'=>$p['id'],'sku'=>$p['sku']];
  if((string)$row->sku!==(string)$p['sku']) $out['sku_changed'][]=$base+['old'=>$row->sku];
  if(trim($row->name)!==trim($p['name'])) $out['title'][]=$base+['old'=>$row->name,'new'=>$p['name']];
  $ni=$isPh($p['image'])?null:$p['image']; $oi=$isPh($row->image)?null:$row->image;
  if($ni!==$oi) $out['image'][]=$base+['old'=>$oi,'new'=>$ni];
  if(array_values($p['gallery'])!==(json_decode($row->gallery,true)?:[])) $out['gallery'][]=$base;
}
$gone=$cl->keys()->diff(array_keys($realIds))->count();
file_put_contents('/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/real-diff.json',json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "reales: ".count($real)." | SKU cambiado: ".count($out['sku_changed'])." | título: ".count($out['title'])." | imagen: ".count($out['image'])." (nuevas: ".count(array_filter($out['image'],fn($x)=>!$x['old'])).", quitadas: ".count(array_filter($out['image'],fn($x)=>!$x['new'])).") | galería: ".count($out['gallery'])." | productos nuevos: ".count($out['new'])." | ya no están en vivo: $gone | protegidos (admin): ".count($out['locked'])."\n";
