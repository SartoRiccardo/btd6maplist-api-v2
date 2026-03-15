<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW all_leaderboards AS
            -- Format 1: Maplist
            SELECT 1 AS lb_format, 'points'::text AS lb_type, user_id, score, placement
            FROM leaderboard_maplist_points
            UNION ALL
            SELECT 1 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(1)
            UNION ALL
            SELECT 1 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(1)
            UNION ALL
            SELECT 1 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(1)
            UNION ALL
            -- Format 51: Expert List
            SELECT 51 AS lb_format, 'points'::text AS lb_type, user_id, score, placement
            FROM leaderboard_experts_points
            UNION ALL
            SELECT 51 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(51)
            UNION ALL
            SELECT 51 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(51)
            UNION ALL
            SELECT 51 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(51)
            UNION ALL
            -- Format 52: Best of the Best
            SELECT 52 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(52)
            UNION ALL
            SELECT 52 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(52)
            UNION ALL
            SELECT 52 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(52)
            UNION ALL
            -- Format 11: Nostalgia Pack
            SELECT 11 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(11)
            UNION ALL
            SELECT 11 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(11)
            UNION ALL
            SELECT 11 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(11)
        ");

        DB::statement("REFRESH MATERIALIZED VIEW snapshot_lb_linked_roles");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original (buggy) version
        DB::statement("
            CREATE OR REPLACE VIEW all_leaderboards AS
            SELECT 1 AS lb_format, 'points'::text AS lb_type, user_id, score, placement
            FROM leaderboard_maplist_points
            UNION ALL
            SELECT 1 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(1)
            UNION ALL
            SELECT 1 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(51)
            UNION ALL
            SELECT 1 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(1)
            UNION ALL
            SELECT 51 AS lb_format, 'points'::text AS lb_type, user_id, score, placement
            FROM leaderboard_experts_points
            UNION ALL
            SELECT 51 AS lb_format, 'lccs'::text AS lb_type, user_id, score, placement
            FROM leaderboard_lccs(51)
            UNION ALL
            SELECT 51 AS lb_format, 'no_geraldo'::text AS lb_type, user_id, score, placement
            FROM leaderboard_no_geraldo(51)
            UNION ALL
            SELECT 51 AS lb_format, 'black_border'::text AS lb_type, user_id, score, placement
            FROM leaderboard_black_border(51)
        ");

        DB::statement("REFRESH MATERIALIZED VIEW snapshot_lb_linked_roles");
    }
};
