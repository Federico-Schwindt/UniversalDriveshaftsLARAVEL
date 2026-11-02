<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) { $t->string('pay_token', 48)->nullable()->index()->after('stripe_payment_intent'); $t->timestamp('pay_link_sent_at')->nullable(); });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) { $t->dropColumn(['pay_token', 'pay_link_sent_at']); });
    }
};
