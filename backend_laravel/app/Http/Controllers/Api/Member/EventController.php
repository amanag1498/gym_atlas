<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events, private readonly AuditLogService $audit) {}

    public function index(Request $request)
    {
        $paginator = $this->events->memberQuery($request->user())
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->paginate(min(100, max(1, $request->integer('per_page', 30))));

        return $this->paginated($paginator, EventResource::collection($paginator->getCollection()), 'Upcoming events fetched successfully.');
    }

    public function show(Request $request, Event $event)
    {
        $resolved = $this->events->memberQuery($request->user())->whereKey($event->id)->first();
        if (! $resolved) {
            $resolved = Event::query()
                ->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar', 'bookings' => fn ($query) => $query->where('user_id', $request->user()->id)])
                ->withCount(['bookings as reserved_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended'])])
                ->whereHas('bookings', fn ($query) => $query->where('user_id', $request->user()->id))
                ->whereKey($event->id)
                ->firstOrFail();
        }

        return $this->success(EventResource::make($resolved));
    }

    public function bookings(Request $request)
    {
        $userId = $request->user()->id;
        $paginator = $request->user()->eventBookings()->with([
            'event' => fn ($event) => $event
                ->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar', 'bookings' => fn ($booking) => $booking->where('user_id', $userId)])
                ->withCount(['bookings as reserved_count' => fn ($booking) => $booking->whereIn('status', ['reserved', 'attended'])]),
        ])
            ->whereHas('event', fn ($q) => $q->where('ends_at', '>=', now()))
            ->latest('booked_at')->paginate(min(100, max(1, $request->integer('per_page', 30))));

        return $this->paginated($paginator, EventBookingResource::collection($paginator->getCollection()), 'Event bookings fetched successfully.');
    }

    public function book(Request $request, Event $event)
    {
        $booking = $this->events->book($request->user(), $event);
        $this->audit->log('member.event.booked', 'create', $request, $booking, $event->gym, $event->branch, newValues: $booking->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($booking), 'Event booking saved.', 201);
    }

    public function cancel(Request $request, Event $event)
    {
        $booking = $this->events->cancel($request->user(), $event);
        $this->audit->log('member.event_booking.cancelled', 'update', $request, $booking, $event->gym, $event->branch, newValues: $booking->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($booking), 'Event booking cancelled.');
    }
}
