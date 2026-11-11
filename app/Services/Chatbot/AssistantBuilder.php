<?php

namespace App\Services\Chatbot;

use App\Http\Controllers\DsbController;
use App\Services\AmPricing;
use App\Services\DsbBuilds;
use App\Services\DsbCatalog;
use App\Services\DsbPricing;
use Illuminate\Support\Facades\Cache;

/**
 * UDS Assistant's guided build (owner, 2026-11-11: "que lo guíe y el bot se encargue del proceso"): the online
 * builder's questions asked one at a time in the chat, in the builder's own order — the question tree
 * (storage/app/builder-tree.json), then series, material, ends, yokes, hardware, measurements, speed check, supplier
 * options and the review — with the same options at every point, the same rules, and prices and the cart line from
 * DsbBuilds, the code the builder page itself uses. The model never sends a price or a key the server did not offer:
 * it picks an option id from the last result, or types measurements, and everything is checked here again.
 *
 * The state is the builder's own state (public/js/dsb.js), kept per conversation, and doubles as the builder link
 * (the same dsb_* query the page restores), so the customer can open the build on the page at any point.
 */
class AssistantBuilder
{
    private const TTL_DAYS = 14;
    private const MAX_OPTIONS = 12;
    private const STEP_NAMES = ['series' => 'U-joint series', 'material' => 'Tube material', 'front' => 'Front end', 'rear' => 'Rear end', 'rear_yoke' => 'Rear yoke',
        'rear_pick' => 'Pick the yoke', 'hardware' => 'Rear hardware', 'measure' => 'Measurements', 'speed' => 'Speed check', 'options' => 'Build options',
        'front_yoke' => 'Front yoke', 'front_pick' => 'Pick the yoke', 'review' => 'Your driveshaft'];
    private const PHASES = [['Application', ['tree']], ['Shaft', ['series', 'material']], ['Ends', ['front', 'rear', 'rear_yoke', 'rear_pick', 'hardware']],
        ['Length', ['measure', 'speed']], ['Your price', ['options', 'front_yoke', 'front_pick', 'review']]];
    /** dsb.js planFor(): which drawing dimension each length field is, per end pair (the rest: A1 = A1, C1 = C1). */
    private const DIMMAP = ['weld__weld' => ['a1' => 'C1', 'c1' => 'W1'], 'slip_no_flange__weld' => ['a1' => 'C1', 'c1' => 'C1']];
    private const DIMDESC = [
        'A1' => 'Overall length, from the flange face or the end of the slip yoke to the far end', 'C1' => 'U-joint center to U-joint center',
        'W1' => 'Weld to weld (the length of the tube)',
    ];

    private array $s = [];
    private ?array $quoteCache = null;
    private string $key = '';

    public function __construct(private DsbBuilds $builds, private DsbCatalog $catalog, private AmPricing $am)
    {
    }

    /* ---------------------------------------------------------------- state */

    public static function blank(): array
    {
        return [
            'step' => 'tree', 'node' => 'start', 'answers' => [], 'family' => null, 'duty' => null, 'subcat' => null,
            'series' => null, 'variant' => '', 'material' => null, 'front' => null, 'rear' => null,
            'fy' => ['kind' => '', 'pid' => 0, 'model' => ''], 'ry' => ['kind' => '', 'pid' => 0], 'hw' => ['kind' => '', 'pid' => 0],
            'a1' => 0.0, 'c1' => 0.0, 'tier' => 'certified', 'notes' => '', 'speed' => ['mph' => 0.0, 'ratio' => 0.0, 'tire' => 0.0],
            'am' => [], 'speedSeen' => false, 'optionsSeen' => false, 'page' => 1, 'reviewMsg' => null, 'carted' => null, 'started' => false, 'asked' => [],
        ];
    }

    /** The guided build of one conversation (a fresh one when none is going or it expired). */
    public function load(int $conversationId): self
    {
        $this->key = 'assistant:build:' . $conversationId;
        $this->s = array_replace(self::blank(), (array) Cache::get($this->key, []));
        $this->quoteCache = null;
        return $this;
    }

    public function save(): void
    {
        if ($this->key !== '') Cache::put($this->key, $this->s, now()->addDays(self::TTL_DAYS));
    }

    public function state(): array
    {
        return $this->s;
    }

    /* ---------------------------------------------------------------- the tool */

    /**
     * One driveshaft_builder call. $in: action (start|show|answer|back|go_to|measure|speed|add_to_cart), choice,
     * a1, c1, mph, axle_ratio, tire_in, text, quantity. $customerMsg = id of the newest message the customer typed
     * (adding to the cart needs a message after the one the review was shown for).
     */
    public function run(array $in, int $customerMsg = 0): array
    {
        $action = (string) ($in['action'] ?? 'show');
        $choice = trim((string) ($in['choice'] ?? ''));
        $text = trim(strip_tags((string) ($in['text'] ?? '')));
        $error = null;

        if ($action === 'start' || (!$this->s['started'] && $action !== 'show')) {
            $this->s = self::blank();
            $this->s['started'] = true;
            if ($action === 'start') { $this->save(); return $this->view(null, 'New build started.', true); }
        }
        if (!$this->s['started']) { $this->s['started'] = true; }

        switch ($action) {
            case 'show':
                break;
            case 'answer':
                $error = $choice === '' && $text === '' ? 'Give the option id (choice) from the options list.' : $this->answer($choice, $text);
                break;
            case 'back':
                $this->back();
                break;
            case 'go_to':
                $error = $this->goTo($choice);
                break;
            case 'measure':
                if ($this->s['step'] !== 'measure' && !in_array('measure', $this->fixedSteps(), true)) { $error = 'Measurements come after the ends are chosen. Answer the current question first.'; break; }
                $error = $this->measure((float) ($in['a1'] ?? 0), (float) ($in['c1'] ?? 0));
                break;
            case 'speed':
                $error = $this->speed((float) ($in['mph'] ?? 0), (float) ($in['axle_ratio'] ?? 0), (float) ($in['tire_in'] ?? 0));
                break;
            case 'add_to_cart':
                $res = $this->addToCart(max(1, (int) ($in['quantity'] ?? 1)), $text, $customerMsg);
                $this->save();
                return $res;
            default:
                $error = 'Unknown action.';
        }
        $view = $this->view($error, null, in_array($action, ['show', 'back', 'go_to'], true));
        // the review the customer is shown now: adding it to the cart needs their answer to it, in a later message
        if (!empty($view['ready']) && $this->s['reviewMsg'] === null) $this->s['reviewMsg'] = $customerMsg;
        $this->save();
        return $view;
    }

    /* ---------------------------------------------------------------- the builder's logic (dsb.js, server side) */

    private function flow(): array
    {
        return DsbController::flow();
    }

    private function settings(): array
    {
        return $this->builds->pricing()->settings();
    }

    private function treeNode(?string $id): ?array
    {
        if ($id === 'heavy') {
            $subs = (array) ($this->settings()['tree']['heavy']['subcategories'] ?? []);
            $n = $this->flow()['nodes']['heavy'] ?? [];
            return ['q' => $n['q'] ?? 'Heavy-duty family', 'short' => $n['short'] ?? 'Heavy-duty family', 'kind' => 'tiles',
                'options' => array_values(array_map(fn ($k, $sc) => ['label' => $sc['label'] ?? $k, 'note' => $sc['hint'] ?? '', 'leaf' => 'HD:' . $k], array_keys($subs), $subs))];
        }
        return $id !== null ? ($this->flow()['nodes'][$id] ?? null) : null;
    }

    /** The family a leaf resolves to: a tree family or a heavy-duty subcategory ("HD:hd_spl"). */
    private function fam(): ?array
    {
        $id = $this->s['family'];
        if (!$id) return null;
        if (isset($this->flow()['families'][$id])) return $this->flow()['families'][$id];
        if (str_starts_with($id, 'HD:')) {
            $sc = $this->settings()['tree']['heavy']['subcategories'][substr($id, 3)] ?? null;
            return $sc ? ['label' => $sc['label'] ?? '', 'series' => array_keys((array) ($sc['series'] ?? [])), 'front_app' => null, 'yoke_choice' => true, 'hd' => true, 'subcat' => substr($id, 3), 'models' => [], 'note' => ''] : null;
        }
        return null;
    }

