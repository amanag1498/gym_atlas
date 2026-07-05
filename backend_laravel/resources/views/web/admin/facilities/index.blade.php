@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.facilities.create') }}">Add Facility</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Catalog System</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Facilities Master</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Maintain the shared amenity catalog used by gym onboarding, branch setup, listing presentation, and discovery filters across the platform.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Catalog Coverage</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $activeCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">active facilities ready for assignment</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Protected Inventory</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $inactiveCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">inactive records preserved for audit</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Facilities" :value="$facilities->total()" hint="Master catalog size" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Visible for gym assignment" tone="emerald" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Disabled but preserved" tone="amber" />
            <x-stat-card label="Loaded" :value="$facilities->count()" hint="Visible on this page" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(200px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search facilities by name or usage intent" />
                <x-form-select
                    name="status"
                    label="Status"
                    :selected="request('status')"
                    :options="['' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive']"
                />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.facilities.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Facility Directory</h3>
                    <p class="panel-section-copy">Review naming quality, assignment footprint, and lifecycle status before deactivating or removing amenities.</p>
                </div>
                <x-status-badge :label="$facilities->total().' total'" tone="neutral" />
            </div>

            @if ($facilities->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1180px]">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Presentation</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facilities as $facility)
                                @php($usageCount = ($facility->gyms_count ?? 0) + ($facility->branches_count ?? 0))
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $facility->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $facility->slug }}</div>
                                        @if ($facility->description)
                                            <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $facility->description }}</div>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Icon {{ $facility->icon ?: 'Not set' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $facility->is_active ? 'Available in gym and branch forms' : 'Hidden from new assignments' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$facility->is_active ? 'Active' : 'Inactive'" :tone="$facility->is_active ? 'success' : 'danger'" />
                                            <x-status-badge :label="str($facility->status)->title()" tone="neutral" />
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Gyms {{ $facility->gyms_count }}</div>
                                        <div>Branches {{ $facility->branches_count }}</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">Total {{ $usageCount }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.facilities.edit', $facility) }}" variant="secondary">Edit</x-action-button>
                                            <form
                                                action="{{ route('web.admin.facilities.toggle-status', $facility) }}"
                                                method="POST"
                                                data-confirm-submit
                                                data-confirm-title="{{ $facility->is_active ? 'Deactivate facility?' : 'Activate facility?' }}"
                                                data-confirm-message="{{ $facility->is_active ? 'This facility will stop appearing in gym create/edit forms.' : 'This facility will become available in gym create/edit forms again.' }}"
                                                data-confirm-button="{{ $facility->is_active ? 'Deactivate' : 'Activate' }}"
                                            >
                                                @csrf
                                                <x-action-button type="submit" variant="{{ $facility->is_active ? 'danger' : 'primary' }}">
                                                    {{ $facility->is_active ? 'Deactivate' : 'Activate' }}
                                                </x-action-button>
                                            </form>
                                            @if ($usageCount === 0)
                                                <form
                                                    action="{{ route('web.admin.facilities.destroy', $facility) }}"
                                                    method="POST"
                                                    data-confirm-submit
                                                    data-confirm-title="Delete facility?"
                                                    data-confirm-message="This will permanently delete the facility from the platform master."
                                                    data-confirm-button="Delete"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                                </form>
                                            @else
                                                <x-action-button as="span" variant="secondary">In Use</x-action-button>
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
                    <x-empty-state title="No facilities found" message="Start by creating the first platform facility to make it available in gym setup and discovery." action-label="Add Facility" :action-href="route('web.admin.facilities.create')" />
                </div>
            @endif

            @if ($facilities->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $facilities->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
