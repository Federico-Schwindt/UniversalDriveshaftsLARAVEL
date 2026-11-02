<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->unique();          // e.g. UDS-10001
            $t->string('status')->default('pending');       // pending|processing|completed|cancelled|failed
            $t->string('payment_status')->default('unpaid');// unpaid|paid|refunded
            $t->string('payment_method')->default('stripe');// stripe|manual
            $t->string('stripe_payment_intent')->nullable();
            // customer
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('billing_first_name')->nullable();
            $t->string('billing_last_name')->nullable();
            $t->string('billing_company')->nullable();
            $t->string('billing_address_1')->nullable();
            $t->string('billing_address_2')->nullable();
            $t->string('billing_city')->nullable();
            $t->string('billing_state')->nullable();
            $t->string('billing_postcode')->nullable();
            $t->string('billing_country', 2)->default('US');
            $t->boolean('ship_to_different')->default(false);
            $t->string('shipping_first_name')->nullable();
            $t->string('shipping_last_name')->nullable();
            $t->string('shipping_address_1')->nullable();
            $t->string('shipping_address_2')->nullable();
            $t->string('shipping_city')->nullable();
            $t->string('shipping_state')->nullable();
            $t->string('shipping_postcode')->nullable();
            $t->string('shipping_country', 2)->nullable();
            // totals (no tax — per original)
            $t->string('shipping_method')->nullable();      // flat_rate|free_shipping|local_pickup
            $t->string('shipping_label')->nullable();
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('shipping_total', 10, 2)->default(0);
            $t->decimal('discount_total', 10, 2)->default(0);
            $t->string('coupon_code')->nullable();
            $t->decimal('total', 10, 2)->default(0);
            $t->text('customer_note')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['email', 'order_number']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('product');         // product|custom_build|premade_build
            $t->unsignedBigInteger('wp_product_id')->nullable(); // reference into the WP catalog DB
            $t->string('name');
            $t->string('sku')->nullable();
            $t->decimal('price', 10, 2);
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('line_total', 10, 2);
            $t->json('meta')->nullable();                   // full dsb config / attributes
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
