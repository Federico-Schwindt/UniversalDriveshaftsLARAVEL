<?php

namespace App\Http\Controllers;

use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use App\Support\BuildConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The online driveshaft builder (custom shafts only, owner Nov 2026). The question tree lives in
 * storage/app/builder-tree.json; pricing is DsbPricing; the catalog lookups are DsbCatalog.
 */
class DsbController extends Controller
{
    public const PHONE = '(813) 524-6491';
    public const PHONE_HREF = 'tel:18135246491';

    public function __construct(private DsbCatalog $catalog, private DsbPricing $pricing)
    {
    }

    /** Strings the builder's JavaScript shows, English => current language (lang/ files are the lead's). */
    private const JS_STRINGS = [
        // progress / navigation
        'Step :n', 'Step :n of :total', 'Back', 'Continue', 'Edit', 'Change', 'Not selected', 'Pending', 'Ready', 'Start over',
        'Your answers', 'Output type', 'U-joint series', 'Tube material', 'Rear end', 'Rear yoke', 'Rear hardware', 'Measurements',
        'Front yoke', 'Pick the yoke', 'Your driveshaft', 'Front end', 'Heavy-duty family',
        // series / material
        ':n series', 'cap :cap × :width wide', 'cap :cap', 'Steel', 'Aluminum', 'up to :max in', 'Brand new components only',
        'Which u-joint series?', 'Not sure? Measure a bearing cap with calipers: the cap diameter and the width across the joint tell the series apart.',
        'Typical for this vehicle', 'Tube material', 'Standard, strong and economical', 'Lighter, longer builds, new components only',
        // ends
        'What is on the differential end?', 'What is on the other end?', 'Which end is at the transmission or transfer case?',
        'New yoke', 'Pick one from our catalog', 'Good used yoke from our shop', 'Reuse mine', 'I will send it in or bring it to the shop',
        'Slip yoke for this shaft', 'Flange yoke for this end', 'No yokes match. Clear a filter or go back and choose another series.', 'All', 'Select', 'Selected',
        'Prev', 'Next', 'Page :p / :n (:t products)', 'Loading…', 'Network error. Please try again.', 'The shop confirms the yoke with you before building.',
        'Strap kit', 'U-bolt kit', 'No, I have mine', 'Pick the kit', 'No kit is listed for this series; we add the right one when we confirm your order.',
        'How does the rear u-joint attach to the pinion yoke?', 'Kits are listed for every series: the shop confirms the fit.',
        // measurements
        'Overall length, from the flange face or the end of the slip yoke to the far end', 'U-joint center to U-joint center', 'Weld to weld (the length of the tube)',
        'Enter :dim in inches', 'See diagram above for :dim measurement point.', 'Lands on a :od tube', 'Max :max in for :material :series',
        'Please enter the length (A1).', 'Please enter C1 (u-joint center to u-joint center).', 'C1 cannot be longer than A1. Please check both measurements.', 'Length (A1), inches', 'Inches',
        // review
        'Custom :series series :material driveshaft, :front to :rear, :len in', 'Custom :series series :material driveshaft, :front to :rear',
        'Custom driveshaft', 'Price after measurements', ':material :series builds start at :price', 'Base shaft', 'Certified', 'Brand new',
        'Certified reconditioned components', 'All new components', 'Total', 'Add to cart', 'Save design', 'Sign in to save your design.',
        'Design saved to your account.', 'Added to cart!', 'Please complete the driveshaft configuration before adding to cart.',
        'Share link copied to clipboard.', 'Complete the steps to enable checkout.', 'Ready to add to your cart.', 'Error', 'Tube OD',
        'Drawing', 'Build', 'Front', 'Rear', 'Nothing chosen yet', 'Your build', 'Configuration diagram',
        'Your driveshaft is drawn here as you choose the ends.', 'Now pick the rear end', 'Series', 'Material', 'Hardware', 'Splines', ':x Series',
        'Fits', 'Please fill in the required fields.', 'Vehicle', 'Drive', 'Make', 'Model', 'Year', 'Spline count', 'Barrel OD', 'Tail housing',
        // Nov 2026 additions
        ':n splines', ':od barrel', 'Flange face or yoke end to the center of the support bearing', 'Flange yokes in :series', 'Slip yokes in :series',
        'From your answers: :what', 'Inspected, cleaned and fitted here', 'Nothing added', 'Two straps and four bolts hold the caps', 'Two u-bolts with nuts and washers',
        'Reusing your yoke: ship it to us with your order number, or bring it to the shop. We fit it and balance the shaft with it. Questions: :phone',
        'Transmission or transfer case model (optional)', 'Tube :od', 'Type to filter', 'We balance the shaft with its yokes fitted, so tell us which one goes on.', 'from :price',
        // engineering (speed check, guidance)
        'Speed check', 'aluminum', 'steel', ':od × :wall :material tube at :len in', 'This :tube is safe to :safe RPM (critical speed :crit RPM).',
        'At :mph mph your shaft turns :rpm RPM; this :tube is safe to :safe RPM.', 'A :od in tube would be safe to :safe RPM at this length.',
        'At this length a two-piece shaft with a center support bearing is the usual answer.', 'That is close to the limit.', 'That is over the safe speed.', 'Or consider a two-piece shaft.',
        'safe to :safe RPM', 'OK at :mph mph', 'near the limit at :mph mph', 'over the safe speed at :mph mph', 'Critical speed', 'Check it for your vehicle',
        'Inputs: Nc = 4.705×10⁶ × √(OD² + ID²) ÷ L², simply supported tube; OD :od in, wall :wall in, ID :id in, L :len in; safe = 75% of Nc.', 'Shaft RPM = :mph × :ratio × 336 ÷ :tire.',
        ':mph mph, :ratio ratio, :tire in tire', 'photo of a shaft we build; yours is made to your measurements',
        // builder v3 (2026-11): stages, spline guide, yoke stock, option headings, speed span
        'Vehicle', 'Shaft', 'Ends', 'Length', 'Your price', 'How to count the splines', 'End view of a slip yoke: count every tooth, the two wide gaps count as one each',
        'Look into the open end of your old slip yoke, or at the output shaft it slides onto.', 'Put a marker dot on one tooth and count every tooth all the way around back to it.',
        'Some yokes have one or two wide gaps (blind spots) that index them on the shaft. Count each gap as one tooth.', 'Not sure? Send us a photo of the end and we will count it with you.',
        'Blind spot: counts as one', ':make yokes', 'None listed online for :series with :n splines. Reuse yours, or call us and we source one.',
        'None listed online for :series. Reuse yours, or call us and we source one.', 'No yoke matches these filters.', 'We do not list a yoke for this combination online.',
        'Clear filters', 'Call us to source one', 'Nothing else to choose for this build. Continue to your price.', ':od in :material tube, :series series',
        'U-joint hardware kit', 'Slip yoke', 'Long shaft', 'U-joints', '(joint to joint)', '(overall length; enter C1 for a closer figure)',
        'Hub diameter', 'U-joint center to end', 'Bolt circle', 'Pilot diameter', 'Your answers appear here',
    ];

