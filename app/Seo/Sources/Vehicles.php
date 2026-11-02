<?php

namespace App\Seo\Sources;

use App\Services\VehiclePages;

/** Sitemap rows for /driveshafts, every make page and every make/model page. */
class Vehicles
{
    public function urls(): array
    {
        $veh = app(VehiclePages::class);
        $lastmod = $veh->lastmod();
        $out = [['loc' => '/driveshafts', 'pri' => '0.8', 'freq' => 'weekly', 'lastmod' => $lastmod]];
        foreach ($veh->hub()['makes'] as $m) {
            $out[] = ['loc' => $m['url'], 'pri' => '0.6', 'freq' => 'weekly', 'lastmod' => $lastmod];
        }
        foreach ($veh->allModels() as $m) {
            $url = VehiclePages::modelUrl($m['make'], $m['model']);
            if ($url) $out[] = ['loc' => $url, 'pri' => '0.7', 'freq' => 'weekly', 'lastmod' => $lastmod];
        }
        return $out;
    }
}
