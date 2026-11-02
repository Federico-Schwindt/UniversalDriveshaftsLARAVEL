<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vehicle-to-driveshaft finder: commercial parts + vehicle fitments (see app/Services/DriveshaftFinder). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ds_parts', function (Blueprint $t) {
            $t->string('part_number', 40)->primary();
            $t->string('brand', 40)->nullable();
            $t->string('description')->nullable();
            $t->string('condition', 30)->nullable();
            $t->text('notes')->nullable();
            $t->decimal('price_list', 10, 2)->nullable();
            $t->decimal('price_cost', 10, 2)->nullable();   // our cost: never exposed to customers
            $t->decimal('price_retail', 10, 2)->nullable(); // our own selling price, when set it wins
            $t->decimal('core_price', 10, 2)->nullable();
            $t->decimal('weight', 8, 3)->nullable();
            $t->decimal('box_length', 8, 2)->nullable();
            $t->decimal('box_width', 8, 2)->nullable();
            $t->decimal('box_height', 8, 2)->nullable();
            $t->string('upc', 40)->nullable();
            $t->string('series', 60)->nullable();
            $t->string('vehicle_class', 60)->nullable();
            $t->boolean('is_buildable')->default(false);
            $t->unsignedSmallInteger('picture_count')->default(0);
            $t->string('supplier', 40)->nullable();          // never exposed to customers
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('ds_fitments', function (Blueprint $t) {
            $t->id();
            $t->string('part_number', 40)->index();
            $t->string('make', 40);
            $t->unsignedSmallInteger('year');
            $t->string('model', 60);
            foreach (['drive' => 20, 'loc' => 60, 'engine' => 60, 'trans' => 20, 'trans_speeds' => 20, 'trans_code' => 60,
                      'cab' => 40, 'bed' => 40, 'chassis' => 60, 'doors' => 20, 'trim' => 120, 'rear_wheels' => 40,
                      'transfer_case' => 80, 'differential' => 120, 'c_brg' => 60,
                      'weld_to_weld' => 20, 'weld_to_weld_1' => 20, 'weld_to_weld_2' => 20, 'weld_to_weld_3' => 20] as $col => $len) {
                $t->string($col, $len)->nullable();
            }
            $t->text('notes')->nullable();
            $t->char('fkey', 32)->unique(); // md5 of every facet column: makes the import idempotent
            $t->index(['make', 'year', 'model', 'drive']);
            $t->index('c_brg');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ds_fitments');
        Schema::dropIfExists('ds_parts');
    }
};
