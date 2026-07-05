@extends('layouts.panel')

@section('content')
    @php($currentGyms = $gyms->getCollection())

    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.gyms.create') }}">Add Gym</x-action-button>
            <x-action-button as="a" href="{{ route('web.admin.listings.index') }}" variant="secondary">Listings</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Gyms" :value="$gyms->total()" hint="Filtered directory count" tone="sky" />
            <x-stat-card label="Active" :value="$currentGyms->where('is_active', true)->count()" hint="Active on this page" tone="emerald" />
            <x-stat-card label="Pending" :value="$currentGyms->where('approval_status', 'pending')->count()" hint="Awaiting review" tone="amber" />
            <x-stat-card label="Featured / Promoted" :value="$currentGyms->where('is_featured', true)->count() . ' / ' . $currentGyms->where('is_promoted', true)->count()" hint="Discovery visibility" tone="violet" />
        </div>

        <x-premium-card class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <h3 class="panel-section-title">Search and Filters</h3>
                <p class="panel-section-copy">Fine-tune the directory without noisy controls or oversized chips.</p>
            </div>
            <div class="p-5">
                <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="xl:col-span-2">
                        <x-form-input name="search" label="Search Gym" :value="request('search')" placeholder="Gym name or slug" />
                    </div>
                    <div>
                        <x-form-input name="owner" label="Owner" :value="request('owner', request('owner_email'))" placeholder="Name or email" />
                    </div>
                    <div>
                        <x-form-input name="city" label="City" :value="request('city')" placeholder="City" />
                    </div>
                    <div>
                        <x-form-select name="status" label="Status" :selected="request('status')" :options="[
                            '' => 'All statuses',
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'rejected' => 'Rejected',
                            'suspended' => 'Suspended',
                        ]" />
                    </div>
                    <div>
                        <x-form-select name="verified" label="Verified" :selected="request('verified')" :options="['' => 'All', '1' => 'Yes', '0' => 'No']" />
                    </div>
                    <div>
                        <x-form-select name="featured" label="Featured" :selected="request('featured')" :options="['' => 'All', '1' => 'Yes', '0' => 'No']" />
                    </div>
                    <div>
                        <x-form-select name="promoted" label="Promoted" :selected="request('promoted')" :options="['' => 'All', '1' => 'Yes', '0' => 'No']" />
                    </div>
                    <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-2 pt-1">
                        <x-action-button type="submit">Apply Filters</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index') }}">Reset</x-action-button>
                    </div>
                </form>
            </div>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="panel-section-title">Gym Directory</h3>
                        <p class="panel-section-copy">Owner mapping, counts, and all admin actions in a single clean table.</p>
                    </div>
                    <x-status-badge :label="$gyms->total().' results'" tone="neutral" />
                </div>
            </div>

            @if ($gyms->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1180px]">
                        <thead>
                            <tr>
                                <th>Gym</th>
                                <th>Owner</th>
                                <th>City</th>
                                <th>Counts</th>
                                <th>Platform Billing</th>
                                <th>Status</th>
                                <th>Flags</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gyms as $gym)
                                @php($approvalStatus = $gym->approval_status ?: $gym->status ?: 'pending')
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950">{{ $gym->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $gym->slug }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $gym->owner?->name ?? 'Unassigned' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $gym->owner?->email ?? 'No owner email' }}</div>
                                    </td>
                                    <td>{{ $gym->city ?: 'N/A' }}</td>
                                    <td class="text-sm text-slate-600">
                                        <div>Branches {{ $gym->branches_count }}</div>
                                        <div>Members {{ $gym->member_profiles_count }}</div>
                                        <div>Trainers {{ $gym->trainer_profiles_count }}</div>
                                    </td>
                                    <td>
                                        @if ($gym->currentPlatformSubscription)
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $gym->currentPlatformSubscription->plan?->name ?? ($gym->currentPlatformSubscription->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                <x-status-badge :label="$gym->currentPlatformSubscription->status" />
                                                <x-status-badge :label="'₹'.number_format((float) $gym->currentPlatformSubscription->billing_amount, 0)" tone="verified" />
                                            </div>
                                        @else
                                            <div class="text-sm text-slate-500 dark:text-slate-400">Not assigned</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$approvalStatus" />
                                            <x-status-badge :label="$gym->is_active ? 'Active' : 'Inactive'" :tone="$gym->is_active ? 'success' : 'danger'" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$gym->is_verified ? 'Verified' : 'Unverified'" :tone="$gym->is_verified ? 'verified' : 'neutral'" />
                                            <x-status-badge :label="$gym->is_featured ? 'Featured' : 'Standard'" :tone="$gym->is_featured ? 'featured' : 'neutral'" />
                                            <x-status-badge :label="$gym->is_promoted ? 'Promoted' : 'Not promoted'" :tone="$gym->is_promoted ? 'promoted' : 'neutral'" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $gym) }}">View</x-action-button>
                                            <x-action-button as="a" href="{{ route('web.admin.gyms.edit', $gym) }}">Edit</x-action-button>
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id]) }}">Billing</x-action-button>
                                            @if ($approvalStatus !== 'approved')
                                                <form method="POST" action="{{ route('web.admin.gyms.approve', $gym) }}" onsubmit="return confirm('Approve this gym?');">
                                                    @csrf
                                                    <x-action-button type="submit">Approve</x-action-button>
                                                </form>
                                            @endif
                                            @if ($approvalStatus !== 'rejected')
                                                <form method="POST" action="{{ route('web.admin.gyms.reject', $gym) }}" onsubmit="return confirm('Reject this gym?');">
                                                    @csrf
                                                    <x-action-button type="submit" variant="danger">Reject</x-action-button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ $gym->is_active ? route('web.admin.gyms.deactivate', $gym) : route('web.admin.gyms.activate', $gym) }}" onsubmit="return confirm('{{ $gym->is_active ? 'Deactivate' : 'Activate' }} this gym?');">
                                                @csrf
                                                <x-action-button type="submit" :variant="$gym->is_active ? 'danger' : 'secondary'">{{ $gym->is_active ? 'Deactivate' : 'Activate' }}</x-action-button>
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
                    <x-empty-state
                        title="No gyms found"
                        message="Adjust the current filters or create a new gym from the platform panel."
                        action-label="Add Gym"
                        :action-href="route('web.admin.gyms.create')"
                    />
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
