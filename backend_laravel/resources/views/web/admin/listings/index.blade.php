@extends('layouts.panel')

@section('content')
    @php($currentGyms = $gyms->getCollection())

    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.featured-gyms.index') }}" variant="secondary">Featured Gyms</x-action-button>
            <x-action-button as="a" href="{{ route('web.admin.promoted-gyms.index') }}" variant="secondary">Promoted Gyms</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Listing Records" :value="$gyms->total()" hint="Public-enabled gyms" tone="sky" />
            <x-stat-card label="Verified" :value="$currentGyms->where('is_verified', true)->count()" hint="Verified on this page" tone="emerald" />
            <x-stat-card label="Pricing Visible" :value="$currentGyms->where('show_pricing', true)->count()" hint="Pricing shown publicly" tone="violet" />
            <x-stat-card label="Contact Visible" :value="$currentGyms->where('contact_visible', true)->count()" hint="Public contact allowed" tone="amber" />
        </div>

        <x-premium-card class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <h3 class="panel-section-title">Filters</h3>
                <p class="panel-section-copy">Keep the discovery list focused without visually heavy controls.</p>
            </div>
            <div class="p-5">
                <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-form-input name="search" label="Search" :value="request('search')" placeholder="Gym name or owner" />
                    <x-form-input name="city" label="City" :value="request('city')" placeholder="Filter by city" />
                    <x-form-select
                        name="status"
                        label="Gym Status"
                        :selected="request('status')"
                        :options="[
                            '' => 'All statuses',
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'pending' => 'Pending',
                            'rejected' => 'Rejected',
                            'suspended' => 'Suspended',
                        ]"
                    />
                    <x-form-select
                        name="verified"
                        label="Verified"
                        :selected="request('verified')"
                        :options="['' => 'All', '1' => 'Verified', '0' => 'Unverified']"
                    />
                    <div class="md:col-span-2 xl:col-span-4 flex flex-wrap gap-2 pt-1">
                        <x-action-button type="submit">Apply Filters</x-action-button>
                        <x-action-button as="a" href="{{ route('web.admin.listings.index') }}" variant="secondary">Reset</x-action-button>
                    </div>
                </form>
            </div>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="panel-section-title">Listing Directory</h3>
                        <p class="panel-section-copy">Public-enabled gyms with visibility flags and listing actions.</p>
                    </div>
                    <x-status-badge :label="$listingStats['public_enabled'].' enabled'" tone="info" />
                </div>
            </div>

            @if ($gyms->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1120px]">
                        <thead>
                            <tr>
                                <th>Gym</th>
                                <th>Owner</th>
                                <th>City</th>
                                <th>Visibility</th>
                                <th>Placement</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gyms as $gym)
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
                                        <div class="mb-2 text-xs text-slate-500">
                                            {{ $gym->contact_number ?: 'No phone' }}
                                            @if ($gym->instagram_profile)
                                                <span class="mx-1">•</span>{{ str($gym->instagram_profile)->after('instagram.com/') }}
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$gym->status ?: 'Unknown'" />
                                            <x-status-badge :label="$gym->is_verified ? 'Verified' : 'Unverified'" :tone="$gym->is_verified ? 'verified' : 'neutral'" />
                                            <x-status-badge :label="$gym->show_pricing ? 'Pricing visible' : 'Pricing hidden'" :tone="$gym->show_pricing ? 'info' : 'neutral'" />
                                            <x-status-badge :label="$gym->contact_visible ? 'Contact visible' : 'Contact hidden'" :tone="$gym->contact_visible ? 'info' : 'neutral'" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$gym->public_listing_enabled ? 'Public' : 'Hidden'" :tone="$gym->public_listing_enabled ? 'success' : 'danger'" />
                                            <x-status-badge :label="ucfirst($gym->public_listing_approval_status ?? 'pending')" />
                                            <x-status-badge :label="$gym->is_featured ? 'Featured' : 'Standard'" :tone="$gym->is_featured ? 'featured' : 'neutral'" />
                                            <x-status-badge :label="$gym->is_promoted ? 'Promoted' : 'Not promoted'" :tone="$gym->is_promoted ? 'promoted' : 'neutral'" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.gyms.show', $gym) }}" variant="secondary">View</x-action-button>
                                            <x-action-button as="a" href="{{ url('/api/public/discovery/gyms/'.$gym->slug) }}" variant="secondary" target="_blank">Preview</x-action-button>
                                            <form method="POST" action="{{ route('web.admin.gyms.feature', $gym) }}" data-confirm-submit data-confirm-title="{{ $gym->is_featured ? 'Remove featured status?' : 'Feature this gym?' }}" data-confirm-message="Update featured placement for this gym." data-confirm-button="{{ $gym->is_featured ? 'Unfeature' : 'Feature' }}">
                                                @csrf
                                                <x-action-button type="submit" variant="secondary">{{ $gym->is_featured ? 'Unfeature' : 'Feature' }}</x-action-button>
                                            </form>
                                            <form method="POST" action="{{ route('web.admin.gyms.promote', $gym) }}" data-confirm-submit data-confirm-title="{{ $gym->is_promoted ? 'Remove promoted status?' : 'Promote this gym?' }}" data-confirm-message="Update promoted placement for this gym." data-confirm-button="{{ $gym->is_promoted ? 'Unpromote' : 'Promote' }}">
                                                @csrf
                                                <x-action-button type="submit" variant="secondary">{{ $gym->is_promoted ? 'Unpromote' : 'Promote' }}</x-action-button>
                                            </form>
                                            <form method="POST" action="{{ $gym->public_listing_enabled ? route('web.admin.gyms.hide-listing', $gym) : route('web.admin.gyms.show-listing', $gym) }}" data-confirm-submit data-confirm-title="{{ $gym->public_listing_enabled ? 'Hide listing?' : 'Show listing?' }}" data-confirm-message="{{ $gym->public_listing_enabled ? 'This gym will disappear from public discovery.' : 'This gym will become eligible for discovery if it is active and approved.' }}" data-confirm-button="{{ $gym->public_listing_enabled ? 'Hide Listing' : 'Show Listing' }}">
                                                @csrf
                                                <x-action-button type="submit" variant="{{ $gym->public_listing_enabled ? 'danger' : 'primary' }}">{{ $gym->public_listing_enabled ? 'Hide' : 'Show' }}</x-action-button>
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
                    <x-empty-state title="No public listings found" message="No gyms match the current public listing filters." />
                </div>
            @endif

            @if ($gyms->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $gyms->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
