@php
    $heading = $heading ?? 'Activity History';
    $description = $description ?? 'Complete paginated audit history.';
    $backUrl = $backUrl ?? route('web.admin.users.index');
    $backLabel = $backLabel ?? 'Back';
    $filters = $filters ?? ['action' => '', 'start_date' => '', 'end_date' => ''];
    $actionUrl = $actionUrl ?? url()->current();
@endphp

<div class="space-y-6">
    <section class="panel-hero">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300">Activity Archive</span>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $heading }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
            </div>
            <x-action-button as="a" variant="secondary" href="{{ $backUrl }}">{{ $backLabel }}</x-action-button>
        </div>
    </section>

    <x-premium-card class="p-5">
        <form method="GET" action="{{ $actionUrl }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(180px,1fr)_minmax(180px,1fr)_auto]">
            <div>
                <label for="action" class="panel-label">Action or Event</label>
                <input id="action" name="action" value="{{ $filters['action'] }}" class="panel-input" placeholder="create, update, payment...">
            </div>
            <div>
                <label for="start_date" class="panel-label">Start</label>
                <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] }}" class="panel-input">
            </div>
            <div>
                <label for="end_date" class="panel-label">End</label>
                <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] }}" class="panel-input">
            </div>
            <div class="flex items-end gap-2">
                <x-action-button type="submit">Apply</x-action-button>
                <x-action-button as="a" variant="secondary" href="{{ $actionUrl }}">Reset</x-action-button>
            </div>
        </form>
    </x-premium-card>

    <x-table-wrapper class="overflow-hidden p-0">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
            <div>
                <h3 class="panel-section-title">Paginated Audit Ledger</h3>
                <p class="panel-section-copy">Full history with actor, scope, network metadata, and sanitized before/after payloads.</p>
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
                            <th>Scope</th>
                            <th>Changes</th>
                            <th>Network</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($auditLogs as $log)
                            @php
                                $oldValues = $sanitizer->sanitizeValue($log->old_values ?? []);
                                $newValues = $sanitizer->sanitizeValue($log->new_values ?? []);
                                $hasChanges = ! empty($oldValues) || ! empty($newValues);
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $log->action ?: $log->event }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->event }}</div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $log->actor?->name ?? 'System' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->actor?->email ?? ($log->actor_role ?: 'No actor') }}</div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $log->gym?->name ?? 'Platform scope' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->branch?->name ?? 'No branch' }}</div>
                                    @if ($log->subject_type)
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ class_basename($log->subject_type) }} #{{ $log->subject_id ?: '—' }}</div>
                                    @endif
                                </td>
                                <td class="min-w-[340px]">
                                    @if ($hasChanges)
                                        <div class="space-y-3">
                                            <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">Old Values</summary>
                                                <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                            <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300">New Values</summary>
                                                <pre class="mt-3 overflow-x-auto rounded-2xl bg-slate-950 px-4 py-3 text-xs text-slate-100">{{ json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">No value diff stored</span>
                                    @endif
                                </td>
                                <td class="min-w-[240px]">
                                    <div class="text-sm text-slate-700 dark:text-slate-300"><span class="font-semibold text-slate-950 dark:text-white">IP:</span> {{ $log->ip_address ?: 'N/A' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($log->user_agent ?: 'No user agent', 90) }}</div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ optional($log->created_at ?? $log->occurred_at)->format('d M Y') ?: 'N/A' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($log->created_at ?? $log->occurred_at)->format('H:i') ?: 'N/A' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $auditLogs->links() }}
            </div>
        @else
            <div class="p-5">
                <x-empty-state title="No activity found" message="Audit history will appear here once actions are recorded for this account." />
            </div>
        @endif
    </x-table-wrapper>
</div>
