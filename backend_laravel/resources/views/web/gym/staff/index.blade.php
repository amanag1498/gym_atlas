@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <div class="grid gap-3 lg:grid-cols-4">
            <x-stat-card label="Staff" :value="$summary['total'] ?? 0" hint="Visible in this gym scope" tone="sky" />
            <x-stat-card label="Active" :value="$summary['active'] ?? 0" hint="Operational accounts" tone="emerald" />
            <x-stat-card label="Branch Managers" :value="$summary['branch_managers'] ?? 0" hint="Scoped floor operators" tone="amber" />
            <x-stat-card label="Custom Grants" :value="$summary['custom_permission_grants'] ?? 0" hint="Permission toggles in use" tone="violet" />
        </div>

        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Staff Access Directory</h3>
                    <p class="panel-section-copy">Review role placement, branch scope, custom grants, and recent operational activity.</p>
                </div>
                <x-action-button as="a" href="{{ route('web.gym.staff.create', ['gym' => $gym->id]) }}" variant="primary">Add Staff</x-action-button>
            </div>

            <form method="GET" action="{{ route('web.gym.staff.index') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_auto]">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                <x-form-input name="search" label="Search Staff" :value="request('search')" placeholder="{{ $hasPhoneColumn ? 'Name, email, phone' : 'Name or email' }}" />
                <div class="flex items-end">
                    <x-action-button type="submit" variant="secondary" class="w-full justify-center">Apply Filters</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50/90 dark:bg-slate-900/80">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Branch Scope</th>
                            <th class="px-4 py-3">Permission Surface</th>
                            <th class="px-4 py-3">Recent Signal</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($staffMembers as $staff)
                            @php
                                $staffPermissionsRaw = $staff->gyms->firstWhere('id', $gym->id)?->pivot?->custom_permissions ?? [];
                                $staffPermissions = is_array($staffPermissionsRaw) ? $staffPermissionsRaw : (json_decode((string) $staffPermissionsRaw, true) ?: []);
                                $recentSignals = collect($staffActivityTimeline[$staff->id] ?? []);
                                $latestSignal = $recentSignals->first();
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $staff->name }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $staff->email }}</p>
                                    @if ($hasPhoneColumn && $staff->phone)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $staff->phone }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <x-status-badge :label="str($staff->roles->pluck('name')->first() ?? 'gym_staff')->replace('_', ' ')->title()" />
                                        <x-status-badge :label="$staff->is_active ? 'Active' : 'Inactive'" />
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex max-w-xs flex-wrap gap-2">
                                        @forelse ($staff->branches as $branch)
                                            <x-status-badge :label="$branch->name" tone="info" />
                                        @empty
                                            <span class="text-sm text-slate-500 dark:text-slate-400">No branch scope</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <p class="text-sm text-slate-950 dark:text-white">{{ count($staffPermissions) }} custom grants</p>
                                        <p class="max-w-xs text-xs text-slate-500 dark:text-slate-400">
                                            {{ collect($staffPermissions)->map(fn ($permission) => $permissionToggles[$permission] ?? str($permission)->replace('_', ' ')->title())->take(3)->join(', ') ?: 'Default role only' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($latestSignal)
                                        <div class="space-y-1">
                                            <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $latestSignal['title'] }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $latestSignal['date'] }}</p>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">No recent activity</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-action-button as="a" href="{{ route('web.gym.staff.show', ['staff' => $staff->id, 'gym' => $gym->id]) }}" variant="secondary">View</x-action-button>
                                        <x-action-button as="a" href="{{ route('web.gym.staff.edit', ['staff' => $staff->id, 'gym' => $gym->id]) }}" variant="secondary">Edit</x-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-empty-state title="No staff yet" message="Create branch managers and gym staff to delegate branch operations safely." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-premium-card>

        <div>{{ $staffMembers->links() }}</div>
    </div>
@endsection
