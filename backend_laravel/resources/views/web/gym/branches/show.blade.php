@extends('layouts.panel')

@php
    use App\Support\Scheduling\OperatingHours;

    $branchSchedule = OperatingHours::normalize($branch->timings ?? [], $branch->weekly_off ?? []);
@endphp

@section('content')
    <div class="space-y-5">
        <div class="rounded-[30px] border border-slate-200/80 bg-white shadow-[0_30px_90px_-55px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950">
            <div class="grid gap-6 border-b border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 px-5 py-6 text-white dark:border-slate-800 lg:grid-cols-[minmax(0,1.1fr)_360px] lg:px-6">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        Branch Detail
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $branch->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        {{ $branch->city ?: 'City pending' }}{{ $branch->state ? ' • '.$branch->state : '' }} • {{ $branch->timezone ?: 'Timezone pending' }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-status-badge :label="$branch->is_active ? 'Active' : 'Inactive'" :tone="$branch->is_active ? 'success' : 'warning'" />
                        <x-status-badge :label="($branch->cityRecord?->name ?? 'No city link')" tone="info" />
                        <x-status-badge :label="count($branch->photo_urls ?? []).' photos'" tone="neutral" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Live floor</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $branch->today_check_ins_count }}</div>
                        <p class="mt-1 text-sm text-slate-300">Today check-ins</p>
                    </div>
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Branch load</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $branch->member_profiles_count }} / {{ $branch->trainer_profiles_count }}</div>
                        <p class="mt-1 text-sm text-slate-300">Members / trainers</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 px-5 py-4 lg:px-6">
                <a href="{{ route('web.gym.branches.index', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary">Back to Branches</a>
                @if ($canManageBranches)
                    <a href="{{ route('web.gym.branches.edit', ['branch' => $branch->id, 'gym' => $gym->id]) }}" class="panel-btn-secondary">Edit Branch</a>
                    <form method="POST" action="{{ route('web.gym.branches.toggle-status', ['branch' => $branch->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="{{ $branch->is_active ? 'Deactivate branch?' : 'Activate branch?' }}" data-confirm-message="{{ $branch->is_active ? 'Members and staff will stop using this branch operationally.' : 'This branch will become active again.' }}" data-confirm-button="{{ $branch->is_active ? 'Deactivate' : 'Activate' }}">
                        @csrf
                        <button type="submit" class="{{ $branch->is_active ? 'panel-btn-danger' : 'panel-btn-primary' }}">{{ $branch->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Members" :value="$branch->member_profiles_count" hint="Assigned to this branch" tone="sky" />
            <x-stat-card label="Trainers" :value="$branch->trainer_profiles_count" hint="Branch coaching roster" tone="violet" />
            <x-stat-card label="Plans" :value="$branch->membership_plans_count" hint="Branch-scoped plan catalog" tone="amber" />
            <x-stat-card label="Trials" :value="$branch->trial_requests_count" hint="Leads in this branch" tone="info" />
            <x-stat-card label="Today Check-ins" :value="$branch->today_check_ins_count" hint="Current floor activity" tone="emerald" />
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.08fr_0.92fr]">
            <div class="space-y-5">
                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Location Profile</h3>
                            <p class="panel-section-copy">The address, geo markers, and operational identity for this branch.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Address</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $branch->address ?: $branch->address_line ?: 'Not configured' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Pincode / Country</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $branch->pincode ?: 'No pincode' }} • {{ $branch->country ?: 'Country pending' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Coordinates</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $branch->latitude ?: 'N/A' }}{{ $branch->longitude ? ', '.$branch->longitude : '' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Facility Count</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $branch->facilities->count() }} mapped facilities</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Operating Schedule</h3>
                    <p class="panel-section-copy">Split operating windows and weekly closures as configured for the branch.</p>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        @foreach (OperatingHours::DAYS as $day)
                            <div class="panel-card-muted p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ OperatingHours::dayLabel($day) }}</p>
                                    <x-status-badge :label="in_array($day, $branch->weekly_off ?? [], true) ? 'Off' : 'Open'" :tone="in_array($day, $branch->weekly_off ?? [], true) ? 'warning' : 'success'" />
                                </div>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ OperatingHours::formatDaySlots($branchSchedule[$day] ?? []) }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Branch Plans</h3>
                    <p class="panel-section-copy">Recent membership plans scoped to this branch.</p>
                    <div class="mt-5 space-y-3">
                        @forelse ($recentPlans as $plan)
                            <div class="panel-card-muted flex items-center justify-between gap-4 px-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $plan->name }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $plan->cadence_label }} • {{ $plan->price_label }}</p>
                                </div>
                                <x-status-badge :label="ucfirst($plan->status)" :tone="$plan->status === 'active' ? 'success' : 'warning'" />
                            </div>
                        @empty
                            <x-empty-state title="No branch-specific plans yet" message="Membership plans scoped to this branch will appear here." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>

            <div class="space-y-5">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Facilities</h3>
                    <p class="panel-section-copy">The branch service envelope visible to members and staff.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @forelse ($branch->facilities as $facility)
                            <x-status-badge :label="$facility->name" tone="info" />
                        @empty
                            <span class="text-sm text-slate-500 dark:text-slate-400">No facilities assigned yet.</span>
                        @endforelse
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Media & Discovery</h3>
                    <p class="panel-section-copy">Photo readiness and listing support metadata.</p>
                    <div class="mt-5 grid gap-3">
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Photo URLs</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ count($branch->photo_urls ?? []) }} linked images</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Linked city record</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $branch->cityRecord?->name ?? 'No linked city record' }}</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Map placement</p>
                            <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ filled($branch->latitude) && filled($branch->longitude) ? 'Coordinates configured' : 'Coordinates missing' }}</p>
                        </div>
                    </div>
                </x-premium-card>

                @if ($canManageBranches)
                    <x-premium-card class="p-6">
                        <h3 class="panel-section-title">Lifecycle Controls</h3>
                        <p class="panel-section-copy">Only delete when the branch is operationally empty. Otherwise deactivate it.</p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('web.gym.branches.toggle-status', ['branch' => $branch->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="{{ $branch->is_active ? 'Deactivate branch?' : 'Activate branch?' }}" data-confirm-message="{{ $branch->is_active ? 'Members and staff will stop using this branch operationally.' : 'This branch will become active again.' }}" data-confirm-button="{{ $branch->is_active ? 'Deactivate' : 'Activate' }}">
                                @csrf
                                <button type="submit" class="{{ $branch->is_active ? 'panel-btn-danger' : 'panel-btn-primary' }}">{{ $branch->is_active ? 'Deactivate Branch' : 'Activate Branch' }}</button>
                            </form>
                            <form method="POST" action="{{ route('web.gym.branches.destroy', ['branch' => $branch->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="Delete branch?" data-confirm-message="Delete this branch only if it has no active members. Otherwise deactivate it." data-confirm-button="Delete Branch">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="panel-btn-danger">Delete Branch</button>
                            </form>
                        </div>
                    </x-premium-card>
                @endif
            </div>
        </div>
    </div>
@endsection
