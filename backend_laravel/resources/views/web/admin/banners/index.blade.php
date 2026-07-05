@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.banners.create') }}">Create Banner</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Growth Surfaces</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Banner Placements</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Manage lightweight promotional placements, control ordering, and keep platform campaigns visually consistent across app surfaces.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active Slots</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $activeCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">currently visible banners</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Highest Order</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $bannerSlots }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">current sort ceiling</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Banners" :value="$banners->total()" hint="Campaign records" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Live app placements" tone="emerald" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Hidden or paused" tone="amber" />
            <x-stat-card label="Loaded" :value="$banners->count()" hint="Results on this page" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search title or destination URL" />
                <x-form-select
                    name="status"
                    label="Status"
                    :selected="request('status')"
                    :options="['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']"
                />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.banners.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Banner Inventory</h3>
                    <p class="panel-section-copy">Each row represents a live or draft campaign asset with visual readiness, destination, and ordering context.</p>
                </div>
                <x-status-badge :label="$banners->total().' total'" tone="neutral" />
            </div>

            @if ($banners->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1180px]">
                        <thead>
                            <tr>
                                <th>Creative</th>
                                <th>Destination</th>
                                <th>Placement</th>
                                <th>Status</th>
                                <th>Timeline</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($banners as $banner)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-4">
                                            <div class="h-16 w-24 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900/70">
                                                @if ($banner->image_url)
                                                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="h-full w-full object-cover">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">
                                                        No art
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $banner->title }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">ID #{{ $banner->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        @if ($banner->link_url)
                                            <div class="max-w-[320px] break-all">{{ $banner->link_url }}</div>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">No destination attached</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Sort order {{ $banner->sort_order ?? 0 }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $banner->image_url ? 'Image ready' : 'Image missing' }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="$banner->is_active ? 'Active' : 'Inactive'" :tone="$banner->is_active ? 'success' : 'danger'" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Created {{ optional($banner->created_at)->format('d M Y') ?: '--' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Updated {{ optional($banner->updated_at)->diffForHumans() ?: '--' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.banners.edit', $banner) }}" variant="secondary">Edit</x-action-button>
                                            <form
                                                method="POST"
                                                action="{{ route('web.admin.banners.destroy', $banner) }}"
                                                data-confirm-submit
                                                data-confirm-title="Delete banner?"
                                                data-confirm-message="This removes {{ $banner->title }} from the platform banner inventory."
                                                data-confirm-button="Delete"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No banners yet" message="Create the first banner to control promotional placements from the admin portal." action-label="Create Banner" :action-href="route('web.admin.banners.create')" />
                </div>
            @endif

            @if ($banners->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $banners->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
