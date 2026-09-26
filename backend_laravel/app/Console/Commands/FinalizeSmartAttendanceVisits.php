<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class FinalizeSmartAttendanceVisits extends Command
{
    protected $signature = 'attendance:finalize-smart-visits';

    protected $description = 'Save out times for Smart Attendance visits with no recent hub presence';

    public function handle(AttendanceService $attendance): int
    {
        $count = $attendance->finalizeStaleSmartAttendanceVisits();
        $this->info("Finalized {$count} Smart Attendance visit(s).");

        return self::SUCCESS;
    }
}