    private function seriesMap(): array
    {
        $f = $this->fam();
        if (!$f) return [];
        return !empty($f['hd']) ? (array) ($this->settings()['tree']['heavy']['subcategories'][$f['subcat']]['series'] ?? []) : (array) ($this->settings()['tree']['light_medium']['series'] ?? []);
    }

    private function seriesLabel(): string
    {
        return (string) ($this->seriesMap()[$this->s['series']]['label'] ?? $this->s['series'] ?? '');
    }

    private function aluEligible(): bool
    {
        return $this->s['duty'] === 'light_medium' && $this->s['series'] !== null && $this->builds->pricing()->aluminumEligible('light_medium', (string) $this->s['series']);
    }

    private function usedPrice(?string $app): float
    {
        return $app && $this->s['series'] ? $this->builds->pricing()->usedPrice($app, (string) $this->s['series']) : 0.0;
    }

    private function frontYokeAsk(): bool
    {
        $f = $this->fam();
        return (bool) ($f && ($f['yoke_choice'] ?? true) !== false && DsbPricing::yokeTypeFor((string) $this->s['front']));
    }

    private function isReference(): bool
    {
        if (!$this->s['family'] || !$this->s['series']) return false;
        $cover = $this->am->coverage();
        return in_array($this->s['material'] ?: 'steel', (array) ($cover[$this->s['family']][strtolower((string) $this->s['series'])] ?? []), true);
    }

    /** The structured facts the answers carried (make, model, spline…), over the family's own. */
    private function facts(): array
    {
        $f = $this->fam() ?? [];
        $o = ['spline' => (string) ($f['spline'] ?? ''), 'barrel' => (string) ($f['barrel'] ?? ''), 'make' => (string) ($f['make'] ?? '')];
        foreach ($this->s['answers'] as $a) if (!empty($a['set']) && is_array($a['set'])) $o = array_merge($o, $a['set']);
        return $o;
    }

    /** What the builder page posts (dsb.js payload()), from this state. */
    public function payload(): array
    {
        $fx = $this->facts();
        $s = $this->s;
        return [
            'pieces' => 'one', 'family' => $s['family'] ?? '', 'answers' => json_encode(array_map(fn ($a) => ['q' => $a['q'], 'a' => $a['a']], $s['answers'])),
            'duty_key' => $s['duty'] ?? '', 'subcat_key' => $s['subcat'] ?? '', 'series_key' => $s['series'] ?? '', 'series_variant' => $s['variant'] ?: '',
            'material' => $s['material'] ?: 'steel', 'price_tier' => $s['material'] === 'aluminum' ? 'new' : $s['tier'],
            'front_app' => $s['front'] ?? '', 'rear_app' => $s['rear'] ?? '', 'length' => '', 'a1' => $s['a1'] ?: 0, 'c1' => $s['c1'] ?: 0, 'b1' => 0,
            'front_yoke_kind' => $s['fy']['kind'] ?: 'none', 'front_yoke_product_id' => (int) $s['fy']['pid'],
            'front_spline' => (string) ($fx['spline'] ?? ''), 'front_trans_model' => $s['fy']['model'] ?: (string) ($fx['model'] ?? ''),
            'rear_yoke_kind' => $s['ry']['kind'] ?: 'none', 'rear_yoke_product_id' => (int) $s['ry']['pid'],
            'hardware_kind' => $s['hw']['kind'] ?: 'none', 'hardware_product_id' => (int) $s['hw']['pid'],
            'am_options' => json_encode((object) $s['am']), 'notes' => $s['notes'],
            'speed_mph' => $s['speed']['mph'] ?: '', 'axle_ratio' => $s['speed']['ratio'] ?: '', 'tire_in' => $s['speed']['tire'] ?: '',
        ];
    }

    /** DsbBuilds::quote() for this state (cached within one call). */
    private function quote(): ?array
    {
        if (!$this->s['series']) return null;
        if ($this->quoteCache === null) {
            $r = $this->builds->quote($this->payload());
            $this->quoteCache = $r['success'] ? $r['data'] : ['final' => 0, 'breakdown' => [], 'errors' => $r['errors'] ?? []];
        }
        return $this->quoteCache;
    }

    private function changed(): void
    {
        $this->quoteCache = null;
        $this->s['reviewMsg'] = null;
        $this->s['carted'] = null;
    }

    private function refProduct(): ?array
    {
        $q = $this->quote();
        return $q && ($q['breakdown']['pricing'] ?? '') === 'reference' ? $q['breakdown'] : null;
    }

    private static function isLenOpt(array $o): bool
    {
        return (bool) preg_match('/\b88\b|or over|length/i', (string) ($o['name'] ?? ''));
    }

    /** A supplier option is a question only when it offers a choice; the long-shaft option is answered from the length. */
    private function realOptions(): array
    {
        $bd = $this->refProduct();
        return array_values(array_filter((array) ($bd['product']['options'] ?? []), fn ($o) => count((array) ($o['values'] ?? [])) > 1 && !self::isLenOpt($o)));
    }

    public function fixedSteps(): array
    {
        $f = $this->fam();
        if (!$f) return [];
        $st = ['series'];
        if ($this->aluEligible()) $st[] = 'material';
        if (empty($f['front_app'])) $st[] = 'front';
        $st[] = 'rear';
        if (DsbPricing::yokeTypeFor((string) $this->s['rear'])) { $st[] = 'rear_yoke'; if ($this->s['ry']['kind'] === 'new') $st[] = 'rear_pick'; }
        $ref = $this->isReference();
        if ($this->s['rear'] === 'weld' && !$ref) $st[] = 'hardware';
        $st[] = 'measure'; $st[] = 'speed';
        if ($ref) {
            $rp = $this->refProduct();
            if (!($rp && !empty($rp['product']) && !$this->realOptions())) $st[] = 'options';   // supplier builds never ask the front yoke
        } elseif ($this->frontYokeAsk()) {
            $st[] = 'front_yoke'; if ($this->s['fy']['kind'] === 'new') $st[] = 'front_pick';
        }
        $st[] = 'review';
        return $st;
    }

    private function measureError(): string
    {
        $a1 = (float) $this->s['a1']; $c1 = (float) $this->s['c1'];
        if (!($a1 > 0)) return 'Please enter the length (A1).';
        if (DsbBuilds::c1Skip((string) $this->s['front'], (string) $this->s['rear'])) return '';
        if (!($c1 > 0)) return 'Please enter C1 (u-joint center to u-joint center).';
        if ($c1 > $a1) return 'C1 cannot be longer than A1. Please check both measurements.';
        return '';
    }

    public function firstIncomplete(): string
    {
        $s = $this->s;
        foreach ($this->fixedSteps() as $st) {
            if ($st === 'series' && !$s['series']) return $st;
            if ($st === 'material' && !$s['material']) return $st;
            if ($st === 'front' && !$s['front']) return $st;
            if ($st === 'rear' && !$s['rear']) return $st;
            if ($st === 'rear_yoke' && !$s['ry']['kind']) return $st;
            if ($st === 'rear_pick' && !$s['ry']['pid']) return $st;
            if ($st === 'hardware' && (!$s['hw']['kind'] || ($s['hw']['kind'] !== 'own' && !$s['hw']['pid']))) return $st;
            if ($st === 'measure' && ($this->measureError() !== '' || $this->lengthProblem() !== null)) return $st;
            if ($st === 'speed' && !$s['speedSeen'] && !$s['speed']['mph']) return $st;
            if ($st === 'options' && !$s['optionsSeen'] && $this->nextOptionGroup() !== null) return $st;
            if ($st === 'options' && !$s['optionsSeen'] && !$this->refProduct()) return $st;
            if ($st === 'front_yoke' && !$s['fy']['kind']) return $st;
            if ($st === 'front_pick' && !$s['fy']['pid']) return $st;
        }
        return 'review';
    }

    /**
     * The step to ask next: the first one that is incomplete or that the customer has not answered themselves.
     * A pre-filled answer (the material a family suggests) is still asked, as the builder page shows that step with
     * the suggestion selected; the optional steps (speed check, supplier options) count once passed.
     */
    public function nextOpen(): string
    {
        $open = $this->firstIncomplete();
        foreach ($this->fixedSteps() as $st) {
            if ($st === $open || $st === 'review') return $st;
            if (!in_array($st, ['speed', 'options'], true) && empty($this->s['asked'][$st])) return $st;
        }
        return 'review';
    }

