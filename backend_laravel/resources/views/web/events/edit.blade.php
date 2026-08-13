@extends('layouts.panel')

@section('content')
    @php
        $showRoute = $panel === 'admin'
            ? route('web.admin.events.show', $event)
            : route('web.gym.events.show', array_merge(request()->only(['gym', 'branch']), ['event' => $event]));
        $updateRoute = $panel === 'admin'
            ? route('web.admin.events.update', $event)
            : route('web.gym.events.update', array_merge(request()->only(['gym', 'branch']), ['event' => $event]));
    @endphp

    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ $showRoute }}">
            <i class="ti ti-arrow-left"></i>
            Back to roster
        </x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Edit event</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $event->title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Update the member-facing schedule, venue, host, and reservation rules. Material changes notify booked members and rebuild pending reminders.</p>
                </div>
                <div class="panel-card-muted w-full px-4 py-4 xl:max-w-sm">
                    <div class="flex items-center justify-between gap-3"><span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current status</span><x-status-badge :label="str($event->status)->title()" :tone="$event->status === 'published' ? 'success' : 'warning'" /></div>
                    <div class="mt-3 text-sm font-semibold text-slate-950 dark:text-white">{{ $event->starts_at->timezone($event->timezone)->format('d M Y, g:i A') }}</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->location_name ?: 'Location not set' }} · {{ $event->timezone }}</div>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                <div class="flex gap-3"><i class="ti ti-alert-circle mt-0.5 text-lg"></i><div><div class="font-semibold">Review the highlighted event details</div><ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
            </div>
        @endif

        <x-premium-card class="p-0">
            <div class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/60">
                <h3 class="panel-section-title">Event configuration</h3>
                <p class="panel-section-copy">Changes are saved to the existing event; booking history and member reservations are preserved.</p>
            </div>
            <div class="p-4 sm:p-5">
                @include('web.events._form', ['formAction' => $updateRoute, 'cancelHref' => $showRoute])
            </div>
        </x-premium-card>
    </div>
@endsection
