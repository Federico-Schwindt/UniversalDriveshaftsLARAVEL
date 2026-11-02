<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Voice in the store chat: the customer's recording, or the spoken version of the assistant's answer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('chat_messages', 'audio')) $t->string('audio', 190)->nullable()->after('photos');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn('audio'));
    }
};
