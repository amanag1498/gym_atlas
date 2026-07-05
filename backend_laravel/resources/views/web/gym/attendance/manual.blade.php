@extends('layouts.panel')

@section('content')
    @php
        $scopeQuery = request()->only(['gym', 'branch']);
    @endphp

    <div class="space-y-5">
        <div class="rounded-[28px] border border-slate-200 bg-white px-5 py-5 shadow-[0_28px_70px_-52px_rgba(15,23,42,0.42)] dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">Manual Desk</span>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Manual Check-in</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        Use this when biometric scan is unavailable, a front-desk exception is approved, or an allowed backfill must be recorded with a clear note.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('web.gym.attendance.index', $scopeQuery) }}" class="panel-btn-secondary">Back to Attendance</a>
                    <x-status-badge label="Manual Entry" tone="warning" />
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Branches" :value="$branches->count()" hint="Allowed attendance scope" tone="sky" />
            <x-stat-card label="Members" :value="$members->count()" hint="Available for manual entry" tone="emerald" />
            <x-stat-card label="Source Device" value="web-admin" hint="Stored with the check-in" tone="violet" />
            <x-stat-card label="Audit Mode" value="Strict" hint="Actor, notes, and time are retained" tone="amber" />
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.05fr)_360px]">
            <x-premium-card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Check-in Form</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Prefer the current time unless you are intentionally backfilling a real missed entry.</p>
                    </div>
                </div>

                <form action="{{ route('web.gym.attendance.manual.store', $scopeQuery) }}" method="POST" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                    <x-form-select name="branch_id" label="Branch" :selected="old('branch_id', request('branch_id'))" :options="$branches->pluck('name', 'id')->all()" required />
                    <x-form-select name="member_id" label="Member" :selected="old('member_id', request('member_id'))" :options="$members->pluck('name', 'id')->all()" required />
                    <x-form-input type="datetime-local" name="checked_in_at" label="Checked In At" :value="old('checked_in_at')" />
                    <x-form-input name="source_device" label="Source Device" :value="old('source_device', 'web-admin')" />
                    <div class="md:col-span-2">
                        <label for="notes" class="panel-label">Notes</label>
                        <textarea id="notes" name="notes" class="panel-textarea" rows="4" placeholder="Explain why a manual entry is being used">{{ old('notes') }}</textarea>
                    </div>
                    <div class="md:col-span-2 flex flex-wrap gap-2">
                        <button type="submit" class="panel-btn-primary">Record Manual Check-in</button>
                        <a href="{{ route('web.gym.attendance.index', $scopeQuery) }}" class="panel-btn-secondary">Cancel</a>
                    </div>
                </form>
            </x-premium-card>

            <div class="space-y-5">
                <x-premium-card class="p-5">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">When to Use Manual</h3>
                    <div class="mt-4 space-y-3">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Good use case</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Reception desk entry, member phone unavailable, or staff-approved missed scan.</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Backfill caution</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Only set a past timestamp when there is a real operational reason and a note is added.</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Duplicate protection</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">If same-day duplicate blocking is enabled in gym settings, the system will stop double check-ins.</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Recommended Flow</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            1. Choose the exact branch the member entered.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            2. Select the member and leave time blank for real-time check-in.
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            3. Add a short note whenever this is not a normal front-desk entry.
                        </div>
                    </div>
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
