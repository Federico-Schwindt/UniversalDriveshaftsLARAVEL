<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('type')->default('percent');      // percent | fixed_cart
            $t->decimal('amount', 10, 2)->default(0);
            $t->decimal('minimum_amount', 10, 2)->nullable();
            $t->boolean('free_shipping')->default(false);
            $t->unsignedInteger('usage_limit')->nullable(); // null = unlimited
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->string('description')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('coupons'); }
};
