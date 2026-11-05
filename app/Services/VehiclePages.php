<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data behind the server-rendered vehicle landing pages (/driveshafts, /driveshafts/{make},
 * /driveshafts/{make}/{model}). Everything is read from ds_fitments + ds_parts, built once and
 * cached; the views only format. Slugs are deterministic (lowercase, non-alphanumerics -> "-")
 * and were verified collision-free across all 935 make+model combinations.
 */
class VehiclePages
{
    /** Catalogue "makes" that are not vehicles and must not get landing pages. */
    public const EXCLUDED_MAKES = ['Custom / Misc.'];

    public const TTL = 1800;

    public const CATEGORY = 'Vehicles & Parts > Vehicle Parts & Accessories > Motor Vehicle Parts > Motor Vehicle Power & Drive Train Parts';

    public static function slug(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s))), '-');
    }

    public static function makeUrl(string $make): ?string
    {
        if (in_array($make, self::EXCLUDED_MAKES, true) || self::slug($make) === '') return null;
        return '/driveshafts/' . self::slug($make);
    }

    public static function modelUrl(string $make, string $model): ?string
    {
        $m = self::makeUrl($make);
        return $m && self::slug($model) !== '' ? $m . '/' . self::slug($model) : null;
    }

    /** "2003-2005, 2008" from a list of years. */
    public static function yearRanges(array $years, string $dash = '–'): string
    {
        $years = array_values(array_unique(array_map('intval', $years))); sort($years);
        $out = []; $start = $prev = null;
        foreach (array_merge($years, [null]) as $y) {
            if ($start === null) { $start = $prev = $y; continue; }
            if ($y !== null && $y === $prev + 1) { $prev = $y; continue; }
            $out[] = $start === $prev ? (string) $start : $start . $dash . $prev;
            $start = $prev = $y;
        }
        return implode(', ', $out);
    }

    /** <title> that stays at or under 60 characters while keeping the unique part (make/model) intact. */
    public static function title(string $core, string $years = ''): string
    {
        $y = $years !== '' ? " $years" : '';
        foreach (["$core$y | Universal Driveshafts", "$core$y | UDS", "$core | UDS", $core] as $t) if (mb_strlen($t) <= 60) return $t;
        return $core;
    }

    /** Meta description: adds sentences in priority order until 140-160 chars. */
    public static function desc(array $sentences): string
    {
        $out = '';
        foreach ($sentences as $s) {
            $s = trim((string) $s);
            if ($s === '') continue;
            $c = $out === '' ? $s : "$out $s";
            if (mb_strlen($c) <= 160) $out = $c;
            elseif (mb_strlen($out) >= 140) break;
        }
        foreach (['Order online or call (813) 524-6491 for fitment help.', 'Ships from Florida.'] as $f) {
            if (mb_strlen($out) >= 140) break;
            if (mb_strlen("$out $f") <= 160) $out = trim("$out $f");
        }
        return mb_strlen($out) > 160 ? rtrim(mb_substr($out, 0, 157)) . '…' : $out;
    }

    public static function money(?float $n): string
    {
        return $n === null ? 'Call for price' : '$' . number_format($n, 2);
    }

    public function logos(): array
    {
        return Cache::remember('veh:logos', 3600, fn () => array_map(fn ($f) => basename($f, '.png'), glob(public_path('img/makes/*.png')) ?: []));
    }

    public function logo(string $make): ?string
    {
        $s = self::slug($make);
        return in_array($s, $this->logos(), true) ? media_url("/img/makes/$s.png", 160) : null;
    }

    /** All make+model combinations with year span and part count. One query, cached. */
    public function allModels(): array
    {
        return Cache::remember('veh:models', 3600, function () {
            $rows = \App\Services\DriveshaftFinder::fitments()->whereNotIn('make', self::EXCLUDED_MAKES)
                ->selectRaw('make, model, MIN(year) y1, MAX(year) y2, COUNT(DISTINCT part_number) parts, COUNT(*) fitments')
                ->groupBy('make', 'model')->get();
            $out = [];
            foreach ($rows as $r) {
                if (self::slug($r->make) === '' || self::slug($r->model) === '') continue;
                $out[] = ['make' => $r->make, 'make_slug' => self::slug($r->make), 'model' => $r->model, 'slug' => self::slug($r->model),
                          'y1' => (int) $r->y1, 'y2' => (int) $r->y2, 'parts' => (int) $r->parts, 'fitments' => (int) $r->fitments];
            }
            usort($out, fn ($a, $b) => strnatcasecmp($a['make'], $b['make']) ?: strnatcasecmp($a['model'], $b['model']));
            return $out;
        });
    }

    /** slug => make name. */
    public function makeMap(): array
    {
        $out = [];
        foreach ($this->allModels() as $m) $out[$m['make_slug']] = $m['make'];
        return $out;
    }

    public function makeFromSlug(string $slug): ?string
    {
        return $this->makeMap()[$slug] ?? null;
    }

    public function modelFromSlug(string $make, string $slug): ?string
    {
        foreach ($this->allModels() as $m) if ($m['make'] === $make && $m['slug'] === $slug) return $m['model'];
        return null;
    }

    /** Y-m-d of the last catalogue import, for sitemap lastmod. */
    public function lastmod(): string
    {
        return Cache::remember('veh:lastmod', 3600, function () {
            $t = DB::table('ds_parts')->max('updated_at');
            return $t ? substr((string) $t, 0, 10) : date('Y-m-d');
        });
    }

    /* ------------------------------------------------------------------ hub */

    public function hub(): array
    {
        return Cache::remember('veh:hub', self::TTL, function () {
            $parts = \App\Services\DriveshaftFinder::fitments()->whereNotIn('make', self::EXCLUDED_MAKES)
                ->selectRaw('make, COUNT(DISTINCT part_number) parts')->groupBy('make')->pluck('parts', 'make');
            $makes = [];
            foreach ($this->allModels() as $m) {
                $k = $m['make'];
                $makes[$k] ??= ['name' => $k, 'slug' => $m['make_slug'], 'url' => self::makeUrl($k), 'models' => 0, 'parts' => (int) ($parts[$k] ?? 0), 'y1' => $m['y1'], 'y2' => $m['y2'], 'logo' => $this->logo($k)];
                $makes[$k]['models']++;
                $makes[$k]['y1'] = min($makes[$k]['y1'], $m['y1']);
                $makes[$k]['y2'] = max($makes[$k]['y2'], $m['y2']);
            }
            $makes = array_values($makes);
            $totalParts = \App\Services\DriveshaftFinder::fitments()->whereNotIn('make', self::EXCLUDED_MAKES)->distinct()->count('part_number');
            return ['makes' => $makes, 'total_models' => count($this->allModels()), 'total_parts' => $totalParts,
                    'total_makes' => count($makes), 'y1' => min(array_column($makes, 'y1')), 'y2' => max(array_column($makes, 'y2'))];
        });
    }

    /* ----------------------------------------------------------------- make */

    public function make(string $make): array
    {
        return Cache::remember('veh:make:' . md5($make), self::TTL, function () use ($make) {
            $models = array_values(array_filter($this->allModels(), fn ($m) => $m['make'] === $make));
            foreach ($models as &$m) $m['url'] = self::modelUrl($make, $m['model']);
            unset($m);
            $q = \App\Services\DriveshaftFinder::fitments()->where('make', $make);
            $drives = (clone $q)->whereNotNull('drive')->where('drive', '<>', '')->distinct()->orderBy('drive')->pluck('drive')->all();
            $parts = (clone $q)->distinct()->count('part_number');
            $priced = $this->priceRange((clone $q)->distinct()->pluck('part_number')->all());
            $others = [];
            foreach ($this->hub()['makes'] as $o) if ($o['name'] !== $make) $others[] = $o;
            return ['make' => $make, 'slug' => self::slug($make), 'url' => self::makeUrl($make), 'logo' => $this->logo($make),
                    'models' => $models, 'parts' => $parts, 'drives' => $drives,
                    'y1' => min(array_column($models, 'y1')), 'y2' => max(array_column($models, 'y2')),
                    'price_min' => $priced[0], 'price_max' => $priced[1], 'others' => $others];
        });
    }

    private function priceRange(array $pns): array
    {
        $min = $max = null;
        foreach (DB::table('ds_parts')->whereIn('part_number', $pns)->get(['is_active', 'price_list', 'price_retail', 'price_auto']) as $p) {
            $v = DriveshaftFinder::customerPrice($p);
            if ($v === null) continue;
            $min = $min === null ? $v : min($min, $v);
            $max = $max === null ? $v : max($max, $v);
        }
        return [$min, $max];
    }

    /* ---------------------------------------------------------------- model */

    public function model(string $make, string $model): array
    {
        // the intro, FAQ and meta are generated prose: one cached copy per language
        return Cache::remember('veh:model:' . app()->getLocale() . ':' . md5("$make|$model"), self::TTL, fn () => $this->modelUncached($make, $model));
    }

    private function modelUncached(string $make, string $model): array
    {
        $rows = \App\Services\DriveshaftFinder::fitments()->where('make', $make)->where('model', $model)->orderBy('year')->get();
        $pns = $rows->pluck('part_number')->unique()->values()->all();
        $parts = DB::table('ds_parts')->whereIn('part_number', $pns)
            ->get(['part_number', 'description', 'condition', 'price_list', 'price_retail', 'price_auto', 'core_price', 'weight', 'is_active', 'main_image'])->keyBy('part_number');

        // which deep facets carry data for this vehicle: a column earns its place when it actually
        // splits the rows (2+ values) or is filled in on every row
        $cols = array_values(array_filter(DriveshaftFinder::FACETS, function ($c) use ($rows) {
            if ($c === 'loc') return false;
            $vals = $rows->pluck($c)->filter(fn ($v) => $v !== null && $v !== '');
            return $vals->unique()->count() >= 2 || ($vals->isNotEmpty() && $vals->count() === $rows->count());
        }));

        // one table row per part + configuration, consecutive years collapsed
        $groups = [];
        foreach ($rows as $r) {
            $facets = [];
            foreach ($cols as $c) $facets[$c] = ($r->$c !== null && $r->$c !== '') ? (string) $r->$c : '';
            $key = implode('|', array_merge([$r->part_number, (string) $r->drive, (string) $r->loc, (string) $r->c_brg, (string) $r->notes], $facets));
            $groups[$key] ??= ['part_number' => $r->part_number, 'drive' => (string) $r->drive, 'loc' => (string) $r->loc, 'c_brg' => (string) $r->c_brg,
                                'notes' => (string) $r->notes, 'facets' => $facets, 'years' => []];
            $groups[$key]['years'][] = (int) $r->year;
        }
        $items = [];
        foreach ($groups as $g) {
            $p = $parts[$g['part_number']] ?? null;
            $years = array_values(array_unique($g['years'])); sort($years);
            $price = $p ? DriveshaftFinder::customerPrice($p) : null;
            $items[] = $g + [
                'y1' => min($years), 'y2' => max($years), 'years_label' => self::yearRanges($years), 'years_csv' => implode(',', $years),
                'sku' => DriveshaftFinder::sku($g['part_number']),
                'url' => '/catalog/driveshafts/part/' . rawurlencode(DriveshaftFinder::sku($g['part_number'])),
                'description' => $p->description ?? null, 'condition' => $p->condition ?? null,
                'image' => $p && $p->main_image ? media_url($p->main_image, 320) : null,
                'price' => $price, 'can_buy' => $price !== null,
                'core_price' => $p && (float) $p->core_price > 0 ? (float) $p->core_price : null,
                'weight' => $p && $p->weight !== null ? (float) $p->weight : null,
            ];
        }
        $locOrder = fn ($l) => str_starts_with($l, 'Front') ? 0 : (str_starts_with($l, 'Int') ? 1 : 2);
        usort($items, fn ($a, $b) => $b['y2'] <=> $a['y2'] ?: $b['y1'] <=> $a['y1'] ?: $locOrder($a['loc']) <=> $locOrder($b['loc']) ?: strnatcmp($a['part_number'], $b['part_number']));

        // per-year counts for the year chips
        $years = [];
        foreach ($rows->groupBy('year') as $y => $g) $years[(int) $y] = $g->pluck('part_number')->unique()->count();
        krsort($years);

        // stats for the copy
        $y1 = (int) $rows->min('year'); $y2 = (int) $rows->max('year');
        $locs = $rows->pluck('loc')->filter()->unique()->values();
        $positions = $locs->map(fn ($l) => str_starts_with($l, 'Front') ? 'front' : (str_starts_with($l, 'Int') ? 'intermediate' : 'rear'))->unique()->values()->all();
        usort($positions, fn ($a, $b) => ['front' => 0, 'intermediate' => 1, 'rear' => 2][$a] <=> ['front' => 0, 'intermediate' => 1, 'rear' => 2][$b]);
        $drives = $rows->pluck('drive')->filter(fn ($d) => $d && !in_array($d, ['Any', 'Custom']))->unique()->sort()->values()->all();
        // centre bearing numbers for the copy: drop "N/A (1PC)"-style placeholders and trailing remarks
        $cbrgs = $rows->pluck('c_brg')->filter()->map(fn ($c) => trim(preg_replace('/\s*\(.*$/', '', $c)))
            ->filter(fn ($c) => $c !== '' && !preg_match('/^(n\/?a|none|-+)$/i', $c))->unique()->values()->all();
        $pieces = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($pns as $pn) {
            $d = (string) ($parts[$pn]->description ?? '');
            $n = preg_match('/(\d)\s*PC/i', $d, $m) ? (int) $m[1] : null;
            if ($n === null) {
                $l = $rows->where('part_number', $pn)->pluck('loc')->filter()->first() ?? '';
                if (preg_match('/Complete (\d) piece/i', $l, $m)) $n = (int) $m[1];
            }
            if ($n !== null && isset($pieces[$n])) $pieces[$n]++;
        }
        $priced = array_values(array_filter(array_map(fn ($i) => $i['price'], $items), fn ($v) => $v !== null));
        $priceMin = $priced ? min($priced) : null; $priceMax = $priced ? max($priced) : null;
        $trans = $rows->pluck('trans')->filter()->unique()->values()->all();
        $engines = $rows->pluck('engine')->filter()->unique()->values()->all();

        // neighbours in the same make (window around the current model)
        $all = array_values(array_filter($this->allModels(), fn ($m) => $m['make'] === $make));
        $idx = 0; foreach ($all as $i => $m) if ($m['model'] === $model) { $idx = $i; break; }
        $win = max(0, min($idx - 6, count($all) - 13));
        $siblings = [];
        foreach (array_slice($all, $win, 13) as $m) if ($m['model'] !== $model) $siblings[] = $m + ['url' => self::modelUrl($make, $m['model'])];

        $data = compact('make', 'model', 'items', 'cols', 'years', 'y1', 'y2', 'positions', 'drives', 'cbrgs', 'pieces', 'trans', 'engines', 'siblings')
            + ['parts' => count($pns), 'fitments' => $rows->count(), 'price_min' => $priceMin, 'price_max' => $priceMax,
               'url' => self::modelUrl($make, $model), 'make_url' => self::makeUrl($make), 'make_slug' => self::slug($make), 'slug' => self::slug($model),
               'logo' => $this->logo($make), 'make_models' => count($all),
               'finder_url' => '/catalog/driveshafts?' . http_build_query(['make' => $make, 'model' => $model])];
        $data['intro'] = $this->intro($data);
        $data['faq'] = $this->faq($data);
        $span = $y1 === $y2 ? (string) $y1 : "{$y1}–{$y2}";
        // searches name the position ("f150 rear driveshaft", "front drive shaft"): say which ones the page covers
        $pos = array_values(array_intersect(['front', 'rear'], array_map('strtolower', $positions)));
        $core = match (true) {
            count($pos) === 2 => __(':make :model Front & Rear Driveshafts', ['make' => $make, 'model' => $model]),
            $pos === ['rear'] => __(':make :model Rear Driveshaft', ['make' => $make, 'model' => $model]),
            $pos === ['front'] => __(':make :model Front Driveshaft', ['make' => $make, 'model' => $model]),
            default => __(':make :model Driveshaft', ['make' => $make, 'model' => $model]),
        };
        $data['title'] = self::title($core, $span);
        if (mb_strlen($data['title']) > 60 || !str_contains($data['title'], $span)) $data['title'] = self::title(__(':make :model Driveshaft', ['make' => $make, 'model' => $model]), $span);
        $data['desc'] = self::desc([
            trans_choice('{1} Complete driveshafts for the :span :mm: :n part number.|[2,*] Complete driveshafts for the :span :mm: :n part numbers.', count($pns), ['span' => $span, 'mm' => "$make $model", 'n' => count($pns)]),
            $priceMin !== null ? __('Priced from :price.', ['price' => self::money($priceMin)]) : __('Call for pricing.'),
            $cbrgs ? __('Year-by-year fitment table with center bearing numbers.') : __('Year-by-year fitment table.'),
            __('Assembled and balanced, ships from Florida.'),
        ]);
        return $data;
    }

    public static function join(array $list): string
    {
        $list = array_values($list);
        if (count($list) <= 1) return (string) ($list[0] ?? '');
        $last = array_pop($list);
        return implode(', ', $list) . ' ' . __('and') . ' ' . $last;
    }

    /** Positions and the like come from the fitment data in English ("rear", "front"). */
    private static function word(string $w): string
    {
        return __(strtolower(trim($w)));
    }

    private function intro(array $d): string
    {
        $n = $d['parts']; $mm = "{$d['make']} {$d['model']}";
        $s = $d['y1'] === $d['y2']
            ? trans_choice('{1} We list :n complete driveshaft for the :mm, covering the :y model year.|[2,*] We list :n complete driveshafts for the :mm, covering the :y model year.', $n, ['n' => $n, 'mm' => $mm, 'y' => $d['y1']])
            : trans_choice('{1} We list :n complete driveshaft for the :mm, covering model years :y1 to :y2.|[2,*] We list :n complete driveshafts for the :mm, covering model years :y1 to :y2.', $n, ['n' => $n, 'mm' => $mm, 'y1' => $d['y1'], 'y2' => $d['y2']]);
        if ($d['positions']) {
            $pos = self::join(array_map([self::class, 'word'], $d['positions']));
            $drv = $d['drives'] ? self::join($d['drives']) : null;
            $s .= ' ' . match (true) {
                $n === 1 => $drv ? __('It is a :pos shaft for :drv models.', ['pos' => $pos, 'drv' => $drv]) : __('It is a :pos shaft.', ['pos' => $pos]),
                count($d['positions']) === 1 => $drv ? __('All of them are :pos shafts for :drv drivetrains.', ['pos' => $pos, 'drv' => $drv]) : __('All of them are :pos shafts.', ['pos' => $pos]),
                default => $drv ? __('They cover the :pos positions for :drv drivetrains.', ['pos' => $pos, 'drv' => $drv]) : __('They cover the :pos positions.', ['pos' => $pos]),
            };
        } elseif ($d['drives']) {
            $s .= ' ' . __('Applications cover :drv drivetrains.', ['drv' => self::join($d['drives'])]);
        }
        $pc = array_filter($d['pieces']);
        if (count($pc) === 1) {
            $k = array_key_first($pc);
            $kind = __([1 => 'one-piece', 2 => 'two-piece', 3 => 'three-piece', 4 => 'four-piece'][$k]);
            $s .= ' ' . ($n === 1 ? __('It is a :kind design.', ['kind' => $kind]) : __('They are :kind designs.', ['kind' => $kind]));
        } elseif (count($pc) > 1) {
            $s .= ' ' . __('Both one-piece and multi-piece designs are listed, depending on year and configuration.');
        }
        if ($d['cbrgs']) {
            $c = implode(', ', array_slice($d['cbrgs'], 0, 4)) . (count($d['cbrgs']) > 4 ? ' ' . __('and others') : '');
            $s .= ' ' . __('Two-piece shafts ship as a complete assembly with the center support bearing (:list) installed and balanced.', ['list' => $c]);
        }
        if ($d['trans'] && count($d['trans']) > 1) $s .= ' ' . __('Check the transmission column: :list versions use different shafts.', ['list' => self::join($d['trans'])]);
        $s .= ' ' . ($d['price_min'] !== null
            ? __('Prices start at :price. Pick your year below, match drive and transmission, and add the part to your cart or open it for photos, weight and every vehicle it fits.', ['price' => self::money($d['price_min'])])
            : __('Call (813) 524-6491 for pricing. Pick your year below and match drive and transmission to find your part number.'));
        return $s;
    }

    private function faq(array $d): array
    {
        $mm = "{$d['make']} {$d['model']}"; $out = [];
        // 1. most recent year
        $year = array_key_first($d['years']);
        $fit = [];
        foreach ($d['items'] as $i) if (in_array($year, explode(',', $i['years_csv']))) $fit[$i['part_number']] ??= $i; // one entry per part number
        $fit = array_values($fit);
        $list = array_map(fn ($i) => $i['sku'] . ' (' . implode(', ', array_filter([($p = strtolower(preg_replace('/\s*-\s*.*$/', '', $i['loc']))) ? self::word($p) : null, $i['drive'] ?: null])) . ')', array_slice($fit, 0, 6));
        $list = implode(', ', array_map(fn ($x) => str_replace(' ()', '', $x), $list)) . (count($fit) > 6 ? ' ' . __('and :n more', ['n' => count($fit) - 6]) : '');
        $out[] = ['q' => __('Which driveshaft fits a :year :mm?', ['year' => $year, 'mm' => $mm]),
                  'a' => trans_choice('{1} For the :year :mm we list :n part number: :list. Use the drive, position and transmission columns in the table to pick the exact one, or open the part page to see every configuration it fits.|[2,*] For the :year :mm we list :n part numbers: :list. Use the drive, position and transmission columns in the table to pick the exact one, or open the part page to see every configuration it fits.', count($fit), ['year' => $year, 'mm' => $mm, 'n' => count($fit), 'list' => $list])];
        // 2. pieces
        $pc = array_filter($d['pieces']); $words = [1 => 'one-piece', 2 => 'two-piece', 3 => 'three-piece', 4 => 'four-piece'];
        if (count($pc) === 1) {
            $k = array_key_first($pc);
            $a = $k === 1
                ? __('The :mm driveshafts we list are one-piece shafts, so there is no center support bearing to wear out.', ['mm' => $mm])
                : trans_choice('{1} The :mm driveshafts we list are :kind shafts with :b center support bearing.|[2,*] The :mm driveshafts we list are :kind shafts with :b center support bearings.', $k - 1, ['mm' => $mm, 'kind' => __($words[$k]), 'b' => $k - 1]);
        } elseif (count($pc) > 1) {
            $parts = [];
            foreach ($pc as $k => $v) $parts[] = trans_choice('{1} :v is :kind|[2,*] :v are :kind', $v, ['v' => $v, 'kind' => __($words[$k])]);
            $a = __('It depends on the year and configuration. Of the :n shafts, :list. Multi-piece shafts are listed as a complete assembly or as individual sections (see the Position column).', ['n' => $d['parts'], 'list' => self::join($parts)]);
        } else {
            $a = $d['cbrgs'] ? __('The :mm uses a two-piece shaft with a center support bearing; the complete assembly is listed under the Position column.', ['mm' => $mm]) : __('The :mm applications we list are one-piece shafts; the Position column shows front and rear fitments.', ['mm' => $mm]);
        }
        $out[] = ['q' => __('Is the :mm driveshaft one piece or two piece?', ['mm' => $mm]), 'a' => $a];
        // 3. center bearing
        $out[] = ['q' => __('Does it come with the center support bearing?'),
                  'a' => $d['cbrgs'] ? __('Yes. Two-piece :mm shafts ship as a complete assembly with the center support bearing (:list) already installed and the whole shaft balanced, so it bolts in with no extra parts.', ['mm' => $mm, 'list' => implode(', ', array_slice($d['cbrgs'], 0, 4))])
                      : (array_sum(array_slice($d['pieces'], 1, null, true)) > 0
                          ? __('Yes. The two-piece :mm shafts ship as a complete assembly with the center support bearing installed and the whole shaft balanced; the bearing number is listed on each part page. One-piece versions have no center bearing.', ['mm' => $mm])
                          : __('The :mm driveshafts listed here are one-piece shafts, so no center support bearing is needed. Every shaft ships assembled and balanced, ready to install.', ['mm' => $mm]))];
        // 4. drives or years
        if (count($d['drives']) > 1) {
            $cnt = [];
            foreach ($d['drives'] as $dr) $cnt[] = __(':n for :drive', ['n' => count(array_unique(array_map(fn ($i) => $i['part_number'], array_filter($d['items'], fn ($i) => $i['drive'] === $dr)))), 'drive' => $dr]);
            $out[] = ['q' => __('Does the same shaft fit :a and :b models?', ['a' => $d['drives'][0], 'b' => $d['drives'][1]]),
                      'a' => __('No, the drivetrain changes the shaft. We list :list :mm applications. Pick your drive in the table before you compare part numbers.', ['list' => self::join($cnt), 'mm' => $mm])];
        } else {
            $out[] = ['q' => __('Which :mm years do you cover?', ['mm' => $mm]),
                      'a' => $d['y1'] === $d['y2']
                          ? trans_choice('{1} Our :mm driveshaft listings cover the :y model year, with :n part number across :f year-and-configuration fitments. If your year is missing, call us and we will match it or build one to spec.|[2,*] Our :mm driveshaft listings cover the :y model year, with :n part numbers across :f year-and-configuration fitments. If your year is missing, call us and we will match it or build one to spec.', $d['parts'], ['mm' => $mm, 'y' => $d['y1'], 'n' => $d['parts'], 'f' => $d['fitments']])
                          : trans_choice('{1} Our :mm driveshaft listings cover model years :y1 through :y2, with :n part number across :f year-and-configuration fitments. If your year is missing, call us and we will match it or build one to spec.|[2,*] Our :mm driveshaft listings cover model years :y1 through :y2, with :n part numbers across :f year-and-configuration fitments. If your year is missing, call us and we will match it or build one to spec.', $d['parts'], ['mm' => $mm, 'y1' => $d['y1'], 'y2' => $d['y2'], 'n' => $d['parts'], 'f' => $d['fitments']])];
        }
        // 5. price
        $out[] = ['q' => __('How much does a :mm driveshaft cost?', ['mm' => $mm]),
                  'a' => $d['price_min'] !== null
                      ? ($d['price_min'] === $d['price_max'] ? __('A complete :mm driveshaft is :price.', ['mm' => $mm, 'price' => self::money($d['price_min'])]) : __('Complete :mm driveshafts run from :a to :b depending on year, drive and configuration.', ['mm' => $mm, 'a' => self::money($d['price_min']), 'b' => self::money($d['price_max'])]))
                        . ' ' . __('Prices are for a new, assembled and balanced shaft; add it to your cart online or call (813) 524-6491 to order by phone.')
                      : __('Call (813) 524-6491 or use the contact form for a quote on a :mm driveshaft. We can also build one to your measurements if your configuration is not listed.', ['mm' => $mm])];
        return $out;
    }
}
