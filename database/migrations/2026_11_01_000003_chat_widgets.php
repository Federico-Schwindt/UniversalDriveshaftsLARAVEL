<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Store-chat answers: shown as a voice note or as text, plus the visual widgets that came with them (orders, tracking, summary…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('chat_messages', 'format')) $t->string('format', 8)->nullable()->after('audio');
            if (!Schema::hasColumn('chat_messages', 'widgets')) $t->json('widgets')->nullable()->after('cards');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn(['format', 'widgets']));
    }
};