    /** Longest shaft for the chosen series and material: the supplier product's limit (with its long-shaft band) on a
     *  supplier-priced build, else our tables' (dsb.js checkLength). */
    private function maxLen(?string $material = null): ?float
    {
        $mat = $material ?? ($this->s['material'] ?: 'steel');
        if ($material === null && ($bd = $this->refProduct()) && !empty($bd['product'])) {
            $max = !empty($bd['product']['max_len']) ? (float) $bd['product']['max_len'] : null;
            $m = AmPricing::maxLength($bd['product']);
            return $m !== null ? max((float) $max, $m) : $max;
        }
        return $this->builds->pricing()->maxLength((string) $this->s['duty'], (string) $this->s['series'], $mat);
    }

    private function lengthProblem(): ?string
    {
        $a1 = (float) $this->s['a1'];
        if ($a1 <= 0 || !$this->s['series']) return null;
        $alu = $this->s['material'] === 'aluminum';
        $lenFor = fn (string $mat) => $this->maxLen($mat);
        $max = $this->maxLen();
        $series = trim(preg_replace('/\s*series\s*$/i', '', $this->seriesLabel()));
        if ($max && $a1 > $max) {
            $aluMax = $this->aluEligible() ? $lenFor('aluminum') : null;
            if (!$alu && $aluMax && $a1 <= $aluMax) {
                return "Steel tube in the {$series} series goes to {$max} in. Aluminum reaches {$aluMax} in: switch the material to aluminum (go_to material), or call us for a two-piece shaft.";
            }
            return DsbBuilds::inches($a1) . ' in is longer than one ' . ($alu ? 'aluminum' : 'steel') . " tube can run safely in the {$series} series ({$max} in max). This builder makes one-piece shafts: the customer should call (813) 524-6491 for a two-piece shaft with a center support bearing.";
        }
        $q = $this->quote();
        if ($q && ($q['breakdown']['error'] ?? '') === 'over_max' && $this->isReference()) return 'No tube we list covers this length in this series and material. Try the other material or a larger series, or call us for a two-piece shaft.';
        return null;
    }

    /* ---------------------------------------------------------------- answering */

    private function answer(string $choice, string $text): ?string
    {
        $step = $this->s['step'];
        if ($step === 'tree') return $this->answerTree($choice);
        if (in_array($step, ['measure', 'speed'], true)) return $step === 'measure' ? 'This step takes numbers: call action "measure" with a1 and c1.' : 'This step takes numbers: call action "speed" with mph, axle_ratio and tire_in, or "speed" with all three at 0 to skip it.';
        $opts = $this->stepOptions($step);
        $o = self::pick($opts, $choice);
        if (!$o) return 'That is not one of the options for this question. Use an id from the options list.';
        $id = $o['id'];
        $s = &$this->s;
        switch ($step) {
            case 'series':
                [$key, $variant] = array_pad(explode('|', $id, 2), 2, '');
                $changedSeries = $s['series'] !== $key;
                $s['series'] = $key; $s['variant'] = $variant;
                if ($changedSeries) {
                    if ($s['fy']['kind'] === 'new') $s['fy'] = ['kind' => '', 'pid' => 0, 'model' => $s['fy']['model']];
                    if ($s['ry']['kind'] === 'new') $s['ry'] = ['kind' => '', 'pid' => 0];
                    if (in_array($s['hw']['kind'], ['strap', 'ubolt'], true)) $s['hw'] = ['kind' => '', 'pid' => 0];
                    $s['am'] = []; $s['optionsSeen'] = false;
                }
                $sug = (array) (($this->fam() ?? [])['suggest'] ?? []);
                if (!$this->aluEligible()) $s['material'] = 'steel';
                elseif (!$s['material'] && !empty($sug['material'])) $s['material'] = $sug['material'];
                break;
            case 'material':
                $s['material'] = $id;
                if ($id === 'aluminum') $s['tier'] = 'new';
                if ($id === 'steel' && in_array($s['rear'], ['slip_flange', 'slip_no_flange'], true)) $s['rear'] = null;
                $s['am'] = []; $s['optionsSeen'] = false;
                break;
            case 'front':
                if ($s['front'] !== $id) { $s['front'] = $id; $s['fy'] = ['kind' => '', 'pid' => 0, 'model' => '']; $s['rear'] = null; }
                break;
            case 'rear':
                if ($s['rear'] !== $id) { $s['rear'] = $id; $s['ry'] = ['kind' => '', 'pid' => 0]; $s['hw'] = ['kind' => '', 'pid' => 0]; }
                break;
            case 'rear_yoke':
            case 'front_yoke':
                $k = $step === 'front_yoke' ? 'fy' : 'ry';
                $s[$k]['kind'] = $id; if ($id !== 'new') $s[$k]['pid'] = 0;
                if ($k === 'fy' && $text !== '') $s['fy']['model'] = mb_substr($text, 0, 60);
                $s['page'] = 1;
                break;
            case 'rear_pick':
            case 'front_pick':
                $k = $step === 'front_pick' ? 'fy' : 'ry';
                if ($id === 'more') { $s['page']++; $this->changed(); return null; }
                if ($id === 'own') { $s[$k] = ['kind' => 'own', 'pid' => 0] + ($k === 'fy' ? ['model' => $s['fy']['model']] : []); break; }
                $s[$k]['pid'] = (int) substr($id, 1);
                break;
            case 'hardware':
                if (in_array($id, ['strap', 'ubolt', 'own'], true)) {
                    $s['hw'] = ['kind' => $id, 'pid' => 0];
                    if ($id !== 'own') { $this->changed(); return null; }   // the kit list comes next
                } else {
                    $s['hw']['pid'] = (int) substr($id, 1);
                }
                break;
            case 'options':
                $g = $this->nextOptionGroup();
                if ($g) $s['am'][$g['name']] = $o['value'];
                if ($this->nextOptionGroupAfter($s['am']) === null) $s['optionsSeen'] = true;
                $this->changed();
                if (!$s['optionsSeen']) return null;
                break;
            case 'review':
                if (in_array($id, ['certified', 'new'], true)) $s['tier'] = $id;
                $this->changed();
                return null;
        }
        $this->s['asked'][$step] = true;
        if ($step === 'rear_pick' || $step === 'front_pick') $this->s['asked'][$step === 'rear_pick' ? 'rear_yoke' : 'front_yoke'] = true;
        $this->changed();
        $this->s['step'] = $this->nextOpen();
        return null;
    }

    private function answerTree(string $choice): ?string
    {
        $node = $this->treeNode($this->s['node']);
        if (!$node) { $this->s['node'] = 'start'; return 'The question changed; here it is again.'; }
        $opts = [];
        foreach ($node['options'] as $i => $o) $opts[] = ['id' => (string) ($i + 1), 'label' => (string) $o['label'], 'idx' => $i];
        $o = self::pick($opts, $choice);
        if (!$o) return 'That is not one of the options for this question. Use an id from the options list.';
        $opt = $node['options'][$o['idx']];
        $this->s['answers'][] = ['node' => $this->s['node'], 'idx' => $o['idx'], 'q' => $node['short'] ?? $node['q'], 'a' => $opt['label'], 'set' => $opt['set'] ?? null];
        $this->changed();
        if (!empty($opt['next'])) { $this->s['node'] = $opt['next']; return null; }
        $this->applyFamily((string) ($opt['leaf'] ?? ''));
        $this->s['node'] = null;
        $this->s['step'] = $this->nextOpen();
        return null;
    }

    private function applyFamily(string $id): void
    {
        $changed = $this->s['family'] !== $id;
        $this->s['family'] = $id;
        $f = $this->fam();
        if (!$f) { $this->s['family'] = null; return; }
        $this->s['duty'] = !empty($f['hd']) ? 'heavy' : 'light_medium';
        $this->s['subcat'] = !empty($f['hd']) ? $f['subcat'] : null;
        if ($changed) {
            $this->s = array_replace($this->s, ['series' => null, 'variant' => '', 'material' => null, 'front' => $f['front_app'] ?? null, 'rear' => null, 'am' => [], 'optionsSeen' => false]);
            $this->s['fy'] = ['kind' => ($f['yoke_choice'] ?? true) === false ? ($id === 'REUSE' ? 'own' : 'none') : '', 'pid' => 0, 'model' => ''];
            $this->s['ry'] = ['kind' => '', 'pid' => 0]; $this->s['hw'] = ['kind' => '', 'pid' => 0];
            $this->s['asked'] = [];
        } elseif (!empty($f['front_app'])) {
            $this->s['front'] = $f['front_app'];
        }
    }

