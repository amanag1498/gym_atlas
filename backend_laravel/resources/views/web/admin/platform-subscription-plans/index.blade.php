@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.platform-subscription-plans.create') }}">Create Plan</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">Gym Billing</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Plans" :value="$totalPlansCount" hint="Platform billing plans" tone="sky" />
            <x-stat-card label="Default" :value="$defaultPlansCount" hint="Current default plans" tone="emerald" />
            <x-stat-card label="Active" :value="$activePlansCount" hint="Sellable plans" tone="violet" />
            <x-stat-card label="Assigned" :value="$assignedPlansCount" hint="Gym billing records" tone="amber" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-3">
                    <x-form-input name="search" label="Search Plan" :value="request('search')" placeholder="Name, slug, or description" />
                </div>
                <div>
                    <x-form-select name="status" label="Status" :selected="request('status')" :options="['' => 'All statuses', 'draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive']" />
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <x-action-button type="submit">Apply</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.platform-subscription-plans.index') }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden">
            @if ($plans->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1080px]">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Cadence</th>
                                <th>Commercials</th>
                                <th>Services</th>
                                <th>Highlights</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $plan->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->slug }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $plan->cadence_label }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->trial_days }} trial days</div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ $plan->price_label }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Setup ₹{{ number_format((float) $plan->setup_fee, 0) }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->gym_subscriptions_count }} gyms assigned</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @forelse (array_slice($plan->included_services ?? [], 0, 3) as $service)
                                                <x-status-badge :label="$service" tone="info" />
                                            @empty
                                                <x-status-badge label="No services added" tone="neutral" />
                                            @endforelse
                                            @if (count($plan->included_services ?? []) > 3)
                                                <x-status-badge :label="'+' . (count($plan->included_services ?? []) - 3) . ' more'" tone="neutral" />
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ count($plan->feature_highlights ?? []) }} highlights</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {{ \Illuminate\Support\Str::limit(collect($plan->feature_highlights ?? [])->implode(', '), 72) ?: 'No highlight copy added' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="$plan->status" />
                                            @if ($plan->is_default)
                                                <x-status-badge label="Default" tone="verified" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.create', ['plan' => $plan->id]) }}">Assign</x-action-button>
                                            <x-action-button as="a" href="{{ route('web.admin.platform-subscription-plans.edit', $plan) }}">Edit</x-action-button>
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
                        title="No platform plans found"
                        message="Create the first plan so gym billing can be standardized across onboarding and renewals."
                        action-label="Create Plan"
                        :action-href="route('web.admin.platform-subscription-plans.create')"
                    />
                </div>
            @endif

            @if ($plans->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $plans->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
