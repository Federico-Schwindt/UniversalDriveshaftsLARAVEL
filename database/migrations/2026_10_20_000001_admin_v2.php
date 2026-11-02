<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->longText('value')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('admin_activity', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('action', 60)->index();
            $t->string('subject_type', 40)->nullable();
            $t->string('subject_id', 80)->nullable();
            $t->string('label', 200);
            $t->text('detail')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('ds_specs', function (Blueprint $t) {
            $t->string('part_number', 40)->primary();
            $t->string('series_front', 40)->nullable();
            $t->string('series_rear', 40)->nullable();
            $t->string('series_center', 40)->nullable();
            $t->string('tube_od', 20)->nullable();
            $t->string('tube_wall', 20)->nullable();
            $t->string('tube_material', 30)->nullable();
            $t->string('length_cc', 20)->nullable();
            $t->string('length_overall', 20)->nullable();
            $t->string('length_compressed', 20)->nullable();
            $t->string('length_extended', 20)->nullable();
            $t->string('front_end', 120)->nullable();
            $t->string('rear_end', 120)->nullable();
            $t->string('slip_yoke', 120)->nullable();
            $t->string('slip_spline', 40)->nullable();
            $t->string('flange_front', 120)->nullable();
            $t->string('flange_rear', 120)->nullable();
            $t->string('cv_type', 60)->nullable();
            $t->string('center_bearing', 120)->nullable();
            $t->string('pieces', 10)->nullable();
            $t->string('balance_rpm', 20)->nullable();
            $t->string('weight_lb', 20)->nullable();
            $t->string('u_joint_front', 60)->nullable();
            $t->string('u_joint_rear', 60)->nullable();
            $t->string('u_joint_center', 60)->nullable();
            $t->decimal('build_cost', 10, 2)->nullable();
            $t->unsignedSmallInteger('build_minutes')->nullable();
            $t->longText('components')->nullable();   // [{sku, name, qty}]
            $t->longText('photos')->nullable();       // [url]
            $t->text('build_notes')->nullable();
            $t->text('customer_notes')->nullable();
            $t->string('status', 20)->default('draft'); // draft | verified
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
        Schema::create('ds_spec_history', function (Blueprint $t) {
            $t->id();
            $t->string('part_number', 40)->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->longText('snapshot');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('ds_vehicle_notes', function (Blueprint $t) {
            $t->id();
            $t->string('make', 40)->index();
            $t->string('model', 60)->nullable();
            $t->unsignedSmallInteger('year_from')->nullable();
            $t->unsignedSmallInteger('year_to')->nullable();
            $t->text('notes');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('order_notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('kind', 12)->default('internal'); // internal | customer
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('customer_notes', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->index();
            $t->unsignedBigInteger('author_id')->nullable();
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('inventory_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('inventory_id')->index();
            $t->integer('delta');
            $t->integer('before');
            $t->integer('after');
            $t->string('reason', 160)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('admin_role', 20)->nullable()->after('is_admin'); // owner | manager | staff
            $t->string('company', 120)->nullable();
            $t->string('tags', 255)->nullable();
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('source', 20)->default('web')->after('status'); // web | admin | phone | wholesale
            $t->decimal('refund_total', 10, 2)->default(0)->after('total');
            $t->decimal('tax_total', 10, 2)->default(0)->after('discount_total');
            $t->timestamp('paid_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('tags', 255)->nullable();
        });
        Schema::table('catalog_live', function (Blueprint $t) {
            $t->timestamp('locked_at')->nullable(); // set when the admin edited the product: the live sync must not overwrite it
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->decimal('maximum_amount', 10, 2)->nullable()->after('minimum_amount');
            $t->unsignedInteger('per_customer_limit')->nullable()->after('usage_limit');
            $t->timestamp('starts_at')->nullable()->after('expires_at');
            $t->text('restrictions')->nullable(); // {categories:[], skus:[], exclude_sale:bool}
        });
    }

    public function down(): void
    {
        foreach (['settings', 'admin_activity', 'ds_specs', 'ds_spec_history', 'ds_vehicle_notes', 'order_notes', 'customer_notes', 'inventory_adjustments'] as $t) Schema::dropIfExists($t);
    }
};
