<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coordinates of the cities / countries Google Analytics reports (for the dashboard globe), geocoded once (analytics:geocode). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_places', function (Blueprint $t) {
            $t->id();
            $t->string('key', 190)->unique();        // "city|region|CC" or "country|CC"
            $t->string('label', 190);
            $t->decimal('lat', 9, 6)->nullable();
            $t->decimal('lng', 9, 6)->nullable();
            $t->boolean('found')->default(false);
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_places');
    }
};
