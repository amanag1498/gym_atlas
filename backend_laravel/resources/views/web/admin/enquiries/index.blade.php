@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.dashboard') }}" variant="secondary">Dashboard</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Frontend Lead Inbox</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Trainer & Gym Enquiries</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Every public contact submission from the website is now visible here, including gym onboarding and trainer interest enquiries.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">New</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $summary['new'] }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">awaiting first response</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Resolved on Page</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $summary['resolved_visible'] }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">visible records already closed</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="All Enquiries" :value="$summary['total']" hint="Stored website submissions" tone="sky" />
            <x-stat-card label="New" :value="$summary['new']" hint="Still untriaged" tone="amber" />
            <x-stat-card label="Gym" :value="$summary['gym']" hint="From for-gyms and contact flow" tone="emerald" />
            <x-stat-card label="Trainer" :value="$summary['trainer']" hint="Trainer interest submissions" tone="violet" />
            <x-stat-card label="Visible" :value="$summary['visible']" hint="Loaded on this page" tone="rose" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search name, email, phone, or message" />
                <x-form-select name="type" label="Enquiry Type" :selected="request('type')">
                    <option value="">All types</option>
                    @foreach ($inquiryTypes as $inquiryType)
                        <option value="{{ $inquiryType }}" @selected(request('type') === $inquiryType)>{{ str($inquiryType)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </x-form-select>
                <x-form-select name="status" label="Status" :selected="request('status')">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </x-form-select>
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.enquiries.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Enquiry Queue</h3>
                    <p class="panel-section-copy">Use this queue to triage public gym and trainer demand without leaving the admin portal.</p>
                </div>
                <x-status-badge :label="$enquiries->total().' total'" tone="neutral" />
            </div>

            @if ($enquiries->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1380px]">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enquiries as $enquiry)
                                @php
                                    $typeTone = match ($enquiry->inquiry_type) {
                                        'gym' => 'success',
                                        'trainer' => 'info',
                                        'support' => 'warning',
                                        default => 'neutral',
                                    };
                                    $statusTone = match ($enquiry->status) {
                                        'resolved' => 'success',
                                        'in_progress' => 'info',
                                        'spam' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $enquiry->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $enquiry->email }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $enquiry->phone ?: 'No phone shared' }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="str($enquiry->inquiry_type)->replace('_', ' ')->title()" :tone="$typeTone" />
                                    </td>
                                    <td>
                                        <div class="max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ $enquiry->message }}
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ optional($enquiry->created_at)->format('d M Y') ?: '--' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($enquiry->created_at)->format('h:i A') ?: '--' }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="str($enquiry->status)->replace('_', ' ')->title()" :tone="$statusTone" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end">
                                            <form method="POST" action="{{ route('web.admin.enquiries.update-status', $enquiry) }}" class="flex items-center gap-2">
                                                @csrf
                                                <select name="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm outline-none transition focus:border-brand-300 focus:ring-2 focus:ring-brand-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-brand-500 dark:focus:ring-brand-500/30">
                                                    @foreach ($statusOptions as $statusOption)
                                                        <option value="{{ $statusOption }}" @selected($enquiry->status === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                                                    @endforeach
                                                </select>
                                                <x-action-button type="submit" variant="secondary">Save</x-action-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No enquiries found" message="Public website enquiries will appear here as soon as visitors submit the contact forms." />
                </div>
            @endif

            @if ($enquiries->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $enquiries->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
