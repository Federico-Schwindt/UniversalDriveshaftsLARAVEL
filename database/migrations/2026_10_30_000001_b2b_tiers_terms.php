<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // approved B2B accounts: Bronze / Silver / Gold by monthly spend, and Net terms with a credit limit
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'b2b_tier')) {
                $t->string('b2b_tier', 10)->nullable();
                $t->timestamp('b2b_tier_at')->nullable();
                $t->boolean('net_terms')->default(true);
                $t->decimal('credit_limit', 10, 2)->nullable();   // null = the shop default (setting b2b.credit_limit)
            }
        });
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'terms_due_at')) {
                $t->date('terms_due_at')->nullable();
                $t->string('b2b_tier', 10)->nullable();
                $t->decimal('tier_discount', 10, 2)->default(0);
                $t->index(['payment_method', 'payment_status']);
            }
        });
        if (!Schema::hasTable('b2b_tier_log')) {
            Schema::create('b2b_tier_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('from_tier', 10)->nullable();
                $t->string('to_tier', 10);
                $t->decimal('spend', 10, 2)->default(0);
                $t->string('reason', 190)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_tier_log');
        Schema::table('orders', function (Blueprint $t) { $t->dropIndex(['payment_method', 'payment_status']); $t->dropColumn(['terms_due_at', 'b2b_tier', 'tier_discount']); });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['b2b_tier', 'b2b_tier_at', 'net_terms', 'credit_limit']));
    }
};
