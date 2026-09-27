<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_app_presences', function (Blueprint $table): void {
            $table->string('bluetooth_permission_status', 30)->nullable()->after('app_version');
            $table->boolean('smart_attendance_scanning')->nullable()->after('bluetooth_permission_status');
            $table->string('smart_attendance_mode', 30)->nullable()->after('smart_attendance_scanning');
            $table->timestamp('smart_attendance_last_detection_at')->nullable()->after('smart_attendance_mode');
            $table->index('bluetooth_permission_status', 'user_app_presence_bluetooth_permission_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_app_presences', function (Blueprint $table): void {
            $table->dropIndex('user_app_presence_bluetooth_permission_idx');
            $table->dropColumn([
                'bluetooth_permission_status',
                'smart_attendance_scanning',
                'smart_attendance_mode',
                'smart_attendance_last_detection_at',
            ]);
        });
    }
};
