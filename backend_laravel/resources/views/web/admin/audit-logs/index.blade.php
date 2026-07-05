@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Audit Logs</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Readable platform activity for gyms, listings, billing, plans, catalog records, and account operations.</p>
            </div>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.settings.index') }}">Back to Settings</x-action-button>
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(140px,1fr)_minmax(140px,1fr)]">
                <div>
                    <label for="actor" class="panel-label">Actor</label>
                    <input id="actor" name="actor" value="{{ $filters['actor'] }}" class="panel-input" placeholder="Name or email">
                </div>
                <div>
                    <label for="action" class="panel-label">Action</label>
                    <input id="action" name="action" value="{{ $filters['action'] }}" class="panel-input" placeholder="create, update, invoice">
                </div>
                <div>
                    <label for="subject_type" class="panel-label">Subject Type</label>
                    <select id="subject_type" name="subject_type" class="panel-select">
                        <option value="">All subjects</option>
                        @foreach ($subjectTypeOptions as $subjectType)
                            <option value="{{ $subjectType }}" @selected($filters['subject_type'] === $subjectType)>{{ class_basename($subjectType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="gym" class="panel-label">Gym</label>
                    <select id="gym" name="gym" class="panel-select">
                        <option value="">All gyms</option>
                        @foreach ($gyms as $gym)
                            <option value="{{ $gym->id }}" @selected((string) $filters['gym'] === (string) $gym->id)>{{ $gym->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="start_date" class="panel-label">Start</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] }}" class="panel-input">
                </div>
                <div>
                    <label for="end_date" class="panel-label">End</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] }}" class="panel-input">
                </div>
                <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.audit-logs.index') }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Visible</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $auditSummary['visible'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Entries on this page</p>
            </x-premium-card>
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Created</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $auditSummary['created'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">New records and assignments</p>
            </x-premium-card>
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Updated</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $auditSummary['updated'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Configuration and status changes</p>
            </x-premium-card>
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Deleted</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $auditSummary['deleted'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Removed or archived entities</p>
            </x-premium-card>
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">System</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $auditSummary['system'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No actor attached</p>
            </x-premium-card>
        </div>

        @if ($auditLogs->count())
            <div class="space-y-4">
                @foreach ($auditItems as $item)
                    @php
                        $toneClasses = match($item['tone']) {
                            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
                            'danger' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300',
                            'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300',
                            default => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300',
                        };
                    @endphp
                    <x-premium-card class="p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="flex min-w-0 gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $toneClasses }}">
                                    <i class="ti {{ $item['icon'] }} text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $item['title'] }}</h3>
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $toneClasses }}">{{ $item['action_label'] }}</span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300">{{ $item['subject_label'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $item['description'] }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 dark:border-white/10 dark:bg-white/[0.03]">
                                            Subject: <span class="ml-1 font-medium text-slate-700 dark:text-slate-200">{{ $item['subject_name'] }}</span>
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 dark:border-white/10 dark:bg-white/[0.03]">
                                            Actor: <span class="ml-1 font-medium text-slate-700 dark:text-slate-200">{{ $item['actor_name'] }}</span>
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 dark:border-white/10 dark:bg-white/[0.03]">
                                            Role: <span class="ml-1 font-medium text-slate-700 dark:text-slate-200">{{ $item['actor_role'] }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300">{{ $item['occurred_at'] }}</span>
                                @if ($item['relative_time'])
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300">{{ $item['relative_time'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">What changed</p>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ count($item['changes']) }} signal{{ count($item['changes']) === 1 ? '' : 's' }}</span>
                                    </div>

                                    @if ($item['changes'] !== [])
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            @foreach ($item['changes'] as $change)
                                                <div class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950/40">
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ $change['label'] }}</p>
                                                    <div class="mt-2 space-y-1 text-sm">
                                                        <div class="text-slate-500 dark:text-slate-400">Before: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $change['old'] ?? '—' }}</span></div>
                                                        <div class="text-slate-500 dark:text-slate-400">After: <span class="font-medium text-slate-950 dark:text-white">{{ $change['new'] ?? '—' }}</span></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">This event did not store a direct before/after diff. Context and payload details are still available below.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">Context</p>
                                    <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-slate-500 dark:text-slate-400">Gym</span>
                                            <span class="text-right font-medium text-slate-950 dark:text-white">{{ $item['gym_name'] }}</span>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-slate-500 dark:text-slate-400">Branch</span>
                                            <span class="text-right font-medium text-slate-950 dark:text-white">{{ $item['branch_name'] ?? 'No branch' }}</span>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-slate-500 dark:text-slate-400">Actor email</span>
                                            <span class="text-right font-medium text-slate-950 dark:text-white">{{ $item['actor_email'] ?? 'N/A' }}</span>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="text-slate-500 dark:text-slate-400">IP address</span>
                                            <span class="text-right font-medium text-slate-950 dark:text-white">{{ $item['ip_address'] }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-xs text-slate-500 dark:border-white/10 dark:bg-slate-950/40 dark:text-slate-400">
                                        {{ \Illuminate\Support\Str::limit($item['user_agent'], 180) }}
                                    </div>
                                </div>

                                <details class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                                    <summary class="cursor-pointer text-sm font-semibold text-slate-950 dark:text-white">Raw payload</summary>
                                    <div class="mt-4 grid gap-4">
                                        <div>
                                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Context</p>
                                            <pre class="overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($item['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Before</p>
                                            <pre class="overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($item['old_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">After</p>
                                            <pre class="overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($item['new_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </x-premium-card>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-1 py-4 dark:border-slate-800">
                {{ $auditLogs->links() }}
            </div>
        @else
            <x-premium-card class="p-5">
                <x-empty-state
                    title="No audit logs found"
                    message="Platform actions will appear here once settings, gyms, listings, facilities, billing, or users are changed."
                />
            </x-premium-card>
        @endif
    </div>
@endsection
