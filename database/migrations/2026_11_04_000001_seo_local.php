<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO owned by the new store (replaces Rank Math / Redirection / ThinkRank on the old WordPress site):
 * redirects (imported from WordPress + added in the admin), a log of URLs that answered 404,
 * and per-URL title / description / robots overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_redirects')) Schema::create('seo_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('source', 500)->unique();          // normalised path: lowercase, no leading/trailing slash, no query
            $t->string('target', 1000)->nullable();       // site path or absolute URL; null for 410
            $t->unsignedSmallInteger('code')->default(301);
            $t->string('origin', 20)->default('manual');  // rank_math, redirection, thinkrank, legacy, manual
            $t->string('note', 255)->nullable();
            $t->unsignedInteger('hits')->default(0);
            $t->unsignedInteger('wp_hits')->default(0);   // hits counted by the WordPress plugin before the move
            $t->timestamp('last_hit_at')->nullable();
            $t->timestamps();
        });

        if (!Schema::hasTable('seo_404s')) Schema::create('seo_404s', function (Blueprint $t) {
            $t->id();
            $t->string('path', 500)->unique();
            $t->unsignedInteger('hits')->default(1);
            $t->string('referer', 500)->nullable();
            $t->timestamp('last_seen_at')->nullable()->index();
            $t->timestamps();
        });

        if (!Schema::hasTable('seo_pages')) Schema::create('seo_pages', function (Blueprint $t) {
            $t->id();
            $t->string('path', 500)->unique();            // same normalisation as redirects ('' = home page)
            $t->string('title', 160)->nullable();
            $t->string('description', 320)->nullable();
            $t->string('robots', 60)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
        Schema::dropIfExists('seo_404s');
        Schema::dropIfExists('seo_redirects');
    }
};
