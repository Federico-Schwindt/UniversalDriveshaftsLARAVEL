<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // which AI answered the conversation (claude | gemini); each keeps its history in its own wire format
        Schema::table('chat_conversations', function (Blueprint $t) {
            if (!Schema::hasColumn('chat_conversations', 'provider')) $t->string('provider', 20)->default('claude')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', fn (Blueprint $t) => $t->dropColumn('provider'));
    }
};
