@extends('layouts.panel')

@php
    $panelFullWidth = true;
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Scheduled reminders</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">Review upcoming and sent membership, payment, custom due, and inactivity reminders.</p>
            </div>
            <form method="POST" action="{{ route('web.gym.reminders.run-due', request()->only(['gym', 'branch'])) }}" class="flex flex-wrap gap-3">
                @csrf
                @if (request('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <button type="submit" class="panel-btn-primary">Run due now</button>
            </form>
        </header>

        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Scheduled" :value="$reminders->total()" hint="Total matching reminders" tone="sky" />
            <x-stat-card label="Pending" :value="$pendingCount" hint="Awaiting delivery" tone="amber" />
            <x-stat-card label="Sent" :value="$sentCount" hint="Processed reminders" tone="emerald" />
        </div>

        <x-premium-card class="min-w-0 p-5 sm:p-6">
            <div class="mb-4"><h3 class="panel-section-title">Filter reminders</h3><p class="panel-section-copy">Narrow the list by type or delivery status.</p></div>
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] lg:items-end">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <div><label for="reminder-type" class="panel-label">Reminder type</label><select id="reminder-type" name="type" class="panel-select mt-1">
                    <option value="">All reminder types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
                <div><label for="reminder-status" class="panel-label">Delivery status</label><select id="reminder-status" name="status" class="panel-select mt-1">
                    <option value="">All statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="sent" @selected(request('status') === 'sent')>Sent</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select></div>
                <button type="submit" class="panel-btn-primary">Apply filters</button>
                <a href="{{ route('web.gym.reminders.index', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary text-center">Reset</a>
            </form>
        </x-premium-card>

        <section aria-label="Reminder results" class="space-y-3">
            @forelse ($reminders as $reminder)
                <x-premium-card class="min-w-0 p-5 sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="break-words text-base font-semibold text-slate-950 dark:text-white">{{ $reminder->title }}</h3>
                                <x-status-badge :label="$reminder->status" :tone="$reminder->status === 'sent' ? 'success' : ($reminder->status === 'pending' ? 'warning' : 'neutral')" />
                                <x-status-badge :label="str($reminder->type)->replace('_', ' ')->title()" tone="info" />
                            </div>
                            <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $reminder->body }}</p>
                            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ $reminder->user?->name ?? 'Member' }}
                                <span class="mx-2 text-slate-300">•</span>
                                {{ $reminder->branch?->name ?? 'Gym-wide' }}
                                @if ($reminder->membership?->membershipPlan)
                                    <span class="mx-2 text-slate-300">•</span>
                                    {{ $reminder->membership->membershipPlan->name }}
                                @endif
                            </p>
                        </div>
                        <dl class="shrink-0 border-t border-slate-200 pt-3 text-sm lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0 dark:border-slate-800">
                            <div class="flex justify-between gap-4 lg:justify-start"><dt class="text-slate-600 dark:text-slate-400">Scheduled</dt><dd class="font-semibold text-slate-950 dark:text-white">{{ optional($reminder->scheduled_for)->format('d M Y H:i') ?: 'Not scheduled' }}</dd></div>
                            <div class="mt-2 flex justify-between gap-4 lg:justify-start"><dt class="text-slate-600 dark:text-slate-400">Sent</dt><dd class="font-semibold text-slate-950 dark:text-white">{{ optional($reminder->sent_at)->format('d M Y H:i') ?: 'Not sent' }}</dd></div>
                        </dl>
                    </div>
                </x-premium-card>
            @empty
                <x-empty-state title="No reminders found" message="Membership and billing reminders will appear here after memberships are assigned or renewed." />
            @endforelse
        </section>

        @if ($reminders->hasPages())
            <x-premium-card class="p-4">{{ $reminders->links() }}</x-premium-card>
        @endif
    </div>
@endsection
