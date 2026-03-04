<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            // Add achievement_role_id as nullable (no FK yet)
            $table->foreignId('achievement_role_id')->nullable()->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->dropForeign(['achievement_role_id']);
            $table->dropColumn('achievement_role_id');
        });
    }
};
