<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\SaveEventRequest;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventBooking;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Events\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events, private readonly ScopeResolver $scope, private readonly AuditLogService $audit) {}

    public function index(Request $request)
    {
        $gym = $this->scope->resolveGym($request);
        $branch = $this->scope->resolveBranch($request, false);
        $p = Event::query()->where('gym_id', $gym->id)->when($branch, fn ($q) => $q->where(fn ($s) => $s->whereNull('branch_id')->orWhere('branch_id', $branch->id)))->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar'])->withCount(['bookings as reserved_count' => fn ($q) => $q->whereIn('status', ['reserved', 'attended'])])->latest('starts_at')->paginate(30);

        return $this->paginated($p, EventResource::collection($p->getCollection()), 'Gym events fetched successfully.');
    }

    public function store(SaveEventRequest $request)
    {
        $gym = $this->scope->resolveGym($request);
        $branch = $this->scope->resolveBranch($request, false);
        $event = $this->events->save($request->user(), [...$request->validated(), 'scope' => 'gym', 'gym_id' => $gym->id, 'branch_id' => $branch?->id]);
        $this->audit->log('gym.event.created', 'create', $request, $event, $gym, $branch, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Gym event created.', 201);
    }

    public function update(SaveEventRequest $request, Event $event)
    {
        $gym = $this->scope->resolveGym($request);
        abort_unless($event->gym_id === $gym->id, 403);
        $old = $event->toArray();
        $event = $this->events->save($request->user(), $request->safe()->except(['scope', 'gym_id', 'branch_id']), $event);
        $this->audit->log('gym.event.updated', 'update', $request, $event, $gym, $event->branch, oldValues: $old, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Gym event updated.');
    }

    public function cancel(Request $request, Event $event)
    {
        $gym = $this->scope->resolveGym($request);
        abort_unless($event->gym_id === $gym->id, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $updated = $this->events->cancelEvent($event, $data['reason']);
        $this->audit->log('gym.event.cancelled', 'update', $request, $updated, $gym, $event->branch, newValues: $updated->toArray());

        return $this->success(EventResource::make($updated), 'Event cancelled.');
    }

    public function roster(Request $request, Event $event)
    {
        $gym = $this->scope->resolveGym($request);
        abort_unless($event->gym_id === $gym->id, 403);
        $p = $event->bookings()->with('user:id,name,email,phone,avatar')->orderBy('booked_at')->paginate(100);

        return $this->paginated($p, EventBookingResource::collection($p->getCollection()), 'Event roster fetched successfully.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking)
    {
        $gym = $this->scope->resolveGym($request);
        abort_unless($event->gym_id === $gym->id && $booking->event_id === $event->id, 403);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $updated = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('gym.event_attendance.updated', 'update', $request, $updated, $gym, $event->branch, newValues: $updated->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($updated), 'Roster attendance updated.');
    }
}
