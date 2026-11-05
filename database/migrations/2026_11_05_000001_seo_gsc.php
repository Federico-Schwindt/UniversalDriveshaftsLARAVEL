<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Search Console data for Admin → SEO → Performance (seo:gsc-sync): the property's totals per day and the
 * top queries / pages per calendar month (clicks, impressions, average position).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_gsc_daily', function (Blueprint $t) {
            $t->date('date')->primary();
            $t->unsignedInteger('clicks')->default(0);
            $t->unsignedInteger('impressions')->default(0);
            $t->decimal('position', 7, 2)->nullable();
            $t->timestamp('synced_at')->nullable();
        });
        Schema::create('seo_gsc_rows', function (Blueprint $t) {
            $t->id();
            $t->date('month');                       // first day of the month
            $t->string('kind', 8);                   // query | page
            $t->char('key_hash', 40);                // sha1 of the query / page URL (unique index on long text)
            $t->text('key');
            $t->unsignedInteger('clicks')->default(0);
            $t->unsignedInteger('impressions')->default(0);
            $t->decimal('position', 7, 2)->nullable();
            $t->unique(['month', 'kind', 'key_hash']);
            $t->index(['kind', 'month', 'clicks']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_gsc_rows');
        Schema::dropIfExists('seo_gsc_daily');
    }
};
