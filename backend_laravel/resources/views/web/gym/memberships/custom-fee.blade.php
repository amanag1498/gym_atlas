@extends('layouts.panel')

@section('content')
    @php
        $selectedMembership = $memberships->firstWhere('id', $selectedMembershipId) ?? $memberships->first();
        $historicalMemberships = $selectedMembership
            ? $memberships->reject(fn ($membership) => (int) $membership->id === (int) $selectedMembership->id)
            : collect();
        $canEdit = $canEditCustomFee === true;
    @endphp

    <div class="space-y-6">
        @if ($selectedMembership)
            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-6">
                    <div class="panel-card p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pricing desk</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                                    {{ $selectedMembership->membershipPlan?->name ?? 'Membership' }} • #{{ $selectedMembership->id }}
                                </h3>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    Only the selected membership cycle is editable. Older cycles stay available as history.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :label="ucfirst((string) $selectedMembership->status)" :tone="match((string) $selectedMembership->status) { 'active' => 'success', 'frozen' => 'warning', 'expired' => 'neutral', 'cancelled' => 'danger', default => 'neutral' }" />
                                <x-status-badge :label="$canEdit ? 'Editable' : 'View only'" :tone="$canEdit ? 'success' : 'warning'" />
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-4">
                            <div class="panel-card-muted p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Cycle</p>
                                <p class="mt-2 font-semibold text-slate-950 dark:text-white">
                                    {{ optional($selectedMembership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($selectedMembership->expiry_date)->format('d M Y') ?: 'n/a' }}
                                </p>
                            </div>
                            <div class="panel-card-muted p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default plan price</p>
                                <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->default_plan_price, 2) }}</p>
                            </div>
                            <div class="panel-card-muted p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default joining fee</p>
                                <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->default_joining_fee, 2) }}</p>
                            </div>
                            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-200">Branch</p>
                                <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $selectedMembership->branch?->name ?? 'Unassigned' }}</p>
                            </div>
                        </div>

                        <form
                            action="{{ route('web.gym.members.custom-fee.update', $member) }}"
                            method="POST"
                            class="mt-6 grid gap-4 md:grid-cols-2"
                            id="custom-fee-form-{{ $selectedMembership->id }}"
                        >
                            @csrf
                            <input type="hidden" name="member_membership_id" value="{{ $selectedMembership->id }}">

                            <div class="md:col-span-2 panel-card-muted p-4">
                                <label class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-950 dark:text-white">Enable member-specific pricing</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use this only for this cycle. The plan price stays unchanged for everyone else.</p>
                                    </div>
                                    <input type="hidden" name="custom_fee_enabled" value="0">
                                    <input
                                        type="checkbox"
                                        name="custom_fee_enabled"
                                        value="1"
                                        class="h-5 w-5 rounded border-slate-300 bg-white text-sky-500 dark:border-white/20 dark:bg-slate-900"
                                        @checked($selectedMembership->custom_fee_enabled)
                                        id="custom_fee_enabled_{{ $selectedMembership->id }}"
                                        @disabled(! $canEdit)
                                    >
                                </label>
                            </div>

                            <div>
                                <label class="panel-label">Custom Amount</label>
                                <input name="custom_fee_amount" value="{{ $selectedMembership->custom_fee_amount }}" class="panel-input" id="custom_fee_amount_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                            </div>
                            <div>
                                <label class="panel-label">Discount Type</label>
                                <select name="discount_type" class="panel-select" id="discount_type_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                                    @foreach (['none', 'fixed', 'percentage'] as $type)
                                        <option value="{{ $type }}" @selected($selectedMembership->discount_type === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="panel-label">Discount Amount</label>
                                <input name="discount_amount" value="{{ $selectedMembership->discount_amount }}" class="panel-input" id="discount_amount_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                            </div>
                            <div>
                                <label class="panel-label">Custom Joining Fee</label>
                                <input name="custom_joining_fee" value="{{ $selectedMembership->custom_joining_fee }}" class="panel-input" id="custom_joining_fee_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                            </div>
                            <div>
                                <label class="panel-label">Partial Month Fee</label>
                                <input name="partial_month_fee" value="{{ $selectedMembership->partial_month_fee }}" class="panel-input" id="partial_month_fee_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                            </div>
                            <div>
                                <label class="panel-label">PT Custom Fee</label>
                                <input name="pt_custom_fee" value="{{ $selectedMembership->pt_custom_fee }}" class="panel-input" id="pt_custom_fee_{{ $selectedMembership->id }}" @disabled(! $canEdit)>
                            </div>
                            <div>
                                <label class="panel-label">Due Date</label>
                                <input type="date" name="due_date" value="{{ optional($selectedMembership->due_date)->toDateString() }}" class="panel-input" @disabled(! $canEdit)>
                            </div>

                            <div class="md:col-span-2 panel-card-muted p-4">
                                <label class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-950 dark:text-white">Joining fee waiver</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Waive joining fee only when this cycle specifically qualifies.</p>
                                    </div>
                                    <input type="hidden" name="joining_fee_waived" value="0">
                                    <input
                                        type="checkbox"
                                        name="joining_fee_waived"
                                        value="1"
                                        class="h-5 w-5 rounded border-slate-300 bg-white text-sky-500 dark:border-white/20 dark:bg-slate-900"
                                        @checked($selectedMembership->joining_fee_waived)
                                        id="joining_fee_waived_{{ $selectedMembership->id }}"
                                        @disabled(! $canEdit)
                                    >
                                </label>
                            </div>

                            <div class="md:col-span-2">
                                <label class="panel-label">Reason</label>
                                <textarea name="custom_fee_reason" class="panel-textarea" required @disabled(! $canEdit)>{{ $selectedMembership->custom_fee_reason }}</textarea>
                            </div>

                            <div class="md:col-span-2 grid gap-4 xl:grid-cols-4">
                                <div class="panel-card-muted p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Original Price</p>
                                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->default_plan_price, 2) }}</p>
                                </div>
                                <div class="panel-card-muted p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Final Payable</p>
                                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white" id="final_payable_preview_{{ $selectedMembership->id }}">{{ number_format((float) $selectedMembership->final_payable_amount, 2) }}</p>
                                </div>
                                <div class="panel-card-muted p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Paid</p>
                                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->amount_paid, 2) }}</p>
                                </div>
                                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-100">Due Amount</p>
                                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white" id="due_amount_preview_{{ $selectedMembership->id }}">{{ number_format((float) $selectedMembership->due_amount, 2) }}</p>
                                </div>
                            </div>

                            <div class="md:col-span-2 flex flex-wrap gap-3">
                                <button class="panel-btn-primary" @disabled(! $canEdit)>{{ $canEdit ? 'Update Custom Fee' : 'Edit Disabled' }}</button>
                                @unless($canEdit)
                                    <x-status-badge label="You have view access, but not edit_custom_fee permission." tone="warning" />
                                @endunless
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="panel-card p-6">
                        <h3 class="panel-section-title">Billing Snapshot</h3>
                        <div class="mt-5 space-y-3">
                            <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                                <span class="text-slate-600 dark:text-slate-300">Final payable</span>
                                <span class="font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->final_payable_amount, 2) }}</span>
                            </div>
                            <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                                <span class="text-slate-600 dark:text-slate-300">Amount paid</span>
                                <span class="font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->amount_paid, 2) }}</span>
                            </div>
                            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-amber-700 dark:text-amber-100">Due amount</span>
                                    <span class="font-semibold text-slate-950 dark:text-white">{{ number_format((float) $selectedMembership->due_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-card p-6">
                        <h3 class="panel-section-title">Audit Log Timeline</h3>
                        <p class="panel-section-copy">Every custom-fee change for this cycle is recorded here.</p>
                        <div class="mt-5 panel-timeline">
                            <x-web.audit-timeline
                                :items="$selectedMembership->custom_fee_timeline ?? []"
                                empty-title="No audit logs yet"
                                empty-message="Custom pricing changes will appear here."
                            />
                        </div>
                    </div>

                    @if ($historicalMemberships->isNotEmpty())
                        <div class="panel-card p-6">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="panel-section-title">Membership History</h3>
                                    <p class="panel-section-copy">Older cycles stay visible, but only one cycle is active in the pricing desk.</p>
                                </div>
                                <x-status-badge :label="$historicalMemberships->count().' older cycles'" tone="neutral" />
                            </div>
                            <div class="mt-5 space-y-3">
                                @foreach ($historicalMemberships as $membership)
                                    <div class="panel-card-muted rounded-2xl p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-slate-950 dark:text-white">{{ $membership->membershipPlan?->name ?? 'Membership' }} • #{{ $membership->id }}</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                    {{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}
                                                </p>
                                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ ucfirst((string) $membership->status) }} • {{ ucfirst((string) $membership->payment_status) }} • Due {{ number_format((float) $membership->due_amount, 2) }}
                                                </p>
                                            </div>
                                            <a href="{{ route('web.gym.members.custom-fee', ['member' => $member->id, 'member_membership_id' => $membership->id] + request()->query()) }}" class="panel-btn-secondary">Open</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <x-web.empty-state title="No memberships available" message="Assign a membership before editing custom pricing." />
        @endif
    </div>

    @if ($selectedMembership)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const id = '{{ $selectedMembership->id }}';
                const defaultPlanPrice = {{ (float) $selectedMembership->default_plan_price }};
                const defaultJoiningFee = {{ (float) $selectedMembership->default_joining_fee }};
                const amountPaid = {{ (float) $selectedMembership->amount_paid }};
                const fields = {
                    customFeeEnabled: document.getElementById(`custom_fee_enabled_${id}`),
                    customFeeAmount: document.getElementById(`custom_fee_amount_${id}`),
                    discountType: document.getElementById(`discount_type_${id}`),
                    discountAmount: document.getElementById(`discount_amount_${id}`),
                    customJoiningFee: document.getElementById(`custom_joining_fee_${id}`),
                    partialMonthFee: document.getElementById(`partial_month_fee_${id}`),
                    ptCustomFee: document.getElementById(`pt_custom_fee_${id}`),
                    joiningFeeWaived: document.getElementById(`joining_fee_waived_${id}`),
                    finalPayable: document.getElementById(`final_payable_preview_${id}`),
                    dueAmount: document.getElementById(`due_amount_preview_${id}`),
                };

                const parseNumber = (value) => Number.parseFloat(value || '0') || 0;
                const update = () => {
                    const customFeeEnabled = fields.customFeeEnabled?.checked === true;
                    const customFeeAmount = parseNumber(fields.customFeeAmount?.value);
                    const discountType = fields.discountType?.value || 'none';
                    const discountAmount = parseNumber(fields.discountAmount?.value);
                    const customJoiningFee = parseNumber(fields.customJoiningFee?.value);
                    const partialMonthFee = parseNumber(fields.partialMonthFee?.value);
                    const ptCustomFee = parseNumber(fields.ptCustomFee?.value);
                    const joiningFeeWaived = fields.joiningFeeWaived?.checked === true;

                    const basePrice = customFeeEnabled && customFeeAmount > 0 ? customFeeAmount : defaultPlanPrice;
                    const percentageDiscount = discountType === 'percentage' ? (basePrice * (discountAmount / 100)) : 0;
                    const fixedDiscount = discountType === 'fixed' ? discountAmount : 0;
                    const joiningFee = joiningFeeWaived ? 0 : (customJoiningFee > 0 ? customJoiningFee : defaultJoiningFee);
                    const finalPayable = Math.max(0, basePrice - percentageDiscount - fixedDiscount + joiningFee + partialMonthFee + ptCustomFee);
                    const dueAmount = Math.max(0, finalPayable - amountPaid);

                    if (fields.finalPayable) {
                        fields.finalPayable.textContent = finalPayable.toFixed(2);
                    }

                    if (fields.dueAmount) {
                        fields.dueAmount.textContent = dueAmount.toFixed(2);
                    }
                };

                Object.values(fields).forEach((field) => {
                    if (field && 'addEventListener' in field) {
                        field.addEventListener('input', update);
                        field.addEventListener('change', update);
                    }
                });

                update();
            });
        </script>
    @endif
@endsection
