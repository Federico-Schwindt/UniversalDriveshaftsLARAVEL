<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('catalog_live', function (Blueprint $t) { $t->string('thumb', 500)->nullable()->after('image'); }); }
    public function down(): void { Schema::table('catalog_live', function (Blueprint $t) { $t->dropColumn('thumb'); }); }
};
