@props([
    'items' => [],
    'emptyTitle' => 'No audit activity yet',
    'emptyMessage' => 'Trust history will appear here as actions are recorded.',
])

<div class="panel-timeline">
    @forelse ($items as $item)
        @php
            $icon = match($item['icon'] ?? 'activity') {
                'member_created' => '👤',
                'membership_assigned' => '🪪',
                'membership_renewed' => '↺',
                'membership_status' => '❄',
                'trainer_assigned' => '🧑‍🏫',
                'custom_fee' => '₹',
                'payment' => '💳',
                'attendance' => '✓',
                'workout_plan' => '🏋',
                'progress_photo' => '📸',
                default => '•',
            };
        @endphp
        <div class="panel-timeline-item">
            <div class="panel-card-muted p-4 text-sm text-slate-600 dark:text-slate-300">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-base text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white">
                            <span>{{ $icon }}</span>
                        </div>
                        <div>
                            <div class="font-medium text-slate-950 dark:text-white">{{ $item['title'] ?? 'Audit event' }}</div>
                            @if (!empty($item['amount_label']) && !empty($item['amount_value']))
                                <div class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                                    {{ $item['amount_label'] }}: {{ $item['amount_value'] }}
                                </div>
                            @endif
                            @if (!empty($item['change_summary']))
                                <div class="mt-1 text-sky-700 dark:text-sky-300">{{ $item['change_summary'] }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if (!empty($item['changed_by_role']))
                            <x-status-badge :label="$item['changed_by_role']" tone="info" />
                        @endif
                        @if (!empty($item['date']))
                            <x-status-badge :label="$item['date']" tone="info" />
                        @endif
                    </div>
                </div>

                <div class="mt-3">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400">Changed by:</span>
                        <span class="ml-2 text-slate-950 dark:text-white">{{ $item['changed_by'] ?? 'System' }}</span>
                    </div>
                </div>

                @if (!empty($item['reason']))
                    <div class="mt-3">
                        <span class="text-slate-500 dark:text-slate-400">Reason:</span>
                        <span class="ml-2 text-slate-700 dark:text-slate-300">{{ $item['reason'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <x-web.empty-state :title="$emptyTitle" :message="$emptyMessage" />
    @endforelse
</div>
