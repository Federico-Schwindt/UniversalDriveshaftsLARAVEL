<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row per Powertrain Industries part in the synced categories, linked to our product when we carry it
        Schema::create('pti_parts', function (Blueprint $t) {
            $t->id();
            $t->string('part_number', 80)->unique();
            $t->unsignedBigInteger('wp_product_id')->nullable()->index();
            $t->string('match', 20)->default('none');          // sku | sku_u | created | review | none
            $t->string('review_reason', 190)->nullable();
            $t->string('pti_type', 60);                       // PTI catalog type slug (transmission-yokes …)
            $t->decimal('pti_price', 10, 2)->nullable();      // our dealer price at PTI
            $t->json('specs')->nullable();
            $t->json('pictures')->nullable();
            $t->json('stock_by_store')->nullable();           // {"12":1,"2":4} = NV, VA
            $t->integer('stock_total')->nullable();
            $t->integer('can_build')->nullable();
            $t->timestamp('stock_synced_at')->nullable();
            $t->string('description', 190)->nullable();       // PTI's own line description (from the cart)
            $t->decimal('item_weight', 8, 2)->nullable();     // part weight
            $t->decimal('ship_weight', 8, 2)->nullable();     // packed weight PTI rates with
            $t->decimal('box_l', 6, 2)->nullable();
            $t->decimal('box_w', 6, 2)->nullable();
            $t->decimal('box_h', 6, 2)->nullable();
            $t->timestamp('box_checked_at')->nullable();
            $t->timestamps();
        });
        // PTI availability rides next to our own stock: a part shows in stock when either has it
        Schema::table('catalog_live', function (Blueprint $t) {
            if (!Schema::hasColumn('catalog_live', 'pti_in_stock')) $t->boolean('pti_in_stock')->nullable()->after('in_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pti_parts');
        Schema::table('catalog_live', fn (Blueprint $t) => $t->dropColumn('pti_in_stock'));
    }
};
