<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop views that depend on legacy columns
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS snapshot_lb_linked_roles');
        DB::statement('DROP VIEW IF EXISTS lb_linked_roles');

        Schema::table('discord_roles', function (Blueprint $table) {
            // Drop legacy columns (we don't need them anymore)
            $table->dropColumn(['ar_lb_format', 'ar_lb_type', 'ar_threshold']);
        });

        // Recreate the lb_linked_roles view with new join logic
        DB::statement('
            CREATE OR REPLACE VIEW lb_linked_roles AS
            WITH user_linked_roles AS (
                SELECT DISTINCT ON (lb.user_id, ar.lb_format, ar.lb_type)
                    lb.user_id,
                    ar.id as achievement_role_id
                FROM all_leaderboards lb
                JOIN achievement_roles ar
                    ON lb.lb_format = ar.lb_format AND lb.lb_type = ar.lb_type
                WHERE lb.score >= ar.threshold AND NOT ar.for_first
                    OR lb.placement = 1 AND ar.for_first
                ORDER BY
                    lb.user_id,
                    ar.lb_format,
                    ar.lb_type,
                    ar.for_first DESC,
                    ar.threshold DESC
            )
            SELECT
                ulr.user_id,
                dr.guild_id,
                dr.role_id
            FROM user_linked_roles ulr
            JOIN discord_roles dr
                ON ulr.achievement_role_id = dr.achievement_role_id
        ');

        // Recreate the materialized view
        DB::statement('
            CREATE MATERIALIZED VIEW snapshot_lb_linked_roles AS
            SELECT user_id, guild_id, role_id
            FROM lb_linked_roles
        ');
    }

    public function down(): void
    {
        // Drop the new views
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS snapshot_lb_linked_roles');
        DB::statement('DROP VIEW IF EXISTS lb_linked_roles');

        Schema::table('discord_roles', function (Blueprint $table) {
            // Add back legacy columns
            $table->integer('ar_lb_format')->after('role_id');
            $table->string('ar_lb_type', 16)->after('ar_lb_format');
            $table->integer('ar_threshold')->after('ar_lb_type')->default(0);
        });

        // Backfill the legacy columns from achievement_roles
        DB::statement('
            UPDATE discord_roles dr
            SET ar_lb_format = ar.lb_format,
                ar_lb_type = ar.lb_type,
                ar_threshold = ar.threshold
            FROM achievement_roles ar
            WHERE dr.achievement_role_id = ar.id
        ');

        // Add back the composite FK
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->foreign(['ar_lb_format', 'ar_lb_type', 'ar_threshold'])
                ->references(['lb_format', 'lb_type', 'threshold'])
                ->on('achievement_roles')
                ->cascadeOnDelete();
        });

        // Recreate the original lb_linked_roles view
        DB::statement('
            CREATE VIEW lb_linked_roles AS
            WITH user_linked_roles AS (
                SELECT DISTINCT ON (lb.user_id, ar.lb_format, ar.lb_type)
                    lb.user_id,
                    ar.lb_format,
                    ar.lb_type,
                    ar.threshold
                FROM all_leaderboards lb
                JOIN achievement_roles ar
                    ON lb.lb_format = ar.lb_format
                    AND lb.lb_type = ar.lb_type::text
                WHERE (lb.score >= ar.threshold::double precision AND NOT ar.for_first)
                    OR (lb.placement = 1 AND ar.for_first)
                ORDER BY lb.user_id, ar.lb_format, ar.lb_type, ar.for_first DESC, ar.threshold DESC
            )
            SELECT ulr.user_id, dr.guild_id, dr.role_id
            FROM user_linked_roles ulr
            JOIN discord_roles dr
                ON ulr.lb_format = dr.ar_lb_format
                AND ulr.lb_type::text = dr.ar_lb_type::text
                AND ulr.threshold = dr.ar_threshold
        ');

        // Recreate the materialized view
        DB::statement('
            CREATE MATERIALIZED VIEW snapshot_lb_linked_roles AS
            SELECT user_id, guild_id, role_id
            FROM lb_linked_roles
            WITH NO DATA
        ');
    }
};
