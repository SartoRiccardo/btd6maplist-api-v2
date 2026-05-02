<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->boolean('is_lcc_leaderboard_enabled')->default(true)->after('is_no_geraldo_enabled');
            $table->boolean('is_black_border_leaderboard_enabled')->default(true)->after('is_lcc_leaderboard_enabled');
            $table->boolean('is_no_geraldo_leaderboard_enabled')->default(true)->after('is_black_border_leaderboard_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->dropColumn(['is_lcc_leaderboard_enabled', 'is_black_border_leaderboard_enabled', 'is_no_geraldo_leaderboard_enabled']);
        });
    }
};
