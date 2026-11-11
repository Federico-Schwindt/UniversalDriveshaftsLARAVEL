<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The vehicle makes a flange yoke is listed for, so the builder can ask "which make?" before the yoke list
 * (owner, 2026-11-11: "un paso previo que pregunte la marca… pero debe ser muy robusto").
 *
 * A make counts only when data about this very part names it: its title and notes attribute; what PTI says about it
 * when it is linked to PTI as the same part (notes, sales story, the same part's other numbers and its OEM numbers —
 * App\Support\PtiXref); and the vehicle tags our supplier Action Machine gives the same part number, when the
 * series and the bolt circle agree too (builder:sync-am-yokes). Product descriptions are left out on purpose: their
 * general wording ("standard on many Volkswagen, BMW and Mercedes differentials") is not proof that this part fits.
 * A make named only in "does not fit …" is dropped. Parts with no make stay reachable through "Other make or not sure".
 */
class YokeMakes
{
    /** key => [label, pattern of names and models that mean this make]. Order = the order customers see. */
    public const MAKES = [
        'ford'       => ['Ford / Lincoln / Mercury', 'Ford|Lincoln|Mercury|Mustang|F-?150|F-?250|F-?350|F-?450|Super\s?Duty|Thunderbird|T-?Bird|Bronco|Explorer|Expedition|(?<!Polaris\s)Ranger|Econoline|E-?350'],
        'gm'         => ['GM / Chevrolet / GMC', 'GM|GMC|Chevrolet|Chevy|Silverado|Corvette|Camaro|Tahoe|Suburban|Cadillac|Escalade|Hummer|Pontiac|Buick|Oldsmobile'],
        'dodge'      => ['Dodge / Ram / Chrysler', 'Dodge|Ram|Chrysler|Mopar|Charger|Challenger|Hellcat|SRT|Viper|Durango|Dakota|Hemi'],
        'jeep'       => ['Jeep', 'Jeep|Wrangler|Grand\s?Cherokee|Cherokee|Gladiator|Commander'],
        'toyota'     => ['Toyota / Lexus', 'Toyota|Lexus|Tacoma|Tundra|4\s?Runner|Land\s?Cruiser|Sequoia|Hilux'],
        'nissan'     => ['Nissan / Infiniti', 'Nissan|Infiniti|Datsun|Titan|Frontier|Xterra|Pathfinder|Armada'],
        'honda'      => ['Honda / Acura', 'Honda|Acura'],
        'isuzu'      => ['Isuzu', 'Isuzu'],
        'mazda'      => ['Mazda', 'Mazda'],
        'mitsubishi' => ['Mitsubishi', 'Mitsubishi|Fuso'],
        'subaru'     => ['Subaru', 'Subaru'],
        'suzuki'     => ['Suzuki / Geo', 'Suzuki|Geo\s+Tracker|Samurai|Sidekick'],
        'hyundai'    => ['Hyundai / Kia', 'Hyundai|Kia'],
        'bmw'        => ['BMW', 'BMW'],
        'mercedes'   => ['Mercedes-Benz / Sprinter', 'Mercedes|Benz|Sprinter'],
        'vw'         => ['Volkswagen / Audi', 'Volkswagen|VW|Audi'],
        'landrover'  => ['Land Rover', 'Land\s?Rover|Range\s?Rover'],
        'porsche'    => ['Porsche', 'Porsche'],
        'volvo'      => ['Volvo', 'Volvo'],
    ];

    /** The makes a piece of the part's own text names (negative mentions removed). */
    public static function detect(string $text): array
    {
        // "Does not fit Demon/Demon170 but fits most other Charger/Challenger": only the part after "but" counts
        $text = preg_replace('/\b(?:does\s*n[o\']t|doesn\'t|will\s*not|won\'t|not\s+for|not)\s+(?:fit|for)\b.*?(?=\bbut\b|[.;\n]|$)/i', ' ', $text);
        // "Use 3102-50 for GM Astro Van": that make belongs to the other part
        $text = preg_replace('/\buse\s+[A-Z0-9][\w\-]*\s+for\b[^.;\n]*/i', ' ', $text);
        // "This 1350 series Ford flange fits the GM full size vans": the first make names the flange's style
        $text = preg_replace('/\b\w+(\s+style)?\s+flange\s+fits\b/i', ' fits', $text);
        // marine flywheel flanges name an engine maker ("GM 6.0L engines"), not a vehicle
        $text = preg_replace('/[^.;\n|]*\b(?:boats?|marine|flywheels?)\b[^.;\n|]*/i', ' ', $text);
        $out = [];
        foreach (self::MAKES as $key => [, $re]) {
            if (preg_match('/\b(?:' . $re . ')\b/i', $text)) $out[] = $key;
        }
        return $out;
    }

    public static function label(string $key): string
    {
        return self::MAKES[$key][0] ?? '';
    }

