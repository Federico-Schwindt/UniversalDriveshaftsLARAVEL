<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ds_parts', function (Blueprint $t) {
            $t->decimal('price_auto', 10, 2)->nullable()->after('price_retail'); // set by catalog:price-driveshafts
            $t->string('price_rule', 60)->nullable()->after('price_auto');
        });
    }
    public function down(): void
    {
        Schema::table('ds_parts', fn (Blueprint $t) => $t->dropColumn(['price_auto', 'price_rule']));
    }
};
