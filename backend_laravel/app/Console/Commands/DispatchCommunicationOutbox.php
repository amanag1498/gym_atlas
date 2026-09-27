<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotificationOutbox;
use App\Models\CommunicationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

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
                        $stale->whereIn('status', ['queued', 'processing'])
                            ->where(function ($lock): void {
                                $lock->whereNull('locked_at')
                                    ->orWhere('locked_at', '<=', now()->subMinutes(10));
                            });
                    });
            })
            ->where('attempt_count', '<', 5)
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;
        foreach ($ids as $id) {
            $outbox = $this->reserve((int) $id);
            if (! $outbox) {
                continue;
            }

            try {
                DeliverNotificationOutbox::dispatch($outbox->id);
                $dispatched++;
            } catch (Throwable $exception) {
                CommunicationOutbox::query()
                    ->whereKey($outbox->id)
                    ->where('status', 'queued')
                    ->update([
                        'status' => 'pending',
                        'locked_at' => null,
                        'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    ]);

                report($exception);
            }
        }

        $this->info($dispatched.' communication outbox event(s) dispatched.');

        return self::SUCCESS;
    }

    private function reserve(int $id): ?CommunicationOutbox
    {
        return DB::transaction(function () use ($id): ?CommunicationOutbox {
            $outbox = CommunicationOutbox::query()->lockForUpdate()->find($id);
            $staleBefore = now()->subMinutes(10);

            if (! $outbox
                || $outbox->attempt_count >= 5
                || ($outbox->available_at && $outbox->available_at->isFuture())
                || ($outbox->status === 'queued' && $outbox->locked_at?->isAfter($staleBefore))
                || ($outbox->status === 'processing' && $outbox->locked_at?->isAfter($staleBefore))
                || ! in_array($outbox->status, ['pending', 'failed', 'queued', 'processing'], true)) {
                return null;
            }

            $outbox->forceFill([
                'status' => 'queued',
                'locked_at' => now(),
                'last_error' => null,
            ])->save();

            return $outbox;
        });
    }
}
