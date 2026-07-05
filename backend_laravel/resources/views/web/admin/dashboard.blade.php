@extends('layouts.panel')

@php
    $headlineStats = [
        ['label' => 'Pending approvals', 'value' => $stats['pending_gym_approvals'], 'tone' => 'amber', 'toneClass' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300'],
        ['label' => 'Active gyms', 'value' => $stats['active_gyms'], 'tone' => 'emerald', 'toneClass' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300'],
        ['label' => 'Gym enquiries', 'value' => $stats['gym_enquiries'], 'tone' => 'violet', 'toneClass' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-300'],
        ['label' => 'Trainer enquiries', 'value' => $stats['trainer_enquiries'], 'tone' => 'rose', 'toneClass' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300'],
    ];

    $overviewCards = [
        ['label' => 'Total Gyms', 'value' => $stats['total_gyms'], 'tone' => 'sky', 'hint' => 'Platform directory size'],
        ['label' => 'Total Members', 'value' => $stats['total_members'], 'tone' => 'emerald', 'hint' => 'Registered members'],
        ['label' => 'Total Trainers', 'value' => $stats['total_trainers'], 'tone' => 'violet', 'hint' => 'Trainer accounts'],
        ['label' => 'Total Branches', 'value' => $stats['total_branches'], 'tone' => 'amber', 'hint' => 'Operational locations'],
        ['label' => 'Frontend Enquiries', 'value' => $stats['total_frontend_enquiries'], 'tone' => 'rose', 'hint' => 'All public website submissions'],
        ['label' => 'New Enquiries', 'value' => $stats['new_frontend_enquiries'], 'tone' => 'sky', 'hint' => 'Still waiting for action'],
    ];

    $quickActions = [
        ['label' => 'Add Gym', 'href' => route('web.admin.gyms.create'), 'variant' => 'primary', 'icon' => 'ti ti-plus'],
        ['label' => 'Manage Listings', 'href' => route('web.admin.listings.index'), 'variant' => 'secondary', 'icon' => 'ti ti-search'],
        ['label' => 'Open Reports', 'href' => route('web.admin.reports.index'), 'variant' => 'secondary', 'icon' => 'ti ti-chart-bar'],
        ['label' => 'Frontend Enquiries', 'href' => route('web.admin.enquiries.index'), 'variant' => 'secondary', 'icon' => 'ti ti-mail'],
    ];
@endphp

@section('content')
    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.gyms.create') }}">Add Gym</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.enquiries.index') }}">Frontend Enquiries</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index', ['status' => 'pending']) }}">Review Queue</x-action-button>
        @endsection

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($headlineStats as $stat)
                <div class="panel-card rounded-[1rem] border border-slate-200/80 bg-white/85 p-4 shadow-sm dark:border-slate-800/80 dark:bg-slate-900/88 dark:shadow-black/20">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</div>
                    <div class="mt-2 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ $stat['value'] }}</div>
                    <div class="mt-1.5 inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $stat['toneClass'] }}">
                        Live signal
                    </div>
                </div>
            @endforeach
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($overviewCards as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :tone="$card['tone']" :hint="$card['hint']" />
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(340px,1fr)]">
            <x-table-wrapper class="p-0">
                <div class="flex flex-col gap-4 border-b border-slate-200/80 px-5 py-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
                    <div>
                        <h3 class="panel-section-title">Pending Gym Approvals</h3>
                        <p class="panel-section-copy">A compact review queue for submissions that still need platform action.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index', ['status' => 'pending']) }}">
                        Open Queue
                    </x-action-button>
                </div>

                @if (count($pending_gym_approvals) > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[900px]">
                            <thead>
                                <tr>
                                    <th>Gym</th>
                                    <th>Owner</th>
                                    <th>City</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pending_gym_approvals as $gym)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $gym['name'] }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Approval request</div>
                                        </td>
                                        <td>
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $gym['owner_name'] }}</div>
                                            @if ($gym['owner_email'])
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym['owner_email'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-slate-700 dark:text-slate-300">{{ $gym['city'] }}</td>
                                        <td class="text-sm text-slate-500 dark:text-slate-400">{{ optional($gym['submitted_at'])->format('d M Y') ?? 'N/A' }}</td>
                                        <td><x-status-badge :label="$gym['status']" /></td>
                                        <td>
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $gym['id']) }}">View</x-action-button>
                                                <form method="POST" action="{{ route('web.admin.gyms.approve', $gym['id']) }}">
                                                    @csrf
                                                    <x-action-button type="submit">Approve</x-action-button>
                                                </form>
                                                <form method="POST" action="{{ route('web.admin.gyms.reject', $gym['id']) }}" onsubmit="return confirm('Reject this gym approval?');">
                                                    @csrf
                                                    <x-action-button type="submit" variant="danger">Reject</x-action-button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        <x-empty-state
                            title="No pending gym approvals"
                            message="Everything in the queue has already been reviewed."
                            action-label="Add Gym"
                            action-href="{{ route('web.admin.gyms.create') }}"
                        />
                    </div>
                @endif
            </x-table-wrapper>

            <div class="space-y-4">
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                        <h3 class="panel-section-title">Quick Actions</h3>
                        <p class="panel-section-copy">High-frequency admin actions with minimal friction.</p>
                    </div>
                    <div class="space-y-2 p-5">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['href'] }}" class="group flex items-center justify-between rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-brand-300 hover:bg-brand-50 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 transition group-hover:bg-brand-50 group-hover:text-brand-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-brand-500/10 dark:group-hover:text-brand-300">
                                        <i class="{{ $action['icon'] }} text-lg"></i>
                                    </span>
                                    <span>{{ $action['label'] }}</span>
                                </span>
                                <i class="ti ti-arrow-right text-base text-slate-400 dark:text-slate-500"></i>
                            </a>
                        @endforeach
                    </div>
                </x-premium-card>

                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                        <h3 class="panel-section-title">Frontend Enquiries</h3>
                        <p class="panel-section-copy">Latest gym and trainer enquiries arriving from the public website.</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <div class="rounded-[1.2rem] border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <div class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Recent submissions</div>
                            <div class="mt-3 space-y-4">
                                @forelse ($recent_frontend_enquiries as $enquiry)
                                    <div class="border-l-2 border-brand-300 pl-4 dark:border-brand-400">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-medium text-slate-950 dark:text-slate-100">{{ $enquiry['name'] }}</div>
                                            <x-status-badge :label="str($enquiry['inquiry_type'])->replace('_', ' ')->title()" :tone="$enquiry['inquiry_type'] === 'gym' ? 'success' : ($enquiry['inquiry_type'] === 'trainer' ? 'info' : 'neutral')" />
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $enquiry['email'] }} · {{ \Illuminate\Support\Str::limit($enquiry['message'], 68) }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-slate-500 dark:text-slate-400">No frontend enquiries yet.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-[1.2rem] border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <div class="text-[11px] font-medium uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Platform activity</div>
                            <div class="mt-3 space-y-4">
                                @forelse ($platform_activity['latest_gym_approvals'] as $activity)
                                    <div>
                                        <div class="font-medium text-slate-950 dark:text-slate-100">{{ $activity['title'] }}</div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $activity['description'] }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-slate-500 dark:text-slate-400">No activity yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-premium-card>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <x-table-wrapper class="p-0">
                <div class="flex items-end justify-between gap-3 border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                    <div>
                        <h3 class="panel-section-title">Featured Gyms</h3>
                        <p class="panel-section-copy">Gyms currently pushed into the discovery surface.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.featured-gyms.index') }}">Open List</x-action-button>
                </div>
                <div class="p-5">
                            <div class="space-y-3">
                        @forelse (collect($recently_added_gyms)->where('is_featured', true)->take(4) as $gym)
                            <div class="flex items-center justify-between gap-3 rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                                <div>
                                    <div class="font-medium text-slate-950 dark:text-slate-100">{{ $gym['name'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym['city'] }}</div>
                                </div>
                                <x-status-badge label="Featured" tone="featured" />
                            </div>
                        @empty
                            <x-empty-state title="No featured gyms yet" message="Feature a gym from the listings page to surface it here." />
                        @endforelse
                    </div>
                </div>
            </x-table-wrapper>

            <x-table-wrapper class="p-0">
                <div class="flex items-end justify-between gap-3 border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                    <div>
                        <h3 class="panel-section-title">Promoted Gyms</h3>
                        <p class="panel-section-copy">Gyms with extra reach across the platform.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.promoted-gyms.index') }}">Open List</x-action-button>
                </div>
                <div class="p-5">
                            <div class="space-y-3">
                        @forelse (collect($recently_added_gyms)->where('is_promoted', true)->take(4) as $gym)
                            <div class="flex items-center justify-between gap-3 rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                                <div>
                                    <div class="font-medium text-slate-950 dark:text-slate-100">{{ $gym['name'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym['city'] }}</div>
                                </div>
                                <x-status-badge label="Promoted" tone="promoted" />
                            </div>
                        @empty
                            <x-empty-state title="No promoted gyms yet" message="Promote a gym from the listings page to surface it here." />
                        @endforelse
                    </div>
                </div>
            </x-table-wrapper>
        </div>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Recent Frontend Enquiries</h3>
                    <p class="panel-section-copy">Fast visibility for public website demand without leaving the dashboard.</p>
                </div>
                <x-action-button as="a" href="{{ route('web.admin.enquiries.index') }}" variant="secondary">Open Inbox</x-action-button>
            </div>

            @if (count($recent_frontend_enquiries) > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1120px]">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Submitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent_frontend_enquiries as $enquiry)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $enquiry['name'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $enquiry['email'] }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="str($enquiry['inquiry_type'])->replace('_', ' ')->title()" :tone="$enquiry['inquiry_type'] === 'gym' ? 'success' : ($enquiry['inquiry_type'] === 'trainer' ? 'info' : 'neutral')" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($enquiry['message'], 120) }}</td>
                                    <td class="text-sm text-slate-500 dark:text-slate-400">{{ optional($enquiry['created_at'])->format('d M Y, h:i A') ?: '--' }}</td>
                                    <td>
                                        <x-status-badge :label="str($enquiry['status'])->replace('_', ' ')->title()" :tone="$enquiry['status'] === 'resolved' ? 'success' : ($enquiry['status'] === 'in_progress' ? 'info' : ($enquiry['status'] === 'spam' ? 'danger' : 'warning'))" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No frontend enquiries yet" message="Gym and trainer contact submissions will start appearing here once visitors submit the website forms." />
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
