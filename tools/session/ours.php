<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$db = DB::connection('wp');
echo json_encode($db->select("select post_type, post_status, count(*) n from wp_posts where post_type in ('product','product_variation') group by 1,2")),"\n";
echo json_encode($db->select("select t.taxonomy, count(*) n from wp_term_taxonomy t group by 1")),"\n";
$h = $db->getSchemaBuilder()->hasTable('catalog_live');
echo json_encode(DB::select("show columns from catalog_live")),"\n";
