<?php

namespace App\Console\Commands;

use App\Services\Admin\CatalogWriter;
use App\Services\CatalogService as CS;
use App\Services\PtiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bridge to Powertrain Industries (our supplier) for five component categories.
 *
 *   --catalog  refresh pti_parts from PTI and link each part to our product (same SKU, or same SKU + "U")
 *   --create   add the PTI parts we don't carry as drafts, SKU = PTI number + "U", priced PTI x 1.30 (owner, 2026-10-23/24)
 *   --stock    PTI on-hand per part -> catalog_live.pti_in_stock (shown in stock when we OR PTI have it)
 *   --boxes    weight + box per part, read from PTI's own cart shipping quote (cart must be empty)
 *   --apply-boxes  copy PTI's packed weight and box onto our products (owner, 2026-10-24: use PTI's dimensions)
 *   --images   download PTI's photos for the parts we added from PTI into public/img/pti (watermarked; to be cleaned later)
 *
 * Linked products keep our price (owner, 2026-10-23). Anything ambiguous (both X and XU exist, only a
 * cross-ref/OEM match, several products match) is left unlinked with match=review for a person to decide.
 */
class PtiSync extends Command
{
    protected $signature = 'pti:sync {--catalog} {--create} {--stock} {--boxes} {--apply-boxes} {--images} {--limit=0 : max parts for --stock/--boxes} {--force : re-measure boxes already checked} {--dry : report only, write nothing}';
    protected $description = 'Sync parts, stock and ship boxes from the Powertrain Industries dealer portal';

    /** PTI component type => our product category slug and the label used in titles */
    public const TYPES = [
        'transmission-yokes' => ['transmission-yokes', 'Transmission Yokes'],
        'center-bearings' => ['center-support-bearing', 'Center Support Bearing'],
        'flange-yokes' => ['flange-yokes', 'Flange Yokes'],
        'tri-and-quad-prongs' => ['tri-quad-prongs', 'Tri & Quad Prongs'],
        'cvj' => ['cv-joint-kit', 'CV Joint'],
        'cvj-kits' => ['cv-joint-kit', 'CV Joint Kit'],
        'cv-heads' => ['cv-double-joints-heads', 'CV Double Joint Heads'],
    ];
    /**
     * Linked for stock, weight/box and dropship only — never created (owner, 2026-10-24: every PTI part we
     * already carry must link, tubing aside). Small parts live in PTI's second catalog: stored as "small:{type}".
     */
    public const LINK_ONLY = ['ujoints', 'weld-yokes', 'end-yokes', 'slip-yokes', 'splined-stub-shafts', 'rubber-flex-discs', 'yoke-shafts',
        'pto-end-yokes', 'cv-flange-yokes', 'splined-sleeves', 'companion-flanges', 'ball-stud-yokes', 'h-yokes', 'cv-link-yokes',
        'flex-coupling-kits', 'unwelded-assemblies', 'small-parts'];
    public const SMALL_PARTS = ['balance-weights', 'bolts', 'boot-cans-and-brg-retainers', 'boot-clamps', 'boot-kits', 'cv-ball-kits', 'cvj-boots',
        'cvj-end-cap', 'dust-caps-press-on', 'dust-caps-threaded', 'increase-bushings', 'keyways', 'lube-zerk-fittings', 'mounts', 'nose-bushings',
        'nuts', 'snap-rings', 'strap-kits', 'tube-deadeners', 'ubolt-kits', 'ujoint-lock-straps-and-wires', 'ujoint-seal-protectors', 'washers', 'welch-plugs'];
    /**
     * PTI small parts we also ADD to the site (owner, 2026-10-24): every type but mounts, Spicer and PTI brand only.
     * type => [our category slug (under Small Parts), name for titles, category name if it has to be created]
     */
    public const SMALL_CREATE = [
        'balance-weights' => ['balance-weights', 'Balance Weights'], 'bolts' => ['hardware', 'Bolts'],
        'boot-cans-and-brg-retainers' => ['boot-cans-bearing-retainers', 'Boot Cans & Bearing Retainers', 'Boot Cans & Bearing Retainers'],
        'boot-clamps' => ['boot-clamps', 'Boot Clamps', 'Boot Clamps'], 'boot-kits' => ['boot-kits', 'Boot Kits'], 'cv-ball-kits' => ['cv-ball-kit', 'CV Ball Kits'],
        'cvj-boots' => ['boot-kits', 'CVJ Boots'], 'cvj-end-cap' => ['small-parts', 'CVJ End Caps'],
        'dust-caps-press-on' => ['dust-cap-press-on-and-threaded', 'Dust Caps Press On'], 'dust-caps-threaded' => ['dust-cap-press-on-and-threaded', 'Dust Caps Threaded'],
        'increase-bushings' => ['increase-bushings', 'Increase Bushings'], 'keyways' => ['keyway', 'Keyways'], 'lube-zerk-fittings' => ['lube-zerk-fittings', 'Lube Zerk Fittings'],
        'nose-bushings' => ['nose-bushings', 'Nose Bushings'], 'nuts' => ['nuts', 'Nuts'], 'snap-rings' => ['snap-rings', 'Snap Rings'], 'strap-kits' => ['strap-kits', 'Strap Kits'],
        'tube-deadeners' => ['small-parts', 'Tube Deadeners'], 'ubolt-kits' => ['u-bolt-kits', 'U Bolt Kits'],
        'ujoint-lock-straps-and-wires' => ['u-joint-lock-straps-wires', 'U-Joint Lock Straps & Wires', 'U-Joint Lock Straps & Wires'],
        'ujoint-seal-protectors' => ['u-joint-seal-protectors', 'U-Joint Seal Protectors', 'U-Joint Seal Protectors'],
        'washers' => ['washers-snap-rings', 'Washers'], 'welch-plugs' => ['welch-plugs', 'Welch Plugs'],
    ];
    private const SMALL_BRANDS = ['SPICER', 'PTI'];
    /** PTI small-part columns → the attribute names our small parts already use */
    private const SMALL_ATTR = ['thread_size' => 'pa_thread-size', 'hex' => 'pa_hex-size', 'self_locking' => 'pa_self-locking', 'self_locking_' => 'pa_self-locking',
        'length_under_head' => 'pa_length-under-head', 'overall_length' => 'pa_overall-length', 'inside_dia' => 'pa_inside-dia', 'inside_dia_' => 'pa_inside-dia',
        'outside_dia' => 'pa_outside-dia', 'thickness' => 'pa_thickness', 'fits_cap_dia' => 'pa_fits-cap-dia', 'c_to_c' => 'pa_center-to-center', 'diameter' => 'pa_diameter',
        'series' => 'pa_series', 'style' => 'pa_style', 'spline_size' => 'pa_spline-size', 'type' => 'pa_type-sp', 'type_' => 'pa_type-sp', 'grade' => 'pa_grade', 'weight' => 'pa_weight'];
    private const MARKUP_NEW = 1.30;

