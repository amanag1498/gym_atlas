<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('smart_attendance_hub_id')->nullable()->after('biometric_device_event_id');
            $table->json('smart_attendance_detection')->nullable()->after('smart_attendance_hub_id');
            $table->foreign('smart_attendance_hub_id', 'attendance_smart_hub_fk')->references('id')->on('smart_attendance_hubs')->nullOnDelete();
            $table->index(['smart_attendance_hub_id', 'checked_in_at'], 'attendance_smart_hub_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropForeign('attendance_smart_hub_fk');
            $table->dropIndex('attendance_smart_hub_checked_idx');
            $table->dropColumn(['smart_attendance_hub_id', 'smart_attendance_detection']);
        });
    }
};
