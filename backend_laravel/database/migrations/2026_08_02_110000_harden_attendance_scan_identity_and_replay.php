<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropUnique('member_profiles_biometric_identifier_unique');
            $table->unique(
                ['gym_id', 'biometric_identifier'],
                'member_profiles_gym_biometric_identifier_unique'
            );
        });

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->char('scan_reference_hash', 64)->nullable()->after('source_device');
            $table->unique('scan_reference_hash');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropUnique(['scan_reference_hash']);
            $table->dropColumn('scan_reference_hash');
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropUnique('member_profiles_gym_biometric_identifier_unique');
            $table->unique('biometric_identifier');
        });
    }
};
