<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Http\Response;

/**
 * Content hub: /guides and the long-form driveshaft guides under it.
 * The registry below is the single source of truth for the hub cards, the
 * per-guide meta, related links and the sitemap (App\Seo\Sources\Guides).
 */
class GuideController extends Controller
{
    public const PUBLISHED = '2026-10-17';

    /** slug => meta. Order here is the order on the hub. */
    public static function registry(): array
    {
        return [
            'how-to-measure-a-driveshaft' => [
                'title' => 'How to Measure a Driveshaft (1 & 2 Piece) | UDS',
                'desc'  => 'How to measure a one- or two-piece driveshaft: where A, B, C, W and T are taken, slip yoke compressed vs installed, flange and yoke references, common errors.',
                'h1'    => 'How to measure a driveshaft, one-piece and two-piece',
                'lead'  => 'The six numbers a driveshaft shop needs, where each one is taken on the vehicle, and the mistakes that get a shaft built to the wrong length.',
                'kicker'=> 'Measuring',
                'icon'  => 'ruler',
                'card'  => 'Where A, B, C, W and T are taken, slip yoke rules, flange and yoke references.',
                'toc'   => [['refs','The references: A, B, C, W, T'],['tools','What you need'],['one-piece','Measuring a one-piece shaft'],['two-piece','Measuring a two-piece shaft'],['ends','Yokes, flanges and spline data'],['mistakes','Mistakes that cost a rebuild'],['send','Sending the numbers']],
                'related' => ['one-piece-vs-two-piece-driveshaft','slip-yoke-vs-weld-yoke-vs-flange-yoke','custom-driveshaft-cost-and-lead-time'],
                'parts' => ['cat' => 'slip-yoke', 'q' => '1310', 'label' => 'Slip yokes we stock', 'href' => '/shop/driveshaft-components/slip-yoke'],
            ],
            'u-joint-series-identification-chart' => [
                'title' => 'U-Joint Series Identification Chart & Sizes | UDS',
                'desc'  => 'U-joint series chart with cap diameter and width for 1310, 1330, 1350, 1410, 1480, 1550, 7260, 7290, 3R and S44, how to measure a cap, greaseable vs sealed.',
                'h1'    => 'U-joint series identification chart: measure a cap, name the series',
                'lead'  => 'Two measurements identify almost every driveshaft u-joint sold in North America. Here is the chart, how to take the measurements, and which joint to buy.',
                'kicker'=> 'Identification',
                'icon'  => 'crosshair',
                'card'  => 'Cap diameter and width for 1310 through 1550, 7260/7290, 3R and S44, with the measuring method.',
                'toc'   => [['chart','The series chart'],['measure','How to measure a cap'],['snap-rings','Inside vs outside snap rings'],['grease','Greaseable vs non-greaseable'],['conversion','Conversion joints'],['pick','Picking the right joint']],
                'related' => ['slip-yoke-vs-weld-yoke-vs-flange-yoke','driveshaft-vibration-and-noise-diagnosis','replace-or-repair-a-driveshaft'],
                'parts' => ['cat' => 'u-joints', 'q' => '1310', 'label' => '1310 series u-joints in stock', 'href' => '/shop/driveshaft-components/u-joints'],
            ],
            'driveshaft-vibration-and-noise-diagnosis' => [
                'title' => 'Driveshaft Vibration & Noise Diagnosis Guide | UDS',
                'desc'  => 'Diagnose driveshaft vibration and noise by symptom: speed vs load, clunks, squeaks and rumbles traced to u-joints, balance, phasing, angles or center bearing.',
                'h1'    => 'Driveshaft vibration and noise: a diagnosis guide that starts with the symptom',
                'lead'  => 'Vibration that tracks road speed, a clunk in gear, a squeak at walking pace. Each points at a different part of the driveline. Work through them in order.',
                'kicker'=> 'Diagnosis',
                'icon'  => 'gauge',
                'card'  => 'Speed vs load vibration, clunks, squeaks and rumbles traced to the part that causes them.',
                'toc'   => [['first','First: is it the driveshaft?'],['symptoms','Symptom table'],['ujoints','Worn u-joints'],['balance','Balance and lost weights'],['phasing','Phasing'],['angles','Operating angles'],['csb','Center support bearing'],['inspect','Ten-minute inspection']],
                'related' => ['driveshaft-angles-and-pinion-angle','center-support-bearing-replacement','replace-or-repair-a-driveshaft'],
                'parts' => ['cat' => 'u-joints', 'q' => '1350', 'label' => '1350 series u-joints', 'href' => '/shop/driveshaft-components/u-joints'],
            ],
            'slip-yoke-vs-weld-yoke-vs-flange-yoke' => [
                'title' => 'Slip Yoke vs Weld Yoke vs Flange Yoke | UDS Guide',
                'desc'  => 'Slip, weld, flange and end yokes and companion flanges: what each does, how it is specified (spline, tube fit, bolt circle, pilot) and which one you need.',
                'h1'    => 'Slip yoke vs weld yoke vs flange yoke: which one you need',
                'lead'  => 'Every driveshaft is a tube with a yoke at each end. The yoke type is set by what it bolts or slides onto; the size is set by the u-joint series. Here is how to read both.',
                'kicker'=> 'Components',
                'icon'  => 'wrench',
                'card'  => 'Spline, tube fit, bolt circle and pilot: how each yoke is specified and where it goes.',
                'toc'   => [['map','Where each yoke lives'],['slip','Slip yokes'],['weld','Weld yokes'],['flange','Flange yokes and companion flanges'],['end','End yokes and transmission yokes'],['table','Specification table'],['choose','Choosing for a build']],
                'related' => ['u-joint-series-identification-chart','how-to-measure-a-driveshaft','choosing-driveshaft-tubing'],
                'parts' => ['cat' => 'flange-yokes', 'q' => '1350', 'label' => '1350 series flange yokes', 'href' => '/shop/driveshaft-components/flange-yokes'],
            ],
            'center-support-bearing-replacement' => [
                'title' => 'Center Support Bearing Replacement Guide | UDS',
                'desc'  => 'Diagnose and replace a driveshaft center support bearing: symptoms, picking the right bearing by shaft diameter and bracket, step-by-step install and shimming.',
                'h1'    => 'Center support bearing replacement: symptoms, part selection and the job',
                'lead'  => 'The carrier bearing on a two-piece driveshaft is a wear item. Here is how to confirm it has failed, how to order the right one, and how to install it without creating a new vibration.',
                'kicker'=> 'Repair',
                'icon'  => 'returns',
                'card'  => 'Symptoms, choosing the bearing by shaft diameter and bracket, and a step-by-step install.',
                'toc'   => [['what','What the bearing does'],['symptoms','Symptoms of a failed bearing'],['identify','Identifying the right bearing'],['steps','Replacement, step by step'],['shim','Shimming and alignment'],['after','After the job']],
                'related' => ['one-piece-vs-two-piece-driveshaft','driveshaft-vibration-and-noise-diagnosis','how-to-measure-a-driveshaft'],
                'parts' => ['cat' => 'center-support-bearing', 'q' => '', 'label' => 'Center support bearings in stock', 'href' => '/shop/driveshaft-components/center-support-bearing'],
            ],
            'choosing-driveshaft-tubing' => [
                'title' => 'Driveshaft Tubing: DOM Steel vs Aluminum, Size | UDS',
                'desc'  => 'Choose driveshaft tubing: DOM steel vs aluminum, diameter and wall, critical speed vs length and RPM with a worked example, and matching tube to weld yokes.',
                'h1'    => 'Choosing driveshaft tubing: steel vs aluminum, diameter, wall and critical speed',
                'lead'  => 'Tube choice decides how fast and how long a driveshaft can run before it whips. The math is short, and it tells you whether you need a bigger tube, aluminum or a two-piece shaft.',
                'kicker'=> 'Engineering',
                'icon'  => 'factory',
                'card'  => 'DOM vs aluminum, diameter and wall, and the critical speed formula with a worked example.',
                'toc'   => [['materials','DOM steel vs aluminum'],['critical','Critical speed: the formula'],['example','A worked example'],['sizes','Common sizes and what they fit'],['yokes','Matching tube to weld yokes'],['cut','Ordering cut tubing']],
                'related' => ['one-piece-vs-two-piece-driveshaft','slip-yoke-vs-weld-yoke-vs-flange-yoke','custom-driveshaft-cost-and-lead-time'],
                'parts' => ['cat' => 'tubing', 'q' => '', 'label' => 'DOM tubing, sold by the foot', 'href' => '/shop/driveshaft-tubing/tubing'],
            ],
            'custom-driveshaft-cost-and-lead-time' => [
                'title' => 'Custom Driveshaft Cost & Lead Time (2026) | UDS',
                'desc'  => 'What a custom driveshaft costs and how long it takes: the five price drivers, what is included, real catalog component prices and how to get a live price.',
                'h1'    => 'Custom driveshaft cost and lead time: what moves the price',
                'lead'  => 'A built-to-spec driveshaft is priced from a short list of choices. Here is what each one adds, what is included in the number, and how long it takes to get the shaft.',
                'kicker'=> 'Buying',
                'icon'  => 'certificate',
                'card'  => 'Price drivers, what is included, component prices from the catalog and real lead times.',
                'toc'   => [['drivers','The five price drivers'],['included','What is included'],['components','Component prices, from the catalog'],['examples','Three example builds'],['lead','Lead time'],['premade','When premade is cheaper']],
                'related' => ['how-to-measure-a-driveshaft','choosing-driveshaft-tubing','one-piece-vs-two-piece-driveshaft'],
                'parts' => ['cat' => 'premade-driveshafts', 'q' => '', 'label' => 'Premade driveshafts, ready to ship', 'href' => '/shop/complete-driveshafts'],
            ],
            'one-piece-vs-two-piece-driveshaft' => [
                'title' => 'One-Piece vs Two-Piece Driveshaft Explained | UDS',
                'desc'  => 'One-piece vs two-piece driveshafts: how length, RPM and tube size decide, what a two-piece adds, converting between them and which one your truck needs.',
                'h1'    => 'One-piece vs two-piece driveshaft: which one your vehicle needs',
                'lead'  => 'Length and speed decide this, not preference. Below the critical-speed limit a one-piece is simpler and cheaper; above it you need a bigger tube, aluminum or a two-piece with a center bearing.',
                'kicker'=> 'Design',
                'icon'  => 'sliders',
                'card'  => 'Length, RPM and tube size decide; what a two-piece adds; converting between the two.',
                'toc'   => [['difference','The difference'],['decides','What decides it: length and RPM'],['two-piece','What a two-piece adds'],['convert','Converting two-piece to one-piece'],['compare','Side by side'],['order','Specifying either one']],
                'related' => ['choosing-driveshaft-tubing','center-support-bearing-replacement','how-to-measure-a-driveshaft'],
                'parts' => ['cat' => 'stub-midship-shaft', 'q' => '', 'label' => 'Stub and midship shafts', 'href' => '/shop/driveshaft-components/stub-midship-shaft'],
            ],
            'driveshaft-angles-and-pinion-angle' => [
                'title' => 'Driveshaft Angles & Pinion Angle Setup Guide | UDS',
                'desc'  => 'Set driveshaft operating angles and pinion angle: measuring with an angle finder, the equal-and-opposite rule, angle limits by RPM, leaf vs link suspensions.',
                'h1'    => 'Driveshaft angles and pinion angle: measuring and setting them right',
                'lead'  => 'A u-joint speeds up and slows down twice per revolution whenever it runs at an angle. Two joints set equal and opposite cancel that out. Here is how to measure, what the limits are and how to correct it.',
                'kicker'=> 'Setup',
                'icon'  => 'gauge',
                'card'  => 'Measuring with an angle finder, equal-and-opposite, limits by RPM, leaf vs link setups.',
                'toc'   => [['why','Why angles matter'],['measure','Measuring the three angles'],['rule','The equal-and-opposite rule'],['limits','Angle limits by RPM'],['pinion','Setting pinion angle'],['cv','When a CV or double cardan is the answer']],
                'related' => ['driveshaft-vibration-and-noise-diagnosis','one-piece-vs-two-piece-driveshaft','u-joint-series-identification-chart'],
                'parts' => ['cat' => 'cv-double-joints-heads', 'q' => '', 'label' => 'CV double cardan heads', 'href' => '/shop/driveshaft-components/cv-double-joints-heads'],
            ],
            'replace-or-repair-a-driveshaft' => [
                'title' => 'Driveshaft Repair or Replace? How to Decide | UDS',
                'desc'  => 'When to repair a driveshaft (u-joints, center bearing, slip yoke, balance) and when to replace it (dented tube, cracked yoke, runout). Inspection checklist.',
                'h1'    => 'Repair or replace a driveshaft? An inspection-first way to decide',
                'lead'  => 'Most driveshaft faults are a wear part on a good tube, and those are repairs. A few are the tube itself, and those are replacements. Ten minutes with a flashlight tells you which.',
                'kicker'=> 'Decision',
                'icon'  => 'check',
                'card'  => 'Inspection checklist, what is repairable, what is not, and a cost comparison.',
                'toc'   => [['inspect','The inspection'],['repair','Faults that are repairs'],['replace','Faults that mean replacement'],['cost','Cost comparison'],['upgrade','When to upgrade instead'],['next','Next step']],
                'related' => ['driveshaft-vibration-and-noise-diagnosis','custom-driveshaft-cost-and-lead-time','u-joint-series-identification-chart'],
                'parts' => ['cat' => 'u-joints', 'q' => '1330', 'label' => '1330 series u-joints', 'href' => '/shop/driveshaft-components/u-joints'],
            ],
        ];
    }

