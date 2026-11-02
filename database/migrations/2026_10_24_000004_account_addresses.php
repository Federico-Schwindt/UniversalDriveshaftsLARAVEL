<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the customer's billing address, kept on the account and prefilled at checkout
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'billing_address_1')) {
                $t->string('billing_address_1', 190)->nullable();
                $t->string('billing_address_2', 120)->nullable();
                $t->string('billing_city', 120)->nullable();
                $t->string('billing_state', 60)->nullable();
                $t->string('billing_postcode', 20)->nullable();
                $t->char('billing_country', 2)->nullable();
            }
        });
        // dropship_addresses becomes the account's address book: a contact name, a label and a default
        Schema::table('dropship_addresses', function (Blueprint $t) {
            if (!Schema::hasColumn('dropship_addresses', 'name')) {
                $t->string('name', 160)->nullable()->after('user_id');
                $t->string('label', 60)->nullable()->after('name');
                $t->boolean('is_default')->default(false)->after('residential');
            }
        });
        // dropship rows were company-only: let a private address go without one
        Schema::table('dropship_addresses', fn (Blueprint $t) => $t->string('company', 160)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country']));
        Schema::table('dropship_addresses', fn (Blueprint $t) => $t->dropColumn(['name', 'label', 'is_default']));
    }
};
