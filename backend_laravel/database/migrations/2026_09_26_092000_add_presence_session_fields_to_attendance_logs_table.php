<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->timestamp('last_presence_at')->nullable()->after('checked_in_at');
            $table->timestamp('checked_out_at')->nullable()->after('last_presence_at');
            $table->timestamp('attendance_window_ends_at')->nullable()->after('checked_out_at');
            $table->index(['check_in_method', 'checked_out_at', 'last_presence_at'], 'attendance_presence_finalize_idx');
        });

        DB::table('attendance_logs')
            ->where('check_in_method', 'smart_attendance')
            ->whereNull('attendance_window_ends_at')
            ->orderBy('id')
            ->chunkById(200, function ($logs): void {
                foreach ($logs as $log) {
                    $checkedInAt = Carbon::parse($log->checked_in_at);
                    DB::table('attendance_logs')->where('id', $log->id)->update([
                        'last_presence_at' => $checkedInAt,
                        'attendance_window_ends_at' => $checkedInAt->copy()->addHours(6),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropIndex('attendance_presence_finalize_idx');
            $table->dropColumn(['last_presence_at', 'checked_out_at', 'attendance_window_ends_at']);
        });
    }
};
