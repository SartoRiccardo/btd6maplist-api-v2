<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Get the max ID to set sequence correctly
        $maxId = DB::table('achievement_roles')->max('id') ?? 1;

        Schema::table('achievement_roles', function (Blueprint $table) {
            // Make ID NOT NULL
            $table->bigInteger('id')->nullable(false)->change();

            // Set as primary key
            $table->primary('id');

            // Add unique constraint on composite columns
            $table->unique(['lb_format', 'lb_type', 'threshold']);
        });

        // Create sequence owned by achievement_roles.id so it drops with the table
        DB::statement("CREATE SEQUENCE achievement_roles_id_seq OWNED BY achievement_roles.id START WITH " . ($maxId + 1));
        DB::statement("ALTER TABLE achievement_roles ALTER COLUMN id SET DEFAULT nextval('achievement_roles_id_seq')");
        DB::statement("SELECT setval('achievement_roles_id_seq', {$maxId}, true)");
    }

    public function down(): void
    {
        // Drop the sequence
        DB::statement('DROP SEQUENCE IF EXISTS achievement_roles_id_seq');

        Schema::table('achievement_roles', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropUnique(['lb_format', 'lb_type', 'threshold']);

            // Make id nullable again
            $table->bigInteger('id')->nullable()->change();
        });
    }
};
