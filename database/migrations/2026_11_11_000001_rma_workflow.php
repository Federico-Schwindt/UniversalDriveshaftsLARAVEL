<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Returns / exchanges / cancellations as a real workflow (owner, 2026-11-10: a request did not show on its order, the
 * shop was not told, and the admin had no page per request). Requests now link to their order, carry a resolution
 * (refund, replacement, store credit), the return shipment, and a history in rma_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rma_requests', function (Blueprint $t) {
            if (!Schema::hasColumn('rma_requests', 'order_id')) $t->unsignedBigInteger('order_id')->nullable()->index()->after('order_number');
            if (!Schema::hasColumn('rma_requests', 'user_id')) $t->unsignedBigInteger('user_id')->nullable()->after('order_id');
            if (!Schema::hasColumn('rma_requests', 'resolution')) $t->string('resolution', 30)->nullable()->after('status');
            if (!Schema::hasColumn('rma_requests', 'refund_amount')) $t->decimal('refund_amount', 10, 2)->nullable()->after('resolution');
            if (!Schema::hasColumn('rma_requests', 'return_carrier')) $t->string('return_carrier', 40)->nullable()->after('refund_amount');
            if (!Schema::hasColumn('rma_requests', 'return_tracking')) $t->string('return_tracking', 80)->nullable()->after('return_carrier');
            if (!Schema::hasColumn('rma_requests', 'decided_at')) $t->timestamp('decided_at')->nullable();
            if (!Schema::hasColumn('rma_requests', 'received_at')) $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn('rma_requests', 'closed_at')) $t->timestamp('closed_at')->nullable();
            $t->index('status');
        });

        if (!Schema::hasTable('rma_events')) {
            Schema::create('rma_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('rma_id')->index();
                $t->unsignedBigInteger('user_id')->nullable();   // admin who acted; null = customer or system
                $t->string('type', 30);                          // created, status, note, email, email_failed, tracking, resolution
                $t->string('label', 190);
                $t->text('detail')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // existing requests: link the order, keep their history readable, old "completed" becomes "resolved"
        foreach (DB::table('rma_requests')->get() as $r) {
            $orderId = $r->order_number ? DB::table('orders')->where('order_number', $r->order_number)->value('id') : null;
            DB::table('rma_requests')->where('id', $r->id)->update(array_filter([
                'order_id' => $orderId,
                'status' => $r->status === 'completed' ? 'resolved' : null,
            ], fn ($v) => $v !== null));
            if (!DB::table('rma_events')->where('rma_id', $r->id)->exists()) {
                DB::table('rma_events')->insert(['rma_id' => $r->id, 'type' => 'created', 'label' => ucfirst($r->type) . ' request received', 'detail' => $r->reason, 'created_at' => $r->created_at]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rma_events');
        Schema::table('rma_requests', function (Blueprint $t) {
            $t->dropIndex(['status']);
            foreach (['order_id', 'user_id', 'resolution', 'refund_amount', 'return_carrier', 'return_tracking', 'decided_at', 'received_at', 'closed_at'] as $c) {
                if (Schema::hasColumn('rma_requests', $c)) $t->dropColumn($c);
            }
        });
    }
};