    /**
     * The builder's display strings in the visitor's language, for window.UDS_T. Only entries that actually have a
     * translation are sent; the script falls back to English. Data labels (series, applications, tree questions and
     * options) are translated for display only: the English value is what the order keeps.
     */
    public static function jsStrings(array $config): array
    {
        $keys = self::JS_STRINGS;
        $walk = function ($node) use (&$walk, &$keys) {
            if (!is_array($node)) return;
            foreach ($node as $k => $v) {
                if (is_string($v) && in_array($k, ['label', 'description', 'hint', 'q', 'note', 'help', 'group'], true) && $v !== '') $keys[] = $v;
                elseif (is_array($v)) $walk($v);
            }
        };
        foreach (['tree', 'frontApps', 'rearApps', 'appHelp', 'flow'] as $part) $walk($config[$part] ?? []);
        foreach ((array) (self::flow()['engineering']['series_class'] ?? []) as $v) $keys[] = $v;
        foreach ((array) (self::flow()['engineering']['measure_help'] ?? []) as $v) $keys[] = $v;
        $keys[] = (string) (self::flow()['engineering']['angle_note'] ?? '');
        // catalog filter names arrive as slugs ("bolt-pattern") and are shown title-cased
        foreach (['Series', 'Pilot Dia', 'Cj To Flat', 'Bolt Holes', 'Bolt Circle Dia', 'Splines Count Dia', 'Center U Joint To End', 'Hub Dia', 'Fits Cap Dia', 'Thread Dia'] as $f) $keys[] = $f;
        $out = [];
        foreach (array_unique($keys) as $k) {
            $v = __($k);
            if (is_string($v) && $v !== $k) $out[$k] = $v;
        }
        return $out;
    }

