<?php
// read-only: download the live Store API catalog to a JSON file
$all=[]; $page=1;
do {
  $ch=curl_init("https://universaldriveshafts.com/wp-json/wc/store/v1/products?per_page=100&orderby=id&order=asc&page=$page");
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>60,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Mozilla/5.0 (UDS storefront sync)']);
  $b=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code!==200){ fwrite(STDERR,"page $page HTTP $code\n"); exit(1); }
  $j=json_decode($b,true); if(!$j) break;
  foreach($j as $p) $all[]=['id'=>$p['id'],'sku'=>$p['sku'],'name'=>html_entity_decode($p['name'],ENT_QUOTES),'slug'=>$p['slug'],'price'=>$p['prices']['price']??null,'minor'=>$p['prices']['currency_minor_unit']??2,'in_stock'=>$p['is_in_stock'],'image'=>$p['images'][0]['src']??null,'thumb'=>$p['images'][0]['thumbnail']??null,'gallery'=>array_map(fn($i)=>$i['src'],array_slice($p['images']??[],1,8)),'cats'=>array_map(fn($c)=>$c['slug'],$p['categories']??[])];
  fwrite(STDERR,"page $page: ".count($j)."\n"); $page++;
} while(count($j)===100);
file_put_contents($argv[1],json_encode($all)); echo count($all)." live products\n";