    private function back(): void
    {
        $this->changed();
        if ($this->s['step'] === 'tree') {
            $a = array_pop($this->s['answers']);
            if ($a) $this->s['node'] = $a['node'];
            return;
        }
        // a sub-list inside a step (the kit list, the next page of yokes) steps back inside it first
        if ($this->s['step'] === 'hardware' && in_array($this->s['hw']['kind'], ['strap', 'ubolt'], true) && !$this->s['hw']['pid']) { $this->s['hw'] = ['kind' => '', 'pid' => 0]; return; }
        if (in_array($this->s['step'], ['rear_pick', 'front_pick'], true) && $this->s['page'] > 1) { $this->s['page']--; return; }
        if ($this->s['step'] === 'options' && $this->s['am']) { array_pop($this->s['am']); $this->s['optionsSeen'] = false; return; }
        $st = $this->fixedSteps();
        $i = array_search($this->s['step'], $st, true);
        if ($i !== false && $i > 0) { $this->s['step'] = $st[$i - 1]; if ($this->s['step'] === 'options') { $this->s['optionsSeen'] = false; } return; }
        $a = array_pop($this->s['answers']);
        $this->s['node'] = $a ? $a['node'] : 'start';
        $this->s['step'] = 'tree';
    }

    /** Back to an earlier step to change it (the steps listed under "answers" in the results). */
    private function goTo(string $step): ?string
    {
        $st = $this->fixedSteps();
        if ($step === 'tree' || $step === 'application') { $this->s = array_replace($this->s, ['step' => 'tree', 'node' => 'start', 'answers' => []]); $this->changed(); return null; }
        if (!in_array($step, $st, true)) return 'That step is not part of this build. Steps: ' . implode(', ', $st) . '.';
        $this->changed();
        if ($step === 'options') { $this->s['am'] = []; $this->s['optionsSeen'] = false; }
        if ($step === 'speed') $this->s['speedSeen'] = false;
        if ($step === 'hardware') $this->s['hw'] = ['kind' => '', 'pid' => 0];
        if (in_array($step, ['rear_pick', 'front_pick'], true)) $this->s['page'] = 1;
        $this->s['step'] = $step;
        return null;
    }

    private function measure(float $a1, float $c1): ?string
    {
        if ($a1 <= 0 || $a1 >= 400) return 'A1 must be a length in inches (for example 52.25). Ask the customer again.';
        if ($c1 < 0 || $c1 >= 400) return 'C1 must be a length in inches. Ask the customer again.';
        $st = $this->fixedSteps(); $open = $this->nextOpen();
        // given before the ends are chosen: kept, and checked when the build reaches the measurements
        $early = array_search($open, $st, true) < array_search('measure', $st, true);
        $this->s['a1'] = round($a1, 3);
        $this->s['c1'] = DsbBuilds::c1Skip((string) $this->s['front'], (string) $this->s['rear']) ? 0.0 : round($c1, 3);
        $this->changed();
        if ($early) { $this->s['step'] = $this->nextOpen(); return null; }
        $this->s['step'] = 'measure';
        if ($e = $this->measureError()) return $e;
        if ($p = $this->lengthProblem()) return $p;
        $this->s['asked']['measure'] = true;
        $this->s['step'] = $this->nextOpen();
        return null;
    }

    private function speed(float $mph, float $ratio, float $tire): ?string
    {
        if (!in_array('speed', $this->fixedSteps(), true) || $this->measureError() !== '') return 'The speed check comes after the measurements.';
        if ($mph == 0 && $ratio == 0 && $tire == 0) {
            $this->s['speed'] = ['mph' => 0.0, 'ratio' => 0.0, 'tire' => 0.0];
        } else {
            if ($mph < 5 || $mph > 250 || $ratio < 1.5 || $ratio > 8 || $tire < 15 || $tire > 60) return 'For the speed check give top speed in mph (5–250), the axle ratio (for example 3.73) and the tire diameter in inches (15–60); or all three at 0 to skip it.';
            $this->s['speed'] = ['mph' => round($mph, 2), 'ratio' => round($ratio, 2), 'tire' => round($tire, 2)];
        }
        $this->s['speedSeen'] = true;
        $this->changed();
        $this->s['step'] = $this->nextOpen();
        return null;
    }

    private function addToCart(int $qty, string $notes, int $customerMsg): array
    {
        if ($this->nextOpen() !== 'review') {
            $this->s['step'] = $this->nextOpen();
            return ['added' => false, 'error' => 'The build is not finished yet: ' . $this->s['step'] . ' is still open.'] + $this->view();
        }
        if ($this->s['reviewMsg'] === null || $customerMsg <= (int) $this->s['reviewMsg']) {
            return ['added' => false, 'error' => 'Show the customer the finished build and its price first (action "show"), and add it only after they say yes in a new message.'];
        }
        if ($notes !== '') $this->s['notes'] = mb_substr($notes, 0, 2000);
        $res = $this->builds->addToCart($this->payload(), $qty, 'assistant');
        if (!$res['success']) return ['added' => false, 'errors' => $res['errors']] + $this->view(implode(' ', $res['errors']));
        $this->s['carted'] = ['title' => $res['data']['title'], 'price' => $res['data']['price']['final'], 'qty' => $qty];
        return [
            'added' => true, 'title' => $res['data']['title'], 'price_each_usd' => $res['data']['price']['final'], 'qty' => $qty,
            'cart_items' => $res['data']['cart_count'], 'cart_url' => '/cart', 'checkout_url' => '/checkout', 'builder_link' => $this->link(),
            'next' => 'Tell the customer it is in the cart. They can check out at /checkout, or you can place the order for them (view_cart, then prepare_order).',
        ];
    }

    /* ---------------------------------------------------------------- options per step */

    /** Translate a data label for display (the English stays the id); "1350 Series" style labels too. */
    private static function tl(string $s): string
    {
        if ($s === '') return $s;
        $t = __($s);
        if ($t !== $s) return $t;
        if (preg_match('/^(.+) Series$/', $s, $m)) { $x = __(':x Series'); if ($x !== ':x Series') return str_replace(':x', $m[1], $x); }
        return $s;
    }

    private static function money(float $v): string
    {
        return '$' . number_format($v, 2);
    }

