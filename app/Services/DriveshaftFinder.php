<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Vehicle-to-driveshaft matcher.
 *
 * Rules (all covered by `php artisan catalog:verify-driveshafts`):
 *  - NULL in a fitment column means "not restricted", never "unknown": such a row survives any choice.
 *  - Only ask a question that can still split the candidates (2+ distinct non-NULL values).
 *  - Stop when one part number remains or nothing can split further.
 *  - Every option offered is taken from live candidates, so none can lead to zero results.
 */
class DriveshaftFinder
{
    public const FACETS = ['loc', 'engine', 'trans', 'trans_speeds', 'trans_code', 'cab', 'bed', 'chassis',
        'doors', 'trim', 'rear_wheels', 'transfer_case', 'differential'];

    public const LABELS = [
        'make' => 'Make', 'year' => 'Year', 'model' => 'Model', 'drive' => 'Drive', 'loc' => 'Shaft location',
        'engine' => 'Engine', 'trans' => 'Transmission', 'trans_speeds' => 'Transmission speeds',
        'trans_code' => 'Transmission code', 'cab' => 'Cab', 'bed' => 'Bed length', 'chassis' => 'Wheelbase',
        'doors' => 'Doors', 'trim' => 'Trim', 'rear_wheels' => 'Rear wheels', 'transfer_case' => 'Transfer case',
        'differential' => 'Differential',
    ];

    /** Fitment rows of the shafts we can sell: a shaft PTI dropped (is_active = 0) takes its vehicles off the site with it. */
    public static function fitments(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ds_fitments')->whereIn('ds_fitments.part_number', DB::table('ds_parts')->where('is_active', 1)->select('part_number'));
    }

    public function makes(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('finder:makes', 3600, fn () => self::fitments()->distinct()->orderBy('make')->pluck('make')->all());
    }

    /** @param array $in raw request input; unknown keys and empty values are ignored */
    public function step(array $in): array
    {
        $chosen = $this->clean($in);
        return \Illuminate\Support\Facades\Cache::remember('finder:step:' . md5(json_encode($chosen)), 600, fn () => $this->stepUncached($chosen));
    }

    private function stepUncached(array $chosen): array
    {
        $base = ['chosen' => $chosen, 'next' => null, 'candidate_count' => 0, 'fitment_rows' => 0, 'results' => null];

        if (!isset($chosen['make'])) {
            return ['next' => $this->question('make', $this->makes())] + $base;
        }
        $q = self::fitments()->where('make', $chosen['make']);
        if (!isset($chosen['year'])) {
            $years = (clone $q)->distinct()->orderByDesc('year')->pluck('year')->map(fn ($y) => (string) $y)->all();
            return ['next' => $this->question('year', $years), 'candidate_count' => (clone $q)->distinct()->count('part_number')] + $base;
        }
        $q->where('year', (int) $chosen['year']);
        if (!isset($chosen['model'])) {
            $models = (clone $q)->distinct()->pluck('model')->all();
            natcasesort($models);
            return ['next' => $this->question('model', array_values($models)), 'candidate_count' => (clone $q)->distinct()->count('part_number')] + $base;
        }
        $q->where('model', $chosen['model']);
        foreach (array_merge(['drive'], self::FACETS) as $f) {
            if (isset($chosen[$f])) {
                $v = $chosen[$f];
                $q->where(fn ($w) => $w->whereNull($f)->orWhere($f, $v)); // NULL = unrestricted
            }
        }
        $rows = $q->get();
        $parts = $rows->pluck('part_number')->unique();
        $base['candidate_count'] = $parts->count();
        $base['fitment_rows'] = $rows->count();

        if ($parts->count() > 1) {
            foreach (array_merge(['drive'], self::FACETS) as $f) {
                if (isset($chosen[$f])) continue;
                $vals = $rows->pluck($f)->filter(fn ($v) => $v !== null && $v !== '')->unique()->values()->all();
                if (count($vals) >= 2) {
                    natcasesort($vals);
                    // like the reference portal: once the list is short, show it under the question
                    $preview = $parts->count() <= 40 ? ['results' => $this->results($rows)] : [];
                    return ['next' => $this->question($f, array_values($vals))] + $preview + $base;
                }
            }
        }
        return ['results' => $this->results($rows)] + $base;
    }

