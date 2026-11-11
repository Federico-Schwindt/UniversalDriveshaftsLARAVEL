<?php

namespace App\Support;

use App\Services\VehiclePages;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The vehicles a center support bearing is listed for: every complete driveshaft in ds_fitments (PTI's application
 * data, pti:driveshaft-discover) names its bearing in c_brg, so a bearing's vehicles are proven by that data
 * (owner, 2026-11-11: "usa todos los datos de la api de pti que sean relevantes, en todas las partes").
 */
class BearingFits
{
    /** normalised bearing number => [make => [model => [years]]], cached until the fitment table changes. */
    private static function index(): array
    {
        $ver = DB::table('ds_fitments')->max('id') . '-' . DB::table('ds_fitments')->count();
        return Cache::remember('bearing_fits_v1_' . $ver, 86400, function () {
            $out = [];
            foreach (DB::table('ds_fitments')->whereNotNull('c_brg')->where('c_brg', '!=', '')->get(['c_brg', 'make', 'model', 'year']) as $f) {
                if (!$f->make || !$f->model || !$f->year) continue;
                // "2280-10", "2280-10 (rubber)", "9980-49 / 9980-50"; "N/A (1PC)" names none
                foreach (preg_split('/\s*[,\/;]\s*/', preg_replace('/\s*\(.*$/', '', (string) $f->c_brg)) as $c) {
                    $k = self::norm($c);
                    if (strlen($k) < 4 || preg_match('/^(NA|NONE)$/', $k)) continue;
                    $out[$k][$f->make][$f->model][(int) $f->year] = true;
                }
            }
            return $out;
        });
    }

    /**
     * [['make', 'url', 'models' => [['model', 'years' => '2004–2014', 'url'], …]], …] for a SKU (ours, or ours without the U),
     * makes and models in alphabetical order.
     */
    public static function forSku(string $sku): array
    {
        $idx = self::index(); $k = self::norm($sku);
        $hit = $idx[$k] ?? ($idx[preg_replace('/U$/', '', $k)] ?? []);
        ksort($hit, SORT_NATURAL | SORT_FLAG_CASE);
        $out = [];
        foreach ($hit as $make => $models) {
            if (in_array($make, VehiclePages::EXCLUDED_MAKES, true)) continue;   // "Custom / Misc." is not a vehicle
            ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
            $out[] = ['make' => $make, 'url' => VehiclePages::makeUrl($make), 'models' => array_map(fn ($model, $years) => [
                'model' => $model, 'years' => VehiclePages::yearRanges(array_keys($years)), 'url' => VehiclePages::modelUrl($make, $model),
            ], array_keys($models), $models)];
        }
        return $out;
    }

    private static function norm(string $s): string
    {
        return strtoupper((string) preg_replace('/[\s\-.]+/', '', trim($s)));
    }
}
