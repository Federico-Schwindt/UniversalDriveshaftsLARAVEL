<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PTI data the sync used to drop (owner, 2026-11-11: "usa todos los datos de la api de pti que sean relevantes"):
 * the part's sales story, and its cross references — PTI's related parts plus the OEM numbers ("virtual" rows) that point at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pti_parts', function (Blueprint $t) {
            $t->text('story')->nullable()->after('specs');
            $t->json('related')->nullable()->after('story');   // [{number, note, alias, text}]
        });
    }

    public function down(): void
    {
        Schema::table('pti_parts', function (Blueprint $t) {
            $t->dropColumn(['story', 'related']);
        });
    }
};
