<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill achievement_role_id by matching composite columns
        DB::statement('
            UPDATE discord_roles dr
            SET achievement_role_id = ar.id
            FROM achievement_roles ar
            WHERE dr.ar_lb_format = ar.lb_format
              AND dr.ar_lb_type = ar.lb_type
              AND dr.ar_threshold = ar.threshold
        ');
    }

    public function down(): void
    {
        // Clear the achievement_role_id values
        DB::statement('UPDATE discord_roles SET achievement_role_id = NULL');
    }
};
