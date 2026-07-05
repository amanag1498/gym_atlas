@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Notification engine</span>
                    <h3 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Scheduled Reminders</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review pending membership, payment, custom due, and inactivity reminders before they are sent.</p>
                </div>
                <form method="POST" action="{{ route('web.gym.reminders.run-due', request()->only(['gym', 'branch'])) }}" class="flex flex-wrap gap-3">
                    @csrf
                    @if (request('type'))
                        <input type="hidden" name="type" value="{{ request('type') }}">
                    @endif
                    <button class="panel-btn-primary">Run Due Now</button>
                </form>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Scheduled" :value="$reminders->total()" hint="Total matching reminders" tone="sky" />
            <x-stat-card label="Pending" :value="$pendingCount" hint="Awaiting delivery" tone="amber" />
            <x-stat-card label="Sent" :value="$sentCount" hint="Processed reminders" tone="emerald" />
        </div>

        <x-premium-card class="p-6">
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <select name="type" class="panel-select">
                    <option value="">All reminder types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="panel-select">
                    <option value="">All statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="sent" @selected(request('status') === 'sent')>Sent</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
                <button class="panel-btn-primary">Apply Filters</button>
                <a href="{{ route('web.gym.reminders.index', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary text-center">Reset</a>
            </form>
        </x-premium-card>

        <div class="space-y-4">
            @forelse ($reminders as $reminder)
                <x-premium-card class="p-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xl font-semibold text-slate-950">{{ $reminder->title }}</h3>
                                <x-status-badge :label="$reminder->status" :tone="$reminder->status === 'sent' ? 'success' : ($reminder->status === 'pending' ? 'warning' : 'neutral')" />
                                <x-status-badge :label="str($reminder->type)->replace('_', ' ')->title()" tone="info" />
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ $reminder->body }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                {{ $reminder->user?->name ?? 'Member' }}
                                <span class="mx-2 text-slate-300">•</span>
                                {{ $reminder->branch?->name ?? 'Gym-wide' }}
                                @if ($reminder->membership?->membershipPlan)
                                    <span class="mx-2 text-slate-300">•</span>
                                    {{ $reminder->membership->membershipPlan->name }}
                                @endif
                            </p>
                        </div>
                        <div class="text-sm text-slate-500 xl:text-right">
                            <div>Scheduled: <span class="font-semibold text-slate-950">{{ optional($reminder->scheduled_for)->format('d M Y H:i') }}</span></div>
                            <div>Sent: <span class="font-semibold text-slate-950">{{ optional($reminder->sent_at)->format('d M Y H:i') ?: 'Not sent' }}</span></div>
                        </div>
                    </div>
                </x-premium-card>
            @empty
                <x-empty-state title="No reminders found" message="Membership and billing reminders will appear here after memberships are assigned or renewed." />
            @endforelse
        </div>

        @if ($reminders->hasPages())
            <x-premium-card class="p-4">{{ $reminders->links() }}</x-premium-card>
        @endif
    </div>
@endsection
