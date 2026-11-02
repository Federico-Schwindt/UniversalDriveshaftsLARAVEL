<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->string('manage_token', 40)->nullable()->index();
            $t->boolean('reminder_sent')->default(false);
            $t->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropColumn(['manage_token', 'reminder_sent', 'cancelled_at']);
        });
    }
};
