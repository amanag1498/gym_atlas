@php
    $plan = $plan ?? null;
    $lockedBranchId = old('branch_id', $plan?->branch_id ?? $selectedScopeBranch?->id);
    $branchScopeRequired = $branchScopeRequired ?? false;
    $billingType = old('billing_type', $plan?->billing_type ?? (((float) ($plan?->plan_price ?? 0)) <= 0 ? 'free' : 'paid'));
    $billingPeriod = old('billing_period', $plan?->billing_period ?? 'month');
    $billingIntervalCount = old('billing_interval_count', $plan?->billing_interval_count ?? 1);
    $durationDays = old('duration_days', $plan?->duration_days ?? 30);
@endphp

<input type="hidden" name="gym_id" value="{{ old('gym_id', $gym->id) }}">

<div class="space-y-5" data-membership-plan-form>
    <div class="grid gap-5 xl:grid-cols-[1.08fr_0.92fr]">
        <div class="space-y-5">
            <div class="grid gap-5 md:grid-cols-2">
                <x-form-input name="name" label="Plan Name" :value="old('name', $plan?->name)" placeholder="Monthly, Quarterly, Personal Training..." required />
                <div>
                    <label for="branch_id" class="panel-label">Branch Scope</label>
                    <select id="branch_id" name="branch_id" class="panel-select" {{ $branchScopeRequired ? 'required' : '' }}>
                        @if (! $branchScopeRequired)
                            <option value="">Gym-wide plan</option>
                        @endif
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $lockedBranchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @if ($branchScopeRequired)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">This role can manage branch-specific plans only.</p>
                    @endif
                </div>
                <x-form-select
                    name="billing_type"
                    label="Plan Type"
                    :selected="$billingType"
                    :options="['paid' => 'Paid plan', 'free' => 'Free plan']"
                />
                <x-form-select
                    name="billing_period"
                    label="Billing Period"
                    :selected="$billingPeriod"
                    :options="['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Yearly', 'custom' => 'Custom duration']"
                />
                <x-form-input name="billing_interval_count" label="Interval Count" type="number" min="1" max="24" :value="$billingIntervalCount" required />
                <x-form-input name="duration_days" label="Duration Days" type="number" min="1" :value="$durationDays" required data-duration-days-input />
                <x-form-input name="plan_price" label="Plan Price" type="number" min="0" step="0.01" :value="old('plan_price', $plan?->plan_price)" required data-plan-price-input />
                <x-form-input name="joining_fee" label="Joining Fee" type="number" min="0" step="0.01" :value="old('joining_fee', $plan?->joining_fee ?? 0)" required data-joining-fee-input />
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="active" @selected(old('status', $plan?->status ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $plan?->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-[0.7fr_1.3fr]">
                <div class="panel-card-muted px-4 py-4">
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="pt_included" value="0">
                        <input id="pt_included" type="checkbox" name="pt_included" value="1" @checked((bool) old('pt_included', $plan?->pt_included))>
                        <div>
                            <label for="pt_included" class="font-medium text-slate-950 dark:text-white">Personal training included</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use this for PT-inclusive plans or dedicated trainer packages.</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="description" class="panel-label">Description</label>
                    <textarea id="description" name="description" class="panel-textarea" placeholder="Plan notes, eligible audience, PT coverage, and operational details.">{{ old('description', $plan?->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-1">
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Cadence Preview</p>
                    <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white" data-plan-cadence-preview>Monthly</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Price Preview</p>
                    <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white" data-plan-price-preview>₹0</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Coverage</p>
                    <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white" data-plan-duration-preview>{{ $durationDays }} days</p>
                </div>
            </div>

            <div class="panel-card-muted px-4 py-4">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Pricing Guidance</p>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p>Keep plan names and cadence aligned so front-desk staff can assign quickly.</p>
                    <p>Joining fee should only represent first-time onboarding cost, not renewal value.</p>
                    <p>Deactivate outdated plans instead of editing them into a different commercial product.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const dayMap = { day: 1, week: 7, month: 30, quarter: 90, year: 365 };

                document.querySelectorAll('[data-membership-plan-form]').forEach((form) => {
                    const billingType = form.querySelector('[name="billing_type"]');
                    const billingPeriod = form.querySelector('[name="billing_period"]');
                    const intervalCount = form.querySelector('[name="billing_interval_count"]');
                    const durationDays = form.querySelector('[data-duration-days-input]');
                    const planPrice = form.querySelector('[data-plan-price-input]');
                    const joiningFee = form.querySelector('[data-joining-fee-input]');
                    const cadencePreview = form.querySelector('[data-plan-cadence-preview]');
                    const pricePreview = form.querySelector('[data-plan-price-preview]');
                    const durationPreview = form.querySelector('[data-plan-duration-preview]');

                    const labels = {
                        day: 'day',
                        week: 'week',
                        month: 'month',
                        quarter: 'quarter',
                        year: 'year',
                    };

                    const update = () => {
                        const type = billingType.value;
                        const period = billingPeriod.value;
                        const count = Math.max(1, Number(intervalCount.value || 1));
                        const isCustom = period === 'custom';

                        if (!isCustom && dayMap[period]) {
                            durationDays.value = count * dayMap[period];
                            durationDays.readOnly = true;
                        } else {
                            durationDays.readOnly = false;
                        }

                        if (type === 'free') {
                            planPrice.value = 0;
                            joiningFee.value = 0;
                            planPrice.readOnly = true;
                            joiningFee.readOnly = true;
                        } else {
                            planPrice.readOnly = false;
                            joiningFee.readOnly = false;
                        }

                        const cadence = isCustom
                            ? `${durationDays.value || 0} day custom`
                            : `${count} ${labels[period]}${count > 1 ? 's' : ''}`;
                        const numericPrice = Number(planPrice.value || 0);

                        cadencePreview.textContent = type === 'free' ? `Free • ${cadence}` : cadence;
                        pricePreview.textContent = type === 'free'
                            ? 'Free'
                            : `₹${numericPrice.toLocaleString('en-IN')}${period !== 'custom' ? ` / ${labels[period]}` : ''}`;
                        durationPreview.textContent = `${durationDays.value || 0} days`;
                    };

                    ['change', 'input'].forEach((eventName) => form.addEventListener(eventName, update));
                    update();
                });
            })();
        </script>
    @endpush
@endonce
