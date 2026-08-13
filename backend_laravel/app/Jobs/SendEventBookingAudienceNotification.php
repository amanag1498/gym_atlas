<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Events\EventNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventBookingAudienceNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<string> $bookingStatuses */
    public function __construct(
        public readonly int $eventId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $bookingStatuses = ['reserved', 'waitlisted'],
    ) {
        $this->afterCommit();
    }

    public function handle(EventNotificationService $notifications): void
    {
        $event = Event::query()->find($this->eventId);
        if (! $event) {
            return;
        }

        $event->bookings()->whereIn('status', $this->bookingStatuses)->with('user')
            ->chunkById(200, function ($bookings) use ($event, $notifications): void {
                foreach ($bookings as $booking) {
                    $notifications->send($booking->user, $event, $this->type, $this->title, $this->body);
                }
            });
    }
}
