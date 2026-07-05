@extends('layouts.panel')

@section('content')
    @php($currentUsers = $users->getCollection())

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Users" :value="$users->total()" hint="Filtered directory size" tone="sky" />
            <x-stat-card label="Active" :value="$currentUsers->where('is_active', true)->count()" hint="Active accounts on this page" tone="emerald" />
            <x-stat-card label="Inactive" :value="$currentUsers->where('is_active', false)->count()" hint="Accounts needing review" tone="amber" />
            <x-stat-card label="Loaded" :value="$users->count()" hint="Visible results" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(180px,1fr)_minmax(180px,1fr)_minmax(220px,1fr)]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Name, email{{ $hasPhoneColumn ? ', or phone' : '' }}" />
                <x-form-select
                    name="role"
                    label="Role"
                    :selected="request('role')"
                    :options="[
                        '' => 'All Roles',
                        'platform_admin' => 'Platform Admin',
                        'gym_owner' => 'Gym Owner',
                        'branch_manager' => 'Branch Manager',
                        'gym_staff' => 'Gym Staff',
                        'trainer' => 'Trainer',
                        'member' => 'Member',
                    ]"
                />
                <x-form-select
                    name="status"
                    label="Status"
                    :selected="request('status')"
                    :options="[
                        '' => 'All',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]"
                />
                <div>
                    <label class="panel-label" for="gym_id">Gym</label>
                    <select id="gym_id" name="gym_id" class="panel-select">
                        <option value="">All gyms</option>
                        @foreach ($gyms as $gym)
                            <option value="{{ $gym->id }}" @selected((string) request('gym_id') === (string) $gym->id)>{{ $gym->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 xl:col-span-4 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ url()->current() }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="p-0 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="panel-section-title">{{ $title }} Directory</h3>
                    <p class="panel-section-copy">Filter by role, search the directory, and open user-level admin detail pages.</p>
                </div>
                <x-status-badge :label="$users->total().' total'" tone="neutral" />
            </div>

            @if ($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1120px]">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Roles</th>
                                <th>Active Role</th>
                                <th>Gym Context</th>
                                <th>Role Signals</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950">{{ $user->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $user->email }}</div>
                                        @if ($hasPhoneColumn && $user->phone)
                                            <div class="text-xs text-slate-500">{{ $user->phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($user->roles as $role)
                                                <x-status-badge :label="str($role->name)->replace('_', ' ')->title()" tone="neutral" />
                                            @empty
                                                <x-status-badge label="No Roles" tone="neutral" />
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="str($user->active_role ?: 'none')->replace('_', ' ')->title()" tone="info" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Gyms: {{ $user->gyms->count() }}</div>
                                        <div>Branches: {{ $user->branches->count() }}</div>
                                        @if ($user->owned_gyms_count)
                                            <div>Owned gyms: {{ $user->owned_gyms_count }}</div>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        @if ($user->managedTrainerProfile)
                                            <div>Assigned members: {{ $user->assigned_members_count }}</div>
                                        @endif
                                        @if ($user->memberProfile)
                                            <div>Memberships: {{ $user->member_memberships_count }}</div>
                                        @endif
                                        @if (! $user->managedTrainerProfile && ! $user->memberProfile && ! $user->owned_gyms_count)
                                            <div>No extra profile signals</div>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-badge :label="$user->is_active ? 'Active' : 'Inactive'" :tone="$user->is_active ? 'success' : 'danger'" />
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.users.show', $user) }}">View</x-action-button>
                                            @if ($user->is_active)
                                                @if (auth()->id() === $user->id)
                                                    <span class="panel-btn-secondary opacity-70">Current Admin</span>
                                                @else
                                                    <form method="POST" action="{{ route('web.admin.users.deactivate', $user) }}" onsubmit="return confirm('Deactivate this user?');">
                                                        @csrf
                                                        <x-action-button type="submit" variant="danger">Deactivate</x-action-button>
                                                    </form>
                                                @endif
                                            @else
                                                <form method="POST" action="{{ route('web.admin.users.activate', $user) }}">
                                                    @csrf
                                                    <x-action-button type="submit">Activate</x-action-button>
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
                <div class="p-5">
                    <x-empty-state title="No users found" message="No records match the current filters." />
                </div>
            @endif

            @if ($users->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $users->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
