<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // the ShipStation order id, once the order has been pushed — lets the
            // admin screen deep-link straight to it and lets us re-push (update)
            // the same order instead of creating a duplicate.
            $t->unsignedBigInteger('shipstation_order_id')->nullable()->after('stripe_payment_intent');
            $t->timestamp('shipstation_pushed_at')->nullable()->after('shipstation_order_id');
            // packing guidance computed from the cart at checkout time (tubes and
            // complete driveshafts ship one-per-box; everything else shares boxes
            // up to 50lb each) — stored so staff can see it without recomputing.
            $t->unsignedSmallInteger('package_count')->nullable()->after('shipstation_pushed_at');
            $t->decimal('ship_weight_lb', 8, 2)->nullable()->after('package_count');
            $t->string('tracking_service')->nullable()->after('tracking_carrier'); // e.g. "UPS Ground"
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['shipstation_order_id', 'shipstation_pushed_at', 'package_count', 'ship_weight_lb', 'tracking_service']);
        });
    }
};
