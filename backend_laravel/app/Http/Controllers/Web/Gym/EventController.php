<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\SaveEventRequest;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events, private readonly GymWebPanelService $panel, private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventsView->value, $gym);
        $branch = $this->panel->resolveBranch($request, $gym);

        return view('web.events.index', ['pageTitle' => 'Gym Events', 'breadcrumbs' => ['Gym', 'Events'], 'panel' => 'gym', 'gym' => $gym, 'branch' => $branch, 'events' => Event::query()->where('gym_id', $gym->id)->when($branch, fn ($q) => $q->where(fn ($s) => $s->whereNull('branch_id')->orWhere('branch_id', $branch->id)))->with(['host:id,name', 'branch:id,name'])->withCount(['bookings as reserved_count' => fn ($q) => $q->whereIn('status', ['reserved', 'attended'])])->latest('starts_at')->paginate(25), 'hosts' => User::query()->whereHas('trainerProfile', fn ($q) => $q->where('gym_id', $gym->id)->where('is_active', true))->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(SaveEventRequest $request): RedirectResponse
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventsManage->value, $gym);
        $branch = $this->panel->resolveBranch($request, $gym);
        $event = $this->events->save($request->user(), [...$request->validated(), 'scope' => 'gym', 'gym_id' => $gym->id, 'branch_id' => $branch?->id]);
        $this->audit->log('gym.event.created', 'create', $request, $event, $gym, $branch, newValues: $event->toArray());

        return back()->with('status', 'Gym event created successfully.');
    }

    public function show(Request $request, Event $event): View
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventBookingsView->value, $gym);
        abort_unless($event->gym_id === $gym->id, 404);

        $event->load(['host', 'branch'])->loadCount([
            'bookings as confirmed_bookings_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended']),
            'bookings as waitlisted_bookings_count' => fn ($query) => $query->where('status', 'waitlisted'),
            'bookings as attended_bookings_count' => fn ($query) => $query->where('status', 'attended'),
        ]);

        return view('web.events.show', ['pageTitle' => $event->title, 'breadcrumbs' => ['Gym', 'Events', $event->title], 'panel' => 'gym', 'gym' => $gym, 'event' => $event, 'bookings' => $event->bookings()->with('user')->orderBy('booked_at')->paginate(100)]);
    }

    public function edit(Request $request, Event $event): View
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventsManage->value, $gym);
        abort_unless($event->gym_id === $gym->id && in_array($event->status, ['draft', 'published'], true), 404);

        return view('web.events.edit', ['pageTitle' => 'Edit '.$event->title, 'breadcrumbs' => ['Gym', 'Events', 'Edit'], 'panel' => 'gym', 'gym' => $gym, 'event' => $event, 'hosts' => User::query()->whereHas('trainerProfile', fn ($q) => $q->where('gym_id', $gym->id)->where('is_active', true))->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(SaveEventRequest $request, Event $event): RedirectResponse
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventsManage->value, $gym);
        abort_unless($event->gym_id === $gym->id, 404);
        $old = $event->toArray();
        $event = $this->events->save($request->user(), $request->safe()->except(['scope', 'gym_id', 'branch_id']), $event);
        $this->audit->log('gym.event.updated', 'update', $request, $event, $gym, $event->branch, oldValues: $old, newValues: $event->toArray());

        return redirect()->route('web.gym.events.show', array_merge($request->only(['gym', 'branch']), ['event' => $event]))->with('status', 'Event updated successfully.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventsManage->value, $gym);
        abort_unless($event->gym_id === $gym->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $this->events->cancelEvent($event, $data['reason']);
        $this->audit->log('gym.event.cancelled', 'update', $request, $event, $gym, $event->branch, newValues: $event->toArray());

        return back()->with('status', 'Event cancelled.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking): RedirectResponse
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::EventCheckIn->value, $gym);
        abort_unless($event->gym_id === $gym->id && $booking->event_id === $event->id, 404);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $booking = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('gym.event_attendance.updated', 'update', $request, $booking, $gym, $event->branch, newValues: $booking->toArray(), context: ['event_id' => $event->id]);

        return back()->with('status', 'Attendance updated.');
    }
}