    /** The options of a fixed step: [['id', 'label', 'note'?, 'price'?]]; ids are what "choice" takes. */
    private function stepOptions(string $step): array
    {
        $s = $this->s;
        $f = $this->fam() ?? [];
        $sug = (array) ($f['suggest'] ?? []);
        $set = $this->settings();
        switch ($step) {
            case 'series':
                $out = [];
                $specs = (array) config('seo-copy.series', []);
                $class = (array) ($this->flow()['engineering']['series_class'] ?? []);
                foreach ((array) ($f['series'] ?? []) as $k) {
                    $node = $this->seriesMap()[$k] ?? null;
                    if (!$node) continue;
                    $sp = $specs[strtolower((string) $k)] ?? $specs[(string) $k] ?? [];
                    $cap = !empty($sp['cap']) ? ('cap ' . $sp['cap'] . (!empty($sp['width']) ? ' × ' . $sp['width'] . ' wide' : '')) : '';
                    $note = trim(implode(' · ', array_filter([isset($class[$k]) ? self::tl((string) $class[$k]) : '', $cap, ($sug['series'] ?? null) === $k ? __('Typical for this vehicle') : ''])));
                    $from = $this->startsAt((string) $k, 'steel');
                    $out[] = ['id' => (string) $k, 'label' => self::tl((string) $node['label']), 'note' => $note] + ($from > 0 ? ['from_usd' => $from] : []);
                    $variant = $this->flow()['series_variants'][$k] ?? null;
                    if ($variant && empty($f['hd'])) $out[] = ['id' => $k . '|' . $variant, 'label' => self::tl((string) $node['label']) . ' (' . str_replace(':make', trim(str_replace((string) $k, '', $variant)), __(':make yokes')) . ')', 'note' => $cap];
                }
                return $out;
            case 'material':
                $lens = $this->builds->pricing()->maxLengths()[$s['series']] ?? [];
                return [
                    ['id' => 'steel', 'label' => __('Steel'), 'note' => trim(__('Standard, strong and economical') . (!empty($lens['steel']) ? ' · ' . __('up to :max in', ['max' => $lens['steel']]) : '') . (($sug['material'] ?? '') === 'steel' ? ' · ' . __('Typical for this vehicle') : ''))]
                        + (($p = $this->startsAt((string) $s['series'], 'steel')) > 0 ? ['from_usd' => $p] : []),
                    ['id' => 'aluminum', 'label' => __('Aluminum'), 'note' => trim(__('Lighter, longer builds, new components only') . (!empty($lens['aluminum']) ? ' · ' . __('up to :max in', ['max' => $lens['aluminum']]) : '') . (($sug['material'] ?? '') === 'aluminum' ? ' · ' . __('Typical for this vehicle') : ''))]
                        + (($p = $this->startsAt((string) $s['series'], 'aluminum')) > 0 ? ['from_usd' => $p] : []),
                ];
            case 'front':
                $out = [];
                foreach ((array) $set['front_apps'] as $k => $a) {
                    if (!empty($a['lm_only']) && $s['duty'] !== 'light_medium') continue;
                    $out[] = ['id' => (string) $k, 'label' => self::tl((string) $a['label']), 'note' => self::tl((string) (DsbController::APP_HELP['front'][$k]['description'] ?? ''))];
                }
                return $out;
            case 'rear':
                $out = [];
                $alu = $s['material'] === 'aluminum';
                foreach ((array) $set['rear_apps'] as $k => $a) {
                    if (!empty($a['aluminum_only']) && !($alu && in_array($s['front'], ['weld', 'flange'], true))) continue;
                    if (str_starts_with((string) $k, 'carrier')) continue;   // one-piece builds: no carrier section
                    $out[] = ['id' => (string) $k, 'label' => self::tl((string) $a['label']), 'note' => self::tl((string) (DsbController::APP_HELP['rear'][$k]['description'] ?? ''))];
                }
                return $out;
            case 'rear_yoke':
            case 'front_yoke':
                $app = $step === 'front_yoke' ? $s['front'] : $s['rear'];
                $type = DsbPricing::yokeTypeFor((string) $app);
                $used = $this->usedPrice($app);
                $fx = $this->facts();
                $spline = $type === 'trans' ? (string) ($fx['spline'] ?? '') : '';
                $stock = $type ? (int) ($this->catalog->yokes($type, (string) $s['series'], [], 1, 1, $spline)['total'] ?? 0) : 0;
                $out = [];
                if ($stock > 0) $out[] = ['id' => 'new', 'label' => __('New yoke'), 'note' => __('Pick one from our catalog')];
                if ($used > 0 && !($step === 'front_yoke' && $this->isReference())) $out[] = ['id' => 'used', 'label' => __('Good used yoke from our shop'), 'note' => __('Inspected, cleaned and fitted here'), 'price_usd' => $used];
                $out[] = ['id' => 'own', 'label' => __('Reuse mine'), 'note' => __('I will send it in or bring it to the shop'), 'price_usd' => 0];
                return $out;
            case 'rear_pick':
            case 'front_pick':
                $app = $step === 'front_pick' ? $s['front'] : $s['rear'];
                $type = DsbPricing::yokeTypeFor((string) $app) ?: 'flange';
                $fx = $this->facts();
                $res = $this->catalog->yokes($type, (string) $s['series'], [], max(1, (int) $s['page']), 8, $type === 'trans' ? (string) ($fx['spline'] ?? '') : '');
                $out = array_map(fn ($p) => ['id' => 'p' . $p['id'], 'label' => trim(($p['sku'] ?? '') . ' ' . preg_replace('/^' . preg_quote((string) ($p['sku'] ?? ''), '/') . '[\s,|–-]*/i', '', (string) $p['name'])),
                    'note' => implode(', ', array_map(fn ($k, $v) => ucwords(str_replace('-', ' ', (string) $k)) . ' ' . $v, array_keys(array_slice((array) ($p['attributes'] ?? []), 0, 3, true)), array_slice((array) ($p['attributes'] ?? []), 0, 3, true))),
                    'price_usd' => (float) $p['price']], (array) ($res['products'] ?? []));
                if (($res['pages'] ?? 1) > ($res['page'] ?? 1)) $out[] = ['id' => 'more', 'label' => __('Next'), 'note' => 'page ' . ($res['page'] + 1) . ' of ' . $res['pages'] . ' (' . $res['total'] . ' yokes)'];
                if (!$out) $out[] = ['id' => 'own', 'label' => __('Reuse mine'), 'note' => __('The shop confirms the yoke with you before building.')];
                return $out;
            case 'hardware':
                if (in_array($s['hw']['kind'], ['strap', 'ubolt'], true) && !$s['hw']['pid']) {
                    $res = $this->catalog->hardware($s['hw']['kind'], (string) $s['series']);
                    $out = array_map(fn ($p) => ['id' => 'p' . $p['id'], 'label' => trim(($p['sku'] ?? '') . ' ' . $p['name']), 'note' => !empty($p['attributes']['series']) ? __('Series') . ' ' . $p['attributes']['series'] : '', 'price_usd' => (float) $p['price']], (array) ($res['products'] ?? []));
                    if (!$out) $out[] = ['id' => 'own', 'label' => __('No, I have mine'), 'note' => __('No kit is listed for this series; we add the right one when we confirm your order.')];
                    return $out;
                }
                return [
                    ['id' => 'strap', 'label' => __('Strap kit'), 'note' => __('Two straps and four bolts hold the caps')],
                    ['id' => 'ubolt', 'label' => __('U-bolt kit'), 'note' => __('Two u-bolts with nuts and washers')],
                    ['id' => 'own', 'label' => __('No, I have mine'), 'note' => __('Nothing added')],
                ];
            case 'options':
                $g = $this->nextOptionGroup();
                if (!$g) return [];
                $labels = DsbController::OPTION_LABELS;
                $vals = $this->availableValues($g);
                return array_values(array_map(fn ($i, $v) => ['id' => (string) ($i + 1), 'label' => self::tl((string) ($labels[$v] ?? $v)), 'value' => (string) $v], array_keys($vals), $vals));
            case 'review':
                if ($s['material'] === 'aluminum' || $this->isReference()) return [];
                $pc = $this->builds->quote(['price_tier' => 'certified'] + $this->payload());
                $pn = $this->builds->quote(['price_tier' => 'new'] + $this->payload());
                return [
                    ['id' => 'certified', 'label' => __('Certified reconditioned components'), 'price_usd' => (float) ($pc['data']['final'] ?? 0), 'note' => $s['tier'] === 'certified' ? 'chosen now' : ''],
                    ['id' => 'new', 'label' => __('All new components'), 'price_usd' => (float) ($pn['data']['final'] ?? 0), 'note' => $s['tier'] === 'new' ? 'chosen now' : ''],
                ];
        }
        return [];
    }

    /**
     * The values of a supplier option group that still lead to a variant the supplier has available, given the
     * answers already picked and the length (a sold-out combination is never offered, so the build cannot dead-end).
     */
    private function availableValues(array $group): array
    {
        $bd = $this->refProduct();
        $full = !empty($bd['product']['handle']) ? $this->am->product((string) $bd['product']['handle']) : null;
        if (!$full) return array_values((array) $group['values']);
        $want = []; $gi = null;
        foreach ((array) $full['options'] as $i => $o) {
            if ($o['name'] === $group['name']) { $gi = $i; continue; }
            if (self::isLenOpt($o)) $want[$i] = (float) $this->s['a1'] > 0 ? AmPricing::lengthAnswer($o, (float) $this->s['a1']) : null;
            elseif (array_key_exists($o['name'], $this->s['am'])) $want[$i] = $this->s['am'][$o['name']];
        }
        if ($gi === null) return array_values((array) $group['values']);
        $ok = [];
        foreach ((array) ($full['variants'] ?? []) as $v) {
            if (!($v['available'] ?? true)) continue;
            $vals = array_values((array) ($v['options'] ?? []));
            foreach ($want as $i => $w) if ($w !== null && ($vals[$i] ?? null) !== $w) continue 2;
            $ok[(string) ($vals[$gi] ?? '')] = true;
        }
        return array_values(array_filter((array) $group['values'], fn ($x) => isset($ok[(string) $x])));
    }

    /** The supplier option group still to answer, in the product's order. */
    private function nextOptionGroup(): ?array
    {
        return $this->nextOptionGroupAfter($this->s['am']);
    }

    private function nextOptionGroupAfter(array $answered): ?array
    {
        foreach ($this->realOptions() as $o) if (!array_key_exists($o['name'], $answered)) return $o;
        return null;
    }

