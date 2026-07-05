@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.16fr_0.84fr]">
        <x-premium-card class="p-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Edit Membership Plan</h3>
                    <p class="panel-section-copy">Update the plan master safely. Existing member memberships keep their copied `default_plan_price` and `default_joining_fee` values.</p>
                </div>
                <x-status-badge :label="ucfirst($plan->status)" />
            </div>

            <form action="{{ route('web.gym.membership-plans.update', ['plan' => $plan->id] + request()->query()) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                @include('web.gym.membership-plans._form')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('web.gym.membership-plans.show', ['plan' => $plan->id] + request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Save Changes</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <div class="space-y-5">
            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Current Snapshot</h3>
                <div class="mt-4 grid gap-3">
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Scope</p>
                        <p class="mt-2 font-medium text-slate-950 dark:text-white">{{ $plan->branch?->name ?? 'Gym-wide plan' }}</p>
                    </div>
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Base Price</p>
                        <p class="mt-2 font-medium text-slate-950 dark:text-white">{{ $plan->price_label }}</p>
                    </div>
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Joining Fee</p>
                        <p class="mt-2 font-medium text-slate-950 dark:text-white">{{ number_format((float) $plan->joining_fee, 2) }}</p>
                    </div>
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">PT Included</p>
                        <p class="mt-2 font-medium text-slate-950 dark:text-white">{{ $plan->cadence_label }}{{ $plan->pt_included ? ' • PT included' : '' }}</p>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Operational Note</h3>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">If you need to stop future assignments, deactivate the plan instead of deleting it. Current member memberships will remain readable and billing-safe.</p>
            </x-premium-card>
        </div>
    </div>
@endsection
