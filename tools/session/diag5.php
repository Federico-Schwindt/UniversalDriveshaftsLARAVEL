<?php
chdir('/var/www/vhosts/dev.udshafts.com/uds-app');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$wp = DB::connection('wp');
$target = ['1310','1350','1410','1480','1550','1610','1710','186.37 (53mm x 135mm)'];
// map series name -> product ids catalog-wide
$ser = $wp->table('wp_term_relationships as tr')
  ->join('wp_term_taxonomy as tt','tt.term_taxonomy_id','=','tr.term_taxonomy_id')
  ->join('wp_terms as t','t.term_id','=','tt.term_id')
  ->join('wp_posts as p','p.ID','=','tr.object_id')
  ->where('tt.taxonomy','pa_series')->whereIn('t.name',$target)
  ->where('p.post_type','product')->where('p.post_status','publish')
  ->select('t.name as series','tr.object_id as id')->get();
$ids = $ser->pluck('id')->unique()->all();
// thumbnail ids
$thumbs = $wp->table('wp_postmeta')->where('meta_key','_thumbnail_id')->whereIn('post_id',$ids)->pluck('meta_value','post_id');
$attIds = array_filter(array_map('intval',$thumbs->all()));
$files = $wp->table('wp_postmeta')->where('meta_key','_wp_attached_file')->whereIn('post_id',array_values($attIds))->pluck('meta_value','post_id');
// catalog_live images
$live = DB::table('catalog_live')->whereIn('wp_product_id',$ids)->pluck('image','wp_product_id');
$isReal = function($id) use($thumbs,$files,$live){
  $f = isset($thumbs[$id]) ? ($files[(int)$thumbs[$id]] ?? '') : '';
  $l = $live[$id] ?? '';
  $s = strtolower($f.' '.$l);
  if($s==='' || trim($s)==='') return false;
  return !str_contains($s,'placeholder');
};
$bySeries=[];
foreach($ser as $r){ $bySeries[$r->series]['total']=($bySeries[$r->series]['total']??0)+1;
  if($isReal($r->id)) $bySeries[$r->series]['real']=($bySeries[$r->series]['real']??0)+1; }
echo "series | catalog-wide products | with REAL (non-placeholder) image\n";
foreach($target as $s){ printf("  %-26s %5d %5d\n",$s,$bySeries[$s]['total']??0,$bySeries[$s]['real']??0); }
