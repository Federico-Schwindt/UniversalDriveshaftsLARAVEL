<?php require "/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php"; $app = require "/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app("session")->setDefaultDriver("array"); app()->instance("session.store", app("session")->driver()); view()->share("errors", new Illuminate\Support\ViewErrorBag);
Illuminate\Support\Facades\Auth::loginUsingId(12);
foreach ([['edit' => 'new', 'kind' => 'dropship'], ['billing' => 1], []] as $q) {
  app()->instance('request', Illuminate\Http\Request::create('/account/addresses', 'GET', $q));
  $h = app(App\Http\Controllers\AccountController::class)->addressesPage()->render();
  echo json_encode($q), ' gplaces=', substr_count($h, 'data-gplaces'), ' noip=', substr_count($h, 'data-geo-noip'), ' maps=', substr_count($h, 'maps.googleapis.com'), ' geo-fields=', substr_count($h, 'data-geo="'), "\n";
}