    /** The question tree (storage/app/builder-tree.json). */
    public static function flow(): array
    {
        static $flow = null;
        return $flow ??= (json_decode((string) @file_get_contents(storage_path('app/builder-tree.json')), true) ?: ['nodes' => [], 'families' => []]);
    }

    /** GET /online-builder — the builder page (one piece only since 2026-11-10; two-piece shafts are quoted by phone). */
    public function builder(Request $r)
    {
        $pieces = 'one';
        $s = $this->pricing->settings();

        // u-joint series reference (bearing cap diameter × width) from the series pages' own copy; nothing invented
        $specs = [];
        foreach ((array) config('seo-copy.series', []) as $k => $sp) {
            $specs[strtolower($k)] = ['cap' => $sp['cap'] ?? null, 'width' => $sp['width'] ?? null, 'use' => self::firstSentence($sp['desc'] ?? '')];
        }

        $config = [
            'pieces'      => $pieces,
            'currency'    => '$',
            'tree'        => $s['tree'],
            'flow'        => self::flow(),
            'yokes'       => ['enabled' => (bool) $s['yoke_addons_enabled'], 'used_flange' => $s['used_flange_prices'] ?? [], 'used_trans' => $s['used_trans_prices'] ?? []],
            'frontApps'   => $s['front_apps'],
            'rearApps'    => $s['rear_apps'],
            'appHelp'     => self::APP_HELP,
            'appDiagrams' => $s['app_diagrams'],
            'tubeOdPricing'        => $s['tube_od_pricing'],
            'aluminumPricing'      => $s['aluminum_tube_od_pricing'],
            'aluminumFlatPricing'  => $s['aluminum_flat_pricing'],
            'materialSteelLimits'  => $s['material_steel_limits'],
            'maxLengths'  => $this->pricing->maxLengths(),
            'amCoverage'  => app(\App\Services\AmPricing::class)->coverage(),
            'amFrom'      => app(\App\Services\AmPricing::class)->fromPrices(),
            'optionLabels' => self::OPTION_LABELS,
            'seriesSpecs' => $specs,
            'phone'       => self::PHONE,
            'phoneHref'   => self::PHONE_HREF,
            'loggedIn'    => \Auth::check(),
            'loginUrl'    => '/login?next=' . rawurlencode($r->getRequestUri()),
            'endpoints'   => [
                'yokes'    => '/dsb/yokes',
                'quote'    => '/dsb/quote',
                'submit'   => '/dsb/submit',
                'save'     => '/dsb/save-design',
            ],
        ];

        return view('designer', ['config' => $config, 'pieces' => $pieces]);
    }

