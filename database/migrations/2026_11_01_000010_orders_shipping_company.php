<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the ship-to company prices the rate (business vs home) and belongs on the label; it was never stored
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'shipping_company')) $t->string('shipping_company', 190)->nullable()->after('shipping_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('shipping_company'));
    }
};
