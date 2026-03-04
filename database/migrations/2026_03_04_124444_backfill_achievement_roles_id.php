<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill IDs sequentially ordered by composite key
        DB::statement('
            WITH numbered AS (
                SELECT
                    lb_format, lb_type, threshold,
                    ROW_NUMBER() OVER (ORDER BY lb_format, lb_type, threshold) as rn
                FROM achievement_roles
            )
            UPDATE achievement_roles ar
            SET id = n.rn
            FROM numbered n
            WHERE ar.lb_format = n.lb_format
              AND ar.lb_type = n.lb_type
              AND ar.threshold = n.threshold
        ');
    }

    public function down(): void
    {
        // Clear the id values
        DB::statement('UPDATE achievement_roles SET id = NULL');
    }
};
