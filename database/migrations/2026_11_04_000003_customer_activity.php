<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a signed-in customer did on the site, for the shop person answering their chat: pages viewed, actions
 * (form posts), server errors they hit and JavaScript errors their browser reported. Kept 30 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_activity', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('kind', 16);                    // page | action | error | client_error
            $t->string('method', 8)->nullable();
            $t->string('path', 500);
            $t->string('title', 190)->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->unsignedInteger('ms')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('ua', 255)->nullable();
            $t->text('detail')->nullable();
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activity');
    }
};
