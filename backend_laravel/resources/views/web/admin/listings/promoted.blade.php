@extends('layouts.panel')

@section('content')
    @php($currentGyms = $promotedGyms->getCollection())

    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.listings.index') }}" variant="secondary">All Listings</x-action-button>
            <x-action-button as="a" href="{{ route('web.admin.featured-gyms.index') }}" variant="secondary">Featured Gyms</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat-card label="Promoted Gyms" :value="$promotedGyms->total()" hint="Current promoted placements" tone="amber" />
            <x-stat-card label="Verified" :value="$currentGyms->where('is_verified', true)->count()" hint="Verified promoted gyms" tone="emerald" />
            <x-stat-card label="Promoted Price" :value="number_format((float) $promotedListingPrice, 2)" hint="Configured in platform settings" tone="violet" />
        </div>

        <x-premium-card class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <h3 class="panel-section-title">Filters</h3>
                <p class="panel-section-copy">Narrow the promoted set by search and city.</p>
            </div>
            <div class="p-5">
                <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-form-input name="search" label="Search" :value="request('search')" placeholder="Gym or owner" />
                    <x-form-input name="city" label="City" :value="request('city')" placeholder="Filter by city" />
                    <div class="md:col-span-2 xl:col-span-4 flex flex-wrap gap-2 pt-1">
                        <x-action-button type="submit">Apply Filters</x-action-button>
                        <x-action-button as="a" href="{{ route('web.admin.promoted-gyms.index') }}" variant="secondary">Reset</x-action-button>
                    </div>
                </form>
            </div>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="panel-section-title">Promoted Placement Directory</h3>
                        <p class="panel-section-copy">Gyms with extra discovery reach and campaign visibility.</p>
                    </div>
                    <x-status-badge :label="$promotedGyms->total().' promoted'" tone="promoted" />
                </div>
            </div>

            @if ($promotedGyms->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th>Gym</th>
                                <th>Owner</th>
                                <th>City</th>
                                <th>Promotion</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($promotedGyms as $gym)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950">{{ $gym->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $gym->slug }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $gym->owner?->name ?? 'N/A' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $gym->owner?->email ?? 'N/A' }}</div>
                                    </td>
                                    <td>{{ $gym->city ?: 'N/A' }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge label="Promoted" tone="promoted" />
                                            <x-status-badge :label="'Price '.number_format((float) $promotedListingPrice, 2)" tone="neutral" />
                                            <x-status-badge :label="$gym->created_at?->format('d M Y') ?? 'Date unavailable'" tone="neutral" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.gyms.show', $gym) }}" variant="secondary">View</x-action-button>
                                            <form method="POST" action="{{ route('web.admin.gyms.promote', $gym) }}" data-confirm-submit data-confirm-title="Remove promoted status?" data-confirm-message="This gym will lose promoted placement." data-confirm-button="Unpromote">
                                                @csrf
                                                <x-action-button type="submit" variant="danger">Unpromote</x-action-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-6">
                    <x-empty-state title="No promoted gyms found" message="Promote a gym from the listings or gym detail screens to surface it here." />
                </div>
            @endif

            @if ($promotedGyms->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $promotedGyms->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
