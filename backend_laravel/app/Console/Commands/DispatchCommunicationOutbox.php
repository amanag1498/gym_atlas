<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotificationOutbox;
use App\Models\CommunicationOutbox;
use Illuminate\Console\Command;

class DispatchCommunicationOutbox extends Command
{
    protected $signature = 'communications:dispatch-outbox {--limit=500}';

    protected $description = 'Dispatch pending and retryable communication outbox events.';

    public function handle(): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $ids = CommunicationOutbox::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'processing')
                            ->where('locked_at', '<=', now()->subMinutes(10));
                    });
            })
            ->where('attempt_count', '<', 5)
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverNotificationOutbox::dispatch((int) $id);
        }

        $this->info($ids->count().' communication outbox event(s) dispatched.');

        return self::SUCCESS;
    }
}
