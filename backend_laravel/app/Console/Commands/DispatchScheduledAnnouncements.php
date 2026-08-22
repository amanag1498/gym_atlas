<?php

namespace App\Console\Commands;

use App\Services\Communication\AnnouncementService;
use Illuminate\Console\Command;

class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'communications:dispatch-announcements {--limit=100}';

    protected $description = 'Deliver scheduled announcements whose send time has arrived.';

    public function handle(AnnouncementService $announcements): int
    {
        $count = $announcements->dispatchDueAnnouncements((int) $this->option('limit'));
        $this->info($count.' scheduled announcement(s) dispatched.');

        return self::SUCCESS;
    }
}
