<?php

namespace App\Support\Measure;

/**
 * "How to measure" drawing for a catalog category. Callout letters follow the category table: the first measured
 * column is A, the next B… (I and O skipped), and any extra attribute a product carries continues the run, so a
 * letter means the same thing in the table header, the drawing and the product page.
 */
class Diagram
{
    /** category slug => Parts method */
    private const FAMILY = [
        'flange-yokes' => 'flangeYoke', 'high-performance-flange-yokes' => 'flangeYoke', 'cv-flange-yokes' => 'cvFlangeYoke',
        'weld-yoke' => 'weldYoke', 'ball-stud-yokes' => 'ballStudYoke', 'end-yokes' => 'endYoke', 'pto-end-yokes' => 'ptoYoke',
        'slip-yoke' => 'slipYoke', 'transmission-yokes' => 'transYoke', 'yoke-shafts' => 'yokeShaft', 'h-yokes' => 'hYoke',
        'cv-link-yoks' => 'linkYoke', 'cv-double-joints-heads' => 'cvHead', 'tri-quad-prongs' => 'prongYoke',
        'stub-midship-shaft' => 'splineStub', 'splined-sleeves' => 'splinedSleeve', 'tubing' => 'tubing',
        'premade-driveshafts' => 'driveshaft', 'certifed-driveshafts' => 'twoPiece', 'short-couple-driveshafts' => 'shortCouple',
        'center-support-bearing' => 'centerBearing', 'u-joints' => 'uJoint',
        'companion-flanges' => 'companionFlange', 'rubber-flex-disc' => 'flexDisc', 'cv-joint-kit' => 'cvJoint',
        'increase-bushings' => 'increaseBushing', 'nose-bushings' => 'noseBushing', 'boot-cans-bearing-retainers' => 'bootCan', 'boot-kits' => 'boot',
        'strap-kits' => 'strapKit', 'u-bolt-kits' => 'uBolt', 'hardware' => 'bolt', 'nuts' => 'nut', 'lube-zerk-fittings' => 'zerk',
        'snap-rings' => 'snapRing', 'washers-snap-rings' => 'washer', 'dust-cap-press-on-and-threaded' => 'dustCap', 'boot-clamps' => 'clamp',
        'u-joint-seal-protectors' => 'sealGuard', 'cv-ball-kit' => 'cvBall',
    ];

    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function has(?string $cat): bool
    {
        return $cat !== null && isset(self::FAMILY[$cat]);
    }

    /**
     * @param string[] $keys attribute keys in letter order (table columns first, then a product's extra attributes)
     * @param string[]|null $only draw just these callouts (the attributes a product has a value for)
     * @return array{svg: string, codes: array<string,string>, shown: array<string,string>}|null codes = every attribute
     *         with a letter in this drawing, shown = the ones actually drawn
     */
    public static function for(?string $cat, array $keys, ?array $only = null, string $label = ''): ?array
    {
        if (!self::has($cat)) {
            return null;
        }
        $fn = self::FAMILY[$cat];
        $keys = array_values(array_unique($keys));
        $drawn = array_flip(Parts::$fn(array_fill_keys($keys, '?'))->keys());
        $codes = [];
        foreach ($keys as $k) {
            if (isset($drawn[$k]) && count($codes) < strlen(self::LETTERS)) {
                $codes[$k] = self::LETTERS[count($codes)];
            }
        }
        if (!$codes) {
            return null;
        }
        $show = $only === null ? $codes : array_intersect_key($codes, array_flip($only));

        return ['svg' => Parts::$fn($show)->svg($label), 'codes' => $codes, 'shown' => $show];
    }

    /** a spec value worth a callout: not blank, N/A or a zero placeholder */
    public static function measured(?string $v): bool
    {
        $v = strtoupper(trim(html_entity_decode((string) $v)));
        if (in_array($v, ['', '-', '--', 'N/A', 'NA', 'NONE', '.000/.000'], true)) {
            return false;
        }

        return !(is_numeric($v) && (float) $v == 0.0);
    }
}
