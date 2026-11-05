<?php
// renders admin pages in-process as the owner (no session, no new credentials) and writes static HTML for a screenshot
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$out = $argv[1];
foreach (array_slice($argv, 2) as $i => $uri) {
    $req = Illuminate\Http\Request::create('https://dev.udshafts.com' . $uri, 'GET');
    $app->instance('request', $req);
    $admin = App\Models\User::where('is_admin', 1)->where(fn ($q) => $q->whereNull('admin_role')->orWhere('admin_role', 'owner'))->orderBy('id')->first();
    $app['auth']->guard('web')->setUser($admin);
    $req->setUserResolver(fn () => $admin);
    $resp = $kernel->handle($req);
    $html = str_replace('<head>', '<head><base href="https://dev.udshafts.com/">', $resp->getContent());
    file_put_contents("$out/r$i.html", $html);
    echo $uri, ' ', $resp->getStatusCode(), "\n";
}
