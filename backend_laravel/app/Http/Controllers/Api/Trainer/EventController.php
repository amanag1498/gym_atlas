<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\SaveEventRequest;
use App\Http\Resources\EventBookingResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\TrainerProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly AuditLogService $audit,
        private readonly TrainerScopeService $trainerScope,
    ) {}

    public function index(Request $request)
    {
        if ($request->boolean('managed_only')) {
            $profile = $this->gymTrainerProfile($request);
            $query = Event::query()
                ->where('scope', 'gym')
                ->where('gym_id', $profile->gym_id)
                ->where('host_user_id', $request->user()->id)
                ->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar'])
                ->withCount(['bookings as reserved_count' => fn ($booking) => $booking->whereIn('status', ['reserved', 'attended'])])
                ->latest('starts_at');
        } else {
            $query = $this->events->trainerQuery($request->user(), $request->boolean('hosted_only'));
        }
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

    public function update(SaveEventRequest $request, Event $event)
    {
        $profile = $this->gymTrainerProfile($request);
        $this->assertManagedEvent($request, $profile, $event);
        $data = $request->validated();
        $old = $event->toArray();
        unset($data['scope'], $data['gym_id'], $data['branch_id'], $data['host_user_id']);
        $data['host_user_id'] = $request->user()->id;
        $event = $this->events->save($request->user(), $data, $event);
        $this->audit->log('trainer.event.updated', 'update', $request, $event, $profile->gym, $event->branch, oldValues: $old, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Gym event updated.');
    }

    public function cancel(Request $request, Event $event)
    {
        $profile = $this->gymTrainerProfile($request);
        $this->assertManagedEvent($request, $profile, $event);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $this->events->cancelEvent($event, $data['reason']);
        $this->audit->log('trainer.event.cancelled', 'update', $request, $event, $profile->gym, $event->branch, newValues: $event->toArray());

        return $this->success(EventResource::make($event), 'Event cancelled.');
    }

    public function roster(Request $request, Event $event)
    {
        $this->assertHostedEvent($request, $event);
        $paginator = $event->bookings()->with('user:id,name,email,phone,avatar')->orderByRaw("CASE status WHEN 'reserved' THEN 1 WHEN 'waitlisted' THEN 2 WHEN 'attended' THEN 3 ELSE 4 END")->orderBy('booked_at')->paginate(100);

        return $this->paginated($paginator, EventBookingResource::collection($paginator->getCollection()), 'Event roster fetched successfully.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking)
    {
        $this->assertHostedEvent($request, $event);
        abort_unless($booking->event_id === $event->id, 403);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $updated = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('trainer.event_attendance.updated', 'update', $request, $updated, $event->gym, $event->branch, newValues: $updated->toArray(), context: ['event_id' => $event->id]);

        return $this->success(EventBookingResource::make($updated), 'Roster attendance updated.');
    }

    private function gymTrainerProfile(Request $request): TrainerProfile
    {
        $profile = $this->trainerScope->resolveTrainerProfile($request);
        abort_unless($profile->gym_id !== null, 403, 'Only trainers enrolled in a gym can manage gym events.');

        return $profile;
    }

    private function assertManagedEvent(Request $request, TrainerProfile $profile, Event $event): void
    {
        abort_unless(
            $event->scope === 'gym'
            && $event->gym_id === $profile->gym_id
            && $event->host_user_id === $request->user()->id,
            403,
            'You can only manage gym events that you host.',
        );
    }

    private function assertHostedEvent(Request $request, Event $event): void
    {
        abort_unless(
            $event->host_user_id === $request->user()->id,
            403,
            'Only the assigned event host can manage its attendee roster.',
        );
        if ($event->scope === 'gym') {
            $profile = $this->gymTrainerProfile($request);
            $this->assertManagedEvent($request, $profile, $event);
        }
    }
}
