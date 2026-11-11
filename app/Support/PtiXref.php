<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Interchange numbers PTI lists for the parts we carry (pti_parts.related, filled by pti:sync --catalog): the Spicer /
 * Neapco / AAM versions of the same part, its newer part number, and the OEM numbers PTI keeps as "virtual" rows.
 * Only relations that mean the same part count — "different LOP", "strap kit", "boot", "redesign" are other parts.
 * Only products linked to a PTI part as that same part (same SKU, SKU + U, or created from it) get them.
 */
class PtiXref
{
    private const SAME = '/xref|xfer|version|new part|alternate|alt\.? supplier|same part|\bnumber\b|\bvs\.?\b/i';
    private const OTHER = '/differ(?!ent\s+suppliers?)|kit|boot|stub|tubing|u-?bolt|sleeve|slip yoke and|redesign|steel vs|aluminum|bolts?\b|replac/i';

    /** Whether a PTI relation names the same part under another number. */
    public static function interchangeable(array $rel): bool
    {
        $note = (string) ($rel['note'] ?? '');
        if (preg_match(self::OTHER, $note)) return false;
        // an OEM number PTI keeps for this part is the part, unless the note says otherwise
        return !empty($rel['alias']) || preg_match(self::SAME, $note) === 1;
    }

    /** "General Motors OEM number is 12471506", "OEM part number: 68036792AA" in PTI's sales story. */
    private const OEM_RX = '/\\bOEM\\s+(?:part\\s+)?(?:number|no\\.?|#)\\s*(?:is|:)?\\s*([A-Z0-9][A-Z0-9\\-]{4,})/i';

    /**
     * PTI part number => [same part under other numbers]. PTI often notes a relation on one side only
     * (AF1350-088 says "same part" as 3502-104A, not the other way), so every relation counts both ways; an OEM
     * number ("virtual" row, or named in the sales story) belongs to the part it points at.
     */
    private static function graph(): array
    {
        $ver = (string) DB::table('pti_parts')->max('updated_at');
        return Cache::remember('pti_xref_graph_v1_' . md5($ver), 3600, function () {
            $g = [];
            foreach (DB::table('pti_parts')->where(fn ($q) => $q->whereNotNull('related')->orWhereNotNull('story'))->get(['part_number', 'related', 'story']) as $r) {
                $pn = trim((string) $r->part_number); $PN = strtoupper($pn);
                foreach (json_decode((string) $r->related, true) ?: [] as $rel) {
                    $n = trim((string) ($rel['number'] ?? ''));
                    if (mb_strlen($n) < 3 || strtoupper($n) === $PN || !self::interchangeable($rel)) continue;
                    $g[$PN][strtoupper($n)] = $n;
                    if (empty($rel['alias'])) $g[strtoupper($n)][$PN] = $pn;
                }
                if (preg_match_all(self::OEM_RX, (string) $r->story, $m)) foreach ($m[1] as $n) $g[$PN][strtoupper($n)] = $n;
            }
            return $g;
        });
    }

    /** product id => PTI part numbers it is (the linked part), for products linked as that same part. */
    private static function linked(): array
    {
        $out = [];
        foreach (DB::table('pti_parts')->whereIn('match', ['sku', 'sku_u', 'created', 'manual'])->whereNotNull('wp_product_id')->get(['wp_product_id', 'part_number']) as $r)
            $out[(int) $r->wp_product_id][] = trim((string) $r->part_number);
        return $out;
    }

    /** product id => [interchange numbers], cached; a catalog edit or a PTI sync starts a new cache. */
    public static function map(): array
    {
        $ver = Cache::get('catalog_ver', 0) . '-' . (string) DB::table('pti_parts')->max('updated_at');
        return Cache::remember('pti_xref_v2_' . md5($ver), 3600, function () {
            $g = self::graph(); $out = [];
            foreach (self::linked() as $id => $pns) {
                foreach ($pns as $pn) {
                    // PTI's own number counts when ours differs (our 3102-86XU sells PTI 3102-86X)
                    $out[$id][strtoupper($pn)] = $pn;
                    foreach ($g[strtoupper($pn)] ?? [] as $k => $n) $out[$id][$k] = $n;
                }
            }
            return array_map('array_values', $out);
        });
    }

    public static function forProduct(int $id): array
    {
        return self::map()[$id] ?? [];
    }

    /** What PTI writes about a part we carry as that same part: its notes and sales story, those of its other numbers, and its OEM numbers' notes. */
    public static function texts(): array
    {
        $ver = (string) DB::table('pti_parts')->max('updated_at');
        return Cache::remember('pti_texts_v2_' . md5($ver), 3600, function () {
            $rows = DB::table('pti_parts')->get(['part_number', 'specs', 'story', 'related'])->keyBy(fn ($r) => strtoupper(trim((string) $r->part_number)));
            $say = function ($r) {
                $t = [(string) (json_decode((string) $r->specs, true)['notes'] ?? ''), (string) $r->story];
                foreach (json_decode((string) $r->related, true) ?: [] as $rel) if (!empty($rel['alias']) && self::interchangeable($rel)) $t[] = (string) ($rel['text'] ?? '');
                return implode(".\n", array_filter(array_map('trim', $t)));
            };
            $g = self::graph(); $out = [];
            foreach (self::linked() as $id => $pns) {
                foreach ($pns as $pn) {
                    $PN = strtoupper($pn);
                    if (isset($rows[$PN])) $out[$id][] = $say($rows[$PN]);
                    foreach (array_keys($g[$PN] ?? []) as $k) if (isset($rows[$k])) $out[$id][] = $say($rows[$k]);
                }
            }
            return $out;
        });
    }
}
