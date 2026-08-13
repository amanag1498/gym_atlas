@extends('layouts.panel')

@section('content')
    @if ($canManageEvents)
        @section('page_actions')
            <x-action-button as="a" href="#create-event">
                <i class="ti ti-calendar-plus"></i>
                Create Event
            </x-action-button>
        @endsection
    @endif

    @php
        $scopeLabel = $panel === 'admin' ? 'Platform-wide' : (($branch ?? null)?->name ?? 'Gym-wide');
        $indexRoute = $panel === 'admin'
            ? route('web.admin.events.index')
            : route('web.gym.events.index', request()->only(['gym', 'branch']));
        $storeRoute = $panel === 'admin'
            ? route('web.admin.events.store')
            : route('web.gym.events.store', request()->only(['gym', 'branch']));
    @endphp

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Events & classes</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $panel === 'admin' ? 'Global Event Center' : 'Gym Event Center' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ $panel === 'admin'
                            ? 'Create platform-wide classes and community experiences, assign hosts, and manage every reservation from one operational view.'
                            : 'Schedule gym or branch events, assign an enrolled trainer as host, and manage bookings and attendance without leaving the workspace.' }}
                    </p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current scope</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $scopeLabel }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Members only see events available to their account.</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Booking model</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">Free or venue pay</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Online payment is not collected in this release.</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="All Events" :value="$eventSummary['total']" hint="Complete event history" tone="sky" />
            <x-stat-card label="Upcoming" :value="$eventSummary['upcoming']" hint="Published future sessions" tone="emerald" />
            <x-stat-card label="Drafts" :value="$eventSummary['drafts']" hint="Not visible to members" tone="amber" />
            <x-stat-card label="Reserved" :value="$eventSummary['bookings']" hint="Confirmed and attended spots" tone="violet" />
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                <div class="flex gap-3">
                    <i class="ti ti-alert-circle mt-0.5 text-lg"></i>
                    <div>
                        <div class="font-semibold">The event could not be saved</div>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                </div>
            </div>
        @endif

        @if ($canManageEvents)
            <x-premium-card id="create-event" class="scroll-mt-24 p-0">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/60">
                    <div>
                        <h3 class="panel-section-title">Create a new event</h3>
                        <p class="panel-section-copy">Build the member-facing listing, booking rules, venue, and host assignment.</p>
                    </div>
                    <x-status-badge :label="$scopeLabel" tone="info" />
                </div>
                <div class="p-4 sm:p-5">
                    @include('web.events._form', ['formAction' => $storeRoute, 'cancelHref' => $indexRoute])
                </div>
            </x-premium-card>
        @else
            <x-premium-card class="p-5">
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><i class="ti ti-lock text-lg"></i></div>
                    <div><h3 class="font-semibold text-slate-950 dark:text-white">Event creation is restricted</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You can review events and rosters in this workspace, but an owner or branch manager must create or edit the schedule.</p></div>
                </div>
            </x-premium-card>
        @endif

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Event schedule</h3>
                    <p class="panel-section-copy">Upcoming, draft, completed, and cancelled events remain visible for operational history.</p>
                </div>
                <x-status-badge :label="$events->total().' records'" tone="neutral" />
            </div>

            @if ($events->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1180px]">
                        <thead><tr><th>Event</th><th>Schedule</th><th>Host & scope</th><th>Reservations</th><th>Booking</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($events as $event)
                                @php
                                    $showRoute = $panel === 'admin'
                                        ? route('web.admin.events.show', $event)
                                        : route('web.gym.events.show', array_merge(request()->only(['gym', 'branch']), ['event' => $event]));
                                    $statusTone = match ($event->status) {
                                        'published' => 'success', 'draft' => 'warning', 'cancelled' => 'danger', default => 'neutral'
                                    };
                                    $reserved = (int) ($event->reserved_count ?? 0);
                                    $capacity = $event->capacity ? (int) $event->capacity : null;
                                    $occupancy = $capacity ? min(100, (int) round(($reserved / $capacity) * 100)) : null;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex min-w-[250px] items-center gap-3">
                                            <div class="inline-flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                                <span class="text-[10px] font-semibold uppercase">{{ $event->starts_at->timezone($event->timezone)->format('M') }}</span>
                                                <span class="text-base font-bold leading-none">{{ $event->starts_at->timezone($event->timezone)->format('d') }}</span>
                                            </div>
                                            <div>
                                                <a href="{{ $showRoute }}" class="font-semibold text-slate-950 transition hover:text-brand-600 dark:text-white dark:hover:text-brand-300">{{ $event->title }}</a>
                                                <div class="mt-1 flex flex-wrap gap-1.5">
                                                    @if ($event->category)<x-status-badge :label="$event->category" tone="info" />@endif
                                                    <x-status-badge :label="$event->location_name ?: 'Location pending'" tone="neutral" />
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $event->starts_at->timezone($event->timezone)->format('d M Y, g:i A') }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">to {{ $event->ends_at->timezone($event->timezone)->format('g:i A') }} · {{ $event->timezone }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $event->host?->name ?: 'No named host' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $panel === 'admin' ? 'Platform-wide' : ($event->branch?->name ?? 'Gym-wide') }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $reserved }} / {{ $capacity ?: 'Unlimited' }}</div>
                                        @if ($occupancy !== null)
                                            <div class="mt-2 h-1.5 w-28 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-brand-500" style="width: {{ $occupancy }}%"></div></div>
                                            <div class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">{{ $occupancy }}% filled</div>
                                        @else
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">No capacity limit</div>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $event->pricing_type === 'pay_at_venue' ? '₹'.number_format((float) $event->price_amount, 2) : 'Free' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->pricing_type === 'pay_at_venue' ? 'Pay at venue' : 'Instant reservation' }}</div>
                                    </td>
                                    <td><x-status-badge :label="str($event->status)->title()" :tone="$statusTone" /></td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($canViewRoster)
                                                <x-action-button as="a" variant="secondary" href="{{ $showRoute }}">View roster</x-action-button>
                                            @else
                                                <span class="text-xs text-slate-400">View only</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5"><x-empty-state title="No events created yet" message="Create the first event to open reservations and start building the upcoming schedule." /></div>
            @endif

            @if ($events->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $events->withQueryString()->links() }}</div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
