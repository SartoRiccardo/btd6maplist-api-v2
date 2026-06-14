<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('completions', function (Blueprint $table) {
            $table->boolean('is_video_proof_public')->default(true)->after('subm_notes');
        });
    }

    public function down(): void
    {
        Schema::table('completions', function (Blueprint $table) {
            $table->dropColumn('is_video_proof_public');
        });
    }
};
