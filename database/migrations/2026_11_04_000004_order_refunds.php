<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds with their own date, so net sales take them off on the day they were made (the WooCommerce Analytics way),
 * and the original WooCommerce status of imported orders (which statuses count as sales follows WooCommerce for them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedBigInteger('wc_refund_id')->nullable()->unique();
            $t->decimal('amount', 10, 2);
            $t->decimal('shipping', 10, 2)->default(0);   // part of the amount that refunded shipping
            $t->decimal('tax', 10, 2)->default(0);
            $t->string('reason', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
        Schema::table('orders', fn (Blueprint $t) => $t->string('wc_status', 30)->nullable()->after('wc_order_id'));
        // refunds made on this site before this table: dated at the order's last update (best we know)
        foreach (DB::table('orders')->where('refund_total', '>', 0)->whereNull('wc_order_id')->get(['id', 'refund_total', 'updated_at']) as $o) {
            DB::table('order_refunds')->insert(['order_id' => $o->id, 'amount' => $o->refund_total, 'reason' => 'Recorded before refund dates were kept', 'created_at' => $o->updated_at]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('wc_status'));
    }
};
