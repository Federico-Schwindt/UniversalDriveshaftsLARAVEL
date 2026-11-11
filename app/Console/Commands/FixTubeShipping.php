<?php

namespace App\Console\Commands;

use App\Services\Admin\CatalogWriter;
use App\Services\CatalogService;
use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute the shipping weight and box of every tubing product from its own geometry,
 * because the sold-by-the-foot tubes carry the weight of one INCH while the cart counts
 * feet — a twelvefold under-charge on the shipping of every cut-tube order.
 *
 *   php artisan tubes:fix-shipping           (dry run — prints what it would change)
 *   php artisan tubes:fix-shipping --write
 *   php artisan tubes:fix-shipping --write --wall-mm   (treat the -100 series wall as mm)
 */
class FixTubeShipping extends Command
{
    protected $signature = 'tubes:fix-shipping {--write : Actually write the weights and boxes} {--wall-mm : Read the -100 series wall as millimetres rather than inches}';
    protected $description = 'Recompute tubing shipping weight and box size from tube diameter, wall and length';

    /** lb per cubic inch */
    private const STEEL = 0.2836;
    private const ALUMINIUM = 0.0975;

    public function handle(CatalogService $catalog, ProductService $products, CatalogWriter $writer): int
    {
        $write = (bool) $this->option('write');
        $rows = [];
        $ids = DB::connection('wp')->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->pluck('ID');

        foreach ($ids as $id) {
            $group = $catalog->groupOfProduct((int) $id);
            if (($group['slug'] ?? '') !== 'driveshaft-tubing') continue;

            $title = (string) DB::connection('wp')->table('wp_posts')->where('ID', $id)->value('post_title');
            $sku = (string) DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_sku')->value('meta_value');
            $spec = $this->parse($title, $sku, $products->dims((int) $id));
            $now = round($products->weight((int) $id), 2);

            if (!$spec) {
                $rows[] = [$sku, $now, '—', '—', 'cannot read the title'];
                continue;
            }
            $calc = $this->weight($spec);
            $ratio = $now > 0 ? round($calc / $now, 2) : null;
            $note = $spec['ambiguous'] ? 'AMBIGUOUS wall (' . $spec['wall_raw'] . ')' : ($spec['per_foot'] ? 'per foot' : '');

            $rows[] = [$sku, $now, round($calc, 2), $ratio === null ? '—' : $ratio . 'x', $note];

            if ($write && !$spec['ambiguous']) {
                $box = $this->box($spec);
                $writer->setShipBox((int) $id, round($calc, 2), $box[0], $box[1], $box[2]);
            }
        }

        $this->table(['SKU', 'catalogue lb', 'computed lb', 'ratio', 'note'], $rows);
        $amb = count(array_filter($rows, fn ($r) => str_contains((string) $r[4], 'AMBIGUOUS')));
        $this->info(count($rows) . ' tubing products' . ($amb ? ", {$amb} left alone (ambiguous wall units)" : ''));
        $this->line($write ? 'Written.' : 'Dry run — nothing written. Re-run with --write.');
        return self::SUCCESS;
    }

    /** Pull diameter, wall, length and material out of the product title. */
    private function parse(string $title, string $sku, ?array $dims): ?array
    {
        $perFoot = (bool) preg_match('/sold by (the )?(1 )?foot/i', $title);

        preg_match('/Tube Dia\s*([\d.]+)/i', $title, $d);
        preg_match('/Tube Length\s*([\d.]+)/i', $title, $l);
        preg_match('/Tube Wall\s*([\d.]+)/i', $title, $w);
        // A tube listed at 1" long is the shop's sold-by-the-foot listing (see
        // shop/product.blade.php: "tubing listed with a 1\" length (the price is per foot)").
        // The cart counts feet, so the shipping weight must be a foot of tube, not an inch.
        if ($l && (float) $l[1] === 1.0) $perFoot = true;
        // "High Performance tube 3.000x0.125 Tube Length 54" and "2.000 x 0.083 , DOM"
        if ((!$d || !$w) && preg_match('/([\d.]+)\s*x\s*([\d.]+)/i', $title, $m)) {
            $d = $d ?: [1 => $m[1]];
            $w = $w ?: [1 => $m[2]];
        }
        if (!$d || !$w) return null;
        // No length in the title: the box it already ships in is as long as the tube.
        if (!$l && !$perFoot && $dims) $l = [1 => (string) $dims[0]];
        if (!$l && !$perFoot) return null;

        $od = (float) $d[1];
        $length = $perFoot ? 12.0 : (float) $l[1];
        $wallRaw = $w[1];
        $metricSeries = str_ends_with($sku, '-100');

        // "0.125" is inches; "125" and "83" are thousandths; a bare "4" on the metric
        // series could be 4 mm or 4 in, and those differ by a factor of twenty.
        $ambiguous = false;
        if (str_contains($wallRaw, '.') && (float) $wallRaw < 1) {
            $wall = (float) $wallRaw;
        } elseif ((float) $wallRaw >= 50) {
            $wall = (float) $wallRaw / 1000;
        } elseif ($metricSeries) {
            $wall = $this->option('wall-mm') ? (float) $wallRaw / 25.4 : (float) $wallRaw;
            $ambiguous = !$this->option('wall-mm');
        } else {
            $wall = (float) $wallRaw;
        }

        if ($od <= 0 || $wall <= 0 || $length <= 0 || $wall * 2 >= $od) return null;

        return [
            'od' => $od, 'wall' => $wall, 'length' => $length,
            // every aluminium tube's part number starts AT (ATU5125, AT35125); steel is TU/plain
            'aluminium' => str_starts_with(strtoupper($sku), 'AT') || (bool) preg_match('/6061|T6\b|alumin/i', $title),
            'per_foot' => $perFoot, 'ambiguous' => $ambiguous, 'wall_raw' => $wallRaw,
        ];
    }

    /** A tube is a hollow cylinder; its weight is the wall's volume times the metal's density. */
    private function weight(array $s): float
    {
        $id = $s['od'] - 2 * $s['wall'];
        $volume = (M_PI / 4) * ($s['od'] ** 2 - $id ** 2) * $s['length'];
        return $volume * ($s['aluminium'] ? self::ALUMINIUM : self::STEEL);
    }

    /** The box a tube ships in: its length plus a little, and square around the diameter. */
    private function box(array $s): array
    {
        return [round($s['length'] + 2, 2), round($s['od'] + 2, 2), round($s['od'] + 2, 2)];
    }
}
