<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('tracking_number')->nullable();
            $t->string('tracking_carrier')->nullable();  // UPS | FedEx | USPS | ...
            $t->timestamp('shipped_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('orders', fn(Blueprint $t) => $t->dropColumn(['tracking_number','tracking_carrier','shipped_at']));
    }
};
