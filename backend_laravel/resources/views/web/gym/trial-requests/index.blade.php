@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Lead operations</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">Trial lead pipeline</h3>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Assign every enquiry to a branch trainer, track the visit, and convert successful trials without losing the original lead history.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-action-button as="a" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->only(['gym', 'branch']), ['report' => 'lead_conversion'])) }}">Conversion report</x-action-button>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="All leads" :value="$summary['total']" hint="Current gym scope" tone="sky" />
            <x-stat-card label="Unassigned" :value="$summary['unassigned']" hint="Need an owner" tone="warning" />
            <x-stat-card label="Pending" :value="$summary['pending']" hint="Awaiting contact" tone="violet" />
            <x-stat-card label="Accepted" :value="$summary['accepted']" hint="Visit confirmed" tone="success" />
            <x-stat-card label="Completed" :value="$summary['completed']" hint="Ready to convert" tone="emerald" />
            <x-stat-card label="Converted" :value="$summary['converted']" hint="Joined as members" tone="sky" />
        </div>

        <x-premium-card class="p-6">
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="panel-section-title">Find and route leads</h3>
                    <p class="panel-section-copy">Use “Unassigned only” as the daily assignment queue.</p>
                </div>
                @unless ($canManage)
                    <x-status-badge label="View only" tone="warning" />
                @endunless
            </div>
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                @foreach (request()->only(['gym', 'branch']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <input name="search" value="{{ request('search') }}" class="panel-input xl:col-span-2" placeholder="Name, phone or email">
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
                <select name="assignment" class="panel-select">
                    <option value="">Any assignment</option>
                    <option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned only</option>
                    <option value="assigned" @selected(request('assignment') === 'assigned')>Assigned only</option>
                </select>
                <select name="assigned_trainer_id" class="panel-select">
                    <option value="">Any trainer</option>
                    @foreach ($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected((int) request('assigned_trainer_id') === $trainer->id)>{{ $trainer->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <x-action-button type="submit">Apply</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
                <input name="start_date" type="date" value="{{ request('start_date') }}" class="panel-input">
                <input name="end_date" type="date" value="{{ request('end_date') }}" class="panel-input">
            </form>
        </x-premium-card>

        <x-table-wrapper>
            <table class="panel-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Branch & slot</th>
                        <th>Trainer ownership</th>
                        <th>Status</th>
                        <th class="text-end">Next action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trialRequests as $trialRequest)
                        @php
                            $branchTrainers = $trainers->filter(function ($trainer) use ($trialRequest) {
                                $trainerBranchId = $trainer->managedTrainerProfile?->branch_id;
                                return $trainerBranchId === null || (int) $trainerBranchId === (int) $trialRequest->branch_id;
                            });
                        @endphp
                        <tr>
                            <td class="min-w-[250px]">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-100 font-bold text-sky-700 dark:bg-sky-500/15 dark:text-sky-200">{{ str($trialRequest->name ?: 'L')->substr(0, 1)->upper() }}</div>
                                    <div>
                                        <a class="font-semibold text-slate-950 hover:text-sky-600 dark:text-white" href="{{ route('web.gym.trial-requests.show', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">{{ $trialRequest->name ?: 'Unnamed lead' }}</a>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $trialRequest->phone ?: 'No phone' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $trialRequest->email ?: 'No email' }}</div>
                                        <div class="mt-2"><x-status-badge :label="$trialRequest->request_type === 'contact' ? 'Enquiry' : 'Trial'" /></div>
                                    </div>
                                </div>
                            </td>
                            <td class="min-w-[190px]">
                                <div class="font-semibold text-slate-950 dark:text-white">{{ $trialRequest->branch?->name ?? 'No branch' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($trialRequest->preferred_date)->format('d M Y') ?: 'Date not selected' }}{{ $trialRequest->preferred_time ? ' · '.substr((string) $trialRequest->preferred_time, 0, 5) : '' }}</div>
                                <div class="mt-2 text-xs text-slate-400">Received {{ $trialRequest->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="min-w-[300px]">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('web.gym.trial-requests.assign-trainer', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="assigned_trainer_id" class="panel-select min-w-[190px]" aria-label="Trainer for {{ $trialRequest->name }}">
                                            <option value="">Unassigned</option>
                                            @foreach ($branchTrainers as $trainer)
                                                <option value="{{ $trainer->id }}" @selected((int) $trialRequest->assigned_trainer_id === $trainer->id)>{{ $trainer->name }}{{ $trainer->managedTrainerProfile?->branch_id === null ? ' · all branches' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <x-action-button type="submit" variant="secondary">{{ $trialRequest->assigned_trainer_id ? 'Update' : 'Assign' }}</x-action-button>
                                    </form>
                                    @if ($branchTrainers->isEmpty())
                                        <p class="mt-2 text-xs text-amber-600 dark:text-amber-300">Add an active trainer to this branch before assigning.</p>
                                    @endif
                                @else
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $trialRequest->assignedTrainer?->name ?? 'Unassigned' }}</div>
                                @endif
                            </td>
                            <td><x-status-badge :label="ucfirst($trialRequest->status)" /></td>
                            <td class="min-w-[270px]">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.show', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">Open</x-action-button>
                                    @if ($canManage && $trialRequest->status === 'pending')
                                        <form method="POST" action="{{ route('web.gym.trial-requests.accept', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">@csrf<x-action-button type="submit">Accept</x-action-button></form>
                                        <form method="POST" action="{{ route('web.gym.trial-requests.reject', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">@csrf<x-action-button type="submit" variant="danger">Reject</x-action-button></form>
                                    @elseif ($canManage && $trialRequest->status === 'accepted')
                                        <form method="POST" action="{{ route('web.gym.trial-requests.complete', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">@csrf<x-action-button type="submit">Mark visited</x-action-button></form>
                                    @elseif ($canManage && $trialRequest->status === 'completed')
                                        <form method="POST" action="{{ route('web.gym.trial-requests.convert', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">@csrf<x-action-button type="submit">Convert</x-action-button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="No trial leads match" message="New discovery enquiries appear here. Clear filters if you expected an existing lead." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-6">{{ $trialRequests->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
