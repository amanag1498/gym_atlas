<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Console\Command;

class DispatchWhatsAppWebhooks extends Command
{
    protected $signature = 'communications:dispatch-webhooks {--limit=500}';

    protected $description = 'Dispatch pending, retryable, and abandoned WhatsApp webhook events.';

    public function handle(): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $ids = WhatsAppWebhookEvent::query()
            ->where('attempt_count', '<', 5)
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(10));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessWhatsAppWebhook::dispatch((int) $id);
        }

        $this->info($ids->count().' WhatsApp webhook event(s) dispatched.');

        return self::SUCCESS;
    }
}
