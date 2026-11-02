<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // DiceBear profile picture: style + random seed (never the name or e-mail — the seed is sent to dicebear.com)
            $t->string('avatar_style', 32)->nullable()->after('locale');
            $t->string('avatar_seed', 40)->nullable()->after('avatar_style');
            // Stripe Customer that holds the cards the customer chose to save
            $t->string('stripe_customer_id', 64)->nullable()->after('avatar_seed');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_style', 'avatar_seed', 'stripe_customer_id']));
    }
};
