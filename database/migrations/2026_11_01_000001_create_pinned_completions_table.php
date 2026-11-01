<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinned_completions', function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->integer('completion_id');
            $table->primary(['user_id', 'completion_id']);

            $table->foreign('user_id')->references('discord_id')->on('users')->onDelete('cascade');
            $table->foreign('completion_id')->references('id')->on('completions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinned_completions');
    }
};
