<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers and orders imported from the WooCommerce store (woo:import). wp_user_id / wc_order_id make the import
 * re-runnable; wp_password keeps the WordPress hash until the customer's first sign-in turns it into ours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('wp_user_id')->nullable()->unique();
            $t->string('wp_password', 255)->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('wc_order_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['wp_user_id', 'wp_password']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('wc_order_id'));
    }
};
