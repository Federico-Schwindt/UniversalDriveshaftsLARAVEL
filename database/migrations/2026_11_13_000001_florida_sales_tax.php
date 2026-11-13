<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Florida sales tax (owner, 2026-11-13; App\Support\SalesTax). Accounts keep the Florida resale or exemption
 * certificate that lets them buy tax-free (staff approve it); orders keep where the tax went, for the DR-15 return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'tax_exempt_status')) $t->string('tax_exempt_status', 12)->nullable();   // pending, approved, rejected
            if (!Schema::hasColumn('users', 'tax_cert_kind')) $t->string('tax_cert_kind', 12)->nullable();           // resale, exempt
            if (!Schema::hasColumn('users', 'tax_cert_number')) $t->string('tax_cert_number', 40)->nullable();
            if (!Schema::hasColumn('users', 'tax_cert_expires')) $t->date('tax_cert_expires')->nullable();
            if (!Schema::hasColumn('users', 'tax_cert_file')) $t->string('tax_cert_file', 255)->nullable();
            if (!Schema::hasColumn('users', 'tax_cert_note')) $t->string('tax_cert_note', 255)->nullable();
            if (!Schema::hasColumn('users', 'tax_cert_reviewed_at')) $t->timestamp('tax_cert_reviewed_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'tax_county')) $t->string('tax_county', 40)->nullable();     // Florida county the surtax went to
            if (!Schema::hasColumn('orders', 'tax_rate')) $t->decimal('tax_rate', 6, 3)->nullable();     // percent, state + surtax
            if (!Schema::hasColumn('orders', 'taxable_total')) $t->decimal('taxable_total', 10, 2)->nullable();
            if (!Schema::hasColumn('orders', 'tax_exempt_cert')) $t->string('tax_exempt_cert', 40)->nullable();   // certificate number of an exempt Florida sale
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['tax_exempt_status', 'tax_cert_kind', 'tax_cert_number', 'tax_cert_expires', 'tax_cert_file', 'tax_cert_note', 'tax_cert_reviewed_at']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['tax_county', 'tax_rate', 'taxable_total', 'tax_exempt_cert']));
    }
};
