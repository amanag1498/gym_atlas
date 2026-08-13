<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\SaveEventRequest;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Events\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events, private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('web.events.index', ['pageTitle' => 'Global Events', 'breadcrumbs' => ['Platform', 'Events'], 'panel' => 'admin', 'events' => Event::query()->where('scope', 'global')->with(['host:id,name', 'gym:id,name'])->withCount(['bookings as reserved_count' => fn ($q) => $q->whereIn('status', ['reserved', 'attended'])])->latest('starts_at')->paginate(25), 'hosts' => User::query()->role('trainer')->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(SaveEventRequest $request): RedirectResponse
    {
        $event = $this->events->save($request->user(), [...$request->validated(), 'scope' => 'global', 'gym_id' => null, 'branch_id' => null]);
        $this->audit->log('platform.event.created', 'create', $request, $event, newValues: $event->toArray());

        return back()->with('status', 'Global event created successfully.');
    }

    public function show(Event $event): View
    {
        abort_unless($event->scope === 'global', 404);

        $event->load(['host', 'gym', 'branch'])->loadCount([
            'bookings as confirmed_bookings_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended']),
            'bookings as waitlisted_bookings_count' => fn ($query) => $query->where('status', 'waitlisted'),
            'bookings as attended_bookings_count' => fn ($query) => $query->where('status', 'attended'),
        ]);

        return view('web.events.show', ['pageTitle' => $event->title, 'breadcrumbs' => ['Platform', 'Events', $event->title], 'panel' => 'admin', 'event' => $event, 'bookings' => $event->bookings()->with('user')->orderBy('booked_at')->paginate(100)]);
    }

    public function edit(Event $event): View
    {
        abort_unless($event->scope === 'global' && in_array($event->status, ['draft', 'published'], true), 404);

        return view('web.events.edit', ['pageTitle' => 'Edit '.$event->title, 'breadcrumbs' => ['Platform', 'Events', 'Edit'], 'panel' => 'admin', 'event' => $event, 'hosts' => User::query()->role('trainer')->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(SaveEventRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->scope === 'global', 404);
        $old = $event->toArray();
        $event = $this->events->save($request->user(), $request->safe()->except(['scope', 'gym_id', 'branch_id']), $event);
        $this->audit->log('platform.event.updated', 'update', $request, $event, oldValues: $old, newValues: $event->toArray());

        return redirect()->route('web.admin.events.show', $event)->with('status', 'Event updated successfully.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->scope === 'global', 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $this->events->cancelEvent($event, $data['reason']);
        $this->audit->log('platform.event.cancelled', 'update', $request, $event, newValues: $event->toArray());

        return back()->with('status', 'Event cancelled.');
    }

    public function attendance(Request $request, Event $event, EventBooking $booking): RedirectResponse
    {
        abort_unless($event->scope === 'global' && $booking->event_id === $event->id, 404);
        $data = $request->validate(['status' => ['required', 'in:attended,no_show']]);
        $booking = $this->events->checkIn($request->user(), $booking, $data['status'] === 'no_show');
        $this->audit->log('platform.event_attendance.updated', 'update', $request, $booking, newValues: $booking->toArray(), context: ['event_id' => $event->id]);

        return back()->with('status', 'Attendance updated.');
    }
}
