<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('completion_proofs', function (Blueprint $table) {
            $table->boolean('is_added_by_admin')->default(true)->after('proof_type');
        });

        DB::table('completion_proofs')->update(['is_added_by_admin' => false]);
    }

    public function down(): void
    {
        Schema::table('completion_proofs', function (Blueprint $table) {
            $table->dropColumn('is_added_by_admin');
        });
    }
};
