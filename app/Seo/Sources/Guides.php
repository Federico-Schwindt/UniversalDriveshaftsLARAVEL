<?php

namespace App\Seo\Sources;

use App\Http\Controllers\GuideController;

/** Sitemap rows for the content hub (/guides and every published guide). */
class Guides
{
    public function urls(): array
    {
        $rows = [['loc' => '/guides', 'pri' => '0.7', 'freq' => 'weekly', 'lastmod' => GuideController::PUBLISHED]];
        foreach (array_keys(GuideController::registry()) as $slug) {
            $rows[] = ['loc' => '/guides/' . $slug, 'pri' => '0.4', 'freq' => 'monthly', 'lastmod' => GuideController::PUBLISHED];
        }
        return $rows;
    }
}
