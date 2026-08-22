<?php

namespace App\Jobs;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationRecipient;
use App\Services\Communication\CommunicationCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class StartCommunicationCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(CommunicationCampaignService $campaigns): void
    {
        $campaign = DB::transaction(function (): ?CommunicationCampaign {
            $campaign = CommunicationCampaign::query()->lockForUpdate()->find($this->campaignId);
            if (! $campaign || in_array($campaign->status, ['completed', 'cancelled'], true)) {
                return null;
            }
            if ($campaign->status === 'processing' && $campaign->started_at?->isAfter(now()->subMinutes(5))) {
                return null;
            }
            if ($campaign->scheduled_for?->isFuture()) {
                return null;
            }
            $campaign->forceFill([
                'status' => 'processing',
                'started_at' => $campaign->started_at ?? now(),
            ])->save();

            return $campaign;
        });
        if (! $campaign) {
            return;
        }

        $campaigns->snapshotRecipients($campaign);
        $campaign->recipients()
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(10));
                    });
            })
            ->where('attempt_count', '<', 5)
            ->orderBy('id')
            ->chunkById(500, function ($recipients): void {
                $recipients->each(function (CommunicationRecipient $recipient): void {
                    DeliverCommunicationRecipient::dispatch($recipient->id)
                        ->onQueue($recipient->channel === 'whatsapp' ? 'whatsapp' : 'notifications');
                });
            });

        if ($campaign->recipients()->whereIn('status', ['pending', 'processing'])->doesntExist()) {
            $campaign->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }
}
