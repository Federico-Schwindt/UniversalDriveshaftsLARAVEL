<?php
chdir('/var/www/vhosts/dev.udshafts.com/uds-app');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Services\CatalogService;
$cat = app(CatalogService::class); $wp = DB::connection('wp');
$cats = ['premade-driveshafts','short-couple-driveshafts','unwelded-assemblies'];
$ttids=[]; foreach($cats as $c){ $t=$cat->term($c); if($t) $ttids[]=$t->term_taxonomy_id; }
$ids = $wp->table('wp_term_relationships')->whereIn('term_taxonomy_id',$ttids)
  ->join('wp_posts as p','p.ID','=','wp_term_relationships.object_id')
  ->where('p.post_type','product')->where('p.post_status','publish')
  ->pluck('object_id')->unique()->all();
$ser = $wp->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt','tt.term_taxonomy_id','=','tr.term_taxonomy_id')
  ->join('wp_terms as t','t.term_id','=','tt.term_id')->where('tt.taxonomy','pa_series')
  ->whereIn('tr.object_id',$ids)->select('tr.object_id as id','t.name as series')->get()->keyBy('id');
$thumbs = $wp->table('wp_postmeta')->where('meta_key','_thumbnail_id')->whereIn('post_id',$ids)->pluck('meta_value','post_id');
$files = $wp->table('wp_postmeta')->where('meta_key','_wp_attached_file')->whereIn('post_id',array_values(array_filter(array_map('intval',$thumbs->all()))))->pluck('meta_value','post_id');
$live = DB::table('catalog_live')->whereIn('wp_product_id',$ids)->pluck('image','wp_product_id');
$isReal=function($id)use($thumbs,$files,$live){ $f=isset($thumbs[$id])?($files[(int)$thumbs[$id]]??''):''; $l=$live[$id]??''; $s=strtolower(trim($f.' '.$l)); return $s!=='' && !str_contains($s,'placeholder'); };
$bySeries=[];
foreach($ids as $id){ $s=$ser[$id]->series??'(none)'; $bySeries[$s]['total']=($bySeries[$s]['total']??0)+1; if($isReal($id))$bySeries[$s]['real']=($bySeries[$s]['real']??0)+1; }
ksort($bySeries);
echo "WITHIN complete-driveshafts group:\nseries | products | with REAL image\n";
foreach($bySeries as $s=>$c) printf("  %-26s %4d %4d\n",$s,$c['total'],$c['real']??0);
