<?php
$files = array_merge(
  array_map(fn($n)=>"resources/views/$n.blade.php", ['about','services','heavy-duty','industrial','offroad','pto','wholesale','contact']),
  glob('resources/views/guides/*.blade.php'), glob('resources/views/errors/*.blade.php'));
$keys=[];
foreach($files as $f){ $s=file_get_contents($f);
  preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $s, $m); foreach($m[1] as $k) $keys[stripcslashes(str_replace("\\'", "'", $k))]=$f;
  preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $s, $m); foreach($m[1] as $k) $keys[$k]=$f;
}
$es = is_file('lang/i18n/content/es.json') ? json_decode(file_get_contents('lang/i18n/content/es.json'), true) : [];
foreach($keys as $k=>$f) if(!isset($es[$k])) echo basename($f),"\t",$k,"\n";
