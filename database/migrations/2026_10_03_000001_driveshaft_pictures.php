<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ds_parts', function (Blueprint $t) {
            $t->string('main_image')->nullable()->after('picture_count'); // primary photo path, web-relative
            $t->json('images')->nullable()->after('main_image');          // all photo paths, ordered
        });
    }
    public function down(): void
    {
        Schema::table('ds_parts', fn (Blueprint $t) => $t->dropColumn(['main_image', 'images']));
    }
};
