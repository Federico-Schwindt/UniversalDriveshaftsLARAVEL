<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Labels can come from EasyPost or (historically) ShipStation; voiding one has to go back to whoever sold it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_labels', function (Blueprint $t) {
            $t->string('provider', 20)->default('shipstation')->after('kind');
            $t->string('provider_shipment_id', 60)->nullable()->after('shipstation_shipment_id');
        });
        DB::table('shipping_labels')->whereNotNull('shipstation_shipment_id')
            ->update(['provider_shipment_id' => DB::raw('shipstation_shipment_id')]);
    }

    public function down(): void
    {
        Schema::table('shipping_labels', function (Blueprint $t) {
            $t->dropColumn(['provider', 'provider_shipment_id']);
        });
    }
};
