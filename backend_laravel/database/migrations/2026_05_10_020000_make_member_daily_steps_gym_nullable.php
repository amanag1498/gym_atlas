<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->dropForeign(['gym_id']);
        });

        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->change();
        });

        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->dropForeign(['gym_id']);
        });

        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable(false)->change();
        });

        Schema::table('member_daily_steps', function (Blueprint $table): void {
            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
        });
    }
};
