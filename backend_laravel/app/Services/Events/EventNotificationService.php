<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\Notification\NotificationService;

class EventNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function send(User $user, Event $event, string $type, string $title, string $body, string $appRole = 'member'): void
    {
        $this->notifications->create(
            $user, $type, $title, $body, $event->gym_id, $event->branch_id, $event->created_by_user_id,
            data: [
                'event_id' => $event->id,
                'route' => '/events/'.$event->id,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'app_role' => $appRole,
            ],
        );
    }
}
