<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// every carrier scan for an order (from EasyPost), shown on the order status page
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->json('tracking_events')->nullable();
            $t->string('tracking_est', 40)->nullable();
            $t->timestamp('tracking_events_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['tracking_events', 'tracking_est', 'tracking_events_at']));
    }
};
