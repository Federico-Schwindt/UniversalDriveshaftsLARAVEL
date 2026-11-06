<?php

namespace App\Support;

/**
 * Spanish display text for catalogue data that comes from WooCommerce in English: product
 * titles, category names, spec labels. It is a glossary, not machine translation — only the
 * driveline vocabulary is replaced; part numbers, measurements, units, brands and vehicle
 * names pass through untouched. Applied at display time only, never stored.
 */
class PartGlossary
{
    /** Spec labels written after their value in the catalogue ("1.229" Splines Dia"). */
    private const TRAILING_LABELS = [
        'Splines Dia', 'Splines dia', 'Hub Dia', 'ID', 'Center Bearing To Base', 'Ctr To Ctr Of Mount Hole',
        'Bolt Circle Dia', 'Cl To Cl', 'Center Line To Weld',
    ];

    /**
     * Ordered phrase => Spanish. Longest/most specific first; matched case-insensitively on
     * word boundaries. Keep plural before singular.
     */
    private const TERMS = [
        // product types
        'Splined Sleeves & Slip Assemblies' => 'Mangas estriadas y conjuntos deslizantes',
        'Splined Sleeves &amp; Slip Assemblies' => 'Mangas estriadas y conjuntos deslizantes',
        'Cv Double Joints Heads' => 'Cabezales de doble junta CV',
        'CV Double Joints Heads' => 'Cabezales de doble junta CV',
        'Center Support Bearings' => 'Soportes centrales',
        'Center Support Bearing' => 'Soporte central',
        'Centre Support Bearing' => 'Soporte central',
        'Stub Midship Shafts' => 'Ejes intermedios (stub)',
        'Stub Midship Shaft' => 'Eje intermedio (stub)',
        'Driveshaft Slip Yoke' => 'Yugo deslizante de cardán',
        'Cv Flange Yokes' => 'Yugos de brida CV',
        'CV Flange Yokes' => 'Yugos de brida CV',
        'Cv Flange Yoke' => 'Yugo de brida CV',
        'Flange Yokes' => 'Yugos de brida',
        'Flange Yoke' => 'Yugo de brida',
        'Tube Weld Yokes' => 'Yugos para soldar',
        'Tube Weld Yoke' => 'Yugo para soldar',
        'Weld Tube Yokes' => 'Yugos para soldar',
        'Weld Tube Yoke' => 'Yugo para soldar',
        'Weld Yokes' => 'Yugos para soldar',
        'Weld Yoke' => 'Yugo para soldar',
        'Transmission Yokes' => 'Yugos de transmisión',
        'Transmission Yoke' => 'Yugo de transmisión',
        'Tranny Yokes' => 'Yugos de transmisión',
        'Tranny Yoke' => 'Yugo de transmisión',
        'Ball Stud Yokes' => 'Yugos de rótula',
        'Ball Stud Yoke' => 'Yugo de rótula',
        'Slip Yokes' => 'Yugos deslizantes',
        'Slip Yoke' => 'Yugo deslizante',
        'End Yokes' => 'Yugos de extremo',
        'End Yoke' => 'Yugo de extremo',
        'PTO End Yokes' => 'Yugos de extremo PTO',
        'H Yokes' => 'Yugos H',
        'H Yoke' => 'Yugo H',
        'Yoke Shafts' => 'Ejes con yugo',
        'Yoke Shaft' => 'Eje con yugo',
        'Companion Flanges' => 'Bridas acompañantes',
        'Companion Flange' => 'Brida acompañante',
        'Tri & Quad Prongs' => 'Bridas de 3 y 4 brazos',
        'Tri &amp; Quad Prongs' => 'Bridas de 3 y 4 brazos',
        'Rubber Flex Discs' => 'Discos flexibles de goma',
        'Rubber Flex Disc' => 'Disco flexible de goma',
        'Increase Bushings' => 'Bujes de aumento',
        'Increase Bushing' => 'Buje de aumento',
        'Universal Joints' => 'Crucetas',
        'Universal Joint Strap Kit' => 'Kit de abrazaderas para cruceta',
        'Universal Joint' => 'Cruceta',
        'U-Joints' => 'Crucetas',
        'U-Joint' => 'Cruceta',
        'U Joints' => 'Crucetas',
        'U Joint' => 'Cruceta',
        'Strap Kits' => 'Kits de abrazaderas',
        'Strap Kit' => 'Kit de abrazaderas',
        'U-Bolt Kits' => 'Kits de pernos en U',
        'U-Bolt Kit' => 'Kit de pernos en U',
        'Bolt Kits' => 'Kits de pernos',
        'Bolt Kit' => 'Kit de pernos',
        'Dust Caps' => 'Tapas guardapolvo',
        'Dust Cap' => 'Tapa guardapolvo',
        'Slip Assemblies' => 'Conjuntos deslizantes',
        'Slip Assembly' => 'Conjunto deslizante',
        'Unwelded Assemblies' => 'Conjuntos sin soldar',
        'Short Couple Driveshafts' => 'Cardanes cortos',
        'Short Couple Driveshaft' => 'Cardán corto',
        'Balance Weights' => 'Pesas de balanceo',
        'Balance Weight' => 'Pesa de balanceo',
        'Washers & Snap Rings' => 'Arandelas y anillos de retención',
        'Washers &amp; Snap Rings' => 'Arandelas y anillos de retención',
        'CV Joint Kits' => 'Kits de junta CV',
        'CV Joint Kit' => 'Kit de junta CV',
        'Nuts' => 'Tuercas',
        'Washers' => 'Arandelas',
        'Premade Driveshafts' => 'Cardanes prearmados',
        'Pre-made Driveshafts' => 'Cardanes prearmados',
        'Complete Driveshafts' => 'Cardanes completos',
        'Certified Driveshafts' => 'Cardanes certificados',
        'Certifed Driveshafts' => 'Cardanes certificados',
        'Driveshaft Components' => 'Componentes de cardán',
        'Custom Cut Tubing' => 'Tubo cortado a medida',
        'Small Parts' => 'Piezas pequeñas',
        'Driveshafts' => 'Cardanes',
        'Driveshaft' => 'Cardán',
        'Drive Shaft Tubing' => 'Tubo para cardán',
        'Drive Shafts' => 'Cardanes',
        'Drive Shaft' => 'Cardán',
        'DOM Tubing' => 'tubo DOM',
        'DOM Tube' => 'tubo DOM',
        'High Performance Flange Yokes' => 'Yugos de brida de alto rendimiento',
        'High Performance tube' => 'Tubo de alto rendimiento',
        'High Performance' => 'alto rendimiento',
        'Tubing' => 'Tubo',
        'Tube' => 'tubo',
        'Bearings' => 'Rodamientos',
        'Bearing' => 'Rodamiento',
        'Boot Clamps' => 'Abrazaderas de fuelle',
        'Clamps' => 'Abrazaderas',
        'BOLTS' => 'PERNOS',
        'Bolts' => 'Pernos',
        'Seals' => 'Retenes',
        'Replacement Seals' => 'Retenes de repuesto',
        'Power Take Off App' => 'toma de fuerza',
        'Power Take Off' => 'toma de fuerza',

        // spec labels
        'Tube Dia Wall' => 'Diám. × pared de tubo',
        'Ball Stud Length Dia' => 'Largo × diám. de rótula',
        'Bolt Circle Diameter' => 'Diám. círculo de pernos',
        'Internal Diameter' => 'Diám. interior',
        'Bolt Hole Count' => 'Cantidad de agujeros',
        'Mounting Holes' => 'Agujeros de montaje',
        'Centerline To Weld' => 'Eje a soldadura',
        'C/L To Base' => 'Eje a base',
        'Face To Weld' => 'Cara a soldadura',
        'Bore Dia' => 'Diám. de agujero',
        'Thickness' => 'Espesor',
        'Weight' => 'Peso',
        'Holes on' => 'agujeros en',
        'Holes' => 'Agujeros',
        'Notes' => 'Notas',
        'differentials' => 'diferenciales',
        'Aluminum' => 'aluminio',
        'pilot' => 'piloto',
        'for' => 'para',
        'and' => 'y',
        'Tube Dia' => 'Diám. tubo',
        'Tube Length' => 'Largo',
        'Tube Wall' => 'Pared',
        'Splines Count Dia' => 'Estrías × diám.',
        'Splines Count' => 'Cantidad de estrías',
        'Splines Inside Dia' => 'Diám. interior de estrías',
        'Splines Dia' => 'Diám. de estrías',
        'Splines diameter' => 'Diám. de estrías',
        'Hub Dia' => 'Diám. de cubo',
        'Butt Diameyter' => 'Diám. de asiento',
        'Butt Diameter' => 'Diám. de asiento',
        'Butt Dia' => 'Diám. de asiento',
        'Bolt Circle Dia' => 'Diám. círculo de pernos',
        'Bolt Hole Dia' => 'Diám. agujero de perno',
        'Pilot Diameter' => 'Diám. piloto',
        'Pilot Dia' => 'Diám. piloto',
        'Ball Stud Dia' => 'Diám. de rótula',
        'Swing Dia' => 'Diám. de giro',
        'Bearing Dia' => 'Diám. de rodamiento',
        'Inside Dia' => 'Diám. interior',
        'Outer Dia' => 'Diám. exterior',
        'Fits Cap Dia' => 'Para tapa de diám.',
        'Cap Dia' => 'Diám. de tapa',
        'Thread Size' => 'Rosca',
        'Thread Dia' => 'Rosca',
        'Length Under Head' => 'Largo bajo cabeza',
        'Bolt Length' => 'Largo de perno',
        'Bolt Torque' => 'Torque de perno',
        'Disc Width' => 'Ancho de disco',
        'Center Joint To End' => 'Centro de junta a extremo',
        'Center Line To Weld' => 'Eje a soldadura',
        'Center Line To End' => 'Eje a extremo',
        'Centerline to End' => 'Eje a extremo',
        'Center Bearing To Base' => 'Centro de rodamiento a base',
        'Ctr To Ctr Of Mount Holes' => 'Centro a centro de agujeros de montaje',
        'Ctr To Ctr Of Mount Hole' => 'Centro a centro de agujeros de montaje',
        'Center To Center' => 'Centro a centro',
        'CL to Weld' => 'Eje a soldadura',
        'Cl To Cl' => 'Centro a centro',
        'Cj To Cj' => 'Junta a junta',
        'CJ to END' => 'Junta a extremo',
        'End To Weld' => 'Extremo a soldadura',
        'Flange To Flange' => 'Brida a brida',
        'From Tube' => 'Desde tubo',
        'To Tube' => 'A tubo',
        'Round Bore' => 'agujero redondo',
        'Splined Bore' => 'agujero estriado',
        'Keyway' => 'Chavetero',
        'Bore' => 'Agujero',
        'Hub' => 'Cubo',
        'Hex' => 'Hexágono',
        'Length' => 'Largo',
        'Style' => 'Estilo',

        // descriptors
        'Heavy Duty' => 'servicio pesado',
        'HEAVY DUTY' => 'SERVICIO PESADO',
        'HALF ROUND' => 'MEDIA LUNA',
        'Half Round' => 'media luna',
        'Greaseable' => 'engrasable',
        'Greasable' => 'engrasable',
        'Non Greasable' => 'no engrasable',
        'Non-Greasable' => 'no engrasable',
        'Square Pattern' => 'patrón cuadrado',
        'Rectangle' => 'rectangular',
        'Square' => 'cuadrado',
        'Automatic' => 'automática',
        'Manual' => 'manual',
        'Splines' => 'estrías',
        'Spline' => 'estrías',
        'Years' => 'años',
        'Fits' => 'para',
        'With' => 'con',
        'Rear' => 'trasero',
        'Front' => 'delantero',
        'rear' => 'trasero',
        'front' => 'delantero',
        'Remanufactured' => 'remanufacturado',
    ];

