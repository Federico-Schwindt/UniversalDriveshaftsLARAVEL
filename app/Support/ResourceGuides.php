<?php

namespace App\Support;

/**
 * The old store's measuring guides (universaldriveshafts.com/resources/…), kept at their old addresses with the new
 * design (owner, 2026-11-13: "replícalas pero con nuestro diseño, no la añadas a ningún menú … añádelas solo al footer").
 * Content: resources/data/resource-guides.json, converted once from the live Elementor pages (blocks: heading, text,
 * images, defs, table, link, bearing-chart). Pictures in public/img/guides, the u-joint PDF in public/docs/guides.
 */
class ResourceGuides
{
    /** @return array<string, array> path => page, in the old menu order */
    public static function all(): array
    {
        static $pages = null;
        if ($pages === null) {
            $data = json_decode((string) file_get_contents(resource_path('data/resource-guides.json')), true);
            $pages = [];
            foreach ($data['pages'] ?? [] as $p) $pages[$p['path']] = $p;
        }
        return $pages;
    }

    public static function find(string $path): ?array
    {
        return self::all()[trim($path, '/')] ?? null;
    }

    /** The guides with no parent: the footer and the /resources index. */
    public static function top(): array
    {
        return array_filter(self::all(), fn ($p) => empty($p['parent']));
    }

    public static function children(string $path): array
    {
        return array_filter(self::all(), fn ($p) => ($p['parent'] ?? null) === $path);
    }

    public static function url(string $path): string
    {
        return '/resources/' . $path;
    }
}
