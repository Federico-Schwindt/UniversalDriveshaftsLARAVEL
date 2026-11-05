<?php
use App\Services\CatalogService as CS;
$dir = getenv('HOME') . '/pti-work';
$db = DB::connection('wp');
// --- our catalog: every product, any status ---
$prod = $db->table('wp_posts as p')->leftJoin('wp_postmeta as m', fn ($j) => $j->on('m.post_id', '=', 'p.ID')->where('m.meta_key', '_sku'))
    ->where('p.post_type', 'product')->whereNotIn('p.post_status', ['trash', 'auto-draft'])
    ->select('p.ID', 'p.post_title', 'p.post_status', 'm.meta_value as sku')->get()->unique('ID')->keyBy('ID');
$price = $db->table('wp_postmeta')->where('meta_key', '_price')->pluck('meta_value', 'post_id');
$exact = []; $norm = [];
foreach ($prod as $p) { $s = trim((string) $p->sku); if ($s === '') continue;
    $exact[strtoupper($s)][$p->ID] = true; $norm[CS::normKey($s)][$p->ID] = true; }
// cross numbers on our products (pa_partn-number), any status
$xr = [];
$rows = $db->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
    ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'pa_partn-number')->select('tr.object_id', 't.name')->get();
foreach ($rows as $r) { if (!isset($prod[$r->object_id])) continue;
    foreach (preg_split('/\s*[,;]\s*|\s+\/\s+/', html_entity_decode($r->name)) as $n) { $k = CS::normKey($n); if (strlen($k) >= 4) $xr[$k][$r->object_id] = true; } }

$cats = ['transmission-yokes' => 'Transmission Yokes', 'center-bearings' => 'Center Support Bearing', 'flange-yokes' => 'Flange Yokes', 'tri-and-quad-prongs' => 'Tri & Quad Prongs', 'cvj' => 'CV Joint', 'cvj-kits' => 'CV Joint Kits', 'cv-heads' => 'CV Heads'];
$res = ['linked' => [], 'new' => [], 'review' => []]; $seen = [];
foreach ($cats as $slug => $label) {
    $items = json_decode(file_get_contents("$dir/cat-$slug.json"), true)['data']['results'] ?? [];
    // OEM numbers PTI maps onto a real part (virtual rows -> related Alt Part)
    $oem = [];
    foreach ($items as $it) if (($it['is_virtual'] ?? '0') === '1') foreach ($it['related_parts'] ?? [] as $rp) $oem[strtoupper($rp['related_part_number'])][] = $it['part_number'];
    foreach ($items as $it) {
        if (($it['is_virtual'] ?? '0') === '1') continue;               // OEM alias, not a PTI stock part
        $pn = trim($it['part_number']); $PN = strtoupper($pn);
        if (isset($seen[$PN])) continue; $seen[$PN] = true;
        $hits = [];   // id => reason
        foreach (array_keys($exact[$PN] ?? []) as $id) $hits[$id][] = 'same SKU';
        foreach (array_keys($exact[$PN . 'U'] ?? []) as $id) $hits[$id][] = 'SKU + U';
        $k = CS::normKey($pn);
        foreach (array_keys($norm[$k] ?? []) as $id) if (!isset($hits[$id])) $hits[$id][] = 'same SKU ignoring dashes/spaces';
        foreach (array_keys($norm[$k . 'U'] ?? []) as $id) if (!isset($hits[$id])) $hits[$id][] = 'SKU + U ignoring dashes/spaces';
        $soft = [];
        foreach (array_keys($xr[$k] ?? []) as $id) if (!isset($hits[$id])) $soft[$id][] = 'listed as cross-ref on our product';
        $alias = $oem[$PN] ?? [];
        if (preg_match_all('/Replaces\s+([A-Z0-9][A-Z0-9\-\.\/]+)/i', (string) ($it['notes'] ?? ''), $m)) $alias = array_merge($alias, $m[1]);
        foreach ($alias as $a) { $ak = CS::normKey($a); foreach ([$ak, $ak . 'U'] as $kk) foreach (array_keys($norm[$kk] ?? []) as $id) if (!isset($hits[$id]) && !isset($soft[$id])) $soft[$id][] = "our SKU = OEM/old number $a"; }
        $pti = (float) str_replace(['$', ','], '', (string) ($it['price'] ?? ''));
        $row = ['cat' => $label, 'pti' => $pn, 'brand' => $it['brand'] ?? '', 'series' => $it['series'] ?? '', 'pti_price' => $pti ?: null,
            'pic' => !str_contains(($it['part_picture_urls'][0] ?? 'missing'), 'missing'), 'notes' => trim(preg_replace('/\s+/', ' ', (string) ($it['notes'] ?? '')))];
        $fmt = fn ($id, $why) => ['id' => $id, 'sku' => $prod[$id]->sku, 'status' => $prod[$id]->post_status, 'our_price' => $price[$id] ?? null, 'title' => html_entity_decode($prod[$id]->post_title), 'why' => implode(' + ', $why)];
        $row['matches'] = array_merge(array_map($fmt, array_keys($hits), $hits), array_map($fmt, array_keys($soft), $soft));
        $both = isset($exact[$PN]) && isset($exact[$PN . 'U']);
        if (count($hits) === 1 && !$soft) { $res['linked'][] = $row + ['new_price' => $pti ? round($pti * 1.2, 2) : null]; }
        elseif (!$hits && !$soft) { if ($pti) $res['new'][] = $row + ['new_price' => round($pti * 1.3, 2)]; else $res['review'][] = $row + ['issue' => 'Not on our site, but PTI shows no price']; }
        else $res['review'][] = $row + ['issue' => $both ? 'Both the plain SKU and the U version exist on our site' : (count($hits) > 1 ? 'More than one of our products matches' : ($hits ? 'Exact match plus a cross-ref match' : 'Only a cross-ref/OEM match, not the same SKU'))];
    }
}
file_put_contents("$dir/out/dryrun.json", json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
foreach ($res as $k => $v) echo "$k: ", count($v), "\n";
$by = []; foreach ($res as $k => $v) foreach ($v as $r) $by[$r['cat']][$k] = ($by[$r['cat']][$k] ?? 0) + 1;
foreach ($by as $c => $x) echo str_pad($c, 24), json_encode($x), "\n";
$w = []; foreach ($res['linked'] as $r) $w[$r['matches'][0]['why']] = ($w[$r['matches'][0]['why']] ?? 0) + 1; print_r($w);
$i = []; foreach ($res['review'] as $r) $i[$r['issue']] = ($i[$r['issue']] ?? 0) + 1; print_r($i);
