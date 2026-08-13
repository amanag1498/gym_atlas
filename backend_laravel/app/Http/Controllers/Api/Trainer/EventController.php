<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
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
        $query = $this->events->trainerQuery($request->user(), $request->boolean('hosted_only'));
        $paginator = $query->paginate(min(100, max(1, $request->integer('per_page', 30))));

        return $this->paginated($paginator, EventResource::collection($paginator->getCollection()), 'Upcoming events fetched successfully.');
    }

    public function show(Request $request, Event $event)
    {
        $resolved = $this->events->trainerQuery($request->user())->whereKey($event->id)->first();
        if (! $resolved) {
            $resolved = Event::query()->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar'])
                ->withCount(['bookings as reserved_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended'])])
                ->where('host_user_id', $request->user()->id)->whereKey($event->id)->firstOrFail();
        }

        return $this->success(EventResource::make($resolved));
    }

    public function roster(Request $request, Event $event)
    {
        abort_unless($event->host_user_id === $request->user()->id, 403, 'Only the event host can view its attendee roster.');
        $paginator = $event->bookings()->with('user:id,name,email,phone,avatar')->orderByRaw("CASE status WHEN 'reserved' THEN 1 WHEN 'waitlisted' THEN 2 WHEN 'attended' THEN 3 ELSE 4 END")->orderBy('booked_at')->paginate(100);

        return $this->paginated($paginator, EventBookingResource::collection($paginator->getCollection()), 'Event roster fetched successfully.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking)
    {
        abort_unless($event->host_user_id === $request->user()->id && $booking->event_id === $event->id, 403);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $updated = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('trainer.event_attendance.updated', 'update', $request, $updated, $event->gym, $event->branch, newValues: $updated->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($updated), 'Roster attendance updated.');
    }
}
