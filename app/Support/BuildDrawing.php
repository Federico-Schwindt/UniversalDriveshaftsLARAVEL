<?php

namespace App\Support;

use App\Http\Controllers\DesignerController;

/**
 * The online builder's drawing of a build, made on the server: the same composed designer drawing the builder shows
 * on its review page, with only the builder's own dimensions kept and their values written in. The shop station shows
 * it on the work order (owner, 2026-11-11: "quiero que en el workstation se vea el diagrama que devolvió el build").
 * Mirrors planFor() / renderPlan() / paintPlan() in public/js/dsb.js; one-piece builds only, like the builder.
 */
class BuildDrawing
{
    private const FRONT = ['weld' => '01', 'flange' => '02', 'trans' => '09', 'yoke_shaft' => '10', 'slip_flange' => '11', 'slip_no_flange' => '12'];
    private const REAR = ['weld' => '01R', 'flange' => '02R', 'slip_flange' => '11R', 'slip_no_flange' => '12R', 'carrier_end' => '50', 'carrier_slip' => '59'];
    /** A carrier-bearing end prices the front shaft only: its A1 runs to the bearing centre (the designer's B1), the drawing is cut after C1. */
    private const CARRIER = ['carrier_end' => ['cut' => 'C1', 'pad' => 12], 'carrier_slip' => ['cut' => 'C1', 'pad' => 12]];
    private const DIMMAP = ['weld__weld' => ['a1' => 'C1', 'c1' => 'W1'], 'slip_no_flange__weld' => ['a1' => 'C1']];

    /** [sections, field => designer dimension, cut] for the two ends, or null when the builder draws none. */
    public static function plan(string $front, string $rear, string $pieces = 'one'): ?array
    {
        $a = self::FRONT[$front] ?? null; $b = self::REAR[$rear] ?? null;
        if (!$a || !$b || $pieces === 'two') return null;
        if (isset(self::CARRIER[$rear])) return ['sections' => "$a+$b+" . ($front === 'flange' ? '02R' : '01R'), 'map' => ['a1' => 'B1'], 'cut' => self::CARRIER[$rear]];
        return ['sections' => "$a+$b", 'map' => self::DIMMAP[$front . '__' . $rear] ?? ['a1' => 'A1', 'c1' => 'C1'], 'cut' => null];
    }

    /** The drawing as SVG for a build config (front_app, rear_app, pieces, a1, c1), or null. */
    public static function svg(array $c): ?string
    {
        $plan = self::plan((string) ($c['front_app'] ?? ''), (string) ($c['rear_app'] ?? ''), (string) ($c['pieces'] ?? 'one'));
        if (!$plan || !($base = DesignerController::render($plan['sections'], [], true))) return null;
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($base, LIBXML_NONET)) return null;
        $x = new \DOMXPath($doc); $x->registerNamespace('s', 'http://www.w3.org/2000/svg');
        $svg = $doc->documentElement;
        $vb = array_map('floatval', preg_split('/[\s,]+/', trim((string) $svg->getAttribute('viewBox'))));
        $dimText = fn (string $name) => $x->query("//s:g[contains(@class,'dims')]//s:text[contains(concat(' ',@class,' '),' dim-text ') and @name='$name']")->item(0);

