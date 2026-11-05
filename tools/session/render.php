<?php
require __DIR__.'/../../../../../var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
foreach (array_slice($argv,1) as $path) {
  $req = Illuminate\Http\Request::create($path, 'GET');
  $res = $kernel->handle($req);
  $html = $res->getContent();
  echo "=== $path  status=", $res->getStatusCode(), " bytes=", strlen($html), "\n";
  if ($res->getStatusCode() >= 300 && $res->getStatusCode() < 400) { echo "-> ", $res->headers->get('Location'), "\n"; continue; }
  preg_match('/<title>([^<]*)/', $html, $m); echo "TITLE(", mb_strlen($m[1]??''), "): ", $m[1]??'', "\n";
  preg_match('/<meta name="description" content="([^"]*)/', $html, $m); echo "DESC(", mb_strlen(html_entity_decode($m[1]??'')), "): ", html_entity_decode($m[1]??''), "\n";
  preg_match('/<link rel="canonical" href="([^"]*)/', $html, $m); echo "CANON: ", $m[1]??'', "\n";
  preg_match('/<meta name="robots" content="([^"]*)/', $html, $m); echo "ROBOTS: ", $m[1]??'', "\n";
  preg_match_all('/<h1[^>]*>(.*?)<\/h1>/s', $html, $m); echo "H1: ", trim(strip_tags(implode(' | ', $m[1]))), "\n";
  preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $m); echo "H2s: ", implode(' | ', array_map(fn($x)=>trim(preg_replace('/\s+/',' ',strip_tags($x))), $m[1])), "\n";
  preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
  foreach ($m[1] as $j) { $d = json_decode($j, true); if ($d === null) { echo "LD JSON ERROR: ", json_last_error_msg(), "\n"; continue; }
    $nodes = $d['@graph'] ?? [$d]; foreach ($nodes as $n) { echo "LD: ", (is_array($n["@type"] ?? null) ? implode("+", $n["@type"]) : ($n["@type"] ?? "?")), isset($n['sku']) ? " sku={$n['sku']}" : '', isset($n['additionalProperty']) ? " props=".count($n['additionalProperty']) : '', isset($n['mainEntity']) ? " mainEntity=".($n['mainEntity']['@type'] ?? count($n['mainEntity'])) : '', isset($n['itemListElement']) ? " items=".count($n['itemListElement']) : '', isset($n['offers']['availability']) ? " avail=".basename($n['offers']['availability']) : '', isset($n['category']) ? " cat=yes" : '', isset($n['isAccessoryOrSparePartFor']) ? " fits=".count($n['isAccessoryOrSparePartFor']) : '', "\n"; } }
  preg_match_all('/<img[^>]*alt="([^"]*)"/', $html, $m); echo "ALTS: ", implode(' || ', array_slice(array_unique($m[1]), 0, 4)), "\n";
  preg_match_all('/<meta property="product:[^>]*>/', $html, $m); echo "OG: ", count($m[0]), " product metas\n";
  if (preg_match('/Replaces \/ interchanges with/', $html)) echo "XREF block present\n";
  preg_match_all('/rel="nofollow"/', $html, $m); echo "nofollow: ", count($m[0]), "\n";
  preg_match_all('/class="shop-pg".*?<\/div>/s', $html, $m); if ($m[0]) { preg_match_all('/href="([^"]*)"/', $m[0][0], $h); echo "PG: ", implode(' ', array_slice($h[1],0,6)), "\n"; }
  if (preg_match_all('/(Error|Exception|Undefined|ErrorException)[^<]{0,120}/', $html, $e) && str_contains($html, 'Whoops')) echo "ERRORS: ", implode(' / ', array_slice($e[0],0,3)), "\n";
}
