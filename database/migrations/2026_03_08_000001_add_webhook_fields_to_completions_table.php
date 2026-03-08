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
        Schema::table('completions', function (Blueprint $table) {
            $table->text('wh_data')->nullable()->after('subm_notes');
            $table->bigInteger('wh_msg_id')->nullable()->after('wh_data');
            $table->dropColumn('subm_wh_payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('completions', function (Blueprint $table) {
            $table->dropColumn(['wh_data', 'wh_msg_id']);
            $table->text('subm_wh_payload')->nullable()->after('subm_notes');
        });
    }
};
