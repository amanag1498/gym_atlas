@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600 dark:border-brand-400/30 dark:bg-brand-500/10 dark:text-brand-200">Gym Admin</span>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Audit Logs</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-300">Custom fees, collections, member edits, trainer assignments, renewals, attendance changes, staff permission updates, and public listing changes.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.audit-logs.export', request()->query()) }}">Export Filtered CSV</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.settings.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}">Back to Settings</x-action-button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Matching Events" :value="$summary['total']" hint="Current filtered result" tone="sky" />
            <x-stat-card label="User Actions" :value="$summary['people']" hint="Events with an identified actor" tone="success" />
            <x-stat-card label="System Events" :value="$summary['system']" hint="Automated lifecycle and background actions" tone="warning" />
            <x-stat-card label="Branches" :value="$summary['branches']" hint="Branches represented in this result" tone="violet" />
        </section>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(140px,1fr)_minmax(140px,1fr)]">
                @if (request()->filled('gym'))
                    <input type="hidden" name="gym" value="{{ request('gym') }}">
                @endif
                @if (request()->filled('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif

                <div>
                    <label for="actor" class="panel-label">Actor</label>
                    <input id="actor" name="actor" value="{{ $filters['actor'] }}" class="panel-input" placeholder="Name or email">
                </div>
                <div>
                    <label for="action" class="panel-label">Action</label>
                    <input id="action" name="action" value="{{ $filters['action'] }}" class="panel-input" placeholder="payment, custom fee...">
                </div>
                <div>
                    <label for="branch_id" class="panel-label">Branch</label>
                    <select id="branch_id" name="branch_id" class="panel-select">
                        <option value="">All accessible branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
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
                    <label for="start_date" class="panel-label">Start</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] }}" class="panel-input">
                </div>
                <div>
                    <label for="end_date" class="panel-label">End</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] }}" class="panel-input">
                </div>
                <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.audit-logs.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="panel-section-title">Gym Audit Trail</h3>
                    <p class="panel-section-copy">Sanitized before and after payloads with branch-level scoping where applicable.</p>
                </div>
                <x-status-badge :label="$auditLogs->total().' records'" tone="neutral" />
            </div>

            @if ($auditLogs->count())
                <div class="overflow-x-auto">
                        <table class="panel-table min-w-[1380px]">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Actor</th>
                                <th>Subject</th>
                                <th>Branch</th>
                                <th>Changes</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditLogs as $log)
                                @php
                                    $oldValues = $sanitizer->sanitizeValue($log->old_values ?? []);
                                    $newValues = $sanitizer->sanitizeValue($log->new_values ?? []);
                                    $context = $sanitizer->sanitizeValue($log->context ?? []);
                                    $hasChanges = ! empty($oldValues) || ! empty($newValues);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ str($log->action ?: $log->event)->replace(['.', '_'], ' ')->title() }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $log->event }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $log->actor?->name ?? 'System' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $log->actor?->email ?? ($log->actor_role ?: 'Automated process') }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $log->subject_type ? class_basename($log->subject_type) : 'N/A' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">#{{ $log->subject_id ?: '—' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $log->branch?->name ?? 'Gym-wide' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $gym->name }}</div>
                                    </td>
                                    <td class="min-w-[320px]">
                                        @if ($hasChanges)
                                            <div class="space-y-3">
                                                <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                                                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">Old Values</summary>
                                                    <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </details>
                                                <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                                                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">New Values</summary>
                                                    <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </details>
                                            </div>
                                        @else
                                            <span class="text-sm text-slate-500 dark:text-slate-400">No value diff stored</span>
                                        @endif
                                        @if (! empty($context))
                                            <details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:text-violet-300">Event Context</summary>
                                                <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ optional($log->occurred_at ?? $log->created_at)->format('d M Y') ?: 'N/A' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ optional($log->occurred_at ?? $log->created_at)->format('H:i') ?: 'N/A' }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $auditLogs->links() }}
                </div>
            @else
                <div class="p-5">
                    <x-empty-state
                        title="No audit logs found"
                        message="Gym-side changes will appear here once billing, members, trainers, staff, attendance, or listing settings are updated."
                    />
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
