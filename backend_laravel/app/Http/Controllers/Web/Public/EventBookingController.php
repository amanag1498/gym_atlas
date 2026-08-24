<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Public\StorePublicEventBookingRequest;
use App\Models\EventBooking;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventBookingController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly AuditLogService $audit,
    ) {}

    public function show(string $publicToken): View
    {
        return view('public.events.show', ['event' => $this->events->shareableEvent($publicToken)]);
    }

    public function store(StorePublicEventBookingRequest $request, string $publicToken): RedirectResponse
    {
        $event = $this->events->publicEvent($publicToken);
        $result = $this->events->bookGuest($event, $request->validated());
        $this->audit->log('public.event_booking.created', 'create', $request, $result['booking'], $event->gym, $event->branch,
            newValues: ['status' => $result['booking']->status, 'booking_source' => 'public_web'], context: ['event_id' => $event->id]);

        return redirect()->route('public.events.manage', [
            $event->public_token,
            $result['booking'],
            $result['manage_token'],
        ])->with('booking_created', true);
    }

    public function manage(string $publicToken, EventBooking $booking, string $manageToken): View
    {
        $event = $this->events->eventByPublicTokenForManagement($publicToken);

        return view('public.events.manage', [
            'event' => $event,
            'booking' => $this->events->guestBooking($event, $booking, $manageToken),
            'manageToken' => $manageToken,
        ]);
    }

    public function cancel(string $publicToken, EventBooking $booking, string $manageToken): RedirectResponse
    {
        $event = $this->events->eventByPublicTokenForManagement($publicToken);
        $booking = $this->events->cancelGuest($event, $booking, $manageToken);
        $this->audit->log('public.event_booking.cancelled', 'update', request(), $booking, $event->gym, $event->branch,
            newValues: ['status' => $booking->status], context: ['event_id' => $event->id]);

        return redirect()->route('public.events.manage', [$event->public_token, $booking, $manageToken])
            ->with('status', 'Your event booking has been cancelled.');
    }
}
