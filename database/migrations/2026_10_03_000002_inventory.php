<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wp_product_id')->unique(); // ties to the uds_shop product
            $t->string('sku', 100)->nullable()->index();
            $t->string('name')->nullable();
            $t->string('category', 120)->nullable();
            $t->integer('stock')->default(0);                   // current on-hand qty
            $t->enum('source', ['manual', 'shipstation'])->default('manual');
            $t->integer('reorder_at')->nullable();              // per-product low threshold Modi sets
            $t->integer('target_qty')->nullable();              // how many to keep / reorder up to
            $t->boolean('tracked')->default(true);              // include in alerts/report
            $t->timestamp('stock_synced_at')->nullable();       // last ShipStation sync
            $t->timestamps();
            $t->index(['tracked', 'stock']);
        });
        // remembers the last daily alert so we don't nag about the same items every day
        Schema::create('inventory_alert_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wp_product_id')->index();
            $t->integer('stock');
            $t->integer('reorder_at')->nullable();
            $t->string('kind', 20);                             // low | critical | out
            $t->timestamp('created_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_alert_log');
        Schema::dropIfExists('inventory');
    }
};