    public function index()
    {
        return response()->view('guides.index', ['guides' => self::registry()])
            ->header('Cache-Control', 'public, max-age=600');
    }

    public function show(string $slug)
    {
        $all = self::registry();
        abort_unless(isset($all[$slug]), 404);
        $g = $all[$slug] + ['slug' => $slug];

        $related = [];
        foreach ($g['related'] as $rs) {
            if (isset($all[$rs])) $related[$rs] = $all[$rs];
        }

        // Live catalog strip. Never let a catalog hiccup take the article down.
        $parts = [];
        if (!empty($g['parts']['cat'])) {
            try {
                $svc = app(CatalogService::class);
                $cat = $g['parts']['cat'];
                $q   = $g['parts']['q'] ?? '';
                // Exact series (pa_series taxonomy) first, then pad with a text match on the series number.
                if ($q !== '') {
                    $parts = $svc->products($cat, ['pa_series' => $q], 1, 4, 'price_asc')['items'] ?? [];
                }
                if (count($parts) < 4) {
                    $seen = array_column($parts, 'id');
                    foreach ($svc->products($cat, [], 1, 8, 'price_asc', $q)['items'] ?? [] as $p) {
                        if (!in_array($p['id'], $seen, true)) { $parts[] = $p; }
                        if (count($parts) >= 4) break;
                    }
                }
                $parts = array_values(array_filter($parts, fn ($p) => $p['price'] > 0));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->view('guides.' . $slug, ['g' => $g, 'related' => $related, 'parts' => array_slice($parts, 0, 4)])
            ->header('Cache-Control', 'public, max-age=600');
    }
}
