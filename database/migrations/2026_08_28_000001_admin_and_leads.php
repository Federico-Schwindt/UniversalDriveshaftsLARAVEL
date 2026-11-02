<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false)->index();
        });
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->string('source')->default('website');   // contact-us | troubleshooting | custom-driveshaft-quote | ...
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('message')->nullable();
            $t->json('data')->nullable();                // full field payload
            $t->string('status')->default('new');        // new | contacted | closed
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['status', 'source']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