    /** Free-text lookup by part number, centre-bearing number or the ID codes that live in notes. */
    /** Public SKU of a finder part: the supplier number behind the UDS "U" prefix (2594-173 -> U2594-173). */
    public static function sku(string $pn): string
    {
        return 'U' . preg_replace('/^[Uu](?=\d)/', '', trim($pn));
    }

    /** The bare supplier number from either form (U2594-173 or 2594-173). */
    public static function pn(string $in): string
    {
        return preg_replace('/^[Uu](?=\d)/', '', trim($in));
    }

    /** Paginated catalog of every active finder part, for the Complete Driveshafts category page. */
    /** Cascading filter options for the category table: years for a make, models for a make + year. */
    public function options(string $make = '', int $year = 0): array
    {
        // only real makes / plausible years reach the cache key (bounded key space)
        $make = $this->knownMake($make) ?? '';
        if ($year < 1900 || $year > 2100) $year = 0;
        return \Illuminate\Support\Facades\Cache::remember("finder:opts:$make:$year", 600, fn () => $this->optionsUncached($make, $year));
    }

    private function optionsUncached(string $make, int $year): array
    {
        $out = ['makes' => $this->makes(), 'years' => [], 'models' => []];
        if ($make === '') return $out;
        $q = self::fitments()->where('make', $make);
        $out['years'] = (clone $q)->distinct()->orderByDesc('year')->pluck('year')->map(fn ($y) => (string) $y)->all();
        if ($year) {
            $models = (clone $q)->where('year', $year)->distinct()->pluck('model')->all();
            natcasesort($models); $out['models'] = array_values($models);
        }
        return $out;
    }

