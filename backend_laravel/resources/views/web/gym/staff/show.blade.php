@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $staff->name }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $staff->email }}</p>
                @if ($staff->phone)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $staff->phone }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <x-status-badge :label="str($staff->roles->pluck('name')->first() ?? 'gym_staff')->replace('_', ' ')->title()" />
                <x-status-badge :label="$staff->is_active ? 'Active' : 'Inactive'" />
                @if ($canManageStaff)
                    <x-action-button as="a" href="{{ route('web.gym.staff.edit', ['staff' => $staff->id, 'gym' => $gym->id]) }}" variant="secondary">Edit</x-action-button>
                @endif
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_1fr]">
            <div class="space-y-5">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Access Profile</h3>
                    <div class="mt-4 grid gap-3">
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Branches</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($staff->branches as $branch)
                                    <x-status-badge :label="$branch->name" tone="info" />
                                @empty
                                    <span class="text-sm text-slate-500 dark:text-slate-400">No branch scope assigned</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Permissions</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($currentPermissions as $permission)
                                    <x-status-badge :label="$permissionToggles[$permission] ?? str($permission)->replace('_', ' ')->title()" tone="success" />
                                @empty
                                    <span class="text-sm text-slate-500 dark:text-slate-400">No custom permissions enabled</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($canManageStaff)
                        <div class="mt-6 flex flex-wrap gap-3">
                            <form method="POST" action="{{ $staff->is_active ? route('web.gym.staff.deactivate', ['staff' => $staff->id, 'gym' => $gym->id]) : route('web.gym.staff.activate', ['staff' => $staff->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="{{ $staff->is_active ? 'Deactivate staff?' : 'Activate staff?' }}" data-confirm-message="{{ $staff->is_active ? 'This user will lose staff access immediately.' : 'This user will regain staff access.' }}" data-confirm-button="{{ $staff->is_active ? 'Deactivate' : 'Activate' }}">
                                @csrf
                                <x-action-button type="submit" variant="{{ $staff->is_active ? 'danger' : 'primary' }}">{{ $staff->is_active ? 'Deactivate' : 'Activate' }}</x-action-button>
                            </form>
                            <form method="POST" action="{{ route('web.gym.staff.destroy', ['staff' => $staff->id, 'gym' => $gym->id]) }}" data-confirm-submit data-confirm-title="Remove staff member?" data-confirm-message="This will remove the staff member from this gym and clear their branch scope here." data-confirm-button="Remove Staff">
                                @csrf
                                @method('DELETE')
                                <x-action-button type="submit" variant="danger">Remove Staff</x-action-button>
                            </form>
                        </div>
                    @endif
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Operational Summary</h3>
                    <div class="mt-4 grid gap-3">
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Primary role</span>
                            <span class="font-medium text-slate-950 dark:text-white">{{ str($staff->roles->pluck('name')->first() ?? 'gym_staff')->replace('_', ' ')->title() }}</span>
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Branch count</span>
                            <span class="font-medium text-slate-950 dark:text-white">{{ $staff->branches->count() }}</span>
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Custom grants</span>
                            <span class="font-medium text-slate-950 dark:text-white">{{ count($currentPermissions) }}</span>
                        </div>
                    </div>
                </x-premium-card>
            </div>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Recent Activity</h3>
                <div class="mt-4">
                    <x-web.audit-timeline
                        :items="$activityTimeline"
                        empty-title="No staff activity yet"
                        empty-message="This staff member has no recorded audit history in the current gym scope."
                    />
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
