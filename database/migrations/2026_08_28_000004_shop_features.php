<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // B2B wholesale: tag users with a group + discount
        Schema::table('users', function (Blueprint $t) {
            $t->string('wholesale_group')->nullable();        // Business B2B | Platinum | Production STAFF
            $t->decimal('wholesale_discount', 5, 2)->default(0); // percent off catalog
            $t->string('wholesale_status')->default('none');   // none | pending | approved
        });
        Schema::create('wishlists', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('session_id')->nullable()->index();
            $t->unsignedBigInteger('wp_product_id');
            $t->string('name'); $t->string('sku')->nullable(); $t->decimal('price',10,2)->default(0); $t->string('image')->nullable();
            $t->timestamps();
        });
        Schema::create('saved_designs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('ref')->unique();
            $t->string('title')->nullable();
            $t->json('config');
            $t->decimal('price',10,2)->default(0);
            $t->timestamps();
        });
        Schema::create('rma_requests', function (Blueprint $t) {
            $t->id();
            $t->string('ref')->unique();          // RMA-260828-XXXX
            $t->string('type');                    // return | exchange | cancel
            $t->string('order_number')->nullable();
            $t->string('name'); $t->string('email'); $t->string('phone')->nullable();
            $t->string('product')->nullable();
            $t->string('reason')->nullable();
            $t->text('details')->nullable();
            $t->string('status')->default('new');  // new | approved | rejected | completed
            $t->string('ip',45)->nullable();
            $t->timestamps();
            $t->index(['type','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('rma_requests'); Schema::dropIfExists('saved_designs'); Schema::dropIfExists('wishlists');
        Schema::table('users', fn(Blueprint $t)=>$t->dropColumn(['wholesale_group','wholesale_discount','wholesale_status']));
    }
};