    /** What a build "starts at" in a series and material before the length is known (dsb.js startsAt()). */
    private function startsAt(string $series, string $material): float
    {
        $cover = $this->am->coverage();
        if ($this->s['family'] && in_array($material, (array) ($cover[$this->s['family']][strtolower($series)] ?? []), true)) {
            $v = (float) ($this->am->fromPrices()[$this->s['family']][strtolower($series)][$material] ?? 0);
            if ($v > 0) return $v;
        }
        $set = $this->settings();
        $rows = array_values((array) (($material === 'aluminum' ? ($set['aluminum_tube_od_pricing'] ?? []) : ($set['tube_od_pricing'] ?? []))[strtolower($series)] ?? []));
        $tier = $material === 'aluminum' ? 'new' : $this->s['tier'];
        $vals = array_filter(array_map(fn ($r) => (float) ($tier === 'new' ? ($r['new_price'] ?? 0) : ($r['cert_price'] ?? 0)) ?: (float) ($r['new_price'] ?? 0), $rows), fn ($v) => $v > 0);
        if ($vals) return min($vals);
        $node = $this->seriesMap()[$series] ?? [];
        return (float) ($tier === 'new' ? ($node['new_price'] ?? 0) : ($node['price'] ?? 0));
    }

    /* ---------------------------------------------------------------- what the model and the chat get */

