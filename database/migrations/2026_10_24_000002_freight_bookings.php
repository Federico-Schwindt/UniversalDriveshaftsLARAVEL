<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LTL freight booked through Warp from the admin order page. */
return new class extends Migration
{
    public function up(): void
    {
        // how the customer said the truck must deliver at checkout: dock | liftgate | residential
        if (!Schema::hasColumn('orders', 'freight_delivery')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('freight_delivery', 20)->nullable());
        }
        Schema::create('freight_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20)->default('warp');
            $t->string('quote_id', 80)->nullable();
            $t->string('shipment_id', 80)->nullable();
            $t->string('shipment_number', 80)->nullable();
            $t->string('tracking_number', 80)->nullable();
            $t->string('lane_id', 40)->nullable();
            $t->string('carrier', 80)->nullable();
            $t->string('service_level', 40)->nullable();
            $t->decimal('price', 10, 2)->default(0);
            $t->unsignedSmallInteger('pallets')->default(1);
            $t->decimal('weight_lb', 8, 2)->nullable();          // per pallet
            $t->json('dims')->nullable();                         // [l, w, h] inches
            $t->json('accessorials')->nullable();
            $t->date('pickup_date')->nullable();
            $t->string('pickup_ready', 5)->nullable();           // HH:MM ET
            $t->string('pickup_close', 5)->nullable();
            $t->string('reference', 80)->nullable();
            $t->string('status', 20)->default('booked');         // booked | in_transit | delivered | cancelled
            $t->string('bol_url', 255)->nullable();
            $t->string('tracking_url', 255)->nullable();
            $t->json('raw')->nullable();
            $t->boolean('is_test')->default(false);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('status_checked_at')->nullable();
            $t->timestamps();
            $t->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_bookings');
        if (Schema::hasColumn('orders', 'freight_delivery')) Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('freight_delivery'));
    }
};
