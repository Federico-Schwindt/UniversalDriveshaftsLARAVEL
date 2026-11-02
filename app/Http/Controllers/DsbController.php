<?php

namespace App\Http\Controllers;

use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DsbController extends Controller
{
    public function __construct(private DsbCatalog $catalog, private DsbPricing $pricing)
    {
    }

    /** Strings the builder's JavaScript shows, English => current language. */
    private const JS_STRINGS = [
        // build sheet + drawings (Oct 2026)
        'Pick a new :item from our catalog and see its measurements, or say no and we fit a good-condition one from our shop.',
        'Overall length, from the flange face or the end of the slip yoke to the far end', 'U-joint center to U-joint center', 'Weld to weld (the length of the tube)', 'Enter :dim in inches', 'See diagram above for :dim measurement point.', 'Now pick the rear end', 'Your driveshaft is drawn here as you choose the yokes.', 'Build', 'Duty', 'Front', 'Rear', 'Nothing chosen yet', 'Your build', 'Tube OD', 'Product', 'Configuration diagram',

        'Please complete the driveshaft configuration before adding to cart.', 'Share link copied to clipboard.',
        'No series match your search.', 'Complete all the steps above to enable checkout.',
        'Ready. Click below to add this driveshaft to your cart.', 'Not selected', 'Pending', 'Ready',
        'Please fill in the required fields.', 'Network error. Please try again.', 'Pre-Made', 'Custom Build',
        ':n series available', ':n series', 'Showing :a of :b series', 'Do you need a :item?',
        'Choose to add a :item from our shop or catalog, or skip this step.', 'Add a :item to the Order',
        'Loading products...', 'All', 'No products match your filters. Try adjusting the criteria above.', 'Select',
        'Prev', 'Next', 'Page :p / :n (:t products)', 'Loading…',
        'No pre-made driveshafts found for the current filters. Go back and choose a different series, or clear the length filter.',
        'Select this driveshaft', 'Page :p of :n (:t items)', 'Error loading products. Please try again.', 'How to measure',
        'Measurement A (inches)', 'Measurement B (inches)', 'e.g. 48.5', 'e.g. 12.0',
        'See diagram above for Measurement A.', 'See diagram above for Measurement B.', '-- Select --',
        'Aluminum', 'Steel', 'Tube :od', 'Tube OD', 'Error', 'Added to cart!', ':x Series', '__noteA', '__noteAB',
    ];

    /**
     * The builder's display strings in the visitor's language, for window.UDS_T. Only entries
     * that actually have a translation are sent; the script falls back to English. Data labels
     * (duty classes, series, applications, premade options) are translated for display only —
     * the English value is what the order and the premade lookup keep.
     */
    public static function jsStrings(array $config): array
    {
        $keys = self::JS_STRINGS;
        $walk = function ($node) use (&$walk, &$keys) {
            if (!is_array($node)) return;
            foreach ($node as $k => $v) {
                if (is_string($v) && in_array($k, ['label', 'description', 'hint'], true) && $v !== '') $keys[] = $v;
                elseif (is_array($v)) $walk($v);
            }
        };
        foreach (['tree', 'frontApps', 'rearApps', 'premadeExtraFields'] as $part) $walk($config[$part] ?? []);
        // catalog filter names arrive as slugs ("bolt-pattern") and are shown title-cased
        foreach (['Bolt Pattern', 'Series', 'Spline Count', 'Spline Dia', 'Bearing Id', 'Pilot Dia', 'Tube Dia', 'Butt Dia', 'Brand', 'Type', 'Style', 'Material', 'Condition', 'bolt pattern', 'spline count', 'spline dia', 'pilot dia', 'tube dia', 'butt dia'] as $f) $keys[] = $f;
        $out = [];
        foreach (array_unique($keys) as $k) {
            $v = __($k);
            if (is_string($v) && $v !== $k) $out[$k] = $v;
        }
        return $out;
    }

    /** GET /one-piece-designer (+ /two-piece-designer) — the builder page. */
    public function builder(Request $r, string $pieces = 'one')
    {
        $s = $this->pricing->settings();
        $flow = json_decode(file_get_contents(storage_path('app/flowgraph.json')), true);

        $config = [
            'pieces'      => $pieces,
            'currency'    => '$',
            'basePrice'   => 0, // display mirror: an unpriced tier shows $0 like the server (no $568.58 fallback)
            'tree'        => $s['tree'],
            'labels'      => $s['step_labels'],
            'title'       => $s['builder_title'],
            'subtitle'    => $s['builder_subtitle'],
            'extras'      => [
                'show_length' => (bool) $s['show_length'], 'length_required' => (bool) $s['length_required'],
                'show_notes' => (bool) $s['show_notes'], 'yoke_addons_enabled' => (bool) $s['yoke_addons_enabled'],
                'used_yoke_discount' => (int) $s['used_yoke_discount'],
            ],
            'tubeMax'     => collect($s['tube_max_lengths'] ?? [])->filter(fn ($r2) => !empty($r2['tube_od']) && (float) ($r2['max_length'] ?? 0) > 0)->mapWithKeys(fn ($r2) => [$r2['tube_od'] => (float) $r2['max_length']]),
            'tubeRules'   => $s['tube_rules_enabled'] ? ($s['tube_rules'] ?? []) : [],
            'yokes'       => ['enabled' => (bool) $s['yoke_addons_enabled'], 'discount' => (int) $s['used_yoke_discount'], 'used_flange' => $s['used_flange_prices'] ?? [], 'used_trans' => $s['used_trans_prices'] ?? []],
            'frontApps'   => $s['front_apps'],
            'rearApps'    => $s['rear_apps'],
            'appDiagrams' => $s['app_diagrams'],
            'tubeOdPricing'        => $s['tube_od_pricing'],
            'aluminumPricing'      => $s['aluminum_tube_od_pricing'],
            'aluminumFlatPricing'  => $s['aluminum_flat_pricing'],
            'materialSteelLimits'  => $s['material_steel_limits'],
            'lengthLabel' => $s['length_label'],
            'lengthHint'  => $s['length_hint'],
            'flow'        => $flow,
            'diagramAUrl'  => '/img/builder/diagrams/Ameasurement.png',
            'diagramABUrl' => '/img/builder/diagrams/AandBmeasurements.png',
            // hardcoded premade extra option sets — ported verbatim from DSB_Data::premade_extra_fields()
            'premadeExtraFields' => $this->premadeExtraFields(),
            'endpoints'   => [
                'yokes'    => '/dsb/yokes',
                'premades' => '/dsb/premades',
                'quote'    => '/dsb/quote',
                'submit'   => '/dsb/submit',
            ],
        ];

        return view('designer', ['config' => $config, 'pieces' => $pieces]);
    }

    /** GET /dsb/yokes — yoke catalog search (flange|trans). */
    public function yokes(Request $r)
    {
        $type = $r->query('type');
        if (!in_array($type, ['flange', 'trans'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid type.'], 400);
        }
        $filters = json_decode($r->query('filters', '{}'), true);
        return response()->json(['success' => true, 'data' => $this->catalog->yokes(
            $type,
            (string) $r->query('series', ''),
            is_array($filters) ? $filters : [],
            (int) $r->query('page', 1),
            (int) $r->query('per_page', 12),
        )]);
    }

    /** GET /dsb/premades — premade driveshaft search. */
    public function premades(Request $r)
    {
        return response()->json(['success' => true, 'data' => $this->catalog->premades(
            (string) $r->query('series_label', ''),
            (int) $r->query('paged', 1),
            (string) $r->query('length_filter', ''),
        )]);
    }

    /** POST /dsb/quote — live server-side price for the current selections. */
    public function quote(Request $r)
    {
        $cfg = $this->cfgFromRequest($r);
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors], 422);
        $price = $this->pricing->compute($cfg);
        return response()->json(['success' => true, 'data' => $price]);
    }

    /** POST /dsb/submit — final submit: recompute price server-side, persist, return reference. */
    public function submit(Request $r)
    {
        $cfg = $this->cfgFromRequest($r);
        // every key must exist in the server's own tree/app lists — a tampered or mis-cased key is refused, never re-priced
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors], 422);

        // completeness gate (mirrors dsb_configured check, but server-verified)
        $errors = [];
        if (($cfg['build_mode'] ?? '') === 'premade') {
            if (empty($cfg['premade_product_id'])) $errors[] = 'No pre-made product selected.';
            if (($cfg['premade_a'] ?? '') === '') $errors[] = 'Measurement A is required.';
        } else {
            foreach (['duty_key' => 'duty rating', 'series_key' => 'series', 'front_app' => 'front application', 'rear_app' => 'rear application'] as $k => $label) {
                if (($cfg[$k] ?? '') === '') $errors[] = "Please select the {$label}.";
            }
            if ((float) ($cfg['a1'] ?? 0) <= 0) $errors[] = 'Please enter Measurement A1.';
        }
        if ($errors) return response()->json(['success' => false, 'errors' => $errors], 422);

        $price = $this->pricing->compute($cfg);
        if (($price['final'] ?? 0) <= 0) return response()->json(['success' => false, 'errors' => ['This build could not be priced online. Please call (813) 524-6491 for a quote.']], 422);
        $summary = $this->summary($cfg);
        $qty = min(20, max(1, (int) $r->input('quantity', 1)));

        // Add to the session cart — identical configs merge into one line (as the original).
        $cart = app(\App\Services\CartService::class);
        if ($cfg['build_mode'] === 'premade') {
            $name = $this->catalog->productName((int) $cfg['premade_product_id']) ?? 'Pre-Made Driveshaft';
            $cart->add([
                'type' => 'premade_build', 'wp_product_id' => (int) $cfg['premade_product_id'],
                'name' => $name, 'price' => $price['final'], 'qty' => $qty,
                'meta' => ['config' => $cfg, 'price' => $price, 'summary' => $summary],
            ]);
        } else {
            $tierLabel = ($cfg['price_tier'] === 'new') ? 'Brand New' : 'Certified';
            $cart->add([
                'type' => 'custom_build', 'wp_product_id' => null,
                'name' => 'Custom Driveshaft — ' . $cfg['series_label'], // server-side label (invalidKeys)
                'price' => $price['final'], 'qty' => $qty,
                'meta' => ['config' => $cfg, 'price' => $price, 'summary' => $summary . ' • ' . $tierLabel],
            ]);
        }
        Storage::append('dsb_orders.jsonl', json_encode(['ts' => now()->toDateTimeString(), 'event' => 'added_to_cart', 'config' => $cfg, 'price' => $price], JSON_UNESCAPED_SLASHES));

        return response()->json(['success' => true, 'data' => [
            'price' => $price, 'summary' => $summary,
            'cart_count' => $cart->count(), 'cart_url' => '/cart',
        ]]);
    }

    private function cfgFromRequest(Request $r): array
    {
        // scalars only: an array parameter (duty_key[]=x) reads as '' instead of a 500
        $in = fn (string $k, string $d = '') => is_scalar($v = $r->input($k)) ? (string) $v : $d;
        $extras = json_decode($in('premade_extras', '[]'), true);
        $extras = $this->trustedExtras(is_array($extras) ? $extras : [], (int) $in('premade_product_id', '0'));
        $one = fn (string $k, array $ok, string $d) => in_array($in($k), $ok, true) ? $in($k) : $d;
        return [
            'pieces'             => $one('pieces', ['one', 'two'], 'one'),
            'build_mode'         => $one('build_mode', ['custom', 'premade'], 'custom'),
            'premade_product_id' => (int) $in('premade_product_id', '0'),
            'premade_a'          => strip_tags($in('premade_a')),
            'premade_b'          => strip_tags($in('premade_b')),
            'premade_extras'     => $extras,
            'duty_key'           => $in('duty_key'),
            'duty_label'         => '', // labels are derived server-side in invalidKeys(), never taken from the browser
            'subcat_key'         => $in('subcat_key'),
            'series_key'         => $in('series_key'),
            'series_label'       => '',
            'material'           => $one('material', ['steel', 'aluminum'], 'steel'),
            'price_tier'         => $one('price_tier', ['certified', 'new'], 'certified'),
            'front_app'          => $in('front_app'),
            'rear_app'           => $in('rear_app'),
            'front_app_label'    => '',
            'rear_app_label'     => '',
            'length'             => (float) $in('length', '0'),
            'a1'                 => (float) $in('a1', '0'),
            'c1'                 => (float) $in('c1', '0'),
            'b1'                 => (float) $in('b1', '0'),
            'flange_yoke'        => ['kind' => $one('flange_yoke_kind', ['used', 'new', 'none'], 'none'), 'product_id' => (int) $in('flange_yoke_product_id', '0')],
            'trans_yoke'         => ['kind' => $one('trans_yoke_kind', ['used', 'new', 'none'], 'none'), 'product_id' => (int) $in('trans_yoke_product_id', '0')],
            'notes'              => mb_substr(strip_tags($in('notes')), 0, 2000),
        ];
    }

    /**
     * Check every key against the settings tree / app lists and fill the display labels from them (in place).
     * Returns the errors; empty keys pass here (the submit completeness gate catches those).
     */
    private function invalidKeys(array &$cfg): array
    {
        $s = $this->pricing->settings();
        $errors = [];
        $hit = null;
        if ($cfg['duty_key'] !== '' || $cfg['series_key'] !== '' || $cfg['subcat_key'] !== '') {
            $hit = $this->pricing->resolveSeries($cfg['duty_key'], $cfg['subcat_key'], $cfg['series_key']);
            if (!$hit && ($cfg['series_key'] !== '' || !isset($s['tree'][$cfg['duty_key']]))) $errors[] = __('Unknown duty rating or series. Please reload the builder and choose again.');
        }
        if ($hit) {
            $cfg['duty_label'] = $hit['duty_label']; $cfg['series_label'] = $hit['series_label'];
            $cfg['subcat_key'] = (string) $hit['subcat_key'];
            if (!$this->pricing->aluminumEligible($hit['duty_key'], $hit['series_key'])) $cfg['material'] = 'steel';
        } elseif (isset($s['tree'][$cfg['duty_key']])) {
            $cfg['duty_label'] = (string) ($s['tree'][$cfg['duty_key']]['label'] ?? '');
        }
        if ($cfg['material'] === 'aluminum') $cfg['price_tier'] = 'new';

        if ($cfg['build_mode'] === 'premade') {
            if ($cfg['premade_product_id'] && !$this->catalog->isPremade($cfg['premade_product_id'])) $errors[] = __('That pre-made driveshaft is not available.');
            $cfg['front_app'] = $cfg['rear_app'] = '_premade';
            return $errors;
        }
        $front = $s['front_apps'][$cfg['front_app']] ?? null;
        if ($cfg['front_app'] !== '' && (!$front || (!empty($front['lm_only']) && $cfg['duty_key'] !== 'light_medium'))) $errors[] = __('Please choose a valid front application.');
        $rear = $s['rear_apps'][$cfg['rear_app']] ?? null;
        if ($cfg['rear_app'] !== '' && (!$rear || (!empty($rear['aluminum_only']) && $cfg['material'] !== 'aluminum'))) $errors[] = __('Please choose a valid rear application.');
        $cfg['front_app_label'] = (string) ($front['label'] ?? '');
        $cfg['rear_app_label'] = (string) ($rear['label'] ?? '');
        return $errors;
    }

    /**
     * Extras arrive from the browser; only a value that exactly matches one of the product's own options is kept,
     * with the label and cost from the server's option table (one select per product → at most one extra).
     */
    private function trustedExtras(array $extras, int $pid): array
    {
        $fields = $this->premadeExtraFields()[$pid] ?? null;
        if (!$fields) return [];
        foreach ($extras as $e) {
            $value = is_array($e) && is_string($e['value'] ?? null) ? $e['value'] : null;
            foreach ($fields['options'] as $o) {
                if ($value === $o['label']) return [['label' => $fields['label'], 'value' => $o['label'], 'cost' => (float) $o['cost']]];
            }
        }
        return [];
    }

    /** Verbatim port of DSB_Data::premade_extra_fields() (v2.9.1). */
    private function premadeExtraFields(): array
    {
        $slip = fn (float $cost) => ['label' => 'Slip Yoke Options:', 'options' => [
            ['label' => 'Turbo 350', 'cost' => $cost], ['label' => '700R4', 'cost' => $cost],
            ['label' => 'Muncie 27 spline', 'cost' => $cost], ['label' => '4l60E', 'cost' => $cost],
            ['label' => 'AOD', 'cost' => $cost], ['label' => 'AODE', 'cost' => $cost],
            ['label' => 'None', 'cost' => 0],
        ]];
        $strap = fn (float $cost) => ['label' => 'Order Options:', 'options' => [
            ['label' => 'Driveshaft without Strap Kit', 'cost' => 0],
            ['label' => 'Driveshaft with Strap Kit', 'cost' => $cost],
        ]];
        return [
            11209 => $slip(0), 11214 => $slip(0), 11215 => $slip(0), 11216 => $slip(0), 11217 => $slip(0), 11219 => $slip(0),
            11212 => $slip(37.33),
            15513 => ['label' => 'Order Options:', 'options' => [
                ['label' => 'Driveshaft without Strap Kit', 'cost' => 0],
                ['label' => 'Driveshaft with Strap Kit', 'cost' => 20.71],
                ['label' => 'Driveshaft with Pinion Yoke Replacement Kit and Strap Kit', 'cost' => 154.62],
            ]],
            15514 => $strap(8.56), 15516 => $strap(8.56),
            15515 => $strap(20.71), 15517 => $strap(20.71), 15518 => $strap(20.71),
        ];
    }

    private function summary(array $cfg): string
    {
        if (($cfg['build_mode'] ?? '') === 'premade') {
            $name = $this->catalog->productName((int) $cfg['premade_product_id']) ?? '';
            return trim(($cfg['duty_label'] ?? '') . ' > ' . ($cfg['series_label'] ?? '') . ' > ' . $name, ' >');
        }
        return trim(($cfg['duty_label'] ?? '') . ' > ' . ($cfg['series_label'] ?? '') . ' > ' . ($cfg['front_app_label'] ?? '') . ' > ' . ($cfg['rear_app_label'] ?? ''), ' >');
    }

    /** POST /dsb/save-design — save the current config to the customer's account. */
    public function saveDesign(\Illuminate\Http\Request $r)
    {
        if (!\Auth::check()) return response()->json(['success' => false, 'login' => true, 'message' => 'Please sign in to save your design.'], 401);
        $cfg = $this->cfgFromRequest($r);
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors, 'message' => implode(' ', $errors)], 422);
        $price = $this->pricing->compute($cfg);
        $ref = 'DSN-' . now()->format('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        \Illuminate\Support\Facades\DB::table('saved_designs')->insert([
            'user_id' => \Auth::id(), 'ref' => $ref,
            'title' => ($cfg['series_label'] ?: __('Custom driveshaft')) . ' — ' . ($cfg['front_app_label'] ?: ($cfg['build_mode'] === 'premade' ? __('Pre-Made') : 'custom')),
            'config' => json_encode($cfg + ['summary' => $this->summary($cfg)]),
            'price' => $price['final'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'ref' => $ref]);
    }

}
