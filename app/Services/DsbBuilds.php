<?php

namespace App\Services;

use App\Http\Controllers\DsbController;
use Illuminate\Support\Facades\Storage;

/**
 * One custom driveshaft from raw answers to a cart line: read the builder's fields, check every key against the
 * settings tree and the catalog, price it with DsbPricing and put it in the cart. The builder page (/dsb/quote,
 * /dsb/submit, /dsb/save-design) and UDS Assistant's guided build both go through here, so a build ordered in the
 * chat is the same line, at the same price, as one ordered on the page.
 */
class DsbBuilds
{
    public function __construct(private DsbCatalog $catalog, private DsbPricing $pricing)
    {
    }

    public function pricing(): DsbPricing
    {
        return $this->pricing;
    }

    /**
     * The builder's fields (the page's form post, or the assistant's state) as a config. Scalars only: an array
     * value (duty_key[]=x) reads as '' instead of a 500. Labels are never taken from the input (check() fills them).
     */
    public function config(array $data): array
    {
        $in = fn (string $k, string $d = '') => is_scalar($v = $data[$k] ?? null) ? trim((string) $v) : $d;
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
        if (!isset(DsbController::flow()['families'][$family])) $family = '';

        return [
            'pieces'             => $one('pieces', ['one'], 'one'),
            'build_mode'         => 'custom',
            'family'             => $family,
            'answers'            => $answers,
            'duty_key'           => $in('duty_key'),
            'duty_label'         => '', // labels are derived server-side in check(), never taken from the browser
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

    /** Live price for the selections so far: ['success' => true, 'data' => price + title + summary] or the errors. */
    public function quote(array $data): array
    {
        $cfg = $this->config($data);
        if ($errors = $this->check($cfg)) return ['success' => false, 'errors' => $errors];
        $price = $this->pricing->compute($cfg);
        return ['success' => true, 'data' => $price + ['title' => $this->title($cfg), 'summary' => $this->summary($cfg)]];
    }

    /**
     * Why the build cannot be ordered as it stands (empty = it can): the completeness gate, the length rules and
     * an online price. $cfg must have been through check(); $price is its compute().
     */
    public function orderErrors(array $cfg, array $price): array
    {
        $errors = [];
        foreach (['duty_key' => __('duty rating'), 'series_key' => __('series'), 'front_app' => __('front end'), 'rear_app' => __('rear end')] as $k => $label) {
            if (($cfg[$k] ?? '') === '') $errors[] = __('Please select the :what.', ['what' => $label]);
        }
        if ((float) ($cfg['a1'] ?? 0) <= 0) $errors[] = __('Please enter the length (A1).');
        // C1 (u-joint centre to centre) is required (owner, 2026-11-10), except where it equals A1: u-joint at both ends with
        // the slip joint inside the shaft, or a carrier section
        if (!self::c1Skip((string) ($cfg['front_app'] ?? ''), (string) ($cfg['rear_app'] ?? '')) && (float) ($cfg['a1'] ?? 0) > 0) {
            if ((float) ($cfg['c1'] ?? 0) <= 0) $errors[] = __('Please enter C1 (u-joint center to u-joint center).');
            elseif ((float) $cfg['c1'] > (float) $cfg['a1']) $errors[] = __('C1 cannot be longer than A1. Please check both measurements.');
        }
        foreach (['front_yoke' => __('front yoke'), 'rear_yoke' => __('rear yoke')] as $k => $label) {
            if (($cfg[$k]['kind'] ?? 'none') === 'new' && (int) ($cfg[$k]['product_id'] ?? 0) <= 0) $errors[] = __('Please pick the :what from the catalog.', ['what' => $label]);
        }
        if (in_array($cfg['hardware']['kind'] ?? 'none', ['strap', 'ubolt'], true) && (int) ($cfg['hardware']['product_id'] ?? 0) <= 0) $errors[] = __('Please pick the hardware kit.');
        if ($errors) return $errors;

        if (($price['breakdown']['error'] ?? '') === 'length_answer') return [__('The length answer does not match the measured length. Please check A1.')];
        if (($price['final'] ?? 0) <= 0) return [__('This build could not be priced online. Please call :phone for a quote.', ['phone' => DsbController::PHONE])];
        if (!empty($price['breakdown']['over_max'])) {
            // steel too long but aluminum reaches it: say so; otherwise the honest answer is a two-piece shaft
            return [($cfg['material'] ?? 'steel') === 'steel'
                ? __('A :len in shaft is longer than steel tube runs in this series (:max in max). Try aluminum, or call :phone for a two-piece shaft.', ['len' => self::inches((float) $cfg['a1']), 'max' => (string) $price['breakdown']['max_length'], 'phone' => DsbController::PHONE])
                : __('A :len in shaft is longer than one :material tube can run safely in this series (:max in max). Call :phone and we quote it as a two-piece shaft.', ['len' => self::inches((float) $cfg['a1']), 'max' => (string) $price['breakdown']['max_length'], 'material' => (string) $cfg['material'], 'phone' => DsbController::PHONE])];
        }
        return [];
    }

    /** C1 equals A1, so it is not asked: u-joint at both ends with the slip joint in the shaft, or a carrier section. */
    public static function c1Skip(string $front, string $rear): bool
    {
        return str_starts_with($rear, 'carrier') || ($front === 'slip_no_flange' && $rear === 'weld');
    }

    /**
     * Recompute the price server-side and add the build to the cart (identical configs merge into one line).
     * Returns ['success' => true, 'data' => …] or ['success' => false, 'errors' => […]].
     */
    public function addToCart(array $data, int $qty = 1, string $source = 'builder'): array
    {
        $cfg = $this->config($data);
        // every key must exist in the server's own tree/app lists — a tampered or mis-cased key is refused, never re-priced
        if ($errors = $this->check($cfg)) return ['success' => false, 'errors' => $errors];
        $price = $this->pricing->compute($cfg);
        if ($errors = $this->orderErrors($cfg, $price)) return ['success' => false, 'errors' => $errors];

        $qty = min(20, max(1, $qty));
        // supplier-priced build: all new components, and the options picked for it go on the order in our words
        if (($price['breakdown']['pricing'] ?? '') === 'reference') {
            $cfg['price_tier'] = 'new';
            $cfg['build_options'] = DsbController::buildOptionRows((array) ($price['breakdown']['options'] ?? []));
            // the variant price is the whole shaft with its own front end and u-joint hardware (build_options): a front yoke or
            // kit posted with it was never priced, so it must not reach the build sheet or the shop station
            $cfg['front_yoke'] = ['kind' => 'none', 'product_id' => 0] + array_intersect_key((array) $cfg['front_yoke'], ['spline' => 1, 'model' => 1]);
            $cfg['hardware'] = ['kind' => 'none', 'product_id' => 0];
            $famApp = (string) (DsbController::flow()['families'][$cfg['family']]['front_app'] ?? '');
            if ($famApp !== '' && isset($this->pricing->settings()['front_apps'][$famApp])) {
                $cfg['front_app'] = $famApp;
                $cfg['front_app_label'] = (string) ($this->pricing->settings()['front_apps'][$famApp]['label'] ?? '');
            }
        }
        $summary = $this->summary($cfg);
        $cfg['tube_od'] = (string) ($price['breakdown']['tube_od'] ?? '');
        $cfg['speed_check'] = $price['breakdown']['speed'] ?? null;
        $cfg['title'] = $this->title($cfg);
        $cart = app(CartService::class);
        $cart->add([
            'type' => 'custom_build', 'wp_product_id' => null,
            'name' => $cfg['title'], // server-side labels only (check)
            'image' => $this->buildPhoto($cfg),   // the real shaft photo the review shows, not the end diagram
            'price' => $price['final'], 'qty' => $qty,
            'meta' => ['config' => $cfg, 'price' => $price, 'summary' => $summary],
        ]);
        Storage::append('dsb_orders.jsonl', json_encode(['ts' => now()->toDateTimeString(), 'event' => 'added_to_cart', 'source' => $source, 'config' => $cfg, 'price' => $price], JSON_UNESCAPED_SLASHES));

        return ['success' => true, 'data' => [
            'price' => $price, 'summary' => $summary, 'title' => $cfg['title'], 'image' => $this->buildPhoto($cfg),
            'cart_count' => $cart->count(), 'cart_url' => '/cart',
        ]];
    }

    /** {our heading: our wording} for the supplier options of a build — see DsbController::buildOptionRows. */
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
     * Returns the errors; empty keys pass here (orderErrors() catches those).
     */
    public function check(array &$cfg): array
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
        $variants = (array) (DsbController::flow()['series_variants'] ?? []);
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
    public function summary(array $cfg): string
    {
        $fam = DsbController::flow()['families'][$cfg['family'] ?? ''] ?? null;
        $parts = array_filter([
            trim(($cfg['series_label'] ?? '') . ($cfg['series_variant'] ? ' (' . $cfg['series_variant'] . ')' : '')), ucfirst((string) ($cfg['material'] ?? '')),
            trim(($cfg['front_app_label'] ?? '') . ' → ' . ($cfg['rear_app_label'] ?? ''), ' →'),
            (float) ($cfg['a1'] ?? 0) > 0 ? self::inches((float) $cfg['a1']) . ' in' : '',
            $fam['label'] ?? '',
        ]);
        return implode(' · ', $parts);
    }

    /** The product-style name of a build: "Custom 1350 series steel driveshaft, slip yoke to flange yoke, 52.25 in". */
    public function title(array $cfg): string
    {
        $series = trim(preg_replace('/\s*series\s*$/i', '', (string) ($cfg['series_label'] ?? $cfg['series_key'] ?? '')));
        $front = mb_strtolower((string) ($cfg['front_app_label'] ?? '')); $rear = mb_strtolower((string) ($cfg['rear_app_label'] ?? ''));
        $t = 'Custom ' . ($series !== '' ? $series . ' series ' : '') . ($cfg['material'] ?? 'steel') . ' driveshaft';
        if ($front !== '' && $rear !== '') $t .= ', ' . $front . ' to ' . $rear;
        if ((float) ($cfg['a1'] ?? 0) > 0) $t .= ', ' . self::inches((float) $cfg['a1']) . ' in';
        return $t;
    }

    public static function inches(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /** Photo of a finished shaft with these ends (the one the builder's review shows): front__rear, the aluminum copy when
     *  the tube is aluminum, then the front end alone; the end photo only when no shaft photo exists. */
    public function buildPhoto(array $cfg): string
    {
        $photos = DsbController::flow()['photos'] ?? [];
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
}
