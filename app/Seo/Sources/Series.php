<?php

namespace App\Seo\Sources;

use App\Services\CatalogService;

/** Sitemap rows for the u-joint series hubs (/series, /series/{series}). Picked up by SeoController::sitemap(). */
class Series
{
    public function urls(): array
    {
        $out = [['loc' => '/series', 'pri' => '0.7', 'freq' => 'weekly']];
        foreach (app(CatalogService::class)->seriesList() as $s) {
            $out[] = ['loc' => '/series/' . $s['slug'], 'pri' => '0.7', 'freq' => 'weekly'];
        }
        return $out;
    }
}
