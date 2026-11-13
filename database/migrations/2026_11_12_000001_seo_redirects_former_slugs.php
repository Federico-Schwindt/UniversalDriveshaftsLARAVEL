<?php

use App\Support\LegacyUrls;
use App\Support\Seo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The redirects imported from the old site sent a product URL to the shop when they found no match. Where that
 * product is still sold under a newer slug (WordPress keeps the earlier ones), its own page is the answer (owner,
 * 2026-11-12: "urls viejas: arreglala no quiero perder nada de seo"). Rows written in the admin stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $rows = DB::table('seo_redirects')->where('origin', '!=', 'manual')->where('source', 'like', 'product/%')->where('target', '/shop')->get(['id', 'source']);
            foreach ($rows as $r) {
                $slug = substr($r->source, strlen('product/'));
                if (!($to = LegacyUrls::part($slug) ?? LegacyUrls::part($slug, true))) continue;
                DB::table('seo_redirects')->where('id', $r->id)->update(['target' => $to, 'note' => 'found by a slug the product had before', 'updated_at' => now()]);
            }
            Seo::flush();
        } catch (\Throwable $e) {
            // no catalog to ask (a fresh install): nothing to correct
        }
    }

    public function down(): void
    {
    }
};
