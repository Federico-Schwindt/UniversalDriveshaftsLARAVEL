<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->string('ref')->unique();               // APT-260822-XXXX
            $t->string('service');                      // U-Joint Replacement | Balance | ...
            $t->integer('duration')->default(60);       // minutes
            $t->dateTime('starts_at');                  // stored in UTC
            $t->dateTime('ends_at');
            $t->string('customer_timezone')->default('America/New_York');
            $t->string('name');
            $t->string('email');
            $t->string('phone');
            $t->string('address')->nullable();
            $t->string('car_make_model');
            $t->text('details')->nullable();
            $t->string('status')->default('scheduled'); // scheduled|cancelled
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['starts_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
