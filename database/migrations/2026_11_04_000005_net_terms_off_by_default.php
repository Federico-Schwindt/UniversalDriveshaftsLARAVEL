<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Net terms (pay by invoice) only when the shop turns them on for an account (owner, 2026-11-04: only one account has them). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('net_terms')->default(false)->change());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('net_terms')->default(true)->change());
    }
};
