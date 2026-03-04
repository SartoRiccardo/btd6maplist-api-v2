<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            // Make NOT NULL
            $table->foreignId('achievement_role_id')->nullable(false)->change();

            // Drop the temp FK and recreate with CASCADE
            $table->dropForeign(['achievement_role_id']);
            $table->foreign('achievement_role_id')
                ->references('id')
                ->on('achievement_roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->dropForeign(['achievement_role_id']);
            $table->foreignId('achievement_role_id')->nullable()->change();
        });
    }
};
