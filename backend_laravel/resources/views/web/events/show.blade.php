@extends('layouts.panel')

@section('content')
    @php
        $indexRoute = $panel === 'admin'
            ? route('web.admin.events.index')
            : route('web.gym.events.index', request()->only(['gym', 'branch']));
        $editRoute = $panel === 'admin'
            ? route('web.admin.events.edit', $event)
            : route('web.gym.events.edit', array_merge(request()->only(['gym', 'branch']), ['event' => $event]));
        $cancelRoute = $panel === 'admin'
            ? route('web.admin.events.cancel', $event)
            : route('web.gym.events.cancel', array_merge(request()->only(['gym', 'branch']), ['event' => $event]));
        $statusTone = match ($event->status) {
            'published' => 'success', 'draft' => 'warning', 'cancelled' => 'danger', default => 'neutral'
        };
        $confirmed = (int) $event->confirmed_bookings_count;
        $capacity = $event->capacity ? (int) $event->capacity : null;
        $occupancy = $capacity ? min(100, (int) round(($confirmed / $capacity) * 100)) : null;
        $mapHref = $event->latitude !== null && $event->longitude !== null
            ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($event->latitude.','.$event->longitude)
            : null;
    @endphp

    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ $indexRoute }}"><i class="ti ti-arrow-left"></i>All events</x-action-button>
        @if ($canManageEvents && in_array($event->status, ['draft', 'published'], true))
            <x-action-button as="a" href="{{ $editRoute }}"><i class="ti ti-edit"></i>Edit event</x-action-button>
        @endif
    @endsection

    <div class="space-y-6">
        <section class="panel-hero overflow-hidden p-0">
            @if ($event->cover_image_url)
                <div class="relative h-56 w-full overflow-hidden border-b border-slate-200 dark:border-slate-800">
                    <img src="{{ $event->cover_image_url }}" alt="{{ $event->title }}" class="h-full w-full object-cover" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/65 via-transparent to-transparent"></div>
                    <div class="absolute bottom-4 left-4"><x-status-badge :label="str($event->status)->title()" :tone="$statusTone" /></div>
                </div>
            @endif
            <div class="p-5 sm:p-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        @unless ($event->cover_image_url)<x-status-badge :label="str($event->status)->title()" :tone="$statusTone" />@endunless
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($event->category)<x-status-badge :label="$event->category" tone="info" />@endif
                            <x-status-badge :label="$event->scope === 'global' ? 'Platform-wide' : ($event->branch?->name ?? 'Gym-wide')" tone="neutral" />
                        </div>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $event->title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $event->description ?: 'No event description has been added yet.' }}</p>
                    </div>
                    <div class="panel-card-muted w-full px-4 py-4 xl:max-w-md">
                        <div class="flex items-start gap-3"><i class="ti ti-calendar-time mt-0.5 text-xl text-brand-600 dark:text-brand-300"></i><div><div class="font-semibold text-slate-950 dark:text-white">{{ $event->starts_at->timezone($event->timezone)->format('l, d M Y') }}</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $event->starts_at->timezone($event->timezone)->format('g:i A') }} – {{ $event->ends_at->timezone($event->timezone)->format('g:i A') }} · {{ $event->timezone }}</div></div></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Confirmed" :value="$confirmed" hint="Active reservations" tone="emerald" />
            <x-stat-card label="Waitlist" :value="$event->waitlisted_bookings_count" hint="Queued by booking time" tone="amber" />
            <x-stat-card label="Attended" :value="$event->attended_bookings_count" hint="Checked-in members" tone="sky" />
            <x-stat-card label="Capacity" :value="$capacity ?: 'Unlimited'" :hint="$occupancy !== null ? $occupancy.'% currently filled' : 'No reservation limit'" tone="violet" />
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <x-premium-card class="p-5">
                <div class="flex items-center justify-between gap-3"><div><h3 class="panel-section-title">Event details</h3><p class="panel-section-copy">Member-facing venue, host, and reservation information.</p></div><i class="ti ti-info-circle text-xl text-slate-400"></i></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="panel-card-muted p-4"><div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Host</div><div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $event->host?->name ?: 'No named host' }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->host ? 'Can manage this event in the Trainer app' : 'Management remains with the workspace' }}</div></div>
                    <div class="panel-card-muted p-4"><div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Reservation type</div><div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $event->pricing_type === 'pay_at_venue' ? '₹'.number_format((float) $event->price_amount, 2).' at venue' : 'Free reservation' }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->payment_note ?: 'No additional payment instructions' }}</div></div>
                    <div class="panel-card-muted p-4 sm:col-span-2"><div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Venue</div><div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $event->location_name ?: 'Location not set' }}</div><div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $event->address ?: 'No venue address provided' }}</div>@if($mapHref)<a href="{{ $mapHref }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 dark:text-brand-300"><i class="ti ti-map-pin"></i>Open directions</a>@endif</div>
                </div>
            </x-premium-card>

            <div class="space-y-5">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Booking controls</h3>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-500 dark:text-slate-400">Booking opens</span><span class="text-right font-medium text-slate-900 dark:text-white">{{ $event->booking_opens_at?->timezone($event->timezone)->format('d M, g:i A') ?: 'Immediately' }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-500 dark:text-slate-400">Booking closes</span><span class="text-right font-medium text-slate-900 dark:text-white">{{ $event->booking_closes_at?->timezone($event->timezone)->format('d M, g:i A') ?: 'At event start' }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-500 dark:text-slate-400">Cancellation closes</span><span class="text-right font-medium text-slate-900 dark:text-white">{{ $event->cancellation_closes_at?->timezone($event->timezone)->format('d M, g:i A') ?: 'At event start' }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-500 dark:text-slate-400">Waitlist</span><x-status-badge :label="$event->waitlist_enabled ? 'Enabled' : 'Disabled'" :tone="$event->waitlist_enabled ? 'success' : 'neutral'" /></div>
                    </div>
                </x-premium-card>

                @if ($canManageEvents && $event->status === 'published')
                    <x-premium-card class="border-rose-200 bg-rose-50/40 p-5 dark:border-rose-500/20 dark:bg-rose-500/5">
                        <h3 class="font-semibold text-rose-800 dark:text-rose-200">Cancel event</h3>
                        <p class="mt-1 text-xs leading-5 text-rose-600 dark:text-rose-300">Booked members and the assigned host will be notified. Pending reminders are cancelled.</p>
                        <form method="POST" action="{{ $cancelRoute }}" class="mt-4 space-y-3">
                            @csrf
                            <label for="reason" class="panel-label">Cancellation reason</label>
                            <textarea id="reason" required name="reason" rows="3" class="panel-textarea" placeholder="Reason shared with attendees"></textarea>
                            <x-action-button type="submit" variant="danger" class="w-full justify-center">Cancel event</x-action-button>
                        </form>
                    </x-premium-card>
                @endif
            </div>
        </div>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div><h3 class="panel-section-title">Attendee and waitlist roster</h3><p class="panel-section-copy">Reservations are ordered by booking time so waitlist priority remains transparent.</p></div>
                <x-status-badge :label="$bookings->total().' bookings'" tone="neutral" />
            </div>

            @if ($bookings->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[900px]">
                        <thead><tr><th>Member</th><th>Booked</th><th>Reservation</th><th>Check-in</th><th class="text-right">Attendance action</th></tr></thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                @php
                                    $attendanceOpen = in_array($event->status, ['published', 'completed'], true)
                                        && in_array($booking->status, ['reserved', 'attended', 'no_show'], true)
                                        && now()->between($event->starts_at->copy()->subHours(2), $event->ends_at->copy()->addDay());
                                    $attendanceRoute = $panel === 'admin'
                                        ? route('web.admin.events.attendance', ['event' => $event, 'booking' => $booking])
                                        : route('web.gym.events.attendance', array_merge(request()->only(['gym', 'branch']), ['event' => $event, 'booking' => $booking]));
                                @endphp
                                <tr>
                                    <td><div class="flex items-center gap-3"><div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ strtoupper(substr($booking->user->name, 0, 1)) }}</div><div><div class="font-semibold text-slate-950 dark:text-white">{{ $booking->user->name }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $booking->user->email }}{{ $booking->user->phone ? ' · '.$booking->user->phone : '' }}</div></div></div></td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->booked_at->format('d M Y, g:i A') }}</td>
                                    <td><x-status-badge :label="str($booking->status)->replace('_', ' ')->title()" /></td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->checked_in_at?->format('d M Y, g:i A') ?: 'Not checked in' }}</td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($canCheckIn && $attendanceOpen)
                                                <form method="POST" action="{{ $attendanceRoute }}">@csrf @method('PUT')<input type="hidden" name="status" value="attended"><x-action-button type="submit" variant="secondary">Attended</x-action-button></form>
                                                <form method="POST" action="{{ $attendanceRoute }}">@csrf @method('PUT')<input type="hidden" name="status" value="no_show"><x-action-button type="submit" variant="secondary">No-show</x-action-button></form>
                                            @else
                                                <span class="text-xs text-slate-400">{{ $canCheckIn ? 'Opens 2 hours before start' : 'Check-in restricted' }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5"><x-empty-state title="No bookings yet" message="Member reservations and waitlist entries will appear here after the event is published." /></div>
            @endif

            @if ($bookings->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $bookings->withQueryString()->links() }}</div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
