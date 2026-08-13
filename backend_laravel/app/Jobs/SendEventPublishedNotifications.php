<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventNotificationService;
use App\Services\Members\GymMemberAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventPublishedNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $eventId)
    {
        $this->afterCommit();
    }

    public function handle(EventNotificationService $notifications, GymMemberAccessService $memberAccess): void
    {
        $event = Event::query()->find($this->eventId);
        if (! $event || $event->status !== 'published' || $event->starts_at->isPast()) {
            return;
        }

        User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', 'member'))
            ->when($event->scope === 'gym', fn ($q) => $q->whereHas('memberProfiles', function ($profiles) use ($event, $memberAccess): void {
                $profiles->where('gym_id', $event->gym_id)->when($event->branch_id, fn ($branch) => $branch->where('branch_id', $event->branch_id));
                $memberAccess->scopeAccessibleProfiles($profiles);
            }))
            ->select(['users.id', 'users.name', 'users.email', 'users.active_role'])
            ->chunkById(200, function ($users) use ($notifications, $event): void {
                foreach ($users as $user) {
                    $notifications->send($user, $event, NotificationType::EventPublished->value, 'New event: '.$event->title, $event->starts_at->format('D, j M g:i A').' · '.($event->location_name ?: 'View event details'));
                }
            });
    }
}
