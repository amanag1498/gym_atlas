@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.announcements.create') }}">Send Announcement</x-action-button>
    @endsection

    @php($readRate = $recipientCount > 0 ? round(($readCount / $recipientCount) * 100) : 0)

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Communication</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Announcement Center</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Run targeted platform communications, inspect audience scope, and audit delivery engagement from one quiet, operator-first surface.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Recipients</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $recipientCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">notification targets created</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Read Rate</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $readRate }}%</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $readCount }} recipients have opened their notice</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Announcements" :value="$announcements->total()" hint="Broadcast records" tone="sky" />
            <x-stat-card label="Platform Wide" :value="$platformWideCount" hint="All-user broadcasts" tone="emerald" />
            <x-stat-card label="Targeted" :value="$targetedCount" hint="Gym, branch, and selected groups" tone="violet" />
            <x-stat-card label="Reads" :value="$readCount" hint="Opened notifications" tone="amber" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search title or message copy" />
                <x-form-select name="audience_type" label="Audience" :selected="request('audience_type')" :options="$audienceOptions" />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.announcements.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Announcement Log</h3>
                    <p class="panel-section-copy">Inspect message intent, delivery scope, recipient volume, and engagement health before sending the next communication.</p>
                </div>
                <x-status-badge :label="$announcements->total().' total'" tone="neutral" />
            </div>

            @if ($announcements->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1280px]">
                        <thead>
                            <tr>
                                <th>Message</th>
                                <th>Audience</th>
                                <th>Scope</th>
                                <th>Engagement</th>
                                <th>Delivery</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($announcements as $announcement)
                                @php($rowReadRate = ($announcement->recipients_count ?? 0) > 0 ? round((($announcement->read_recipients_count ?? 0) / $announcement->recipients_count) * 100) : 0)
                                <tr>
                                    <td>
                                        <a href="{{ route('web.admin.announcements.show', $announcement) }}" class="font-semibold text-slate-950 transition hover:text-brand-600 dark:text-white dark:hover:text-brand-300">
                                            {{ $announcement->title }}
                                        </a>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($announcement->message, 130) }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="str($announcement->audience_type)->replace('_', ' ')->title()" tone="info" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $announcement->gym?->name ?: 'All gyms' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->branch?->name ?: 'No branch restriction' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $announcement->read_recipients_count ?? 0 }} / {{ $announcement->recipients_count ?? 0 }} read</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $rowReadRate }}% open rate</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ optional($announcement->send_at)->format('d M Y, h:i A') ?: 'Immediate' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">by {{ $announcement->creator?->name ?? 'System' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.announcements.show', $announcement) }}" variant="secondary">View</x-action-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No announcements yet" message="Send the first platform announcement to start building communication history and recipient analytics." action-label="Send Announcement" :action-href="route('web.admin.announcements.create')" />
                </div>
            @endif

            @if ($announcements->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $announcements->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
