<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the address book holds two kinds: the customer's own places (shop, branches, home) and dropship addresses for their customers
        Schema::table('dropship_addresses', function (Blueprint $t) {
            if (!Schema::hasColumn('dropship_addresses', 'kind')) $t->string('kind', 12)->default('shipping')->after('label');
        });
        // rows from the old company-only dropship form never had a contact name
        DB::table('dropship_addresses')->whereNull('name')->update(['kind' => 'dropship', 'is_default' => false]);
    }

    public function down(): void
    {
        Schema::table('dropship_addresses', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