    /** Product-line and brand phrases that contain glossary words but must stay as written. */
    private const KEEP = ['Lube for Life', 'Spicer Life', 'New Holland', 'Front Wheel Drive', 'Weld-on'];

    private static array $compiled = [];

    /**
     * Words the rules below write, per language; %s is the series code, brand or count (Spanish is the hand-made
     * original). Chinese and Japanese put the word after the code ("1310系列", "GM用") and use no spaces between words.
     */
    private const WORDS = [
        'es' => ['series' => 'serie', 'series_of' => 'serie %s', 'application_of' => 'aplicación %s', '1pc' => '1 pieza', 'npc' => '%s piezas', 'ID' => 'DI'],
        'fr' => ['series' => 'série', 'series_of' => 'série %s', 'application_of' => 'application %s', '1pc' => '1 pièce', 'npc' => '%s pièces', 'ID' => 'DI'],
        'zh' => ['series' => '系列', 'series_of' => '%s系列', 'application_of' => '适用于 %s', '1pc' => '一体式', 'npc' => '%s节式', 'ID' => 'ID'],
        'ja' => ['series' => 'シリーズ', 'series_of' => '%sシリーズ', 'application_of' => '%s用', '1pc' => '1ピース', 'npc' => '%sピース', 'ID' => 'ID'],
    ];

