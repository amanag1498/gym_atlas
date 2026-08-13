<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\SaveEventRequest;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventBooking;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events, private readonly AuditLogService $audit) {}

    public function index(Request $request)
    {
        $p = Event::query()->where('scope', 'global')->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar'])->withCount(['bookings as reserved_count' => fn ($q) => $q->whereIn('status', ['reserved', 'attended'])])->latest('starts_at')->paginate(30);

        return $this->paginated($p, EventResource::collection($p->getCollection()), 'Events fetched successfully.');
    }

    public function store(SaveEventRequest $request)
    {
        $event = $this->events->save($request->user(), [...$request->validated(), 'scope' => 'global', 'gym_id' => null, 'branch_id' => null]);
        $this->audit->log('platform.event.created', 'create', $request, $event, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Global event created.', 201);
    }

    public function update(SaveEventRequest $request, Event $event)
    {
        abort_unless($event->scope === 'global', 403);
        $old = $event->toArray();
        $event = $this->events->save($request->user(), $request->safe()->except(['scope', 'gym_id', 'branch_id']), $event);
        $this->audit->log('platform.event.updated', 'update', $request, $event, oldValues: $old, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Global event updated.');
    }

    public function cancel(Request $request, Event $event)
    {
        abort_unless($event->scope === 'global', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $updated = $this->events->cancelEvent($event, $data['reason']);
        $this->audit->log('platform.event.cancelled', 'update', $request, $updated, newValues: $updated->toArray());

        return $this->success(EventResource::make($updated), 'Event cancelled.');
    }

    public function roster(Event $event)
    {
        abort_unless($event->scope === 'global', 404);
        $p = $event->bookings()->with('user:id,name,email,phone,avatar')->orderBy('booked_at')->paginate(100);

        return $this->paginated($p, EventBookingResource::collection($p->getCollection()), 'Event roster fetched successfully.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking)
    {
        abort_unless($event->scope === 'global' && $booking->event_id === $event->id, 404);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $updated = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('platform.event_attendance.updated', 'update', $request, $updated, newValues: $updated->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($updated), 'Roster attendance updated.');
    }
}
