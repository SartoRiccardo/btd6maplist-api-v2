<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // First, drop the foreign key constraint from discord_roles
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->dropForeign('achievement_roles_fk_1');
        });

        Schema::table('achievement_roles', function (Blueprint $table) {
            // Drop the existing composite primary key
            $table->dropPrimary();

            // Drop duplicate unique index
            $table->dropUnique('achievement_roles_uq_1');

            // Add ID column as nullable big integer (NOT auto-increment yet)
            $table->bigInteger('id')->nullable()->first();
        });
    }

    public function down(): void
    {
        Schema::table('achievement_roles', function (Blueprint $table) {
            $table->dropColumn('id');

            // Restore composite primary key
            $table->primary(['lb_format', 'lb_type', 'threshold']);
            $table->unique(['lb_format', 'lb_type', 'threshold'], 'achievement_roles_uq_1');
        });

        // Restore the foreign key constraint
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->foreign(['ar_lb_format', 'ar_lb_type', 'ar_threshold'])
                ->references(['lb_format', 'lb_type', 'threshold'])
                ->on('achievement_roles')
                ->cascadeOnDelete();
        });
    }
};
