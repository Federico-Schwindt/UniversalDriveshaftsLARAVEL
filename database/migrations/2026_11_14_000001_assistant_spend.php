<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What UDS Assistant spent on the Claude API per day (Eastern), for the daily budget (AssistantBudget). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistant_spend')) return;
        Schema::create('assistant_spend', function (Blueprint $t) {
            $t->date('day')->primary();
            $t->decimal('cost_usd', 12, 6)->default(0);
            $t->unsignedInteger('calls')->default(0);
            $t->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_spend');
    }
};