    private static function norm(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(\Illuminate\Support\Str::ascii($s))));
    }

    /** The option the customer meant: its id, its label (English or shown), or the one label that contains it. */
    public static function pick(array $opts, string $choice): ?array
    {
        $c = trim($choice);
        if ($c === '') return null;
        foreach ($opts as $o) if ((string) $o['id'] === $c) return $o;
        $n = self::norm($c);
        if ($n === '') return null;
        foreach ($opts as $o) if (self::norm((string) $o['label']) === $n || self::norm(__((string) $o['label'])) === $n || (isset($o['value']) && self::norm((string) $o['value']) === $n)) return $o;
        $hits = array_values(array_filter($opts, fn ($o) => ($l = self::norm((string) $o['label'])) !== '' && (str_contains($l, $n) || (strlen($n) > 3 && str_contains($n, $l)))));
        return count($hits) === 1 ? $hits[0] : null;
    }

    /** The builder page with this build filled in (the same dsb_* query dsb.js writes and restores). */
    public function link(): string
    {
        $s = $this->s;
        $p = [];
        $put = function (string $k, $v) use (&$p) { if ($v !== null && $v !== '' && $v !== 0 && $v !== 0.0 && $v !== 'none') $p[$k] = is_float($v) ? DsbBuilds::inches($v) : (string) $v; };
        if ($s['answers']) $p['dsb_p'] = implode('.', array_map(fn ($a) => $a['idx'], $s['answers']));
        $put('dsb_fam', $s['family']); $put('dsb_series', $s['series']); $put('dsb_var', $s['variant']); $put('dsb_material', $s['material']);
        $put('dsb_front', $s['front']); $put('dsb_rear', $s['rear']);
        $put('dsb_fy', $s['fy']['kind']); $put('dsb_fyid', (int) $s['fy']['pid']); $put('dsb_fmod', $s['fy']['model']);
        $put('dsb_ry', $s['ry']['kind']); $put('dsb_ryid', (int) $s['ry']['pid']);
        $put('dsb_hw', $s['hw']['kind']); $put('dsb_hwid', (int) $s['hw']['pid']);
        $put('dsb_a1', (float) $s['a1']); $put('dsb_c1', (float) $s['c1']); if ($s['tier'] === 'new') $p['dsb_tier'] = 'new';
        $put('dsb_mph', (float) $s['speed']['mph']); $put('dsb_ratio', (float) $s['speed']['ratio']); $put('dsb_tire', (float) $s['speed']['tire']);
        $i = 0; foreach ($s['am'] as $k => $v) { if ($v !== '') $p['dsb_o' . $i] = $k . '=' . $v; $i++; }
        $seen = ($s['speedSeen'] ? 's' : '') . ($s['optionsSeen'] ? 'o' : '');
        if ($seen !== '') $p['dsb_seen'] = $seen;
        if ($s['step'] !== 'tree') $p['dsb_step'] = $s['step'];
        return rtrim((string) config('app.url'), '/') . '/online-builder' . ($p ? '?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private function stage(): string
    {
        $step = $this->s['step'];
        foreach (self::PHASES as $i => [$name, $steps]) {
            if (in_array($step, $steps, true)) return 'Step ' . ($i + 1) . ' of ' . count(self::PHASES) . ' · ' . $name . ($step !== 'tree' ? ' · ' . (self::STEP_NAMES[$step] ?? $step) : '');
        }
        return 'Step 1 of 5 · Application';
    }

    /**
     * Every choice made so far, in the builder's words, each with the step that changes it (for go_to); "shown" is the
     * same value in the customer's language, as the builder page shows it (for the summary card).
     */
    private function chosen(): array
    {
        $s = $this->s;
        $rows = [];
        foreach ($s['answers'] as $a) $rows[] = ['q' => $a['q'], 'a' => $a['a'], 'shown' => self::tl((string) $a['a']), 'change' => 'tree'];
        if (!$this->fam()) return $rows;
        $set = $this->settings();
        $part = fn (int $pid) => trim($this->catalog->productSku($pid) . ' ' . ($this->catalog->productName($pid) ?? ''));
        $yoke = function (array $y, bool $local) use ($part) {
            if (!$y['kind'] || $y['kind'] === 'none') return '';
            if ($y['kind'] === 'own') return $local ? __('Reuse mine') : 'Reuse mine';
            if ($y['kind'] === 'used') return $local ? __('Good used yoke from our shop') : 'Good used yoke from our shop';
            return $y['pid'] ? $part((int) $y['pid']) : ($local ? __('New yoke') : 'New yoke (not picked yet)');
        };
        $row = fn (string $q, string $a, string $shown, string $change) => ['q' => $q, 'a' => $a, 'shown' => $shown, 'change' => $change];
        if ($s['series']) $rows[] = $row('Series', $this->seriesLabel() . ($s['variant'] ? ' (' . $s['variant'] . ')' : ''), self::tl($this->seriesLabel()) . ($s['variant'] ? ' (' . $s['variant'] . ')' : ''), 'series');
        if ($s['material']) $rows[] = $row('Material', ucfirst($s['material']), __(ucfirst($s['material'])), $this->aluEligible() ? 'material' : '');
        if ($s['front']) { $l = (string) ($set['front_apps'][$s['front']]['label'] ?? $s['front']); $rows[] = $row('Front end', $l, self::tl($l), empty(($this->fam() ?? [])['front_app']) ? 'front' : 'tree'); }
        if ($s['rear']) { $l = (string) ($set['rear_apps'][$s['rear']]['label'] ?? $s['rear']); $rows[] = $row('Rear end', $l, self::tl($l), 'rear'); }
        if (DsbPricing::yokeTypeFor((string) $s['rear']) && $s['ry']['kind']) $rows[] = $row('Rear yoke', $yoke($s['ry'], false), $yoke($s['ry'], true), 'rear_yoke');
        if ($s['rear'] === 'weld' && $s['hw']['kind']) {
            $hw = $s['hw']['kind'] === 'own' ? ['Customer has it', __('No, I have mine')] : ($s['hw']['pid'] ? [$part((int) $s['hw']['pid']), $part((int) $s['hw']['pid'])] : [ucfirst($s['hw']['kind']) . ' kit (not picked yet)', __($s['hw']['kind'] === 'strap' ? 'Strap kit' : 'U-bolt kit')]);
            $rows[] = $row('Rear hardware', $hw[0], $hw[1], 'hardware');
        }
        if ($s['a1'] > 0) $rows[] = $row('A1', DsbBuilds::inches((float) $s['a1']) . ' in', DsbBuilds::inches((float) $s['a1']) . ' in', 'measure');
        if ($s['c1'] > 0) $rows[] = $row('C1', DsbBuilds::inches((float) $s['c1']) . ' in', DsbBuilds::inches((float) $s['c1']) . ' in', 'measure');
        if ($s['speed']['mph'] > 0) $rows[] = $row('Speed check', $s['speed']['mph'] . ' mph, ' . $s['speed']['ratio'] . ' ratio, ' . $s['speed']['tire'] . ' in tire',
            __(':mph mph, :ratio ratio, :tire in tire', ['mph' => $s['speed']['mph'], 'ratio' => $s['speed']['ratio'], 'tire' => $s['speed']['tire']]), 'speed');
        foreach ($s['am'] as $k => $v) {
            $head = (string) (array_key_first(DsbController::buildOptionRows([$k => $v])) ?? $k);
            $val = (string) (DsbController::OPTION_LABELS[$v] ?? $v);
            $rows[] = $row($head, $val, self::tl($val), 'options');
        }
        if ($this->frontYokeAsk() && $s['fy']['kind']) $rows[] = $row('Front yoke', $yoke($s['fy'], false) . ($s['fy']['model'] ? ' · ' . $s['fy']['model'] : ''), $yoke($s['fy'], true) . ($s['fy']['model'] ? ' · ' . $s['fy']['model'] : ''), 'front_yoke');
        return $rows;
    }

    /** The speed check in plain words (dsb.js speedText), from the server's own numbers; $local = the page's translated wording. */
    private function speedText(?array $sp, bool $local = false): ?string
    {
        if (!$sp) return null;
        $L = fn (string $en, array $r = []) => $local ? __($en, $r) : strtr($en, array_combine(array_map(fn ($k) => ':' . $k, array_keys($r)), array_map('strval', array_values($r))) ?: []);
        $od = rtrim(rtrim(number_format((float) $sp['tube_od'], 3, '.', ''), '0'), '.');
        $joint = (float) $this->s['c1'] > 0 && (float) $sp['length'] === (float) $this->s['c1'];
        $tube = $L(':od × :wall :material tube at :len in', ['od' => $od, 'wall' => $sp['wall'], 'material' => $local ? __((string) $sp['material']) : $sp['material'], 'len' => DsbBuilds::inches((float) $sp['length'])])
            . ' ' . ($joint ? $L('(joint to joint)') : $L('(overall length; enter C1 for a closer figure)'));
        if (empty($sp['shaft_rpm'])) return $L('This :tube is safe to :safe RPM (critical speed :crit RPM).', ['tube' => $tube, 'safe' => number_format($sp['safe_rpm']), 'crit' => number_format($sp['critical_rpm'])]);
        $lead = $L('At :mph mph your shaft turns :rpm RPM; this :tube is safe to :safe RPM.', ['mph' => $sp['mph'], 'rpm' => number_format($sp['shaft_rpm']), 'tube' => $tube, 'safe' => number_format($sp['safe_rpm'])]);
        if ($sp['status'] === 'ok') return $lead;
        $next = $this->biggerTube((float) $sp['tube_od'], (float) $sp['wall'], (float) $sp['length']);
        $fix = $next ? $L('A :od in tube would be safe to :safe RPM at this length.', ['od' => $next['od'], 'safe' => number_format($next['safe'])]) : $L('At this length a two-piece shaft with a center support bearing is the usual answer.');
        return $lead . ' ' . ($sp['status'] === 'warn' ? $L('That is close to the limit.') : $L('That is over the safe speed.')) . ' ' . $fix;
    }

    /** The next larger tube of the series and material at the same length (our own size table; supplier builds have none). */
    private function biggerTube(float $od, float $wall, float $len): ?array
    {
        if ($this->isReference() || $len <= 0) return null;
        $set = $this->settings();
        $rows = (array) ((($this->s['material'] === 'aluminum') ? ($set['aluminum_tube_od_pricing'] ?? []) : ($set['tube_od_pricing'] ?? []))[strtolower((string) $this->s['series'])] ?? []);
        $ods = array_filter(array_map(fn ($r) => (float) preg_replace('/[^0-9.]/', '', (string) ($r['od'] ?? '')), $rows), fn ($x) => $x > $od);
        if (!$ods) return null;
        sort($ods);
        $b = $ods[0];
        $eng = (array) ($this->flow()['engineering'] ?? []);
        $id = max(0.1, $b - 2 * $wall);
        return ['od' => rtrim(rtrim(number_format($b, 3, '.', ''), '0'), '.'), 'safe' => (int) round((float) ($eng['critical_speed_k'] ?? 4705000) * sqrt($b * $b + $id * $id) / ($len * $len) * (float) ($eng['safe_fraction'] ?? 0.75))];
    }

    /**
     * The current question for the model: what to ask, the options with their ids, what was chosen, the price so
     * far, the builder link, and when the build is complete, the full review. $error is what went wrong with the
     * last call (asked again, in words the model can pass on).
     */
    public function view(?string $error = null, ?string $note = null, bool $full = false): array
    {
        $s = &$this->s;
        // a step that no longer belongs to the build (an answer changed the flow) moves on to the first open one
        if ($s['step'] !== 'tree' && !in_array($s['step'], $this->fixedSteps(), true)) $s['step'] = $this->fam() ? $this->nextOpen() : 'tree';
        if ($s['step'] === 'tree' && $s['node'] === null) $s['node'] = 'start';
        $step = $s['step'];
        $out = ['step' => $step, 'stage' => $this->stage()];
        if ($error) $out['problem'] = $error;
        if ($note) $out['note'] = $note;
        $q = $this->quote();
        $hasLen = (float) $s['a1'] > 0;

        if ($step === 'tree') {
            $node = $this->treeNode($s['node']) ?? $this->treeNode('start');
            $out['question'] = $node['q'];
            if (!empty($node['help'])) $out['help'] = $node['help'];
            $counting = preg_match('/spline/i', (string) $node['q']) && array_filter($node['options'], fn ($o) => preg_match('/^\d/', (string) $o['label']));
            if ($counting) $out['how_to_count_splines'] = 'Look into the open end of the old slip yoke, or at the output shaft it slides onto. Put a marker dot on one tooth and count every tooth all the way around back to it. Some yokes have one or two wide gaps (blind spots) that index them on the shaft: count each gap as one tooth. Not sure? They can send a photo on WhatsApp (813) 644-5707 and the shop counts it with them.';
            $out['options'] = array_values(array_map(fn ($i, $o) => array_filter(['id' => (string) ($i + 1), 'label' => self::tl((string) $o['label']), 'note' => !empty($o['note']) ? self::tl((string) $o['note']) : null, 'group' => !empty($o['group']) ? self::tl((string) $o['group']) : null]), array_keys($node['options']), $node['options']));
        } elseif ($step === 'measure') {
            $front = (string) $s['front']; $rear = (string) $s['rear'];
            $map = self::DIMMAP[$front . '__' . $rear] ?? ['a1' => 'A1', 'c1' => 'C1'];
            $eng = (array) ($this->flow()['engineering'] ?? []);
            $out['question'] = 'Measurements, in inches, with the vehicle at ride height.';
            $out['ask'] = ['a1' => self::DIMDESC[$map['a1']] ?? self::DIMDESC['A1']] + (DsbBuilds::c1Skip($front, $rear) ? [] : ['c1' => self::DIMDESC[$map['c1']] ?? self::DIMDESC['C1']]);
            if (DsbBuilds::c1Skip($front, $rear)) $out['c1'] = 'Not needed for this build (it equals A1).';
            if (!empty($eng['measure_help'][$front])) $out['how_to_measure'] = $eng['measure_help'][$front];
            if (!empty($eng['angle_note'])) $out['angle_note'] = $eng['angle_note'];
            if ($max = $this->maxLen()) $out['max_length_in'] = $max;
        } elseif ($step === 'speed') {
            $out['question'] = 'Optional speed check: top speed (mph), axle ratio and tire diameter (inches). Or skip it.';
            $sp = $q['breakdown']['speed'] ?? null;
            if ($sp) $out['tube'] = $this->speedText($sp);
        } elseif ($step === 'review') {
            $out['question'] = 'The build is complete. Show the price and ask whether to add it to the cart.';
        } else {
            $questions = ['series' => 'Which u-joint series?', 'material' => 'Tube material?', 'front' => 'Which end is at the transmission or transfer case?', 'rear' => 'What is on the differential end?',
                'rear_yoke' => 'Rear yoke: a new one, a good used one from the shop, or reuse theirs?', 'front_yoke' => 'Front yoke: a new one, a good used one from the shop, or reuse theirs?',
                'rear_pick' => 'Pick the rear yoke from our catalog.', 'front_pick' => 'Pick the front yoke from our catalog.', 'hardware' => 'How does the rear u-joint attach to the pinion yoke?', 'options' => 'Build option'];
            $out['question'] = $questions[$step] ?? $step;
            if ($step === 'series') $out['help'] = 'Not sure? Measure a bearing cap with calipers: the cap diameter and the width across the joint tell the series apart.';
            if ($step === 'hardware' && in_array($s['hw']['kind'], ['strap', 'ubolt'], true) && !$s['hw']['pid']) $out['question'] = 'Pick the ' . ($s['hw']['kind'] === 'strap' ? 'strap' : 'u-bolt') . ' kit.';
            if ($step === 'front_yoke' && DsbPricing::yokeTypeFor((string) $s['front']) === 'trans') $out['optional_text'] = 'The transmission or transfer case model, if they know it (pass it as text with the answer).';
            if ($step === 'options' && ($g = $this->nextOptionGroup())) $out['question'] = (string) (array_key_first(DsbController::buildOptionRows([$g['name'] => $g['values'][0]])) ?? $g['name']);
            $out['options'] = $this->stepOptions($step);
            if ($step === 'options' && !$out['options']) $out['problem'] = 'The supplier has no available build for this tube right now, so it cannot be priced online. Try the other material or series (go_to), or the customer can call (813) 524-6491 for a quote.';
        }
        // what was chosen and the link: on the review, on show / back / go_to (the history already has the rest)
        if ($full || $step === 'review') $out['answers'] = $this->chosen();

        // the price: the server quote once there is a length; before it, what the series starts at
        if ($q && $hasLen && (float) ($q['final'] ?? 0) > 0) $out['price_usd'] = (float) $q['final'];
        elseif ($s['series'] && ($from = $this->startsAt((string) $s['series'], $s['material'] ?: 'steel')) > 0) $out['from_usd'] = $from;
        if ($q && !empty($q['breakdown']['tube_od']) && $hasLen) $out['tube_od'] = $q['breakdown']['tube_od'];

        if ($step === 'review') {
            $errs = [];
            if ($q) { $cfg = $this->builds->config($this->payload()); $this->builds->check($cfg); $errs = $this->builds->orderErrors($cfg, $this->builds->pricing()->compute($cfg)); }
            if ($errs) { $out['problem'] = implode(' ', $errs); }
            else {
                $out['ready'] = true;
                $out['build'] = $this->review($q);
                if ($opts = $this->stepOptions('review')) $out['component_choice'] = $opts;
                $out['next'] = 'Show the build in one or two lines (the chat shows the full summary card with the price and an "Add to cart" button) and ask if they want it in their cart. Call add_to_cart only after they say yes in their next message.' . (!empty($out['component_choice']) ? ' They can also choose certified reconditioned or all new components (answer with that id).' : '');
            }
        } elseif ($step === 'measure') {
            $out['next'] = 'Ask for ' . (isset($out['ask']['c1']) ? 'A1 and C1' : 'A1') . ' (explain how to measure in a sentence or two), then call action "measure".';
        } elseif ($step === 'speed') {
            $out['next'] = 'Offer the speed check (top speed, axle ratio, tire diameter) or to skip it; then call action "speed" (all zeros = skip).';
        } elseif (!empty($out['options'])) {
            $out['next'] = count($out['options']) > self::MAX_OPTIONS
                ? 'Ask the question; there are many options, so ask them to type theirs (the chat cannot show them all) and answer with the matching id.'
                : 'Ask the question in one line; the options show as buttons under your answer. When they pick one, call action "answer" with its id.';
        }
        if ($full || in_array($step, ['review', 'measure'], true)) $out['builder_link'] = $this->link();
        if ($s['carted']) $out['in_cart'] = $s['carted'];
        return $out;
    }

    /** The finished build as the review page shows it: title, photo, lines, specs, speed check. */
    private function review(?array $q): array
    {
        $cfg = $this->builds->config($this->payload());
        $this->builds->check($cfg);
        $bd = $q['breakdown'] ?? [];
        $lines = [];
        $mat = ucfirst($this->s['material'] ?: 'steel');
        if (($bd['pricing'] ?? '') === 'reference') {
            $opts = array_filter(DsbController::buildOptionRows((array) ($bd['options'] ?? [])));
            $lines[] = ['label' => 'Driveshaft — ' . implode(', ', array_filter([$this->seriesLabel(), $mat, 'tube ' . ($bd['tube_od'] ?? ''), 'new components'])) . ($opts ? ' · ' . implode(' · ', $opts) : ''), 'usd' => (float) ($q['base'] ?? 0)];
        } else {
            $lines[] = ['label' => 'Base shaft — ' . implode(', ', array_filter([$this->seriesLabel(), $mat, !empty($bd['tube_od']) ? 'tube ' . $bd['tube_od'] : '', ($cfg['price_tier'] ?? 'certified') === 'new' ? 'brand new' : 'certified'])), 'usd' => (float) ($q['base'] ?? 0)];
            if (!empty($bd['front_label'])) $lines[] = ['label' => 'Front yoke — ' . $bd['front_label'], 'usd' => (float) ($q['front_addon'] ?? 0)];
            if (!empty($bd['hardware_label'])) $lines[] = ['label' => 'Hardware — ' . $bd['hardware_label'], 'usd' => (float) ($q['hardware_addon'] ?? 0)];
        }
        if (!empty($bd['rear_label'])) $lines[] = ['label' => 'Rear yoke — ' . $bd['rear_label'], 'usd' => (float) ($q['rear_addon'] ?? 0)];
        $f = $this->fam() ?? [];
        // the same lines in the customer's language, as the builder page's review writes them
        $matL = __($mat); $shown = [];
        $yokeL = fn (array $y) => $y['kind'] === 'own' ? __('Reuse mine') : ($y['kind'] === 'used' ? __('Good used yoke from our shop') : ($y['pid'] ? trim($this->catalog->productSku((int) $y['pid']) . ' ' . ($this->catalog->productName((int) $y['pid']) ?? '')) : __('New yoke')));
        if (($bd['pricing'] ?? '') === 'reference') {
            $opts = array_map(fn ($v) => self::tl((string) $v), array_filter(DsbController::buildOptionRows((array) ($bd['options'] ?? []))));
            $shown[] = ['label' => __('Driveshaft') . ' — ' . implode(', ', array_filter([self::tl($this->seriesLabel()), $matL, __('Tube :od', ['od' => (string) ($bd['tube_od'] ?? '')]), __('new components')])) . ($opts ? ' · ' . implode(' · ', $opts) : ''), 'usd' => (float) ($q['base'] ?? 0)];
        } else {
            $shown[] = ['label' => __('Base shaft') . ' — ' . implode(', ', array_filter([self::tl($this->seriesLabel()), $matL, !empty($bd['tube_od']) ? __('Tube :od', ['od' => $bd['tube_od']]) : '', ($cfg['price_tier'] ?? 'certified') === 'new' ? __('Brand new') : __('Certified')])), 'usd' => (float) ($q['base'] ?? 0)];
            if (!empty($bd['front_label'])) $shown[] = ['label' => __('Front yoke') . ' — ' . $yokeL($this->s['fy']), 'usd' => (float) ($q['front_addon'] ?? 0)];
            if (!empty($bd['hardware_label'])) $shown[] = ['label' => __('Hardware') . ' — ' . ($this->s['hw']['kind'] === 'own' ? __('No, I have mine') : trim($this->catalog->productSku((int) $this->s['hw']['pid']) . ' ' . ($this->catalog->productName((int) $this->s['hw']['pid']) ?? ''))), 'usd' => (float) ($q['hardware_addon'] ?? 0)];
        }
        if (!empty($bd['rear_label'])) $shown[] = ['label' => __('Rear yoke') . ' — ' . $yokeL($this->s['ry']), 'usd' => (float) ($q['rear_addon'] ?? 0)];
        return [
            'title' => $this->builds->title($cfg),
            'price_usd' => (float) ($q['final'] ?? 0),
            'photo' => $this->builds->buildPhoto($cfg),
            'photo_note' => 'Photo of a shaft we build; theirs is made to their measurements.',
            'fits' => !empty($f['models']) ? implode(', ', $f['models']) : null,
            'lines' => $lines,
            'lines_shown' => $shown,
            'speed_check' => $this->speedText($bd['speed'] ?? null),
            'speed_shown' => $this->speedText($bd['speed'] ?? null, true),
            'own_yoke_note' => in_array('own', [$this->s['fy']['kind'], $this->s['ry']['kind']], true) ? 'Reusing a yoke: ship it to us with the order number, or bring it to the shop. We fit it and balance the shaft with it.' : null,
            'lead_time_note' => 'Built to order and balanced in our Florida shop.',
        ];
    }
}
