<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            // ShipStation requires the order's own orderKey ("Import Key") to be
            // echoed back on any update to an API-created order — orderId alone
            // isn't accepted (confirmed against this account: "Import Key must
            // be set for order" on a re-push carrying orderId without it).
            $t->string('shipstation_order_key')->nullable()->after('shipstation_order_id');
        });
    }
    public function down(): void {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('shipstation_order_key'));
    }
};
