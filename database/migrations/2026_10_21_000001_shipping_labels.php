<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_labels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12)->default('outbound');     // outbound | return
            $t->string('carrier_code', 60);
            $t->string('service_code', 60);
            $t->string('package_code', 60)->default('package');
            $t->string('service_label', 120)->nullable();
            $t->string('tracking_number', 120)->nullable();
            $t->decimal('cost', 10, 2)->default(0);
            $t->decimal('insurance_cost', 10, 2)->default(0);
            $t->decimal('weight_lb', 8, 2)->nullable();
            $t->decimal('length_in', 8, 2)->nullable();
            $t->decimal('width_in', 8, 2)->nullable();
            $t->decimal('height_in', 8, 2)->nullable();
            $t->unsignedBigInteger('shipstation_shipment_id')->nullable();
            $t->string('pdf_path', 255)->nullable();
            $t->boolean('is_test')->default(false);
            $t->timestamp('voided_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['order_id', 'kind']);
            $t->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_labels');
    }
};
