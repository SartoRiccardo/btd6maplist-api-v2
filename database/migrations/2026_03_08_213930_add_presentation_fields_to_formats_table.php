<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->string('slug', 255)->default('')->after('name');
            $table->text('description')->default('')->after('slug');
            $table->string('button_text', 255)->default('Submit')->after('description');
            $table->string('preview_map_1_code')->nullable()->after('button_text');
            $table->string('preview_map_2_code')->nullable()->after('preview_map_1_code');
            $table->string('preview_map_3_code')->nullable()->after('preview_map_2_code');
            $table->text('map_submission_rules')->default('')->after('preview_map_3_code');
            $table->text('completion_submission_rules')->default('')->after('map_submission_rules');
            $table->text('discord_server_url')->nullable()->after('completion_submission_rules');
        });

        // Set default values for existing formats
        DB::statement("UPDATE formats SET slug = CONCAT('format-', id) WHERE slug = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'description',
                'button_text',
                'preview_map_1_code',
                'preview_map_2_code',
                'preview_map_3_code',
                'map_submission_rules',
                'completion_submission_rules',
                'discord_server_url',
            ]);
        });
    }
};
