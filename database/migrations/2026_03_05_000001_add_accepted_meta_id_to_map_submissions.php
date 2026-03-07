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
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->bigInteger('accepted_meta_id')->nullable()->after('wh_msg_id');
            $table->foreign('accepted_meta_id')->references('id')->on('map_list_meta')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->dropForeign(['accepted_meta_id']);
            $table->dropColumn('accepted_meta_id');
        });
    }
};
