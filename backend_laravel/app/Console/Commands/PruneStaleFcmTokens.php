<?php

namespace App\Console\Commands;

use App\Models\UserFcmToken;
use Illuminate\Console\Command;

class PruneStaleFcmTokens extends Command
{
    protected $signature = 'notifications:prune-fcm-tokens {--days=}';

    protected $description = 'Delete device registrations that have not refreshed recently.';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('services.firebase.token_stale_days', 60)));
        $deleted = UserFcmToken::query()
            ->where(fn ($query) => $query
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subDays($days)))
            ->delete();

        $this->info($deleted.' stale Firebase token(s) removed.');

        return self::SUCCESS;
    }
}
