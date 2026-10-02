@extends('layouts.panel')

@php
    $panelFullWidth = true;
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Trial requests</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">Assign enquiries, confirm visits, and convert successful trials.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-action-button as="a" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->only(['gym', 'branch']), ['report' => 'lead_conversion'])) }}">Conversion report</x-action-button>
            </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="All leads" :value="$summary['total']" hint="Current gym scope" tone="sky" />
            <x-stat-card label="Unassigned" :value="$summary['unassigned']" hint="Need an owner" tone="warning" />
            <x-stat-card label="Pending" :value="$summary['pending']" hint="Awaiting contact" tone="violet" />
            <x-stat-card label="Accepted" :value="$summary['accepted']" hint="Visit confirmed" tone="success" />
            <x-stat-card label="Completed" :value="$summary['completed']" hint="Ready to convert" tone="emerald" />
            <x-stat-card label="Converted" :value="$summary['converted']" hint="Joined as members" tone="sky" />
        </div>

        <x-premium-card class="min-w-0 p-5 sm:p-6">
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="panel-section-title">Find and route leads</h3>
                    <p class="panel-section-copy">Use “Unassigned only” as the daily assignment queue.</p>
                </div>
                @unless ($canManage)
                    <x-status-badge label="View only" tone="warning" />
                @endunless
            </div>
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                @foreach (request()->only(['gym', 'branch']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div class="sm:col-span-2"><label for="trial-search" class="panel-label">Search leads</label><input id="trial-search" name="search" value="{{ request('search') }}" class="panel-input mt-1" placeholder="Name, phone or email"></div>
                <div><label for="trial-type" class="panel-label">Lead type</label><select id="trial-type" name="request_type" class="panel-select mt-1">
                    <option value="">All lead types</option>
                    <option value="trial" @selected(request('request_type') === 'trial')>Trial requests</option>
                    <option value="contact" @selected(request('request_type') === 'contact')>Direct enquiries</option>
                </select></div>
                <div><label for="trial-status" class="panel-label">Status</label><select id="trial-status" name="status" class="panel-select mt-1">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'accepted', 'rejected', 'completed', 'converted'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select></div>
                <div><label for="trial-assignment" class="panel-label">Assignment</label><select id="trial-assignment" name="assignment" class="panel-select mt-1">
                    <option value="">Any assignment</option>
                    <option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned only</option>
                    <option value="assigned" @selected(request('assignment') === 'assigned')>Assigned only</option>
                </select></div>
                <div><label for="trial-trainer" class="panel-label">Trainer</label><select id="trial-trainer" name="assigned_trainer_id" class="panel-select mt-1">
                    <option value="">Any trainer</option>
                    @foreach ($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected((int) request('assigned_trainer_id') === $trainer->id)>{{ $trainer->name }}</option>
                    @endforeach
                </select></div>
                <div><label for="trial-start-date" class="panel-label">From</label><input id="trial-start-date" name="start_date" type="date" value="{{ request('start_date') }}" class="panel-input mt-1"></div>
                <div><label for="trial-end-date" class="panel-label">To</label><input id="trial-end-date" name="end_date" type="date" value="{{ request('end_date') }}" class="panel-input mt-1"></div>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-3 2xl:col-span-6">
                    <x-action-button type="submit">Apply</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div><h3 class="panel-section-title">Leads</h3><p class="panel-section-copy">{{ $trialRequests->total() }} matching {{ $trialRequests->total() === 1 ? 'lead' : 'leads' }}</p></div>
                <span class="text-xs text-slate-500 dark:text-slate-400 lg:hidden">Swipe to see all columns</span>
            </div>
            <div class="overflow-x-auto">
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
                                        <a class="mt-2 block text-xs font-semibold text-indigo-700 underline underline-offset-2 lg:hidden dark:text-indigo-300" href="{{ route('web.gym.trial-requests.show', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">Open lead</a>
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
                                    @elseif ($canManage && $trialRequest->status === 'completed' && $trialRequest->canConvert())
                                        <form method="POST" action="{{ route('web.gym.trial-requests.convert', array_merge(request()->only(['gym', 'branch']), ['trial' => $trialRequest->id])) }}">@csrf<x-action-button type="submit">Convert</x-action-button></form>
                                    @elseif ($canManage && $trialRequest->status === 'completed' && $trialRequest->linkedMemberHasGymProfile())
                                        <x-status-badge label="Already a member" tone="warning" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="No trial leads match" message="New discovery enquiries appear here. Clear filters if you expected an existing lead." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $trialRequests->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
