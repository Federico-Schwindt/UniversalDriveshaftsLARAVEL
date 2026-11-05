<?php
require "/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php";
$app=require "/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$svc = app(App\Services\CouponService::class);
$cols = Schema::getColumnListing('coupons');
$base = ['type'=>'percent','amount'=>10,'active'=>1,'created_at'=>now(),'updated_at'=>now()];
$base = array_intersect_key($base, array_flip($cols));
$items = [['type'=>'product','wp_product_id'=>null,'price'=>100,'qty'=>2,'sku'=>'X','name'=>'x','meta'=>[]]];
$cases = [
 'T_MIN' => ['minimum_amount'=>500],
 'T_MAX' => ['maximum_amount'=>5],
 'T_FUT' => ['starts_at'=>now()->addDay()],
 'T_EXP' => ['expires_at'=>now()->subDay()],
 'T_LIM' => ['usage_limit'=>1,'used_count'=>1],
 'T_WS'  => ['restrictions'=>json_encode(['wholesale_only'=>true])],
 'T_CAT' => ['restrictions'=>json_encode(['categories'=>['nonexistent-cat']])],
 'T_OK'  => [],
];
foreach ($cases as $code=>$extra) {
  DB::table('coupons')->where('code',$code)->delete();
  DB::table('coupons')->insert(array_merge($base,['code'=>$code],$extra));
  $r = $svc->validate($code, 200, $items, 'nobody@example.com');
  $c = is_array($r) ? ($r[1] ?? null) : $r;
  echo str_pad($code,8), json_encode($r, JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
  if (is_object($c) && isset($c->code)) echo "   discount=", $svc->discountFor($c, 200, $items), "\n";
  DB::table('coupons')->where('code',$code)->delete();
}
