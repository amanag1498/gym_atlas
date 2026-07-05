@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Location Catalog</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Cities</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Maintain the location catalog used in gym onboarding, branch setup, and public discovery without relying on oversized form shells.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active Cities</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $activeCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">ready for gym and branch selection</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Inactive Cities</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $inactiveCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">hidden from new selections</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Cities" :value="$cities->total()" hint="Platform location catalog" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Usable in setup flows" tone="emerald" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Hidden from selection" tone="amber" />
            <x-stat-card label="Loaded" :value="$cities->count()" hint="Current page results" tone="violet" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <x-premium-card class="p-5">
                <div>
                    <h3 class="panel-section-title">Create City</h3>
                    <p class="panel-section-copy">Add a clean city record for future gym listings and branch assignments.</p>
                </div>

                <form action="{{ route('web.admin.cities.store') }}" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <x-form-input name="name" label="City Name" :value="old('name')" placeholder="Mumbai" required />
                    <x-form-input name="state" label="State" :value="old('state')" placeholder="Maharashtra" required />
                    <x-form-input name="country" label="Country" :value="old('country')" placeholder="India" />
                    <label class="panel-card-muted flex items-start gap-3 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="mt-1" @checked(old('is_active', true))>
                        <span>
                            <span class="block font-semibold text-slate-950 dark:text-white">Active for selection</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Available in web and app flows for gyms and branches.</span>
                        </span>
                    </label>
                    <x-action-button type="submit" class="w-full justify-center">Create City</x-action-button>
                </form>
            </x-premium-card>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                        <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search city, state, or country" />
                        <x-form-select name="status" label="Status" :selected="request('status')" :options="['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']" />
                        <div class="flex items-end gap-2">
                            <x-action-button type="submit">Apply Filters</x-action-button>
                            <x-action-button as="a" href="{{ route('web.admin.cities.index') }}" variant="secondary">Reset</x-action-button>
                        </div>
                    </form>
                </x-premium-card>

                <x-table-wrapper class="overflow-hidden p-0">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="panel-section-title">City Directory</h3>
                            <p class="panel-section-copy">Inline maintenance for city names, state normalization, and activation state.</p>
                        </div>
                        <x-status-badge :label="$cities->total().' total'" tone="neutral" />
                    </div>

                    @if ($cities->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[1240px]">
                                <thead>
                                    <tr>
                                        <th>City</th>
                                        <th>Coverage</th>
                                        <th>Status</th>
                                        <th>Quick Update</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cities as $city)
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $city->name }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $city->state ?: 'No state' }}{{ $city->country ? ' • '.$city->country : '' }}</div>
                                                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Created {{ optional($city->created_at)->format('d M Y') }}</div>
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>Gyms {{ $city->gyms_count ?? 0 }}</div>
                                                <div>Branches {{ $city->branches_count ?? 0 }}</div>
                                            </td>
                                            <td>
                                                <x-status-badge :label="$city->is_active ? 'Active' : 'Inactive'" :tone="$city->is_active ? 'success' : 'danger'" />
                                            </td>
                                            <td>
                                                <form action="{{ route('web.admin.cities.update', $city) }}" method="POST" class="grid gap-2 lg:grid-cols-4">
                                                    @csrf
                                                    @method('PUT')
                                                    <input name="name" class="panel-input" value="{{ $city->name }}" required>
                                                    <input name="state" class="panel-input" value="{{ $city->state }}" required>
                                                    <input name="country" class="panel-input" value="{{ $city->country }}">
                                                    <select name="is_active" class="panel-select">
                                                        <option value="1" @selected($city->is_active)>Active</option>
                                                        <option value="0" @selected(! $city->is_active)>Inactive</option>
                                                    </select>
                                                    <div class="lg:col-span-4">
                                                        <x-action-button type="submit" variant="secondary">Save Changes</x-action-button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="flex justify-end">
                                                    @if (($city->gyms_count ?? 0) > 0 || ($city->branches_count ?? 0) > 0)
                                                        <x-action-button as="span" variant="secondary">In Use</x-action-button>
                                                    @else
                                                        <form action="{{ route('web.admin.cities.destroy', $city) }}" method="POST" data-confirm-submit data-confirm-title="Delete city?" data-confirm-message="This will remove {{ $city->name }} from the platform city catalog." data-confirm-button="Delete">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-action-button type="submit" variant="danger">Delete</x-action-button>
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
                            <x-empty-state title="No cities yet" message="Create the first city to start the platform location catalog." />
                        </div>
                    @endif

                    @if ($cities->hasPages())
                        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                            {{ $cities->links() }}
                        </div>
                    @endif
                </x-table-wrapper>
            </div>
        </div>
    </div>
@endsection
