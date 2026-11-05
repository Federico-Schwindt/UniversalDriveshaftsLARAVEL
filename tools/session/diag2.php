<?php
use Illuminate\Support\Facades\DB;
use App\Services\CatalogService;
$cat = app(CatalogService::class);
$term = $cat->term('short-couple-driveshafts');
$wp = DB::connection('wp');
$rows = $wp->table('wp_posts as p')
  ->join('wp_term_relationships as tr','tr.object_id','=','p.ID')
  ->where('tr.term_taxonomy_id',$term->term_taxonomy_id)
  ->where('p.post_type','product')->where('p.post_status','publish')
  ->leftJoin('wp_postmeta as thumb', function($j){ $j->on('thumb.post_id','=','p.ID')->where('thumb.meta_key','_thumbnail_id'); })
  ->select('p.ID','p.post_title','thumb.meta_value as thumb')
  ->groupBy('p.ID','p.post_title','thumb.meta_value')->get();
// series per product
$ids = $rows->pluck('ID')->all();
$series = $wp->table('wp_term_relationships as tr')
  ->join('wp_term_taxonomy as tt','tt.term_taxonomy_id','=','tr.term_taxonomy_id')
  ->join('wp_terms as t','t.term_id','=','tt.term_id')
  ->where('tt.taxonomy','pa_series')->whereIn('tr.object_id',$ids)
  ->select('tr.object_id','t.name')->get()->groupBy('object_id')->map(fn($g)=>$g->first()->name);
$cl = array_flip(DB::table('catalog_live')->whereNotNull('image')->where('image','!=','')->pluck('wp_product_id')->map(fn($v)=>(int)$v)->all());
$bySeries=[];$noimg=0;$withimg=0;
foreach($rows as $r){
  $s = $series[$r->ID] ?? '(none)';
  $has = (!empty($r->thumb)) || isset($cl[(int)$r->ID]);
  $bySeries[$s]['total']=($bySeries[$s]['total']??0)+1;
  $bySeries[$s]['img']=($bySeries[$s]['img']??0)+($has?1:0);
  $has?$withimg++:$noimg++;
}
echo "products in category: ".count($rows)." | with image: $withimg | without: $noimg\n\nseries | items | with_image\n";
ksort($bySeries);
foreach($bySeries as $s=>$c) printf("  %-18s %4d %4d\n",$s,$c['total'],$c['img']);
$fill=0;$orph=0;
foreach($rows as $r){ $has=(!empty($r->thumb))||isset($cl[(int)$r->ID]); if($has)continue;
  $s=$series[$r->ID]??'(none)'; (($bySeries[$s]['img']??0)>0 && $s!=='(none)')?$fill++:$orph++; }
echo "\nfillable from same-series sibling: $fill\nstay blank: $orph\n";
