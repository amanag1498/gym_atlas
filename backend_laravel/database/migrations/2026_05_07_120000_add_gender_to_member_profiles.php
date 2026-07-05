<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->string('gender', 40)->nullable()->after('fitness_goal');
        });
    }

    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropColumn('gender');
        });
    }
};
