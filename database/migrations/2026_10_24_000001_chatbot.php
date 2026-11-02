<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('open');          // open | handed_off | closed
            // frozen for the life of the conversation: rebuilding it would invalidate the
            // model's earlier reasoning (preserved thinking) and restart the prompt cache
            $t->longText('system_prompt');
            $t->unsignedInteger('turns')->default(0);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->timestamp('handed_off_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->string('role', 12);                               // user | assistant
            $t->json('content');                                  // API content blocks, verbatim
            $t->text('display')->nullable();                      // what the customer saw
            $t->timestamps();
            $t->index('conversation_id');
        });
        Schema::create('chat_handoffs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('email', 190);
            $t->string('name', 120)->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('request');
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_handoffs');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
