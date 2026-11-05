<?php
use Illuminate\Support\Facades\DB;
$wp = DB::connection('wp');
$rows = $wp->table('wp_posts as p')->join('wp_postmeta as m', fn($j)=>$j->on('m.post_id','=','p.ID')->where('m.meta_key','_sku'))
  ->where('p.post_type','product')->where('p.post_status','publish')->select('p.ID','p.post_title','p.post_name','p.post_content','p.post_excerpt','m.meta_value as sku')->get();
$n=count($rows); $startsWithSku=0; $emptySku=0; $noContent=0; $dupNames=[]; $skuDup=[]; $shortContent=0; $lenTitles=[];
foreach($rows as $r){ $t=trim($r->post_title); $s=trim($r->sku);
  if($s==='') $emptySku++; elseif(stripos($t,$s)===0) $startsWithSku++;
  $c=trim(strip_tags($r->post_content.' '.$r->post_excerpt)); if($c==='') $noContent++; elseif(strlen($c)<80) $shortContent++;
  $dupNames[$t]=($dupNames[$t]??0)+1; if($s!=='') $skuDup[$s]=($skuDup[$s]??0)+1; $lenTitles[]=strlen($t);
}
$dn=array_filter($dupNames,fn($v)=>$v>1); $sd=array_filter($skuDup,fn($v)=>$v>1);
echo "products=$n startsWithSku=$startsWithSku emptySku=$emptySku noDescription=$noContent shortDesc(<80)=$shortContent dupTitles=".count($dn)." (rows ".array_sum($dn).") dupSkus=".count($sd)."\n";
echo "sample dup titles: ".implode(' | ',array_slice(array_keys($dn),0,8))."\n";
echo "sample dup skus: ".implode(' | ',array_slice(array_keys($sd),0,8))."\n";
echo "sample titles:\n"; foreach(array_slice($rows->all(),0,12) as $r) echo "  [{$r->sku}] {$r->post_title}\n";
// images: products without image
$noImg = $wp->table('wp_posts as p')->where('p.post_type','product')->where('p.post_status','publish')->whereNotExists(fn($q)=>$q->from('wp_postmeta as mm')->whereColumn('mm.post_id','p.ID')->where('mm.meta_key','_thumbnail_id'))->count();
echo "productsWithoutThumbnail=$noImg\n";
// finder parts count
try { foreach(['driveshaft_parts','finder_parts','ds_parts','driveshafts','catalog_driveshafts'] as $t){ if (\Illuminate\Support\Facades\Schema::hasTable($t)) echo "table $t: ".DB::table($t)->count()."\n"; } } catch(\Throwable $e){ echo $e->getMessage()."\n"; }
echo implode(',', array_map(fn($t)=>array_values((array)$t)[0], DB::select('SHOW TABLES')))."\n";