    public function handle(PtiClient $pti, CatalogWriter $writer): int
    {
        if (!$pti->configured()) { $this->error('PTI_USER / PTI_PASS missing from .env'); return self::FAILURE; }
        $any = false;
        foreach (['catalog', 'create', 'stock', 'boxes', 'apply-boxes', 'images'] as $o) $any = $any || $this->option($o);
        if (!$any) { $this->error('Pick at least one of --catalog --create --stock --boxes --apply-boxes --images'); return self::FAILURE; }
        try {
            if ($this->option('catalog')) $this->catalog($pti);
            if ($this->option('create')) $this->create($writer);
            if ($this->option('stock')) $this->stock($pti, $writer);
            if ($this->option('boxes')) $this->boxes($pti);
            if ($this->option('apply-boxes')) $this->applyBoxes($writer);
            if ($this->option('images')) $this->images();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- catalog + links

    private function catalog(PtiClient $pti): void
    {
        $db = DB::connection('wp');
        $prod = $db->table('wp_posts as p')->join('wp_postmeta as m', fn ($j) => $j->on('m.post_id', '=', 'p.ID')->where('m.meta_key', '_sku'))
            ->where('p.post_type', 'product')->whereNotIn('p.post_status', ['trash', 'auto-draft'])->where('m.meta_value', '!=', '')
            ->select('p.ID', 'm.meta_value as sku')->get()->unique('ID');
        $exact = []; $norm = [];
        foreach ($prod as $p) { $s = trim($p->sku); $exact[strtoupper($s)][$p->ID] = true; $norm[CS::normKey($s)][$p->ID] = true; }
        $xr = [];
        foreach ($db->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'pa_partn-number')->select('tr.object_id', 't.name')->get() as $r) {
            foreach (preg_split('/\s*[,;]\s*|\s+\/\s+/', html_entity_decode($r->name)) as $n) { $k = CS::normKey($n); if (strlen($k) >= 4) $xr[$k][$r->object_id] = true; }
        }
        $existing = DB::table('pti_parts')->get()->keyBy('part_number');
        $count = ['sku' => 0, 'sku_u' => 0, 'review' => 0, 'none' => 0, 'kept' => 0]; $seen = [];
        $all = array_merge(array_map(fn ($t) => [$t, $t, 'component_parts'], array_keys(self::TYPES)), array_map(fn ($t) => [$t, $t, 'component_parts'], self::LINK_ONLY),
            array_map(fn ($t) => [$t, 'small:' . $t, 'small_parts'], self::SMALL_PARTS));
        foreach ($all as [$apiType, $type, $catalogName]) {
            try { $data = $pti->catalog($apiType, $catalogName); } catch (\Throwable $e) { $this->warn("$type: {$e->getMessage()}"); continue; }
            $items = $data['results'];
            $oem = [];   // PTI "virtual" rows are OEM numbers pointing at a real PTI part
            foreach ($items as $it) if (($it['is_virtual'] ?? '0') === '1') foreach ($it['related_parts'] ?? [] as $rp) $oem[strtoupper($rp['related_part_number'])][] = $it['part_number'];
            foreach ($items as $it) {
                if (($it['is_virtual'] ?? '0') === '1') continue;
                $pn = trim((string) $it['part_number']); $PN = strtoupper($pn);
                if ($pn === '' || isset($seen[$PN])) continue;
                $seen[$PN] = true;
                $specs = array_intersect_key($it, $data['cols']);
                $pics = array_values(array_filter($it['part_picture_urls'] ?? [], fn ($u) => !str_contains($u, 'picture-missing')));
                $price = (float) str_replace(['$', ','], '', (string) ($it['price'] ?? '')) ?: null;
                $row = ['pti_type' => $type, 'pti_price' => $price, 'specs' => json_encode($specs), 'pictures' => json_encode($pics), 'updated_at' => now()];

                $prev = $existing[$pn] ?? null;
                if ($prev && in_array($prev->match, ['created', 'manual'], true)) {   // ours already: keep the link
                    if (!$this->option('dry')) DB::table('pti_parts')->where('id', $prev->id)->update($row);
                    $count['kept']++; continue;
                }
                $k = CS::normKey($pn);
                $hard = [];
                foreach ([[$exact[$PN] ?? [], 'sku'], [$exact[$PN . 'U'] ?? [], 'sku_u'], [$norm[$k] ?? [], 'sku'], [$norm[$k . 'U'] ?? [], 'sku_u']] as [$ids, $how])
                    foreach (array_keys($ids) as $id) $hard[$id] ??= $how;
                $soft = array_keys($xr[$k] ?? []);
                $alias = $oem[$PN] ?? [];
                if (preg_match_all('/Replaces\s+([A-Z0-9][A-Z0-9\-\.\/]+)/i', (string) ($it['notes'] ?? ''), $m)) $alias = array_merge($alias, $m[1]);
                foreach ($alias as $a) { $ak = CS::normKey($a); foreach ([$ak, $ak . 'U'] as $kk) $soft = array_merge($soft, array_keys($norm[$kk] ?? [])); }
                $soft = array_values(array_diff(array_unique($soft), array_keys($hard)));

                if (count($hard) === 1 && !$soft) { $id = array_key_first($hard); $match = $hard[$id]; $why = null; }
                elseif (!$hard && !$soft) { $id = null; $match = 'none'; $why = $price ? null : 'PTI shows no price'; }
                else {
                    $id = null; $match = 'review';
                    $why = isset($exact[$PN], $exact[$PN . 'U']) ? 'Both the plain SKU and the U version are on our site'
                        : (count($hard) > 1 ? 'More than one of our products matches' : ($hard ? 'Same SKU plus a cross-ref match' : 'Only a cross-ref / OEM number matches'));
                }
                $count[$match]++;
                if ($this->option('dry')) continue;
                DB::table('pti_parts')->updateOrInsert(['part_number' => $pn], $row + ['wp_product_id' => $id, 'match' => $match, 'review_reason' => $why] + ($prev ? [] : ['created_at' => now()]));
            }
        }
        $this->info('catalog: ' . json_encode($count));
    }

    // ---------------------------------------------------------------- new products

    private function create(CatalogWriter $writer): void
    {
        // new products only come from the seven categories the owner picked, and from small parts (no mounts; Spicer and PTI brand only)
        $smallTypes = array_map(fn ($t) => 'small:' . $t, array_keys(self::SMALL_CREATE));
        $rows = DB::table('pti_parts')->where('match', 'none')->whereNotNull('pti_price')->whereIn('pti_type', array_merge(array_keys(self::TYPES), $smallTypes))->get()
            ->filter(fn ($r) => !str_starts_with($r->pti_type, 'small:') || in_array(strtoupper(trim((string) (json_decode($r->specs, true)['brand'] ?? ''))), self::SMALL_BRANDS, true));
        $this->smallCategories($writer);
        $n = 0;
        foreach ($rows as $r) {
            // last guard against duplicates: someone may have added the SKU since --catalog ran
            if (DB::connection('wp')->table('wp_postmeta as m')->join('wp_posts as p', 'p.ID', '=', 'm.post_id')->where('p.post_type', 'product')
                ->whereNotIn('p.post_status', ['trash'])->where('m.meta_key', '_sku')->whereIn('m.meta_value', [$r->part_number, $r->part_number . 'U'])->exists()) {
                $this->warn("skip {$r->part_number}: SKU now exists"); continue;
            }
            [$name, $attrs, $desc, $cat] = str_starts_with($r->pti_type, 'small:')
                ? $this->describeSmall($r->part_number, substr($r->pti_type, 6), json_decode($r->specs, true) ?: [])
                : $this->describe($r->part_number, $r->pti_type, json_decode($r->specs, true) ?: []);
            if ($this->option('dry')) { $this->line("would create {$r->part_number}  \${$this->sell($r->pti_price)}  $name"); $n++; continue; }
            $id = $writer->saveProduct(null, [
                'name' => $name, 'slug' => '', 'status' => 'draft', 'sku' => $r->part_number . 'U',   // our SKUs for PTI parts end in U (owner, 2026-10-24); the title keeps PTI's number
                'regular_price' => (string) $this->sell($r->pti_price), 'sale_price' => '', 'cost' => (string) $r->pti_price,
                'description' => $desc, 'short' => '', 'categories' => str_starts_with($r->pti_type, 'small:') ? array_values(array_unique(['small-parts', $cat])) : [$cat], 'attrs' => $attrs, 'images' => [],
                'manage_stock' => false, 'stock_status' => 'outofstock',   // availability comes from PTI (--stock)
                'seo_title' => '', 'seo_desc' => '', 'interchange' => '',
            ]);
            DB::table('pti_parts')->where('id', $r->id)->update(['wp_product_id' => $id, 'match' => 'created', 'updated_at' => now()]);
            $n++;
        }
        Cache::forget('catalog_live_map');
        $this->info(($this->option('dry') ? 'would create ' : 'created ') . "$n product(s)");
    }

    /** Categories the small parts need that we don't have yet, created under Small Parts. */
    private function smallCategories(CatalogWriter $writer): void
    {
        $parent = (int) DB::connection('wp')->table('wp_terms as t')->join('wp_term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')->where('tt.taxonomy', 'product_cat')->where('t.slug', 'small-parts')->value('t.term_id');
        foreach (self::SMALL_CREATE as $row) {
            [$slug, $newName] = [$row[0], $row[2] ?? null];
            if (!$newName || $this->option('dry')) continue;
            $exists = DB::connection('wp')->table('wp_terms as t')->join('wp_term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')->where('tt.taxonomy', 'product_cat')->where('t.slug', $slug)->exists();
            if (!$exists) { $writer->createTerm('product_cat', $newName, $slug, $parent); $this->line("created category $slug"); }
        }
    }

    /** Small parts: "1-2018 – Strap Kits – Series 7290 – Fits Cap Dia 1.125 – Center To Center 1.531", attributes named like ours. */
    private function describeSmall(string $sku, string $type, array $s): array
    {
        [$cat, $label] = self::SMALL_CREATE[$type];
        $attrs = []; $bits = [];
        foreach ($s as $k => $v) {
            $v = trim(preg_replace('/\s+/', ' ', (string) $v));
            if ($v === '' || in_array(strtoupper($v), ['N/A', 'NULL', '-'], true) || in_array($k, ['brand', 'notes', 'vehicle_class', 'is_virtual'], true)) continue;
            $tax = self::SMALL_ATTR[$k] ?? 'pa_' . trim(str_replace('_', '-', strtolower($k)), '-');
            if (preg_match('/^0+(\.0+)?$/', $v)) continue;   // PTI's "0.000" means "not given"
            $attrs[$tax] = $v;
            if (count($bits) < 3) $bits[] = trim(preg_replace('/\bSp$/', '', ucwords(str_replace('-', ' ', substr($tax, 3))))) . ' ' . $v;
        }
        $notes = trim(preg_replace(['/#+\s*/', '/\s+/'], ['', ' '], (string) ($s['notes'] ?? '')), ' ,.');
        if ($notes !== '') $attrs['pa_notes'] = $notes;
        if (!empty($s['brand'])) $attrs['pa_brand'] = ucfirst(strtolower((string) $s['brand'])) === 'Pti' ? 'PTI' : ucfirst(strtolower((string) $s['brand']));
        $attrs['pa_partn-number'] = $sku;
        $name = implode(' – ', array_merge([$sku, $label], $bits));
        $spec = implode('; ', array_map(fn ($t, $v) => ucwords(str_replace('-', ' ', substr($t, 3))) . ': ' . $v, array_keys($attrs), $attrs));
        $desc = '<p>' . e($sku) . ' ' . e(rtrim($label, 's')) . '. ' . e($spec) . '.</p>' . ($notes ? '<p>' . e($notes) . '</p>' : '');
        return [$name, $attrs, $desc, $cat];
    }

    private function sell(float $pti): float
    {
        return round($pti * self::MARKUP_NEW, 2);
    }

    /** Title, attributes, description and category in the same shape as the products we already list. */
    private function describe(string $sku, string $type, array $s): array
    {
        [$cat, $label] = self::TYPES[$type];
        $v = fn ($k) => ($x = trim((string) ($s[$k] ?? ''))) !== '' && !in_array(strtoupper($x), ['N/A', 'NULL'], true) ? $x : null;
        $in = fn ($x) => $x !== null && is_numeric($x) ? $x . '"' : $x;
        $tubeWall = $v('tube_diameter') && $v('tube_wall') ? $v('tube_diameter') . 'X' . $v('tube_wall') : null;
        $notes = $v('notes') ? trim(preg_replace(['/#+\s*/', '/\s+/'], ['', ' '], $v('notes')), ' ,.') : null;
        $map = match ($type) {
            'transmission-yokes' => ['pa_series' => $v('series'), 'pa_splines-count-dia' => $v('spline_count') && $v('spline_diameter') ? $v('spline_count') . ' X ' . $v('spline_diameter') : null,
                'pa_hub-dia' => $v('hub_diameter'), 'pa_center-u-joint-to-end' => $v('centerline_to_end'), 'pa_counter-bore-dia' => $v('counterbore_diameter')],
            'center-bearings' => ['pa_bearing-id' => $v('bearing_id'), 'pa_ctr-to-ctr-of-mount-holes' => $v('center_to_center_of_mount_holes'), 'pa_center-bearing-to-base' => $v('centerline_to_base'), 'pa_application' => $v('application')],
            'flange-yokes' => ['pa_series' => $v('series'), 'pa_pilot-dia' => array_values(array_filter([$v('pilot_diameter'), $v('pilot_type')])), 'pa_bolt-circle-dia' => $v('bolt_circle_diameter'),
                'pa_bolt-holes' => $v('bolt_hole_count'), 'pa_bolt-hole-dia' => $v('bolt_hole_diameter'), 'pa_cj-to-flat' => $v('centerline_to_face')],
            'tri-and-quad-prongs' => ['pa_bolt-circle-dia' => $v('bolt_circle_diameter'), 'pa_bolt-hole-dia' => $v('bolt_hole_diameter'), 'pa_bolt-hole-pilot-dia' => $v('bolt_hole_pilot_diameter'),
                'pa_nose-bushing-dia' => $v('nose_bushing_diameter'), 'pa_tube-dia-wall' => $tubeWall, 'pa_butt-dia' => $v('butt_diameter'), 'pa_face-to-weld' => $v('face_to_weld')],
            'cvj', 'cvj-kits' => ['pa_splines-count' => $v('spline_count'), 'pa_splines-dia' => $v('spline_diameter'), 'pa_bolt-hole-count' => $v('bolt_hole_count'), 'pa_bolt-hole-dia' => $v('bolt_hole_diameter'),
                'pa_outer-dia' => $v('outer_diameter'), 'pa_outer-race-width' => $v('outer_race_width'), 'pa_inner-race-width' => $v('inner_race_width'), 'pa_application' => $v('application')],
            'cv-heads' => ['pa_style-series' => implode(' ', array_unique(array_filter([$v('series'), $v('style')]))) ?: null, 'pa_tube-dia-wall' => $tubeWall, 'pa_butt-dia' => $v('butt_diameter'), 'pa_pilot-dia' => $v('pilot_diameter'),
                'pa_bolt-circle-dia' => $v('bolt_circle_diameter'), 'pa_bolt-hole-dia' => $v('bolt_hole_diameter'), 'pa_end-to-weld' => $v('end_to_weld')],
        };
        $map['pa_notes'] = $notes; $map['pa_partn-number'] = $sku;
        $attrs = array_filter($map, fn ($x) => $x !== null && $x !== []);

        $bits = match ($type) {
            'transmission-yokes' => [$v('series') ? $v('series') . ' Series' : null, $attrs['pa_splines-count-dia'] ?? null ? $attrs['pa_splines-count-dia'] . ' Splines' : null, $v('hub_diameter') ? $in($v('hub_diameter')) . ' Hub Dia' : null],
            'center-bearings' => [$v('application') ? $v('application') . ' Application' : null, $v('bearing_id') ? $in($v('bearing_id')) . ' ID' : null, $v('centerline_to_base') ? $in($v('centerline_to_base')) . ' Center Bearing To Base' : null],
            'flange-yokes' => [$v('series') ? $v('series') . ' Series' : null, $v('bolt_circle_diameter') ? 'Bolt Circle Dia ' . $in($v('bolt_circle_diameter')) : null, $v('centerline_to_face') ? 'Cj To Flat ' . $v('centerline_to_face') : null],
            'tri-and-quad-prongs' => [$v('bolt_circle_diameter') ? 'Bolt Circle Dia ' . strtoupper($v('bolt_circle_diameter')) : null, $v('butt_diameter') ? 'Butt Dia ' . $in($v('butt_diameter')) : null, $v('face_to_weld') ? 'Face To Weld ' . $v('face_to_weld') : null],
            'cvj', 'cvj-kits' => [$v('spline_diameter') ? $v('spline_diameter') . ' Splines Dia' : null, $v('spline_count') ? $v('spline_count') . ' Splines' : null, $v('bolt_hole_diameter') ? 'Bolt Hole Dia ' . $v('bolt_hole_diameter') : null],
            'cv-heads' => [$v('series') ? $v('series') . ' Series' : null, $v('butt_diameter') ? 'Butt Dia ' . $in($v('butt_diameter')) : null, $tubeWall ? 'Tube Dia Wall ' . $tubeWall : null],
        };
        $name = implode(' – ', array_merge([$sku, $label], array_slice(array_filter($bits), 0, 3)));

        $specLine = [];
        foreach ($attrs as $tax => $val) {
            if (in_array($tax, ['pa_partn-number', 'pa_notes'], true)) continue;
            $specLine[] = ucwords(str_replace(['pa_', '-'], ['', ' '], $tax)) . ': ' . (is_array($val) ? implode(' / ', $val) : $val);
        }
        $desc = '<p>' . e($sku) . ' ' . e(rtrim($label, 's')) . ($v('series') ? ', ' . e($v('series')) . ' series' : '') . '. ' . ($specLine ? e(implode('; ', $specLine)) . '.' : '') . '</p>'
            . ($notes ? '<p>' . e($notes) . '</p>' : '')
            . '<p>Check every dimension against the part you are replacing before ordering. Not sure? Call us with the part number stamped on your old part.</p>';
        return [$name, $attrs, $desc, $cat];
    }

    // ---------------------------------------------------------------- stock

    private function stock(PtiClient $pti, CatalogWriter $writer): void
    {
        $rows = DB::table('pti_parts')->whereNotNull('wp_product_id')->orderByRaw('stock_synced_at IS NOT NULL, stock_synced_at')
            ->when((int) $this->option('limit'), fn ($q, $l) => $q->limit($l))->get();
        $in = 0; $out = 0; $err = 0;
        foreach ($rows as $r) {
            try { $s = $pti->stock($r->part_number); } catch (\Throwable $e) { $err++; $this->warn("{$r->part_number}: {$e->getMessage()}"); continue; }
            $avail = ($s['total'] + $s['can_build']) > 0;
            $avail ? $in++ : $out++;
            if ($this->option('dry')) continue;
            DB::table('pti_parts')->where('id', $r->id)->update(['stock_by_store' => json_encode($s['by_store']), 'stock_total' => $s['total'], 'can_build' => $s['can_build'], 'stock_synced_at' => now()]);
            DB::table('catalog_live')->where('wp_product_id', $r->wp_product_id)->update(['pti_in_stock' => $avail]);
            if ($r->match === 'created') {   // products that only exist through PTI carry PTI's exact on-hand as their own stock
                $wp = DB::connection('wp');
                foreach (['_manage_stock' => 'yes', '_stock' => (string) $s['total'], '_stock_status' => $avail ? 'instock' : 'outofstock'] as $k => $val) {
                    if ($wp->table('wp_postmeta')->where('post_id', $r->wp_product_id)->where('meta_key', $k)->exists()) $wp->table('wp_postmeta')->where('post_id', $r->wp_product_id)->where('meta_key', $k)->update(['meta_value' => $val]);
                    else $wp->table('wp_postmeta')->insert(['post_id' => $r->wp_product_id, 'meta_key' => $k, 'meta_value' => $val]);
                }
                DB::table('catalog_live')->where('wp_product_id', $r->wp_product_id)->update(['in_stock' => $avail]);
                DB::table('inventory')->updateOrInsert(['wp_product_id' => $r->wp_product_id], ['sku' => $r->part_number . 'U', 'stock' => $s['total'], 'stock_synced_at' => now(), 'updated_at' => now()]);
            }
            usleep(150000);
        }
        if (!$this->option('dry')) { Cache::forget('catalog_live_map'); CatalogWriter::invalidate(); }
        $this->info("stock: $in available at PTI, $out not, $err error(s)");
    }

    // ---------------------------------------------------------------- boxes

    private function boxes(PtiClient $pti): void
    {
        // the same lock PtiDropship takes around prepare/place/cancel: never quote boxes while a dropship is being built in PTI's cart
        $lock = \Illuminate\Support\Facades\Cache::lock('pti-cart', 3600);
        if (!$lock->get()) { $this->error('PTI cart is locked by a dropship in progress - boxes skipped this run'); return; }
        try { $this->measureBoxes($pti); } finally { $lock->release(); }
    }

    private function measureBoxes(PtiClient $pti): void
    {
        $rows = DB::table('pti_parts')->whereNotNull('wp_product_id')->whereNotNull('pti_price')   // unpriced parts can't go in PTI's cart
            ->when(!$this->option('force'), fn ($q) => $q->whereNull('box_checked_at'))
            ->orderBy('id')->when((int) $this->option('limit'), fn ($q, $l) => $q->limit($l))->get();
        $ok = 0; $miss = 0;
        foreach ($rows as $r) {
            // someone at the shop may be building a real PTI order: never quote or delete alongside it
            if ($items = $pti->cartItems()) { $this->error('PTI cart is not empty (' . count($items) . ' item(s)) - stopped so a real order is not disturbed'); break; }
            if ($this->option('dry')) { $this->line("would measure {$r->part_number}"); continue; }
            try { $b = $pti->shipBox($r->part_number); } catch (\Throwable $e) {
                $this->warn("{$r->part_number}: {$e->getMessage()}"); $miss++;
                DB::table('pti_parts')->where('id', $r->id)->update(['box_checked_at' => now()]);   // don't retry nightly; --force re-measures
                continue;
            }
            DB::table('pti_parts')->where('id', $r->id)->update([
                'description' => $b['description'] ?? null, 'item_weight' => $b['item_weight'] ?? null, 'ship_weight' => $b['ship_weight'] ?? null,
                'box_l' => $b['box'][0] ?? null, 'box_w' => $b['box'][1] ?? null, 'box_h' => $b['box'][2] ?? null, 'box_checked_at' => now(),
            ]);
            $b && ($b['ship_weight'] ?? null) ? $ok++ : $miss++;
            if (($ok + $miss) % 50 === 0) $this->line(($ok + $miss) . ' / ' . count($rows));
        }
        $this->info("boxes: $ok measured, $miss without a weight");
    }

    /** Copy PTI's packed weight and box onto our products; the previous values are saved to storage/app/pti/ship-*.json first. */
    private function applyBoxes(CatalogWriter $writer): void
    {
        $rows = DB::table('pti_parts')->whereNotNull('wp_product_id')->whereNotNull('ship_weight')->get();
        $before = DB::connection('wp')->table('wp_postmeta')->whereIn('post_id', $rows->pluck('wp_product_id'))->whereIn('meta_key', ['_weight', '_length', '_width', '_height'])
            ->get(['post_id', 'meta_key', 'meta_value'])->groupBy('post_id')->map(fn ($g) => $g->pluck('meta_value', 'meta_key'));
        if (!$this->option('dry')) {
            @mkdir(storage_path('app/pti'), 0775, true);
            file_put_contents(storage_path('app/pti/ship-' . now()->format('Ymd-His') . '.json'), json_encode($before));
        }
        $n = 0;
        foreach ($rows as $r) {
            $want = ['_weight' => (float) $r->ship_weight, '_length' => (float) $r->box_l, '_width' => (float) $r->box_w, '_height' => (float) $r->box_h];
            $cur = $before[$r->wp_product_id] ?? collect();
            if (!array_filter($want, fn ($v, $k) => abs((float) ($cur[$k] ?? 0) - $v) >= 0.01, ARRAY_FILTER_USE_BOTH)) continue;
            $n++;
            if (!$this->option('dry')) $writer->setShipBox((int) $r->wp_product_id, $want['_weight'], $want['_length'] ?: null, $want['_width'] ?: null, $want['_height'] ?: null);
        }
        $this->info(($this->option('dry') ? 'would update ' : 'updated ') . "$n product weight/box(es)");
    }

    // ---------------------------------------------------------------- photos

    /** PTI photos for the parts we added from PTI, stored locally so they can be cleaned (de-watermarked) and served by us. */
    private function images(): void
    {
        $rows = DB::table('pti_parts')->where('match', 'created')->get();
        $dir = public_path('img/pti'); @mkdir($dir, 0775, true);
        $done = 0; $files = 0;
        foreach ($rows as $r) {
            $pics = array_slice(json_decode($r->pictures, true) ?: [], 0, 6);
            if (!$pics) continue;
            $slug = \Illuminate\Support\Str::slug($r->part_number) ?: md5($r->part_number);
            $urls = [];
            foreach ($pics as $i => $src) {
                $file = "$dir/$slug-" . ($i + 1) . '.jpg';
                if (!is_file($file) || $this->option('force')) {
                    if ($this->option('dry')) { $urls[] = "/img/pti/$slug-" . ($i + 1) . '.jpg'; continue; }
                    $resp = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Referer' => 'https://c2.powertrainindustries.com/'])
                        ->timeout(30)->retry(2, 1000, throw: false)->get($src);
                    if (!$resp->successful() || !str_starts_with((string) $resp->header('Content-Type'), 'image/')) { $this->warn("{$r->part_number}: photo " . ($i + 1) . ' failed'); continue; }
                    file_put_contents($file, $resp->body()); $files++;
                }
                $urls[] = "/img/pti/$slug-" . ($i + 1) . '.jpg';
            }
            if (!$urls || $this->option('dry')) { $done += (bool) $urls; continue; }
            DB::table('catalog_live')->where('wp_product_id', $r->wp_product_id)->update(['image' => $urls[0], 'thumb' => $urls[0], 'gallery' => json_encode(array_slice($urls, 1))]);
            $done++;
        }
        Cache::forget('catalog_live_map'); CatalogWriter::invalidate();
        $this->info("photos: $done product(s), $files file(s) downloaded");
    }
}
