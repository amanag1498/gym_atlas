<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->string('biometric_identifier')->nullable()->after('emergency_contact_phone');
            $table->boolean('biometric_enabled')->default(false)->after('biometric_identifier');
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->unique('biometric_identifier');
        });

        DB::table('attendance_logs')
            ->where('check_in_method', 'qr')
            ->update(['check_in_method' => 'biometric']);
    }

    public function down(): void
    {
        DB::table('attendance_logs')
            ->where('check_in_method', 'biometric')
            ->update(['check_in_method' => 'qr']);

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropUnique(['biometric_identifier']);
            $table->dropColumn(['biometric_identifier', 'biometric_enabled']);
        });
    }
};
