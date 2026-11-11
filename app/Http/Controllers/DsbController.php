<?php

namespace App\Http\Controllers;

use App\Services\DsbBuilds;
use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use Illuminate\Http\Request;

/**
 * The online driveshaft builder (custom shafts only, owner Nov 2026). The question tree lives in
 * storage/app/builder-tree.json; pricing is DsbPricing; the catalog lookups are DsbCatalog.
 */
class DsbController extends Controller
{
    public const PHONE = '(813) 524-6491';
    public const PHONE_HREF = 'tel:18135246491';

    public function __construct(private DsbCatalog $catalog, private DsbPricing $pricing, private DsbBuilds $builds)
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
        ':mph mph, :ratio ratio, :tire in tire',
        // vehicle make before a new flange yoke, start over (2026-11-11)
        'Vehicle make', 'No :series flange yoke names a vehicle make yet.', 'From your answers', '1 yoke listed', ':n yokes listed', 'Other make or not sure',
        'See all :n flange yokes in :series', 'See every flange yoke in :series', 'Listed for :make: :n of :total.', 'Start over? Your answers will be cleared.',
        // builder v3 (2026-11): stages, spline guide, yoke stock, option headings, speed span
        'Vehicle', 'Application', 'Shaft', 'Ends', 'Length', 'Your price', 'How to count the splines', 'End view of a slip yoke: count every tooth, the two wide gaps count as one each',
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
        // our wording of the supplier's options and the review's line words (shown with tl() / t())
        foreach (self::OPTION_LABELS as $v) $keys[] = $v;
        $keys[] = 'Driveshaft'; $keys[] = 'new components';
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
            $qs('make'),
        )]);
    }

    /** GET /online-builder/drawing?f=&r=&a1=&c1= — a build's drawing as SVG; the shop station shows it on the work order. */
    public function drawing(Request $r)
    {
        $q = fn (string $k) => is_scalar($v = $r->query($k)) ? (string) $v : '';
        $svg = \App\Support\BuildDrawing::svg(['front_app' => $q('f'), 'rear_app' => $q('r'), 'pieces' => 'one',
            'a1' => min(400, max(0, (float) $q('a1'))), 'c1' => min(400, max(0, (float) $q('c1')))]);
        abort_unless($svg, 404);
        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:", 'Content-Disposition' => 'inline', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** POST /dsb/quote — live server-side price for the current selections. */
    public function quote(Request $r)
    {
        $res = $this->builds->quote($r->all());
        return response()->json($res, $res['success'] ? 200 : 422);
    }

    /** POST /dsb/submit — final submit: recompute price server-side, add to the cart, return reference (DsbBuilds). */
    public function submit(Request $r)
    {
        $res = $this->builds->addToCart($r->all(), (int) $r->input('quantity', 1));
        return response()->json($res, $res['success'] ? 200 : 422);
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

    /** POST /dsb/save-design — save the current config to the customer's account. */
    public function saveDesign(Request $r)
    {
        if (!\Auth::check()) return response()->json(['success' => false, 'login' => true, 'message' => __('Please sign in to save your design.')], 401);
        $cfg = $this->builds->config($r->all());
        if ($errors = $this->builds->check($cfg)) return response()->json(['success' => false, 'errors' => $errors, 'message' => implode(' ', $errors)], 422);
        $price = $this->pricing->compute($cfg);
        $cfg['tube_od'] = (string) ($price['breakdown']['tube_od'] ?? '');
        $ref = 'DSN-' . now()->format('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        \Illuminate\Support\Facades\DB::table('saved_designs')->insert([
            'user_id' => \Auth::id(), 'ref' => $ref,
            'title' => $this->builds->title($cfg),
            'config' => json_encode($cfg + ['summary' => $this->builds->summary($cfg)]),
            'price' => $price['final'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'ref' => $ref]);
    }
}
