<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_id')->nullable()->index();
            $t->string('phone')->nullable();
            $t->string('password')->nullable()->change();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['google_id', 'phone']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('user_id');
        });
    }
};
