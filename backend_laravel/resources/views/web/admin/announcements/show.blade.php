@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.announcements.index') }}" variant="secondary">Back to Announcements</x-action-button>
    @endsection

    @php($readRate = ($announcement->recipients_count ?? 0) > 0 ? round((($announcement->read_recipients_count ?? 0) / $announcement->recipients_count) * 100) : 0)

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Announcement Audit</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $announcement->title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $announcement->message }}</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Audience</div>
                        <div class="mt-2">
                            <x-status-badge :label="str($announcement->audience_type)->replace('_', ' ')->title()" tone="info" />
                        </div>
                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->gym?->name ?: 'All gyms' }}</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Read Rate</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $readRate }}%</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->read_recipients_count ?? 0 }} of {{ $announcement->recipients_count ?? 0 }} recipients</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Recipients" :value="$announcement->recipients_count ?? 0" hint="Delivery records created" tone="sky" />
            <x-stat-card label="Read" :value="$announcement->read_recipients_count ?? 0" hint="Notification opens logged" tone="emerald" />
            <x-stat-card label="Unread" :value="max(($announcement->recipients_count ?? 0) - ($announcement->read_recipients_count ?? 0), 0)" hint="Pending engagement" tone="amber" />
            <x-stat-card label="Status" :value="str($announcement->status)->title()" hint="Stored delivery state" tone="violet" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_420px]">
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Recipients</h3>
                <p class="panel-section-copy">Recent delivery state across targeted accounts.</p>

                @if ($announcement->recipients->count() > 0)
                    <div class="mt-4 overflow-x-auto">
                        <table class="panel-table min-w-[860px]">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Channel</th>
                                    <th>Scope</th>
                                    <th>Read State</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($announcement->recipients->sortByDesc('id') as $recipient)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $recipient->user?->name ?: 'Deleted user' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $recipient->user?->email ?: 'No email on record' }}</div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>Notification #{{ $recipient->notification_id ?: '--' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Recipient row #{{ $recipient->id }}</div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $announcement->gym?->name ?: 'All gyms' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->branch?->name ?: 'No branch restriction' }}</div>
                                        </td>
                                        <td>
                                            @if ($recipient->read_at)
                                                <div class="space-y-1">
                                                    <x-status-badge label="Read" tone="success" />
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $recipient->read_at->format('d M Y, h:i A') }}</div>
                                                </div>
                                            @else
                                                <div class="space-y-1">
                                                    <x-status-badge label="Unread" tone="warning" />
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">No open event yet</div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="mt-4">
                        <x-empty-state title="No recipients found" message="This announcement does not have delivery rows yet." />
                    </div>
                @endif
            </x-premium-card>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Delivery Metadata</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="panel-card-muted px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Created By</div>
                            <div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $announcement->creator?->name ?: 'System' }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->creator?->email ?: 'No email recorded' }}</div>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Send Time</div>
                            <div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ optional($announcement->send_at)->format('d M Y, h:i A') ?: 'Immediate' }}</div>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Branch Scope</div>
                            <div class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $announcement->branch?->name ?: 'No branch restriction' }}</div>
                        </div>
                    </div>
                </x-premium-card>

                @if (!empty($announcement->metadata))
                    <x-premium-card class="p-5">
                        <h3 class="panel-section-title">Metadata Payload</h3>
                        <pre class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-950/95 p-4 text-xs leading-6 text-slate-100 dark:border-slate-800">{{ json_encode($announcement->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </x-premium-card>
                @endif
            </div>
        </div>
    </div>
@endsection
