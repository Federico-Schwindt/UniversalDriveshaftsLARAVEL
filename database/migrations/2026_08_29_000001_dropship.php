<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('dropship_addresses')) {
            Schema::create('dropship_addresses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('company', 160);
                $t->string('street', 190);
                $t->string('unit', 60)->nullable();
                $t->string('city', 120);
                $t->string('state', 60)->nullable();
                $t->string('postcode', 20);
                $t->string('country', 2)->default('US');
                $t->string('phone', 40)->nullable();
                $t->boolean('residential')->default(false);
                $t->timestamps();
            });
        }
    }
    public function down(): void {}
};