    /** One plain line under each end option (display only; the settings label is what the order keeps). */
    public const APP_HELP = [
        'front' => [
            'slip_flange'    => ['description' => 'Slip joint built into the shaft, bolts to a companion flange.'],
            'slip_no_flange' => ['description' => 'Slip joint built into the shaft, open u-joint on the end.'],
            'trans'          => ['description' => 'Slip yoke that slides onto the output shaft.'],
            'weld'           => ['description' => 'Open u-joint, bolts into an end yoke with straps or u-bolts.'],
            'flange'         => ['description' => 'Bolts to a companion flange, no slip.'],
            'yoke_shaft'     => ['description' => 'Splined stub on the shaft goes into a female output.'],
        ],
        'rear' => [
            'flange'         => ['description' => 'Bolts to the pinion or transfer-case flange.'],
            'weld'           => ['description' => 'Open u-joint into the pinion yoke, held by straps or u-bolts.'],
            'carrier_end'    => ['description' => 'Center support bearing with an end yoke.'],
            'carrier_slip'   => ['description' => 'Center support bearing with a slip yoke.'],
            'slip_flange'    => ['description' => 'Slip joint on this end, bolts to a flange.'],
            'slip_no_flange' => ['description' => 'Slip joint on this end, open u-joint on the end.'],
        ],
    ];

    private static function firstSentence(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        $p = strpos($s, '. ');
        return $p === false ? $s : substr($s, 0, $p + 1);
    }

    /** Our wording for the supplier's option values (facts stay; phrasing is ours). Unknown values are shown as-is. */
    public const OPTION_LABELS = [
        'Driveshaft Only' => 'No hardware kit', 'No Change' => 'Standard build, new slip yoke included', 'Upgrade to 8 inch long slip yoke.' => 'Long slip yoke, 8 in',
        'I will send in my slip yoke.' => 'Reuse my slip yoke (credit)', 'I will send in my slip yoke & flange yoke.' => 'Reuse my slip yoke and flange yoke (credit)',
        'I will send in my yoke shaft.' => 'Reuse my yoke shaft (credit)', 'I will send in my yoke shaft & flange yoke.' => 'Reuse my yoke shaft and flange yoke (credit)',
        'Driveshaft & yoke shaft boot.' => 'Add the yoke shaft boot', 'Driveshaft without Strap Kit' => 'No strap kit', 'Driveshaft with Strap Kit' => 'With strap kit',
        'Driveshaft with Pinion Yoke Replacement Kit and Strap Kit' => 'With pinion yoke replacement kit and strap kit',
    ];

    /** GET /dsb/yokes — yoke catalog search (flange|trans) and u-joint hardware kits (strap|ubolt). */
    public function yokes(Request $r)
    {
        $qs = fn (string $k, string $d = '') => is_scalar($v = $r->query($k)) ? (string) $v : $d;
        $type = $qs('type');
        $series = mb_substr(trim($qs('series')), 0, 20);
        if (isset(DsbCatalog::HARDWARE_TYPES[$type])) {
            return response()->json(['success' => true, 'data' => $this->catalog->hardware($type, $series)]);
        }
        if (!isset(DsbCatalog::YOKE_TYPES[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid type.'], 400);
        }
        $filters = json_decode($qs('filters', '{}'), true);
        $ids = array_filter(array_map('intval', explode(',', $qs('id'))));
        return response()->json(['success' => true, 'data' => $this->catalog->yokes(
            $type,
            $series,
            is_array($filters) ? $filters : [],
            (int) $qs('page', '1'),
            (int) $qs('per_page', '12'),
            $qs('spline'),
            $ids,
        )]);
    }

    /** POST /dsb/quote — live server-side price for the current selections. */
    public function quote(Request $r)
    {
        $cfg = $this->cfgFromRequest($r);
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors], 422);
        $price = $this->pricing->compute($cfg);
        return response()->json(['success' => true, 'data' => $price + ['title' => $this->title($cfg), 'summary' => $this->summary($cfg)]]);
    }

