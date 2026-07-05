@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Lead exports</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">Trial Requests</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Export branch-safe lead lists for follow-up while keeping the existing trainer assignment and trial status workflow intact.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-action-button as="a" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export Trial Leads CSV</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->only(['gym', 'branch']), ['report' => 'lead_conversion'])) }}">Lead Conversion Report</x-action-button>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Leads" :value="$trialRequests->total()" hint="Visible trial requests" tone="sky" />
            <x-stat-card label="Pending" :value="$trialRequests->getCollection()->where('status', 'pending')->count()" hint="Need first follow-up" tone="warning" />
            <x-stat-card label="Accepted" :value="$trialRequests->getCollection()->where('status', 'accepted')->count()" hint="Approved trial visits" tone="success" />
            <x-stat-card label="Converted" :value="$trialRequests->getCollection()->where('status', 'converted')->count()" hint="Turned into members" tone="emerald" />
        </div>

        <x-premium-card class="p-6">
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <input name="search" value="{{ request('search') }}" class="panel-input" placeholder="Search lead">
                <select name="request_type" class="panel-select">
                    <option value="">All lead types</option>
                    <option value="trial" @selected(request('request_type') === 'trial')>Trial requests</option>
                    <option value="contact" @selected(request('request_type') === 'contact')>Direct enquiries</option>
                </select>
                <select name="status" class="panel-select">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'accepted', 'rejected', 'completed', 'converted'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="assigned_trainer_id" class="panel-select">
                    <option value="">All trainers</option>
                    @foreach ($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected((int) request('assigned_trainer_id') === $trainer->id)>{{ $trainer->name }}</option>
                    @endforeach
                </select>
                <input name="start_date" type="date" value="{{ request('start_date') }}" class="panel-input">
                <input name="end_date" type="date" value="{{ request('end_date') }}" class="panel-input">
                <div class="flex items-end gap-3">
                    <x-action-button type="submit" variant="secondary">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper>
            <table class="panel-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Preferred</th>
                        <th>Trainer</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trialRequests as $trialRequest)
                        <tr>
                            <td>
                                <div class="font-semibold text-white">{{ $trialRequest->name }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $trialRequest->phone ?: 'No phone' }}{{ $trialRequest->email ? ' · '.$trialRequest->email : '' }}</div>
                            </td>
                            <td><x-status-badge :label="$trialRequest->request_type === 'contact' ? 'Enquiry' : 'Trial'" /></td>
                            <td>{{ $trialRequest->branch?->name ?? 'Unassigned' }}</td>
                            <td>{{ optional($trialRequest->preferred_date)->format('d M Y') }}{{ $trialRequest->preferred_time ? ' · '.substr((string) $trialRequest->preferred_time, 0, 5) : '' }}</td>
                            <td>{{ $trialRequest->assignedTrainer?->name ?? 'Unassigned' }}</td>
                            <td><x-status-badge :label="ucfirst($trialRequest->status)" /></td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.show', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">View</x-action-button>
                                    @if ($trialRequest->status === 'pending')
                                        <form method="POST" action="{{ route('web.gym.trial-requests.accept', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">
                                            @csrf
                                            <x-action-button type="submit">Accept</x-action-button>
                                        </form>
                                        <form method="POST" action="{{ route('web.gym.trial-requests.reject', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">
                                            @csrf
                                            <x-action-button type="submit" variant="danger">Reject</x-action-button>
                                        </form>
                                    @endif
                                    @if (in_array($trialRequest->status, ['accepted', 'completed'], true))
                                        <form method="POST" action="{{ route('web.gym.trial-requests.convert', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">
                                            @csrf
                                            <x-action-button type="submit">Convert</x-action-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                                <td colspan="7">
                                    <x-empty-state title="No trial requests yet" message="Requests from nearby gym discovery will appear here." />
                                </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-6">{{ $trialRequests->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
