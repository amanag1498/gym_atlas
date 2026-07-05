@extends('layouts.panel')

@section('content')
    @php($currentOwners = $owners->getCollection())

    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.gym-owners.create') }}">Add Owner</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Owners" :value="$owners->total()" hint="Filtered owners" tone="sky" />
            <x-stat-card label="Active" :value="$currentOwners->where('is_active', true)->count()" hint="Active accounts on this page" tone="emerald" />
            <x-stat-card label="Inactive" :value="$currentOwners->where('is_active', false)->count()" hint="Accounts needing review" tone="amber" />
            <x-stat-card label="Owned Gyms" :value="$currentOwners->sum('owned_gyms_count')" hint="Gyms across visible owners" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(180px,1fr)]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Name, email{{ $hasPhoneColumn ?? false ? ', or phone' : '' }}" />
                <x-form-select name="status" label="Status" :selected="request('status')" :options="[
                    '' => 'All',
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ]" />
                <div class="md:col-span-2 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.index') }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="p-0 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="panel-section-title">Owners Directory</h3>
                    <p class="panel-section-copy">Every user with the gym owner role, their ownership footprint, and status controls.</p>
                </div>
                <x-status-badge :label="$owners->total().' results'" tone="neutral" />
            </div>

            @if ($owners->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1080px]">
                        <thead>
                            <tr>
                                <th>Owner</th>
                                <th>Contact</th>
                                <th>Owned Gyms</th>
                                <th>Active Gyms</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($owners as $owner)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950">{{ $owner->name }}</div>
                                        <div class="text-xs text-slate-500">Role: Gym Owner</div>
                                    </td>
                                    <td>
                                        <div class="text-slate-800">{{ $owner->email }}</div>
                                        @if (($hasPhoneColumn ?? false) && $owner->phone)
                                            <div class="text-xs text-slate-500">{{ $owner->phone }}</div>
                                        @endif
                                    </td>
                                    <td><x-status-badge :label="$owner->owned_gyms_count.' gyms'" tone="info" /></td>
                                    <td><x-status-badge :label="$owner->active_owned_gyms_count.' active'" :tone="$owner->active_owned_gyms_count > 0 ? 'warning' : 'neutral'" /></td>
                                    <td><x-status-badge :label="$owner->is_active ? 'Active' : 'Inactive'" :tone="$owner->is_active ? 'success' : 'danger'" /></td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.show', $owner) }}">View</x-action-button>
                                            @if ($owner->owned_gyms_count > 0)
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.dashboard', $owner) }}">Open Gym Panel</x-action-button>
                                            @endif
                                            <x-action-button as="a" href="{{ route('web.admin.gym-owners.edit', $owner) }}">Edit</x-action-button>
                                            @if ($owner->is_active)
                                                @if ($owner->active_owned_gyms_count > 0)
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.show', $owner) }}">Review</x-action-button>
                                                @else
                                                    <form method="POST" action="{{ route('web.admin.gym-owners.deactivate', $owner) }}" onsubmit="return confirm('Deactivate this gym owner?');">
                                                        @csrf
                                                        <x-action-button type="submit" variant="danger">Deactivate</x-action-button>
                                                    </form>
                                                @endif
                                            @else
                                                <form method="POST" action="{{ route('web.admin.gym-owners.activate', $owner) }}">
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
                    <x-empty-state title="No gym owners found" message="Create the first gym owner to start assigning gyms." action-label="Add Owner" :action-href="route('web.admin.gym-owners.create')" />
                </div>
            @endif

            @if ($owners->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $owners->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
