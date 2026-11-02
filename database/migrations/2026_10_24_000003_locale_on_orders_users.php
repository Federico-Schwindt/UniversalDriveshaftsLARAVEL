<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The language a customer used the site in, so their e-mails and texts arrive in it. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'users'] as $t) {
            if (!Schema::hasColumn($t, 'locale')) Schema::table($t, fn (Blueprint $b) => $b->string('locale', 5)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['orders', 'users'] as $t) {
            if (Schema::hasColumn($t, 'locale')) Schema::table($t, fn (Blueprint $b) => $b->dropColumn('locale'));
        }
    }
};
