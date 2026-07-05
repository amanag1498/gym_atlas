@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">Membership Plans</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Plan catalogue built for faster billing operations, cleaner scope control, and safer pricing edits.</p>
            </div>
            @if ($canManagePlans)
                <x-action-button as="a" href="{{ route('web.gym.membership-plans.create', request()->query()) }}" variant="primary">Add Plan</x-action-button>
            @endif
        </div>

        <div class="grid gap-3 lg:grid-cols-4">
            <x-stat-card label="Plans" :value="$plans->total()" hint="Membership catalogue in current scope" tone="sky" />
            <x-stat-card label="Active" :value="$plans->getCollection()->where('status', 'active')->count()" hint="Ready to assign now" tone="emerald" />
            <x-stat-card label="PT Included" :value="$plans->getCollection()->where('pt_included', true)->count()" hint="Plans with coaching support" tone="violet" />
            <x-stat-card label="Gym-wide" :value="$plans->getCollection()->whereNull('branch_id')->count()" hint="Available across branches" tone="amber" />
        </div>

        <x-premium-card class="p-6">
            <form method="GET" action="{{ route('web.gym.membership-plans.index') }}" class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_0.8fr_auto]">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                <x-form-input name="search" label="Search Plans" :value="request('search')" placeholder="Monthly, yearly, PT..." />
                <div>
                    <label for="branch_id" class="panel-label">Branch Scope</label>
                    <select id="branch_id" name="branch_id" class="panel-select">
                        <option value="">All visible scopes</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) request('branch_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <x-action-button type="submit" variant="secondary" class="w-full justify-center">Apply Filters</x-action-button>
                </div>
            </form>
        </x-premium-card>

        @if ($selectedScopeBranch && auth()->user()?->active_role === 'branch_manager')
            <x-premium-card class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-950 dark:text-white">Branch-managed plan scope</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You can manage plans only for {{ $selectedScopeBranch->name }}. Gym-wide plans stay visible for history but cannot be edited from this scope.</p>
                    </div>
                    <x-status-badge :label="$selectedScopeBranch->name" tone="info" />
                </div>
            </x-premium-card>
        @endif

        <x-premium-card class="overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50/90 dark:bg-slate-900/80">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Scope</th>
                            <th class="px-4 py-3">Cadence</th>
                            <th class="px-4 py-3">Commercials</th>
                            <th class="px-4 py-3">Usage</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($plans as $plan)
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-slate-950 dark:text-white">{{ $plan->name }}</p>
                                            <x-status-badge :label="ucfirst($plan->status)" />
                                            @if ($plan->pt_included)
                                                <x-status-badge label="PT Included" tone="info" />
                                            @endif
                                        </div>
                                        <p class="max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $plan->description ?: 'No plan description added yet.' }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $plan->branch?->name ?? 'Gym-wide plan' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->branch_id ? 'Branch scoped' : 'Works across branches' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $plan->cadence_label }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->duration_label }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $plan->price_label }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Joining ₹{{ number_format((float) $plan->joining_fee, 0) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $plan->member_memberships_count }} active usages</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $plan->billing_type === 'free' ? 'Free access model' : 'Paid billing model' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-action-button as="a" href="{{ route('web.gym.membership-plans.show', ['plan' => $plan->id] + request()->query()) }}" variant="secondary">View</x-action-button>
                                        @if ($canManagePlans && (auth()->user()?->active_role === 'gym_owner' || $plan->branch_id !== null))
                                            <x-action-button as="a" href="{{ route('web.gym.membership-plans.edit', ['plan' => $plan->id] + request()->query()) }}" variant="secondary">Edit</x-action-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-empty-state title="No plans yet" message="Create the first membership plan for this gym to unlock quick membership assignment." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-premium-card>

        <div>{{ $plans->links() }}</div>
    </div>
@endsection
