<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what the customer saw with each answer (part cards), their photos, and who wrote it (a person from the shop)
        Schema::table('chat_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('chat_messages', 'cards')) $t->json('cards')->nullable()->after('display');
            if (!Schema::hasColumn('chat_messages', 'photos')) $t->json('photos')->nullable()->after('cards');
            if (!Schema::hasColumn('chat_messages', 'agent_id')) $t->unsignedBigInteger('agent_id')->nullable()->after('photos');
        });
        // bot = the AI answers; human = someone from the shop took the chat over in the admin
        Schema::table('chat_conversations', function (Blueprint $t) {
            if (!Schema::hasColumn('chat_conversations', 'mode')) $t->string('mode', 10)->default('bot')->after('provider');
            if (!Schema::hasColumn('chat_conversations', 'agent_id')) $t->unsignedBigInteger('agent_id')->nullable()->after('mode');
            if (!Schema::hasColumn('chat_conversations', 'needs_human_at')) $t->timestamp('needs_human_at')->nullable()->after('agent_id');
            if (!Schema::hasColumn('chat_conversations', 'admin_seen_id')) $t->unsignedBigInteger('admin_seen_id')->default(0)->after('needs_human_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn(['cards', 'photos', 'agent_id']));
        Schema::table('chat_conversations', fn (Blueprint $t) => $t->dropColumn(['mode', 'agent_id', 'needs_human_at', 'admin_seen_id']));
    }
};
