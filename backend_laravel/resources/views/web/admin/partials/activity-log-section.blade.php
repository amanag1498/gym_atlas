@php
    $activityStats = collect($activityStats ?? []);
    $activityTimeline = array_slice($activityTimeline ?? [], 0, 6);
    $activityRows = collect($activityRows ?? [])->take(6);
    $activityLatestLabel = $activityLatestLabel ?? null;
    $title = $title ?? 'Activity Intelligence';
    $description = $description ?? 'Recent audit history, scope, and operator-level change tracking.';
    $emptyTitle = $emptyTitle ?? 'No activity recorded yet';
    $emptyMessage = $emptyMessage ?? 'Audit activity will appear here as actions are recorded.';
    $historyUrl = $historyUrl ?? null;

    $topActions = $activityRows->pluck('action')->filter()->map(fn ($action) => strtoupper((string) $action))->countBy()->sortDesc()->take(3);
@endphp

<x-premium-card class="overflow-hidden">
    <div class="border-b border-slate-200/80 px-6 py-5 dark:border-slate-800">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Recent Trust Layer</div>
                <h3 class="mt-2 text-[1.7rem] font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($activityLatestLabel)
                    <x-status-badge :label="'Latest '.$activityLatestLabel" tone="info" />
                @endif
                @if ($historyUrl)
                    <x-action-button as="a" href="{{ $historyUrl }}">View Full Activity</x-action-button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 px-6 py-6 xl:grid-cols-[minmax(0,1.12fr)_minmax(360px,0.88fr)]">
        <div class="space-y-6">
            <div class="admin-detail-grid-compact">
                @foreach ($activityStats as $index => $stat)
                    <div class="panel-card-muted px-4 py-4 {{ $index === 0 ? 'border-sky-200/80 bg-sky-50/70 dark:border-sky-500/20 dark:bg-sky-500/10' : '' }}">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $stat['value'] }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $stat['hint'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="admin-detail-grid-compact">
                <div class="panel-card-muted px-5 py-5">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Recent Highlights</div>
                    <div class="mt-4 space-y-3">
                        @forelse ($activityRows->take(3) as $row)
                            <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $row['title'] }}</div>
                                    <x-status-badge :label="$row['date'] ?? 'Recent'" tone="neutral" />
                                </div>
                                @if ($row['change_summary'])
                                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $row['change_summary'] }}</div>
                                @endif
                                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $row['changed_by'] }} • {{ $row['changed_by_role'] }}</div>
                            </div>
                        @empty
                            <x-empty-state :title="$emptyTitle" :message="$emptyMessage" />
                        @endforelse
                    </div>
                </div>

                <div class="panel-card-muted px-5 py-5">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Action Mix</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($topActions as $action => $count)
                            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                <span>{{ $action }}</span>
                                <strong class="text-slate-950 dark:text-white">{{ $count }}</strong>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500 dark:text-slate-400">No action mix available yet.</div>
                        @endforelse
                    </div>
                    @if ($historyUrl)
                        <div class="mt-5">
                            <x-action-button as="a" variant="secondary" href="{{ $historyUrl }}">Open Complete History</x-action-button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="panel-card-muted px-5 py-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Recent Timeline</div>
                    <div class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">Latest chronological audit events</div>
                </div>
                <x-status-badge :label="count($activityTimeline).' recent'" tone="info" />
            </div>
            <div class="mt-5">
                <x-web.audit-timeline
                    :items="$activityTimeline"
                    :empty-title="$emptyTitle"
                    :empty-message="$emptyMessage"
                />
            </div>
        </div>
    </div>
</x-premium-card>
