<?php
$live=json_decode(file_get_contents('/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/live.json'),true); $cl=DB::table('catalog_live')->get()->keyBy('wp_product_id'); $g=$c=$s=0; $ex=[];
foreach($live as $p){ $r=$cl[$p['id']]??null; if(!$r){echo "sin fila: {$p['sku']}\n";continue;}
 if(array_values($p['gallery'])!==(json_decode($r->gallery,true)?:[])){$g++; if(count($ex)<3)$ex[]=[$p['sku'],count($p['gallery']),count(json_decode($r->gallery,true)?:[])];}
 if(array_values($p['cats'])!==(json_decode($r->categories,true)?:[])) $c++;
 if((bool)$p['in_stock']!==(bool)$r->in_stock) $s++; }
echo "galería distinta: $g | categorías distintas: $c | stock distinto: $s\n"; echo json_encode($ex)."\n";
echo "último sync: ".DB::table('catalog_live')->max('synced_at')."\n";
