<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order status page + SMS updates: the customer's SMS consent, delivery
 * tracking fields refreshed by `orders:track`, and an append-only event
 * log that drives the public timeline and dedupes every notification.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'sms_opt_in')) $t->boolean('sms_opt_in')->default(false)->after('phone');
            if (!Schema::hasColumn('orders', 'delivered_at')) $t->timestamp('delivered_at')->nullable()->after('shipped_at');
            if (!Schema::hasColumn('orders', 'last_tracking_status')) $t->string('last_tracking_status', 120)->nullable()->after('delivered_at');
            if (!Schema::hasColumn('orders', 'last_tracking_checked_at')) $t->timestamp('last_tracking_checked_at')->nullable()->after('last_tracking_status');
        });

        if (!Schema::hasTable('order_events')) {
            Schema::create('order_events', function (Blueprint $t) {
                $t->id();
                $t->foreignId('order_id')->constrained()->cascadeOnDelete();
                $t->string('type', 60);              // placed|paid|processing|shipped|delivered|cancelled|tracking|notify:sms:shipped|…
                $t->string('label', 160);
                $t->text('detail')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['order_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['sms_opt_in', 'delivered_at', 'last_tracking_status', 'last_tracking_checked_at']));
    }
};
