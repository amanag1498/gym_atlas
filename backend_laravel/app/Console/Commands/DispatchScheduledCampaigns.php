<?php

namespace App\Console\Commands;

use App\Jobs\StartCommunicationCampaign;
use App\Models\CommunicationCampaign;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'communications:dispatch-campaigns {--limit=100}';

    protected $description = 'Dispatch communication campaigns whose scheduled time has arrived.';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = CommunicationCampaign::query()
            ->where(function ($query): void {
                $query->where(function ($scheduled): void {
                    $scheduled->where('status', 'scheduled')
                        ->where('scheduled_for', '<=', now());
                })->orWhere(function ($processing): void {
                    $processing->where('status', 'processing')
                        ->where('started_at', '<=', now()->subMinutes(5));
                });
            })
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            StartCommunicationCampaign::dispatch((int) $id);
        }
        $this->info($ids->count().' campaign(s) dispatched.');

        return self::SUCCESS;
    }
}
