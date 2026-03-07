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
        Schema::table('users', function (Blueprint $table) {
            $table->string('cached_avatar_url')->nullable()->after('nk_oak');
            $table->string('cached_banner_url')->nullable()->after('cached_avatar_url');
            $table->timestamp('ninjakiwi_cache_expire')->nullable()->after('cached_banner_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cached_avatar_url', 'cached_banner_url', 'ninjakiwi_cache_expire']);
        });
    }
};
