<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TransactionalEmailService;

class EventNotificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TransactionalEmailService $emails,
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

    public function sendBooking(EventBooking $booking, Event $event, string $type, string $title, string $body, ?string $manageToken = null): void
    {
        if ($booking->user) {
            $this->send($booking->user, $event, $type, $title, $body);

            return;
        }

        $manageToken ??= $booking->manage_token_ciphertext;
        $manageUrl = $manageToken && $event->public_token
            ? route('public.events.manage', [$event->public_token, $booking->id, $manageToken])
            : null;
        $this->emails->sendTo(
            $booking->attendee_email,
            $title.' — '.$event->title,
            $body,
            array_values(array_filter([
                'When: '.$event->starts_at->timezone($event->timezone ?: config('app.timezone'))->format('D, j M Y · g:i A'),
                $event->location_name ? 'Where: '.$event->location_name : null,
                $manageUrl ? 'Manage your booking: '.$manageUrl : null,
            ])),
            $event->gym_id,
            'event_'.$type,
            ['recipient_name' => $booking->attendee_name, 'branch_id' => $event->branch_id, 'category_label' => 'Event update'],
        );
    }
}
