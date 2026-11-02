<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // /avatar/{style}/{seed} looks the seed up on every uncached hit (only account seeds are kept on disk)
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->index('avatar_seed', 'users_avatar_seed_index'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_avatar_seed_index'));
    }
};
