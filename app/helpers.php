<?php

if (!function_exists('media_url')) {
    /** Remote catalog image through the local WebP cache (see MediaController); local paths pass through untouched. */
    function media_url(?string $src, int $w = 640): string
    {
        return \App\Http\Controllers\MediaController::url($src, $w);
    }
}

if (!function_exists('part_path')) {
    /** The one address of a part page. A slash in a part number (GU7280/4) stays a slash: Apache refuses the encoded one. */
    function part_path(string $sku): string
    {
        return '/parts/' . str_replace('%2F', '/', rawurlencode($sku));
    }
}

if (!function_exists('setting')) {
    function setting(string $key, $default = null) { return \App\Support\Settings::get($key, $default); }
}
if (!function_exists('adm_can')) {
    function adm_can(string $section): bool { return \App\Support\AdminAccess::can($section); }
}
if (!function_exists('money')) {
    function money($v, bool $zeroDash = false): string {
        $v = (float) $v;
        if ($zeroDash && abs($v) < 0.005) return '—';
        return ($v < 0 ? '-' : '') . '$' . number_format(abs($v), 2);
    }
}
if (!function_exists('ic')) {
    function ic(string $name, string $cls = ''): string { return '<svg class="ic' . ($cls ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#i-' . $name . '"/></svg>'; }
}

if (!function_exists('i18n_view')) {
    /** Translated long-form page for the current language: i18n-content.<name> is Spanish, i18n-content.<lang>.<name> any other. */
    function i18n_view(string $name): ?string
    {
        $loc = app()->getLocale();
        if ($loc === 'en') return null;
        $v = $loc === 'es' ? "i18n-content.$name" : "i18n-content.$loc.$name";
        return view()->exists($v) ? $v : null;
    }
}

if (!function_exists('stock_label')) {
    /** A stock count as customers see it: the real number up to 50, "50+" above (owner, 2026-11). */
    function stock_label($n): string
    {
        $n = (int) $n;
        return $n > 50 ? '50+' : (string) $n;
    }
}
