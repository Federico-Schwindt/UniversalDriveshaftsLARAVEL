<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$db = DB::connection('wp');
$rows = $db->select("select p.ID id, p.post_title title, p.post_name slug,
 max(case when m.meta_key='_sku' then m.meta_value end) sku,
 max(case when m.meta_key='_price' then m.meta_value end) price,
 max(case when m.meta_key='_regular_price' then m.meta_value end) regular
 from wp_posts p left join wp_postmeta m on m.post_id=p.ID and m.meta_key in ('_sku','_price','_regular_price')
 where p.post_type='product' and p.post_status='publish' group by p.ID, p.post_title, p.post_name");
$terms = $db->select("select tr.object_id id, tt.taxonomy tax, t.name, t.slug, tt.parent, tt.term_taxonomy_id ttid from wp_term_relationships tr join wp_term_taxonomy tt on tt.term_taxonomy_id=tr.term_taxonomy_id join wp_terms t on t.term_id=tt.term_id where tt.taxonomy in ('product_cat','pa_brand','pa_series','pa_partn-number','product_type')");
$cats = $db->select("select tt.term_id id, t.name, t.slug, tt.parent from wp_term_taxonomy tt join wp_terms t on t.term_id=tt.term_id where tt.taxonomy='product_cat'");
$by=[]; foreach($terms as $t){ $by[$t->id][$t->tax][]=['name'=>$t->name,'slug'=>$t->slug]; }
foreach($rows as $r){ $r->terms=$by[$r->id]??[]; }
file_put_contents($argv[1], json_encode(['products'=>$rows,'cats'=>$cats]));
echo count($rows),"\n";
