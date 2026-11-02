<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // an order (or part of one) sent to Powertrain Industries to ship straight to our customer
        Schema::create('pti_dropships', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->string('status', 20)->default('prepared');        // prepared | placed | cancelled | failed
            $t->json('lines');                                     // [{item_id, part_number, qty, cost}]
            $t->json('ship_to');
            $t->unsignedBigInteger('pti_address_id')->nullable();
            $t->string('shipping_method', 40)->nullable();
            $t->string('shipping_name', 120)->nullable();
            $t->decimal('shipping_cost', 10, 2)->nullable();
            $t->string('ship_from_store_id', 10)->nullable();
            $t->string('card_last_four', 4)->nullable();
            $t->decimal('pti_total', 10, 2)->nullable();
            $t->string('pti_order_id', 40)->nullable()->index();
            $t->string('tracking_number', 80)->nullable();
            $t->string('error', 500)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('placed_at')->nullable();
            $t->timestamp('tracking_checked_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pti_dropships');
    }
};
