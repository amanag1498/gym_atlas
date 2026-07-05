<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatRetentionService;
use Illuminate\Console\Command;

class PruneChatMessages extends Command
{
    protected $signature = 'chat:prune {--days= : Override chat retention window in days}';

    protected $description = 'Prune chat messages older than the configured retention window.';

    public function handle(ChatRetentionService $retentionService): int
    {
        $days = (int) ($this->option('days') ?? config('chat.message_retention_days', 365));

        if ($days <= 0) {
            $this->info('Chat message pruning is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = $retentionService->pruneOlderThan($cutoff);

        $this->info("Pruned {$deleted} chat messages older than {$days} days.");

        return self::SUCCESS;
    }
}