    public function listing(string $q = '', int $page = 1, int $per = 60, string $sort = 'part_asc', array $vehicle = []): array
    {
        $base = DB::table('ds_parts')->where('is_active', 1);
        $q = trim($q);
        if (!empty($vehicle['make'])) {
            $fq = self::fitments()->where('make', $vehicle['make']);
            if (!empty($vehicle['year'])) $fq->where('year', (int) $vehicle['year']);
            if (!empty($vehicle['model'])) $fq->where('model', $vehicle['model']);
            $base->whereIn('part_number', $fq->distinct()->pluck('part_number'));
        }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], self::pn($q)) . '%';
            $fit = self::fitments()->where(fn ($w) => $w->where('make', 'like', $like)->orWhere('model', 'like', $like))->distinct()->limit(500)->pluck('part_number');
            $base->where(fn ($w) => $w->where('part_number', 'like', $like)->orWhere('description', 'like', $like)->orWhereIn('part_number', $fit));
        }
        $total = (clone $base)->count();
        $pages = (int) ceil($total / $per);
        $page = max(1, min($page, max(1, $pages)));
        $sel = clone $base;
        match ($sort) {
            'part_desc' => $sel->orderByDesc('part_number'),
            'price_asc' => $sel->orderByRaw('COALESCE(price_retail, price_auto, price_list) asc'),
            'price_desc' => $sel->orderByRaw('COALESCE(price_retail, price_auto, price_list) desc'),
            default => $sel->orderBy('part_number'),
        };
        $pns = $sel->offset(($page - 1) * $per)->limit($per)->pluck('part_number')->all();
        $items = $this->results(self::fitments()->whereIn('part_number', $pns)->get(), true, $pns);
        $order = array_flip($pns);
        usort($items, fn ($a, $b) => $order[$a['part_number']] <=> $order[$b['part_number']]);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function search(string $term): array
    {
        $term = self::pn($term);
        if (mb_strlen($term) < 2) return [];
        // shafts whose fitments name it (part number, carrier bearing, notes) first, then by part number or description,
        // then those holding every word in any order, vehicles and years included ("ford f250 2005", "silverado 2pc")
        $idx = $this->searchIndex(); $pns = [];
        foreach ($idx['p'] as $pn => $r) { if (count($pns) >= 40) break; if ($r[1] !== '' && CatalogService::found($r[1], $term)) $pns[$pn] = true; }
        foreach ($idx['p'] as $pn => $r) { if (count($pns) >= 40) break; if (CatalogService::found($r[0], $term)) $pns[$pn] = true; }
        $words = array_values(array_diff(CatalogService::understand($term, $idx, false)['words'], ['driveshaft', 'shaft', 'complete', 'assembly']));   // every row is one
        if ($words) foreach ($idx['p'] as $pn => $r) { if (count($pns) >= 40) break; if (CatalogService::hasWords($r[2], $words)) $pns[$pn] = true; }
        $pns = collect(array_map('strval', array_keys($pns)));
        if ($pns->isEmpty()) return [];
        $rows = self::fitments()->whereIn('part_number', $pns)->get();
        $out = $this->results($rows, true);
        if (array_filter($words, fn ($w) => preg_match('/[a-z]/', $w) || preg_match('/^(19|20)\d\d$/', $w))) $out = $this->vehicleFirst($out, $rows, $words);   // a vehicle or a year named
        $have = array_column($out, 'part_number');
        foreach ($pns as $pn) { // parts with no fitment rows at all (boat shafts)
            if (!in_array($pn, $have, true)) $out = array_merge($out, $this->results(collect(), true, [$pn]));
        }
        return $out;
    }

    /**
     * The vehicle a search names first: shafts with a fitment row holding the most query words (a year by its own row,
     * so "2005" picks the 2002–2007 F250 group) ahead of the rest, and that vehicle at the top of each shaft's list.
     */
    private function vehicleFirst(array $out, $rows, array $words): array
    {
        $years = array_filter($words, fn ($w) => preg_match('/^(19|20)\d\d$/', $w)); $rest = array_diff($words, $years);
        $text = []; $best = [];
        foreach ($rows as $r) {
            $car = $r->make . '|' . $r->model . '|' . $r->drive;
            $t = $text[$car] ??= CatalogService::canon($r->make . ' ' . $r->model . ' ' . $r->drive);
            $n = count(array_filter($rest, fn ($w) => CatalogService::hasWords($t, [$w]))) + (in_array((string) $r->year, $years, true) ? 1 : 0);
            if ($n > ($best[$r->part_number][0] ?? 0)) $best[$r->part_number] = [$n, $car];
        }
        foreach ($out as $i => &$p) {
            $p['_rank'] = [-($best[$p['part_number']][0] ?? 0), $i];
            if (!isset($best[$p['part_number']])) continue;
            [$make, $model, $drive] = explode('|', $best[$p['part_number']][1]);
            $g = $rows->where('part_number', $p['part_number'])->where('make', $make)->where('model', $model)->where('drive', $drive);
            $fit = trim($g->min('year') . '-' . $g->max('year') . ' ' . $make . ' ' . $model . ' ' . $drive);
            $p['fits'] = array_values(array_unique(array_merge([$fit], $p['fits'] ?? [])));
        }
        unset($p);
        usort($out, fn ($a, $b) => $a['_rank'] <=> $b['_rank']);
        return array_map(function ($p) { unset($p['_rank']); return $p; }, $out);
    }

    /**
     * The active shafts as lowercase strings for search(): 'p' => [part number => [part number + description, part number
     * + carrier bearing + notes of its fitment rows ('' without any), all of it plus the makes, models and years it fits
     * in catalog spelling (CatalogService::canon())]], with that text's vocabulary ('v', 'vb') for typo correction.
     * The LIKE over 42,000 fitment rows took 65–230 ms of every search; this scan takes about a millisecond. Active parts
     * only: a shaft PTI dropped never resurfaces.
     */
    private function searchIndex(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('finder:search-index-v2', 21600,   // PTI's discovery and price runs forget it
            function () {
            $parts = []; $fit = []; $cars = [];
            foreach (DB::table('ds_parts')->where('is_active', 1)->get(['part_number', 'description']) as $p) $parts[$p->part_number] = (string) $p->description;
            foreach (self::fitments()->select('part_number', 'c_brg', 'notes')->distinct()->get() as $f) {
                $fit[$f->part_number] ??= [$f->part_number];
                array_push($fit[$f->part_number], (string) $f->c_brg, (string) $f->notes);
            }
            foreach (self::fitments()->select('part_number', 'make', 'model', 'year')->distinct()->get() as $f) {
                $cars[$f->part_number][(string) $f->make] = true; $cars[$f->part_number][(string) $f->model] = true; $cars[$f->part_number][(string) $f->year] = true;
            }
            $idx = []; $vocab = [];
            foreach ($parts as $pn => $desc) {
                $words = CatalogService::canon($pn . ' ' . $desc . ' ' . implode(' ', array_unique($fit[$pn] ?? [])) . ' ' . implode(' ', array_keys($cars[$pn] ?? [])));
                foreach (CatalogService::vocabOf($words) as $w) $vocab[$w] = ($vocab[$w] ?? 0) + 1;
                $idx[$pn] = [CatalogService::haystack([$pn, $desc]), isset($fit[$pn]) ? CatalogService::haystack(array_unique($fit[$pn])) : '', $words];
            }
            ksort($idx, SORT_STRING | SORT_FLAG_CASE);
            return ['p' => $idx, 'v' => $vocab, 'vb' => ' ' . implode(' ', array_keys($vocab)) . ' '];
        });
    }

    /** Customer-safe part payload. price_cost and supplier are never read into it. */
    public function publicPart(string $pn): ?array
    {
        $pn = self::pn($pn);
        $r = $this->results(DB::table('ds_fitments')->where('part_number', $pn)->get(), true, [$pn]);
        return $r[0] ?? null;
    }

    /** Everything the part page needs. Built only from customer-safe fields. */
    public function partPage(string $pn): ?array
    {
        $pn = self::pn($pn);
        $rows = DB::table('ds_fitments')->where('part_number', $pn)->orderBy('make')->orderBy('model')->orderBy('year')->get();
        $part = $this->results($rows, true, [$pn])[0] ?? null;
        if (!$part || (!$rows->count() && !DB::table('ds_parts')->where('part_number', $pn)->exists())) return null;

        // applications: consecutive model years collapsed into ranges, per make / model / drive
        $apps = [];
        foreach ($rows->groupBy(fn ($r) => $r->make . '|' . $r->model . '|' . ($r->drive ?? '')) as $g) {
            $f = $g->first(); $years = $g->pluck('year')->unique()->sort()->values()->all(); $start = $prev = null;
            foreach (array_merge($years, [null]) as $y) {
                if ($start === null) { $start = $prev = $y; continue; }
                if ($y !== null && $y === $prev + 1) { $prev = $y; continue; }
                $apps[] = ['years' => $start === $prev ? (string) $start : "$start-$prev", 'make' => $f->make, 'model' => $f->model,
                           'drive' => $f->drive, 'url' => '/catalog/driveshafts?' . http_build_query(array_filter(['make' => $f->make, 'year' => $prev, 'model' => $f->model, 'drive' => $f->drive]))];
                $start = $prev = $y;
            }
        }
        // distinct configurations (deep facets), years collapsed
        $cols = array_values(array_filter(self::FACETS, fn ($c) => $rows->pluck($c)->filter()->isNotEmpty()));
        $configs = [];
        foreach ($rows->groupBy(fn ($r) => implode('|', array_map(fn ($c) => (string) $r->$c, array_merge(['make', 'model', 'drive'], $cols)))) as $g) {
            $f = $g->first(); $ys = $g->pluck('year');
            $configs[] = ['vehicle' => trim(($ys->min() == $ys->max() ? $ys->min() : $ys->min() . '-' . $ys->max()) . " {$f->make} {$f->model}"),
                          'drive' => $f->drive, 'values' => array_map(fn ($c) => $f->$c, $cols)];
        }
        preg_match('/(\d)\s*PC/i', (string) $part['description'], $m);
        return $part + ['applications' => $apps, 'config_cols' => array_map(fn ($c) => self::LABELS[$c], $cols),
            'configs' => array_slice($configs, 0, 250), 'config_total' => count($configs),
            'pieces' => isset($m[1]) ? max(1, min(3, (int) $m[1])) : (stripos((string) $part['description'], 'boat') !== false ? 1 : null),
            'year_min' => $rows->min('year'), 'year_max' => $rows->max('year'),
            'makes' => $rows->pluck('make')->unique()->values()->all()];
    }

    public static function customerPrice(object $p): ?float
    {
        if (!$p->is_active) return null;
        if ($p->price_retail !== null) return (float) $p->price_retail;          // manual override
        if (($p->price_auto ?? null) !== null) return (float) $p->price_auto;    // config/driveshaft_pricing.php rules
        if ($p->price_list === null) return null;
        $markup = (float) config('uds.ds_markup_pct', 0);
        return round((float) $p->price_list * (1 + $markup / 100), 2);
    }

    private function results($rows, bool $withFits = false, array $extraPns = []): array
    {
        $pns = $rows->pluck('part_number')->merge($extraPns)->unique()->values();
        $parts = DB::table('ds_parts')->whereIn('part_number', $pns)
            ->get(['part_number', 'description', 'condition', 'notes', 'price_list', 'price_retail', 'price_auto', 'core_price', 'weight',
                   'series', 'is_buildable', 'is_active', 'main_image', 'images'])->keyBy('part_number');
        $out = [];
        foreach ($pns as $pn) {
            $p = $parts[$pn] ?? null;
            $mine = $rows->where('part_number', $pn);
            $facets = [];
            foreach (array_merge(['drive'], self::FACETS) as $f) {
                $v = $mine->pluck($f)->filter()->unique()->values()->all();
                if ($v) $facets[$f] = ['label' => self::LABELS[$f], 'values' => $v];
            }
            $price = $p ? self::customerPrice($p) : null;
            $list = $p && $p->price_list !== null ? (float) $p->price_list : null;
            $item = [
                'part_number' => $pn,
                'sku'         => self::sku($pn),
                'url'         => '/catalog/driveshafts/part/' . rawurlencode(self::sku($pn)),
                'image'       => media_url($p->main_image ?? null, 320) ?: null,
                'description' => $p->description ?? null,
                'condition'   => $p->condition ?? null,
                'series'      => $p->series ?? null,
                'c_brg'       => $mine->pluck('c_brg')->filter()->unique()->values()->all(),
                'notes'       => $mine->pluck('notes')->filter()->unique()->values()->all() ?: array_filter([$p->notes ?? null]),
                'weld_to_weld' => $mine->flatMap(fn ($r) => [$r->weld_to_weld, $r->weld_to_weld_1, $r->weld_to_weld_2, $r->weld_to_weld_3])->filter()->unique()->values()->all(),
                'facets'      => $facets,
                'price'       => $price,
                'price_list'  => null, // supplier list price is internal; we show only our price
                'core_price'  => $p && (float) $p->core_price > 0 ? (float) $p->core_price : null,
                'weight'      => $p && $p->weight !== null ? (float) $p->weight : null,
                'can_buy'     => $price !== null && $price > 0, // a $0 price is "call for price", never a free shaft
            ];
            if ($withFits) {
                $item['images'] = ($p && !empty($p->images)) ? array_values(array_map(fn ($u) => media_url($u, 1200), (array) json_decode($p->images, true))) : [];
                $item['fits'] = $mine->groupBy(fn ($r) => $r->make . '|' . $r->model . '|' . $r->drive)->map(function ($g) {
                    $f = $g->first();
                    return trim($g->min('year') . '-' . $g->max('year') . ' ' . $f->make . ' ' . $f->model . ' ' . $f->drive);
                })->values()->take(12)->all();
            }
            $out[] = $item;
        }
        return $out;
    }

    private function question(string $facet, array $options): array
    {
        return ['facet' => $facet, 'label' => self::LABELS[$facet], 'options' => array_values($options)];
    }

    /** The make as stored ("ford" -> "Ford"), or null when no active shaft fits it. */
    public function knownMake(string $make): ?string
    {
        if ($make === '') return null;
        foreach ($this->makes() as $m) if (strcasecmp($m, $make) === 0) return $m;
        return null;
    }

    private function clean(array $in): array
    {
        $out = [];
        foreach (array_merge(['make', 'year', 'model', 'drive'], self::FACETS) as $k) {
            $v = isset($in[$k]) && is_scalar($in[$k]) ? mb_substr(trim((string) $in[$k]), 0, 80) : '';
            if ($v !== '') $out[$k] = $k === 'year' ? (int) $v : $v;
        }
        if (isset($out['make'])) $out['make'] = $this->knownMake(mb_substr($out['make'], 0, 60));
        if (empty($out['make'])) return [];
        if (!isset($out['year'])) return ['make' => $out['make']];
        if (!isset($out['model'])) return ['make' => $out['make'], 'year' => $out['year']];
        return $out;
    }
}