        // a carrier end: the shaft stops at the bearing, so the drawing is cut after C1 and A1 sits on the shorter run
        if ($plan['cut'] && count($vb) === 4 && ($ref = $dimText($plan['cut']['cut'])) && ($line = $x->query(".//s:line[contains(@class,'dim-line')]", $ref->parentNode)->item(0))) {
            $x2 = max((float) $line->getAttribute('x1'), (float) $line->getAttribute('x2'));
            $vb[2] = $x2 + $plan['cut']['pad'] - $vb[0];
            if ($keep = $dimText($plan['map']['a1'])) {
                $keep->setAttribute('x', (string) (($vb[0] + $x2) / 2));
                $kl = $x->query(".//s:line[contains(@class,'dim-line')]", $keep->parentNode)->item(0);
                $end = $kl ? max((float) $kl->getAttribute('x1'), (float) $kl->getAttribute('x2')) : null;
                if ($end !== null) foreach (iterator_to_array($x->query('.//s:line', $keep->parentNode)) as $ln) {
                    if ($ln !== $kl && !str_contains((string) $ln->getAttribute('class'), 'dim-ext') && min((float) $ln->getAttribute('x1'), (float) $ln->getAttribute('x2')) >= $end - 0.5) $ln->parentNode->removeChild($ln);
                }
            }
        }
        // only the builder's dimensions, labelled "A1 = 52.25"" (C1 only where the builder asks for it and it was given)
        $values = ['a1' => (float) ($c['a1'] ?? 0), 'c1' => (float) ($c['c1'] ?? 0)];
        $keep = [];
        foreach ($plan['map'] as $field => $dim) if ($values[$field] > 0) $keep[$dim] = $field;
        foreach (iterator_to_array($x->query("//s:g[contains(@class,'dims')]/s:g")) as $g) {
            $texts = iterator_to_array($x->query(".//s:text[contains(concat(' ',@class,' '),' dim-text ')]", $g));
            if (count($texts) > 1) foreach ($texts as $t) if (!isset($keep[$t->getAttribute('name')])) $t->parentNode->removeChild($t);
            $t = $x->query(".//s:text[contains(concat(' ',@class,' '),' dim-text ')]", $g)->item(0);
            if (!$t || !isset($keep[$t->getAttribute('name')])) { $g->parentNode->removeChild($g); continue; }
            $field = $keep[$t->getAttribute('name')];
            $t->nodeValue = strtoupper($field) . ' = ' . rtrim(rtrim(number_format($values[$field], 3, '.', ''), '0'), '.') . '"';
            $t->setAttribute('class', trim($t->getAttribute('class') . ' is-set'));
        }
        foreach (iterator_to_array($x->query("//s:text[contains(concat(' ',@class,' '),' front ')]")) as $f) $f->parentNode->removeChild($f);
        // a real size for <img> (the station shows it as the work order's product image), white behind it
        if (count($vb) === 4) {
            $svg->setAttribute('viewBox', implode(' ', $vb));
            $svg->setAttribute('width', (string) round($vb[2] * 4)); $svg->setAttribute('height', (string) round($vb[3] * 4));
            $bg = $doc->createElementNS('http://www.w3.org/2000/svg', 'rect');
            foreach (['x' => $vb[0], 'y' => $vb[1], 'width' => $vb[2], 'height' => $vb[3], 'fill' => '#ffffff'] as $k => $v) $bg->setAttribute($k, (string) $v);
            $first = null;
            foreach ($svg->childNodes as $n) if ($n instanceof \DOMElement && !in_array($n->localName, ['defs', 'style'], true)) { $first = $n; break; }
            $first ? $svg->insertBefore($bg, $first) : $svg->appendChild($bg);
        }
        return $doc->saveXML($svg);
    }

    /** Public address of a build's drawing (it carries only the ends and the dimensions, nothing about the customer). */
    public static function url(array $c): ?string
    {
        if (!self::plan((string) ($c['front_app'] ?? ''), (string) ($c['rear_app'] ?? ''), (string) ($c['pieces'] ?? 'one'))) return null;
        $q = array_filter(['f' => $c['front_app'], 'r' => $c['rear_app'],
            'a1' => (float) ($c['a1'] ?? 0) > 0 ? (string) (float) $c['a1'] : null, 'c1' => (float) ($c['c1'] ?? 0) > 0 ? (string) (float) $c['c1'] : null]);
        return rtrim((string) config('app.url'), '/') . '/online-builder/drawing?' . http_build_query($q);
    }
}
