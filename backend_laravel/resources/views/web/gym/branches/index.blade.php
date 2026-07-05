@extends('layouts.panel')

@php
    use App\Support\Scheduling\OperatingHours;
@endphp

@section('content')
    @php
        $scopeQuery = request()->only(['gym', 'branch']);
        $activeCount = $branches->getCollection()->where('is_active', true)->count();
        $totalMembers = $branches->getCollection()->sum('member_profiles_count');
        $totalCheckIns = $branches->getCollection()->sum('today_check_ins_count');
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Branches</h1>
            </div>
            @if ($canManageBranches)
                <div class="flex items-center gap-2">
                    <a href="{{ route('web.gym.branches.create', ['gym' => $gym->id]) }}" class="panel-btn-primary">Add Branch</a>
                </div>
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Visible Branches" :value="$branches->total()" hint="Current scoped directory" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Operational locations on this page" tone="emerald" />
            <x-stat-card label="Members" :value="$totalMembers" hint="Assigned across visible branches" tone="violet" />
            <x-stat-card label="Today Check-ins" :value="$totalCheckIns" hint="Live branch activity total" tone="amber" />
            <x-stat-card label="Facilities" :value="$facilities->count()" hint="Facility catalog available to map" tone="info" />
        </div>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Filter Bar</h3>
            </div>
            <form method="GET" action="{{ route('web.gym.branches.index') }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1.2fr_0.8fr_auto]">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                @if (request()->filled('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Name, city, pincode" />
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="panel-btn-primary w-full justify-center">Apply</button>
                    <a href="{{ route('web.gym.branches.index', $scopeQuery) }}" class="panel-btn-secondary w-full justify-center">Reset</a>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Branch Directory</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Compact operational matrix instead of large stacked cards.</p>
                    </div>
                </div>
            </div>

            @if ($branches->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1340px]">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Location</th>
                                <th>Today</th>
                                <th>Schedule</th>
                                <th>Facilities</th>
                                <th>Discovery</th>
                                <th class="w-[16rem]">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($branches as $branch)
                                @php
                                    $branchSchedule = OperatingHours::normalize($branch->timings ?? [], $branch->weekly_off ?? []);
                                    $todayKey = strtolower(now($branch->timezone ?: $gym->timezone ?: config('app.timezone'))->englishDayOfWeek);
                                    $todayHours = OperatingHours::formatDaySlots($branchSchedule[$todayKey] ?? []);
                                    $facilityPreview = $branch->facilities->pluck('name')->take(3);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="min-w-[16rem]">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $branch->name }}</div>
                                                <x-status-badge :label="$branch->is_active ? 'Active' : 'Inactive'" :tone="$branch->is_active ? 'success' : 'warning'" />
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $branch->timezone ?: 'Timezone pending' }} • {{ $branch->membership_plans_count ?? 0 }} plans • {{ $branch->trial_requests_count ?? 0 }} trials
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[15rem] text-sm">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $branch->city ?: 'City pending' }}{{ $branch->state ? ', '.$branch->state : '' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $branch->address ?: $branch->address_line ?: 'Address not configured' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $branch->pincode ?: 'No pincode' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[11rem]">
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $branch->today_check_ins_count }} check-ins</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $branch->member_profiles_count }} members • {{ $branch->trainer_profiles_count }} trainers</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[16rem] text-sm">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ OperatingHours::dayLabel($todayKey) }} • {{ $todayHours }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $branch->opening_time ?: 'n/a' }}{{ $branch->closing_time ? ' to '.$branch->closing_time : '' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[16rem]">
                                            <div class="flex flex-wrap gap-1.5">
                                                @forelse ($facilityPreview as $facilityName)
                                                    <x-status-badge :label="$facilityName" tone="info" />
                                                @empty
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">No facilities</span>
                                                @endforelse
                                                @if ($branch->facilities->count() > 3)
                                                    <x-status-badge :label="'+' . ($branch->facilities->count() - 3) . ' more'" tone="neutral" />
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[13rem] text-sm">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ filled($branch->latitude) && filled($branch->longitude) ? 'Map ready' : 'Map pending' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ count($branch->photo_urls ?? []) }} photos • {{ $branch->cityRecord?->name ?? 'No city link' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('web.gym.branches.show', ['branch' => $branch->id, 'gym' => $gym->id]) }}" class="panel-btn-secondary !px-3 !py-2 !text-xs">Open</a>
                                            @if ($canManageBranches)
                                                <a href="{{ route('web.gym.branches.edit', ['branch' => $branch->id, 'gym' => $gym->id]) }}" class="panel-btn-secondary !px-3 !py-2 !text-xs">Edit</a>
                                                <form method="POST" action="{{ route('web.gym.branches.toggle-status', ['branch' => $branch->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="{{ $branch->is_active ? 'Deactivate branch?' : 'Activate branch?' }}" data-confirm-message="{{ $branch->is_active ? 'Members and staff will stop using this branch operationally.' : 'This branch will become active again.' }}" data-confirm-button="{{ $branch->is_active ? 'Deactivate' : 'Activate' }}">
                                                    @csrf
                                                    <button type="submit" class="{{ $branch->is_active ? 'panel-btn-danger' : 'panel-btn-primary' }} !px-3 !py-2 !text-xs">
                                                        {{ $branch->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-6">
                    <x-empty-state title="No branches yet" message="Create the first branch to unlock branch-scoped operations." />
                </div>
            @endif

            @if ($branches->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $branches->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
