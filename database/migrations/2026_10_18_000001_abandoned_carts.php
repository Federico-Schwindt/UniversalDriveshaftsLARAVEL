<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Abandoned-cart recovery: a snapshot of the session cart keyed by session, with the reminder state. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 120)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('token', 64)->unique();
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('abandoned_carts'); }
};
