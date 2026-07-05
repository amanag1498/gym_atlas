@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $plan->name }}</h2>
                        <x-status-badge :label="ucfirst($plan->status)" />
                        @if ($plan->pt_included)
                            <x-status-badge label="PT Included" tone="info" />
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $plan->branch?->name ?? 'Gym-wide plan' }} • {{ $plan->cadence_label }}</p>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $plan->description ?: 'No plan description added yet.' }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($canManagePlans)
                        <a href="{{ route('web.gym.membership-plans.edit', ['plan' => $plan->id] + request()->query()) }}" class="panel-btn-secondary">Edit Plan</a>
                        <form method="POST" action="{{ route('web.gym.membership-plans.' . ($plan->status === 'active' ? 'deactivate' : 'activate'), ['plan' => $plan->id] + request()->query()) }}" data-confirm-submit data-confirm-title="{{ $plan->status === 'active' ? 'Deactivate plan?' : 'Activate plan?' }}" data-confirm-message="{{ $plan->status === 'active' ? 'This plan will stop showing in assign membership, but current memberships keep their copied pricing.' : 'This plan will become assignable again.' }}" data-confirm-button="{{ $plan->status === 'active' ? 'Deactivate' : 'Activate' }}">
                            @csrf
                            <x-action-button type="submit" variant="{{ $plan->status === 'active' ? 'danger' : 'primary' }}">{{ $plan->status === 'active' ? 'Deactivate' : 'Activate' }}</x-action-button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-3 lg:grid-cols-5">
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Plan Price</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $plan->price_label }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Joining Fee</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ number_format((float) $plan->joining_fee, 2) }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Members Using Plan</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $plan->member_memberships_count }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Created By</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ $plan->creator?->name ?? 'System' }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Billing Model</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ ucfirst($plan->billing_type) }}</p>
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Plan Summary</h3>
                <div class="mt-4 space-y-3">
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Scope</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->branch?->name ?? 'All branches' }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Duration</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->duration_label }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Trainer support</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->pt_included ? 'Included' : 'Not included' }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Interval count</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->billing_interval_count }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Duration days</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->duration_days }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-slate-500 dark:text-slate-400">Last updated</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ optional($plan->updated_at)->format('d M Y, h:i A') }}</span>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Recent Membership Usage</h3>
                <p class="panel-section-copy">These member memberships keep their copied pricing even if the plan master is edited later.</p>
                <div class="mt-4 space-y-3">
                    @forelse ($recentMemberships as $membership)
                        <div class="panel-card-muted flex items-center justify-between gap-4 px-4 py-3">
                            <div>
                                <p class="font-medium text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'Gym-wide' }} • {{ optional($membership->start_date)->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <x-status-badge :label="ucfirst($membership->status)" />
                                <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ number_format((float) $membership->final_payable_amount, 2) }}</p>
                            </div>
                        </div>
                    @empty
                        <x-empty-state title="No memberships yet" message="This plan has not been used in any member membership assignments yet." />
                    @endforelse
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