    /** POST /dsb/submit — final submit: recompute price server-side, add to the cart, return reference. */
    public function submit(Request $r)
    {
        $cfg = $this->cfgFromRequest($r);
        // every key must exist in the server's own tree/app lists — a tampered or mis-cased key is refused, never re-priced
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors], 422);

        // completeness gate (server-verified)
        $errors = [];
        foreach (['duty_key' => __('duty rating'), 'series_key' => __('series'), 'front_app' => __('front end'), 'rear_app' => __('rear end')] as $k => $label) {
            if (($cfg[$k] ?? '') === '') $errors[] = __('Please select the :what.', ['what' => $label]);
        }
        if ((float) ($cfg['a1'] ?? 0) <= 0) $errors[] = __('Please enter the length (A1).');
        // C1 (u-joint centre to centre) is required (owner, 2026-11-10), except where it equals A1: u-joint at both ends with
        // the slip joint inside the shaft, or a carrier section
        $c1Skip = str_starts_with((string) ($cfg['rear_app'] ?? ''), 'carrier') || (($cfg['front_app'] ?? '') === 'slip_no_flange' && ($cfg['rear_app'] ?? '') === 'weld');
        if (!$c1Skip && (float) ($cfg['a1'] ?? 0) > 0) {
            if ((float) ($cfg['c1'] ?? 0) <= 0) $errors[] = __('Please enter C1 (u-joint center to u-joint center).');
            elseif ((float) $cfg['c1'] > (float) $cfg['a1']) $errors[] = __('C1 cannot be longer than A1. Please check both measurements.');
        }
        foreach (['front_yoke' => __('front yoke'), 'rear_yoke' => __('rear yoke')] as $k => $label) {
            if (($cfg[$k]['kind'] ?? 'none') === 'new' && (int) ($cfg[$k]['product_id'] ?? 0) <= 0) $errors[] = __('Please pick the :what from the catalog.', ['what' => $label]);
        }
        if (in_array($cfg['hardware']['kind'] ?? 'none', ['strap', 'ubolt'], true) && (int) ($cfg['hardware']['product_id'] ?? 0) <= 0) $errors[] = __('Please pick the hardware kit.');
        if ($errors) return response()->json(['success' => false, 'errors' => $errors], 422);

        $price = $this->pricing->compute($cfg);
        if (($price['breakdown']['error'] ?? '') === 'length_answer') return response()->json(['success' => false, 'errors' => [__('The length answer does not match the measured length. Please check A1.')]], 422);
        if (($price['final'] ?? 0) <= 0) return response()->json(['success' => false, 'errors' => [__('This build could not be priced online. Please call :phone for a quote.', ['phone' => self::PHONE])]], 422);
        if (!empty($price['breakdown']['over_max'])) {
            // steel too long but aluminum reaches it: say so; otherwise the honest answer is a two-piece shaft
            $msg = ($cfg['material'] ?? 'steel') === 'steel'
                ? __('A :len in shaft is longer than steel tube runs in this series (:max in max). Try aluminum, or call :phone for a two-piece shaft.', ['len' => self::inches((float) $cfg['a1']), 'max' => (string) $price['breakdown']['max_length'], 'phone' => self::PHONE])
                : __('A :len in shaft is longer than one :material tube can run safely in this series (:max in max). Call :phone and we quote it as a two-piece shaft.', ['len' => self::inches((float) $cfg['a1']), 'max' => (string) $price['breakdown']['max_length'], 'material' => (string) $cfg['material'], 'phone' => self::PHONE]);
            return response()->json(['success' => false, 'errors' => [$msg]], 422);
        }
        $summary = $this->summary($cfg);
        $qty = min(20, max(1, (int) $r->input('quantity', 1)));

        // supplier-priced build: all new components, and the options picked for it go on the order in our words
        if (($price['breakdown']['pricing'] ?? '') === 'reference') {
            $cfg['price_tier'] = 'new';
            $cfg['build_options'] = self::buildOptionRows((array) ($price['breakdown']['options'] ?? []));
        }
        $cfg['tube_od'] = (string) ($price['breakdown']['tube_od'] ?? '');
        $cfg['speed_check'] = $price['breakdown']['speed'] ?? null;
        $cfg['title'] = $this->title($cfg);
        // Add to the session cart — identical configs merge into one line.
        $cart = app(\App\Services\CartService::class);
        $cart->add([
            'type' => 'custom_build', 'wp_product_id' => null,
            'name' => $cfg['title'], // server-side labels only (invalidKeys)
            'image' => $this->buildPhoto($cfg),   // the real shaft photo the review shows, not the end diagram
            'price' => $price['final'], 'qty' => $qty,
            'meta' => ['config' => $cfg, 'price' => $price, 'summary' => $summary],
        ]);
        Storage::append('dsb_orders.jsonl', json_encode(['ts' => now()->toDateTimeString(), 'event' => 'added_to_cart', 'config' => $cfg, 'price' => $price], JSON_UNESCAPED_SLASHES));

        return response()->json(['success' => true, 'data' => [
            'price' => $price, 'summary' => $summary, 'title' => $cfg['title'],
            'cart_count' => $cart->count(), 'cart_url' => '/cart',
        ]]);
    }

    private function cfgFromRequest(Request $r): array
    {
        // scalars only: an array parameter (duty_key[]=x) reads as '' instead of a 500
        $in = fn (string $k, string $d = '') => is_scalar($v = $r->input($k)) ? trim((string) $v) : $d;
        $text = fn (string $k, int $max) => mb_substr(strip_tags($in($k)), 0, $max);
        $one = fn (string $k, array $ok, string $d) => in_array($in($k), $ok, true) ? $in($k) : $d;

        // the tree answers travel as [{q, a}] and are kept as plain text for the shop (the keys that price are separate)
        $answers = [];
        $raw = json_decode($in('answers', '[]'), true);
        foreach (is_array($raw) ? array_slice($raw, 0, 24) : [] as $row) {
            if (!is_array($row)) continue;
            $q = mb_substr(strip_tags(trim((string) ($row['q'] ?? ''))), 0, 90);
            $a = mb_substr(strip_tags(trim((string) ($row['a'] ?? ''))), 0, 90);
            if ($q !== '' && $a !== '') $answers[] = ['q' => $q, 'a' => $a];
        }
        $family = $text('family', 16);
        if (!isset(self::flow()['families'][$family])) $family = '';

        return [
            'pieces'             => $one('pieces', ['one'], 'one'),
            'build_mode'         => 'custom',
            'family'             => $family,
            'answers'            => $answers,
            'duty_key'           => $in('duty_key'),
            'duty_label'         => '', // labels are derived server-side in invalidKeys(), never taken from the browser
            'subcat_key'         => $in('subcat_key'),
            'series_key'         => $in('series_key'),
            'series_label'       => '',
            'series_variant'     => $text('series_variant', 30),
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
            'front_yoke'         => [
                'kind' => $one('front_yoke_kind', DsbPricing::YOKE_KINDS, 'none'), 'product_id' => (int) $in('front_yoke_product_id', '0'),
                'spline' => preg_match('/^\d{1,2}$/', $in('front_spline')) ? $in('front_spline') : '', 'model' => $text('front_trans_model', 60),
            ],
            'rear_yoke'          => ['kind' => $one('rear_yoke_kind', DsbPricing::YOKE_KINDS, 'none'), 'product_id' => (int) $in('rear_yoke_product_id', '0')],
            'hardware'           => ['kind' => $one('hardware_kind', DsbPricing::HARDWARE_KINDS, 'none'), 'product_id' => (int) $in('hardware_product_id', '0')],
            'speed'              => ['mph' => self::num($in('speed_mph'), 5, 250), 'ratio' => self::num($in('axle_ratio'), 1.5, 8), 'tire' => self::num($in('tire_in'), 15, 60)],
            'am_options'         => $this->amOptions($in('am_options')),
            'notes'              => mb_substr(strip_tags($in('notes')), 0, 2000),
        ];
    }

    /** {our heading: our wording} for the supplier options of a build; Shopify's lone "Title: Default Title" is dropped. */
    public static function buildOptionRows(array $options): array
    {
        $out = [];
        foreach ($options as $name => $value) {
            $name = (string) $name; $value = (string) $value;
            if ($value === '' || strcasecmp($value, 'Default Title') === 0 || strcasecmp($name, 'Title') === 0) continue;
            $head = preg_match('/hardware|kit/i', $name) ? 'U-joint hardware kit'
                : (preg_match('/build option|slip yoke/i', $name) ? 'Slip yoke'
                : (preg_match('/\b88\b|or over|length/i', $name) ? 'Long shaft' : rtrim($name, ': ?')));
            $out[$head] = self::OPTION_LABELS[$value] ?? $value;
        }
        return $out;
    }

    /** Chosen option values of a reference-priced product: {option name: value}, plain text, bounded. */
    private function amOptions(string $json): array
    {
        $raw = json_decode($json, true);
        $out = [];
        foreach (is_array($raw) ? array_slice($raw, 0, 6, true) : [] as $k => $v) {
            if (is_string($k) && is_scalar($v)) $out[mb_substr(strip_tags($k), 0, 80)] = mb_substr(strip_tags(trim((string) $v)), 0, 80);
        }
        return $out;
    }

    /** A number inside a plausible range, else 0 (unset). */
    private static function num(string $v, float $min, float $max): float
    {
        $f = (float) str_replace(',', '.', $v);
        return $f >= $min && $f <= $max ? round($f, 2) : 0.0;
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
        // a series variant is only the Ford-cap note the tree offers for that series
        $variants = (array) (self::flow()['series_variants'] ?? []);
        if ($cfg['series_variant'] !== '' && ($variants[$cfg['series_key']] ?? null) !== $cfg['series_variant']) $cfg['series_variant'] = '';
        if ($cfg['material'] === 'aluminum') $cfg['price_tier'] = 'new';

        $front = $s['front_apps'][$cfg['front_app']] ?? null;
        if ($cfg['front_app'] !== '' && (!$front || (!empty($front['lm_only']) && $cfg['duty_key'] !== 'light_medium'))) $errors[] = __('Please choose a valid front end.');
        $rear = $s['rear_apps'][$cfg['rear_app']] ?? null;
        if ($cfg['rear_app'] !== '' && (!$rear || (!empty($rear['aluminum_only']) && $cfg['material'] !== 'aluminum'))) $errors[] = __('Please choose a valid rear end.');
        $cfg['front_app_label'] = (string) ($front['label'] ?? '');
        $cfg['rear_app_label'] = (string) ($rear['label'] ?? '');

        // end options: the yoke must come from the category that end uses, a used one must be offered for the series
        $errors = array_merge($errors, $this->checkYoke($cfg['front_yoke'], $cfg['front_app'], $cfg['series_key']));
        $errors = array_merge($errors, $this->checkYoke($cfg['rear_yoke'], $cfg['rear_app'], $cfg['series_key']));
        if ($cfg['rear_app'] !== 'weld') {
            $cfg['hardware'] = ['kind' => 'none', 'product_id' => 0];
        } elseif (in_array($cfg['hardware']['kind'], ['strap', 'ubolt'], true)) {
            $pid = (int) $cfg['hardware']['product_id'];
            if ($pid > 0 && !$this->catalog->inCategory($pid, [DsbCatalog::HARDWARE_TYPES[$cfg['hardware']['kind']]])) $errors[] = __('That hardware kit is not available. Please pick it again.');
        } else {
            $cfg['hardware']['product_id'] = 0;
        }
        return $errors;
    }

    /** Normalise one end's yoke choice against the end type and the catalog (in place); returns errors. */
    private function checkYoke(array &$yoke, string $app, string $series): array
    {
        $type = DsbPricing::yokeTypeFor($app);
        if ($type === null) { // weld yoke, yoke shaft, carrier bearing or nothing chosen yet: no yoke question on this end
            $yoke = ['kind' => 'none', 'product_id' => 0] + (isset($yoke['spline']) ? ['spline' => $yoke['spline'], 'model' => $yoke['model']] : []);
            return [];
        }
        $errors = [];
        if ($yoke['kind'] === 'new') {
            $pid = (int) $yoke['product_id'];
            if ($pid > 0 && !$this->catalog->inCategory($pid, [DsbCatalog::YOKE_TYPES[$type]])) $errors[] = __('That yoke is not available. Please pick it again.');
        } else {
            $yoke['product_id'] = 0;
            if ($yoke['kind'] === 'used' && $series !== '' && $this->pricing->usedPrice($app, $series) <= 0) $errors[] = __('A good used yoke is not offered for this series. Pick a new one or reuse yours.');
        }
        return $errors;
    }

    /** Short line under the cart line / in e-mails (English: the shop reads it). */
    private function summary(array $cfg): string
    {
        $fam = self::flow()['families'][$cfg['family'] ?? ''] ?? null;
        $parts = array_filter([
            trim(($cfg['series_label'] ?? '') . ($cfg['series_variant'] ? ' (' . $cfg['series_variant'] . ')' : '')), ucfirst((string) ($cfg['material'] ?? '')),
            trim(($cfg['front_app_label'] ?? '') . ' → ' . ($cfg['rear_app_label'] ?? ''), ' →'),
            (float) ($cfg['a1'] ?? 0) > 0 ? self::inches((float) $cfg['a1']) . ' in' : '',
            $fam['label'] ?? '',
        ]);
        return implode(' · ', $parts);
    }

    /** The product-style name of a build: "Custom 1350 series steel driveshaft, slip yoke to flange yoke, 52.25 in". */
    private function title(array $cfg): string
    {
        $series = trim(preg_replace('/\s*series\s*$/i', '', (string) ($cfg['series_label'] ?? $cfg['series_key'] ?? '')));
        $front = mb_strtolower((string) ($cfg['front_app_label'] ?? '')); $rear = mb_strtolower((string) ($cfg['rear_app_label'] ?? ''));
        $t = 'Custom ' . ($series !== '' ? $series . ' series ' : '') . ($cfg['material'] ?? 'steel') . ' driveshaft';
        if ($front !== '' && $rear !== '') $t .= ', ' . $front . ' to ' . $rear;
        if ((float) ($cfg['a1'] ?? 0) > 0) $t .= ', ' . self::inches((float) $cfg['a1']) . ' in';
        return $t;
    }

    private static function inches(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /** Photo of a finished shaft with these ends (the one the builder's review shows): front__rear, the aluminum copy when
     *  the tube is aluminum, then the front end alone; the end photo only when no shaft photo exists. */
    private function buildPhoto(array $cfg): string
    {
        $photos = self::flow()['photos'] ?? [];
        $front = (string) ($cfg['front_app'] ?? ''); $rear = (string) ($cfg['rear_app'] ?? '');
        $p = $photos[$front . '__' . $rear] ?? null;
        if ($p && ($cfg['material'] ?? '') === 'aluminum' && !empty($p['aluminum']['file'])) $p = $p['aluminum'] + $p;
        $p = $p ?: ($photos['front:' . $front] ?? null);
        return (string) ($p['file'] ?? '') ?: $this->endImage($front, 'front');
    }

    /** The clean photo of an end option (the un-watermarked copy when one exists). */
    private function endImage(string $key, string $side): string
    {
        $s = $this->pricing->settings();
        $app = $s[$side === 'front' ? 'front_apps' : 'rear_apps'][$key] ?? null;
        return (string) ($app['image_clean'] ?? $app['image'] ?? '');
    }

    /** POST /dsb/save-design — save the current config to the customer's account. */
    public function saveDesign(Request $r)
    {
        if (!\Auth::check()) return response()->json(['success' => false, 'login' => true, 'message' => __('Please sign in to save your design.')], 401);
        $cfg = $this->cfgFromRequest($r);
        if ($errors = $this->invalidKeys($cfg)) return response()->json(['success' => false, 'errors' => $errors, 'message' => implode(' ', $errors)], 422);
        $price = $this->pricing->compute($cfg);
        $cfg['tube_od'] = (string) ($price['breakdown']['tube_od'] ?? '');
        $ref = 'DSN-' . now()->format('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        \Illuminate\Support\Facades\DB::table('saved_designs')->insert([
            'user_id' => \Auth::id(), 'ref' => $ref,
            'title' => $this->title($cfg),
            'config' => json_encode($cfg + ['summary' => $this->summary($cfg)]),
            'price' => $price['final'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'ref' => $ref]);
    }
}
