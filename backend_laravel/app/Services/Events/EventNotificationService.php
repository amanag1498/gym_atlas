<?php

namespace App\Services\Events;

use App\Jobs\PublishRealtimeEvent;
use App\Models\Event;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class EventNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly FcmNotificationService $fcm,
    ) {}

    public function send(User $user, Event $event, string $type, string $title, string $body, string $appRole = 'member'): void
    {
        $notification = $this->notifications->create(
            $user, $type, $title, $body, $event->gym_id, $event->branch_id, $event->created_by_user_id,
            data: ['event_id' => $event->id, 'route' => '/events/'.$event->id, 'starts_at' => $event->starts_at?->toIso8601String()],
        );
        if (! $notification) {
            return;
        }

        $data = ['notification_id' => $notification->id, 'event_id' => $event->id, 'route' => '/events/'.$event->id, 'type' => $type];
        DB::afterCommit(function () use ($user, $event, $type, $title, $body, $data, $appRole): void {
            $this->fcm->sendToUser($user, $title, $body, $data, $appRole);
            PublishRealtimeEvent::dispatch('internal/notifications', [
                'userId' => $user->id, 'title' => $title, 'body' => $body, 'type' => $type,
                'gymId' => $event->gym_id, 'branchId' => $event->branch_id, 'data' => $data,
            ]);
        });
    }
}
