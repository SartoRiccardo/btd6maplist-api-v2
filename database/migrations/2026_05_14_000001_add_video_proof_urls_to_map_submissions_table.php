<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->json('video_proof_urls')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->dropColumn('video_proof_urls');
        });
    }
};