    public static function valid(string $key): bool
    {
        return isset(self::MAKES[$key]);
    }

    /** product id => [make keys] for every published product in the category (cached; a catalog edit or a sync starts a new cache). */
    public static function forCategory(int $catId): array
    {
        return array_map(fn ($x) => array_keys($x), self::sources($catId));
    }

    /** product id => [make key => [where it was read: title, notes, pti, am]], for the admin and the checks. */
    public static function sources(int $catId): array
    {
        $am = @filemtime(storage_path('app/' . \App\Console\Commands\BuilderSyncAmYokes::FILE)) ?: 0;
        $ver = Cache::get('catalog_ver', 0) . '-' . DB::table('pti_parts')->max('updated_at') . '-' . $am;
        return Cache::remember('dsb_yoke_makes_v4_' . $catId . '_' . md5($ver), 600, function () use ($catId) {
            $w = DB::connection('wp');
            $rows = $w->table('wp_posts as p')->where('p.post_type', 'product')->where('p.post_status', 'publish')
                ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('wp_term_relationships as tr')
                    ->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                    ->whereColumn('tr.object_id', 'p.ID')->where('tt.taxonomy', 'product_cat')->where('tt.term_id', $catId))
                ->pluck('p.post_title', 'p.ID');
            if ($rows->isEmpty()) return [];
            $ids = $rows->keys()->all();
            $attr = fn (string $tax) => $w->table('wp_term_relationships as tr')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->whereIn('tr.object_id', $ids)->where('tt.taxonomy', $tax)
                ->get(['tr.object_id', 't.name'])->groupBy('object_id');
            $notes = $attr('pa_notes'); $series = $attr('pa_series'); $bc = $attr('pa_bolt-circle-dia');
            $skus = $w->table('wp_postmeta')->whereIn('post_id', $ids)->where('meta_key', '_sku')->pluck('meta_value', 'post_id');
            $pti = PtiXref::texts();
            // and PTI's notes / story for every number our own SKU field names ("3102-36U,2-2-349,N2-2-349"), ours or ours + U
            $ptiNum = [];
            foreach (DB::table('pti_parts')->get(['part_number', 'specs', 'story']) as $p) {
                $t = trim((string) (json_decode((string) $p->specs, true)['notes'] ?? '') . ".\n" . (string) $p->story, ".\n ");
                if ($t !== '') $ptiNum[self::norm((string) $p->part_number)][] = $t;
            }
            // the supplier's tagged yokes, by every number they are sold under
            $am = [];
            foreach ((array) (json_decode((string) @file_get_contents(storage_path('app/' . \App\Console\Commands\BuilderSyncAmYokes::FILE)), true)['items'] ?? []) as $it) {
                if (!$it['makes']) continue;
                foreach ($it['numbers'] as $n) $am[self::norm($n)][] = $it;
            }
            $out = [];
            foreach ($rows as $id => $title) {
                $add = function (array $makes, string $src) use (&$out, $id) { foreach ($makes as $m) $out[(int) $id][$m][$src] = true; };
                $add(self::detect((string) $title), 'title');
                $add(self::detect(collect($notes[$id] ?? [])->pluck('name')->implode(".\n")), 'notes');
                $mine = $pti[$id] ?? [];
                foreach (preg_split('/\s*[,|]\s*/', (string) ($skus[$id] ?? '')) as $sku) {
                    $k = self::norm($sku);
                    if ($k !== '') $mine = array_merge($mine, $ptiNum[$k] ?? [], str_ends_with($k, 'U') ? ($ptiNum[substr($k, 0, -1)] ?? []) : []);
                }
                $add(self::detect(implode(".\n", array_unique($mine))), 'pti');
                // Action Machine: same number (ours or ours without the U), and the same series and bolt circle
                $mySeries = collect($series[$id] ?? [])->pluck('name')->flatMap(fn ($v) => preg_split('/\s*,\s*/', strtoupper((string) $v)))->all();
                $myBc = (float) (collect($bc[$id] ?? [])->pluck('name')->first() ?? 0);
                foreach (preg_split('/\s*[,|]\s*/', (string) ($skus[$id] ?? '')) as $sku) {
                    $k = self::norm($sku);
                    foreach (array_unique([$k, preg_replace('/U$/', '', $k)]) as $kk) foreach ($am[$kk] ?? [] as $it) {
                        if ($it['series'] === '' || !in_array(strtoupper($it['series']), $mySeries, true)) continue;
                        if ((float) $it['bolt_circle'] > 0 && $myBc > 0 && abs((float) $it['bolt_circle'] - $myBc) > 0.011) continue;
                        $add($it['makes'], 'am');
                    }
                }
            }
            return array_map(fn ($m) => array_map('array_keys', $m), $out);
        });
    }

    private static function norm(string $part): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', trim($part)));
    }
}