    public static function active(): bool
    {
        return isset(self::WORDS[app()->getLocale()]);
    }

    private static function w(string $k): string
    {
        return self::WORDS[app()->getLocale()][$k] ?? self::WORDS['es'][$k];
    }

    /** Title, category or label in the visitor's language (unchanged in English). */
    /** English phrases of the glossary (the source i18n:translate uses for other languages). */
    public static function sourceTerms(): array
    {
        return self::TERMS;
    }

    public static function t(?string $text): string
    {
        $text = (string) $text;
        if ($text === '' || !self::active()) return $text;
        return self::translate($text);
    }

    public static function translate(string $text): string
    {
        $kept = [];
        foreach (self::KEEP as $i => $k) {
            $text = preg_replace_callback('/' . preg_quote($k, '/') . '/i', function ($m) use (&$kept, $i) { $kept["\u{E000}$i\u{E001}"] = $m[0]; return "\u{E000}$i\u{E001}"; }, $text);
        }
        $text = self::glossary($text);
        return strtr($text, $kept);
    }

    private static function glossary(string $text): string
    {
        // "1310 Series" → "serie 1310"; "Series 1310" stays "serie 1310"
        // only a real series code (has a digit: 1310, 3R, SPL90, 68740/2040) — never "Slip Yoke Series"
        $text = preg_replace('/\b((?=[\w.\/-]*\d)[0-9A-Z][\w.\/-]*)\s+Series\b/iu', sprintf(self::w('series_of'), '$1'), $text);
        $text = preg_replace('/\bSeries\b/iu', self::w('series'), $text);
        // "Gm Application" / "Volvo Application" → "aplicación GM"
        $text = preg_replace_callback('/\b([A-Z][\w-]*(?:\s?\/\s?[A-Z][\w-]*|\s[A-Z][\w-]*)?)\s+Application\b/u', fn ($m) => sprintf(self::w('application_of'), strlen($m[1]) <= 3 ? strtoupper($m[1]) : $m[1]), $text);
        // value-then-label ("1.229\" Splines Dia") reads label-then-value in Spanish
        foreach (self::TRAILING_LABELS as $l) {
            // only when the label is not itself followed by its own value ("1.391 Hub Dia: 1.886")
            $text = preg_replace('/(\d[\d.\/]*\s*(?:"|”|in|mm|MM)?)\s+' . preg_quote($l, '/') . '\b(?!\s*[:\d])/u', $l . ' $1', $text);
        }
        // "1pc" / "2pc"
        $text = preg_replace_callback('/\b(\d)\s?pc\b/iu', fn ($m) => $m[1] === '1' ? self::w('1pc') : sprintf(self::w('npc'), $m[1]), $text);

        foreach (self::compiled() as [$re, $es]) $text = preg_replace($re, $es, $text);
        // ID as a label right after a number
        $text = preg_replace('/\bID\b/u', self::w('ID'), $text);
        // Chinese / Japanese: words translated one by one keep the English spaces between them
        if (in_array(app()->getLocale(), ['zh', 'ja'], true)) $text = preg_replace('/(?<=[\p{Han}\p{Hiragana}\p{Katakana}ー])\s+(?=[\p{Han}\p{Hiragana}\p{Katakana}])/u', '', $text);
        return $text;
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function compiled(): array
    {
        $loc = app()->getLocale();
        if (isset(self::$compiled[$loc])) return self::$compiled[$loc];
        // other languages: lang/i18n/catalog/glossary-<lang>.php (English phrase => translation), made by i18n:translate
        $terms = $loc === 'es' ? self::TERMS : (is_file($f = lang_path("i18n/catalog/glossary-$loc.php")) ? (include $f) : []);
        uksort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $out = [];
        foreach ($terms as $en => $es) {
            $q = preg_quote($en, '/');
            // case-insensitive unless the term is ALL CAPS (then match exactly, keep its case)
            $flags = $en === mb_strtoupper($en) ? 'u' : 'iu';
            $out[] = ['/(?<![\p{L}\p{N}])' . $q . '(?![\p{L}])/' . $flags, addcslashes($es, '\\$')];
        }
        return self::$compiled[$loc] = $out;
    }
}
