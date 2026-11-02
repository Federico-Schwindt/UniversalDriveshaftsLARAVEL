<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'po_number')) $t->string('po_number', 80)->nullable()->after('coupon_code');
            if (!Schema::hasColumn('orders', 'signature_required')) $t->boolean('signature_required')->default(false)->after('po_number');
            if (!Schema::hasColumn('orders', 'ship_instructions')) $t->string('ship_instructions', 500)->nullable()->after('signature_required');
        });
    }
    public function down(): void {}
};
