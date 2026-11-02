<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Live overlay of the WooCommerce catalog (the WP copy we read is a read-only snapshot). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_live', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_product_id')->primary();
            $table->string('sku', 80)->nullable()->index();
            $table->string('name', 255)->nullable();
            $table->string('slug', 255)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->string('image', 500)->nullable();
            $table->json('gallery')->nullable();
            $table->json('categories')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('catalog_live'); }
};
