<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('gender', 40)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
        });

        DB::table('member_profiles')
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->orderByDesc('id')
            ->cursor()
            ->each(function (object $profile): void {
                DB::table('users')
                    ->where('id', $profile->user_id)
                    ->whereNull('gender')
                    ->update(['gender' => $profile->gender]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['gender', 'date_of_birth']);
        });
    }
};
