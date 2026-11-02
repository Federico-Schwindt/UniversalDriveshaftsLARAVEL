<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OrderEvents::record($once): a unique key makes "send once" atomic (two webhooks racing used to both pass the exists() check)
        if (!Schema::hasColumn('order_events', 'dedupe_key')) {
            Schema::table('order_events', fn (Blueprint $t) => $t->string('dedupe_key', 60)->nullable()->after('type'));
            // only the first row of each (order, type) gets the key, so existing duplicates can't break the unique index
            DB::statement('UPDATE order_events e JOIN (SELECT MIN(id) id FROM order_events GROUP BY order_id, type) f ON f.id = e.id SET e.dedupe_key = e.type');
            Schema::table('order_events', fn (Blueprint $t) => $t->unique(['order_id', 'dedupe_key'], 'order_events_once'));
        }

        // "stop e-mailing me" that outlives the cart / signup it came from
        if (!Schema::hasTable('marketing_suppressions')) {
            Schema::create('marketing_suppressions', function (Blueprint $t) {
                $t->id();
                $t->string('email', 190);
                $t->string('channel', 30);   // cart_reminders | newsletter
                $t->timestamp('created_at')->nullable();
                $t->unique(['email', 'channel']);
            });
        }

        // why a complete driveshaft is off the site: pti_absent comes back by itself, anything else (admin) stays hidden
        if (!Schema::hasColumn('ds_parts', 'hidden_reason')) {
            Schema::table('ds_parts', fn (Blueprint $t) => $t->string('hidden_reason', 20)->nullable()->after('is_active'));
            // until now only pti:driveshaft-prices hid PTI shafts in bulk: keep them returning by themselves
            DB::table('ds_parts')->where('supplier', 'PTI')->where('is_active', 0)->update(['hidden_reason' => 'pti_absent']);
        }

        // back-in-stock subscriptions stop being checked after 180 days
        if (!Schema::hasColumn('stock_alerts', 'expired_at')) {
            Schema::table('stock_alerts', fn (Blueprint $t) => $t->timestamp('expired_at')->nullable()->after('notified_at'));
        }
    }

    public function down(): void
    {
        Schema::table('order_events', function (Blueprint $t) { $t->dropUnique('order_events_once'); $t->dropColumn('dedupe_key'); });
        Schema::dropIfExists('marketing_suppressions');
        Schema::table('ds_parts', fn (Blueprint $t) => $t->dropColumn('hidden_reason'));
        Schema::table('stock_alerts', fn (Blueprint $t) => $t->dropColumn('expired_at'));
    }
};
