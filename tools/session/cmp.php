<?php
$live=json_decode(file_get_contents('/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/live.json'),true);
$isPh=fn($u)=>!$u||stripos($u,'placeholder')!==false||stripos($u,'IMAGE-MISSING')!==false||stripos($u,'1312312312132')!==false;
$loc=[]; foreach(DB::connection('wp')->table('wp_postmeta')->join('wp_posts','wp_posts.ID','=','wp_postmeta.post_id')->where('meta_key','_sku')->where('post_type','product')->where('post_status','publish')->get(['ID','post_title','meta_value']) as $r) $loc[$r->meta_value][]=$r;
$cl=DB::table('catalog_live')->get()->keyBy('wp_product_id');
$st=['same_id'=>0,'id_changed'=>[],'missing_local'=>[],'name'=>[],'img_new'=>[],'img_diff'=>[],'price'=>[]];
$liveSkus=[];
foreach($live as $p){ $sku=$p['sku']; if($sku==='') continue; $liveSkus[$sku]=1;
  if(!isset($loc[$sku])){ $st['missing_local'][]=[$sku,$p['id']]; continue; }
  $ids=array_unique(array_map(fn($r)=>$r->ID,$loc[$sku]));
  if(!in_array($p['id'],$ids)){ $st['id_changed'][]=[$sku,$p['id'],$ids]; continue; }
  $st['same_id']++; $row=$cl[$p['id']]??null; $title=$loc[$sku][0]->post_title;
  if(html_entity_decode($title,ENT_QUOTES)!==$p['name'] && (!$row||$row->name!==$p['name'])) $st['name'][]=[$sku,$title,$p['name']];
  $li=$isPh($p['image'])?null:$p['image']; $ci=$row&&!$isPh($row->image)?$row->image:null;
  if($li && !$ci) $st['img_new'][]=[$sku,$li]; elseif($li&&$ci&&$li!==$ci) $st['img_diff'][]=[$sku,$ci,$li];
  if($row && $row->price!==null && $p['price']!==null && abs($row->price-$p['price']/10**$p['minor'])>0.005) $st['price'][]=[$sku,$row->price,$p['price']/10**$p['minor']];
}
$onlyLocal=[]; foreach($loc as $sku=>$r) if(!isset($liveSkus[$sku])) $onlyLocal[]=$sku;
echo "live con SKU: ".count($liveSkus)." | mismo ID: {$st['same_id']}\n";
echo "mismo SKU, ID distinto (re-creados): ".count($st['id_changed'])."\n";
echo "SKU vivo que no existe local: ".count($st['missing_local'])."\n";
echo "SKU local que ya no está en vivo: ".count($onlyLocal)."\n";
echo "nombre distinto (mismo ID): ".count($st['name'])." | imagen nueva: ".count($st['img_new'])." | imagen cambiada: ".count($st['img_diff'])." | precio distinto: ".count($st['price'])."\n";
file_put_contents('/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/diff.json',json_encode($st+['only_local'=>$onlyLocal]));
foreach(['id_changed','missing_local','name','img_new','img_diff','price'] as $k) { echo "\n[$k] ejemplos:\n"; foreach(array_slice($st[$k],0,4) as $x) echo '  '.substr(json_encode($x,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),0,230)."\n"; }
