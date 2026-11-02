<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Back-in-stock subscriptions for catalog products and finder driveshafts. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->enum('kind', ['product', 'driveshaft']);
            $table->string('ref', 80);                 // wp product id, or finder part number
            $table->string('label', 255)->nullable();  // what the customer saw (name / sku)
            $table->string('vehicle', 160)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['email', 'kind', 'ref']);
            $table->index(['kind', 'notified_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_alerts'); }
};
