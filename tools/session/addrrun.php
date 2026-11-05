<?php require "/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php"; $app = require "/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); app("session")->setDefaultDriver("array"); app()->instance("session.store", app("session")->driver()); ?>
<?php
use Illuminate\Support\Facades\{DB,Auth};
use App\Http\Controllers\AccountController;
DB::beginTransaction(); view()->share("errors", new Illuminate\Support\ViewErrorBag);
Auth::loginUsingId(12);
$c = app(AccountController::class);
$mk = function ($data) use ($c) { $r = Illuminate\Http\Request::create('/account/addresses', 'POST', $data); app()->instance('request', $r); return $c->addressSave($r); };
DB::table('dropship_addresses')->where('user_id', 12)->delete();
$base = ['street' => '100 Main St', 'city' => 'Tampa', 'state' => 'FL', 'postcode' => '33602', 'country' => 'US'];
$mk($base + ['name' => 'Alex Morgan', 'label' => 'Main shop', 'kind' => 'shipping']);
$mk($base + ['name' => 'Alex Morgan', 'label' => 'Warehouse 2', 'kind' => 'shipping', 'street' => '200 Oak Ave']);
$mk($base + ['company' => 'Customer Co', 'kind' => 'dropship', 'is_default' => '1', 'street' => '300 Pine Rd']);
foreach (DB::table('dropship_addresses')->where('user_id', 12)->orderBy('id')->get() as $a) echo "$a->id {$a->label}{$a->company} kind=$a->kind def=$a->is_default\n";
// move the default shipping address to dropship: Warehouse 2 must take over
$main = DB::table('dropship_addresses')->where('user_id', 12)->where('label', 'Main shop')->first();
$mk($base + ['id' => $main->id, 'name' => 'Alex Morgan', 'label' => 'Main shop', 'kind' => 'dropship']);
echo "after move:\n"; foreach (DB::table('dropship_addresses')->where('user_id', 12)->orderBy('id')->get() as $a) echo "$a->id {$a->label}{$a->company} kind=$a->kind def=$a->is_default\n";
app()->instance('request', Illuminate\Http\Request::create('/account/addresses', 'GET'));
$html = $c->addressesPage()->render();
foreach (['My shipping addresses', 'Dropship addresses', 'Add a dropship address', 'Customer Co', 'Warehouse 2'] as $n) echo $n, ': ', substr_count($html, $n), "\n";
app()->instance('request', Illuminate\Http\Request::create('/account/addresses', 'GET', ['edit' => 'new', 'kind' => 'dropship']));
$html = $c->addressesPage()->render();
echo 'form dropship checked: ', preg_match('/value="dropship"\s+checked/', $html), ' | title: ', substr_count($html, 'New dropship address'), "\n";
DB::rollBack();
