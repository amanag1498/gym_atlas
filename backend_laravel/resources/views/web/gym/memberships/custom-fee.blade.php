@extends('layouts.panel')

@section('content')
    @php
        $selectedMembership = $memberships->firstWhere('id', $selectedMembershipId) ?? $memberships->first();
        $canEdit = $canEditCustomFee === true;
        $customFeeEnabled = (bool) old('custom_fee_enabled', $selectedMembership?->custom_fee_enabled ?? false);
        $joiningFeeWaived = (bool) old('joining_fee_waived', $selectedMembership?->joining_fee_waived ?? false);
        $discountType = old('discount_type', $selectedMembership?->discount_type ?? 'none');
        $timeline = $selectedMembership?->custom_fee_timeline ?? [];
        $hasAdjustments = $selectedMembership && (
            $joiningFeeWaived
            || (float) old('custom_joining_fee', $selectedMembership->custom_joining_fee) !== (float) $selectedMembership->default_joining_fee
            || (float) old('partial_month_fee', $selectedMembership->partial_month_fee) > 0
            || (float) old('pt_custom_fee', $selectedMembership->pt_custom_fee) > 0
            || $errors->hasAny(['custom_joining_fee', 'partial_month_fee', 'pt_custom_fee'])
        );
    @endphp

    @if ($selectedMembership)
        <div class="space-y-5">
            <section class="panel-card px-5 py-5 sm:px-6" aria-labelledby="pricing-member-title">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <a href="{{ route('web.gym.members.show', ['member' => $member->id] + request()->only(['gym', 'branch'])) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"><i class="ti ti-arrow-left" aria-hidden="true"></i> Member profile</a>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <h2 id="pricing-member-title" class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $member->name }}</h2>
                            <x-status-badge :label="ucfirst((string) $selectedMembership->status)" :tone="match((string) $selectedMembership->status) { 'active' => 'success', 'frozen' => 'warning', 'expired' => 'neutral', 'cancelled' => 'danger', default => 'neutral' }" />
                        </div>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $selectedMembership->membershipPlan?->name ?? 'Membership' }} · {{ optional($selectedMembership->start_date)->format('d M Y') ?: 'No start date' }} – {{ optional($selectedMembership->expiry_date)->format('d M Y') ?: 'No end date' }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $selectedMembership->branch?->name ?? 'Unassigned branch' }} · Membership #{{ $selectedMembership->id }}</p>
                    </div>
                    @if ($memberships->count() > 1)
                        <form method="GET" action="{{ route('web.gym.members.custom-fee', ['member' => $member->id]) }}" class="flex w-full flex-col gap-2 sm:flex-row sm:items-end lg:w-auto">
                            @foreach (request()->only(['gym', 'branch']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <div class="min-w-0 flex-1 lg:min-w-64">
                                <label for="member_membership_select" class="panel-label">Membership cycle</label>
                                <select id="member_membership_select" name="member_membership_id" class="panel-select">
                                    @foreach ($memberships as $membership)
                                        <option value="{{ $membership->id }}" @selected((int) $membership->id === (int) $selectedMembership->id)>{{ $membership->membershipPlan?->name ?? 'Membership' }} · #{{ $membership->id }} · {{ optional($membership->start_date)->format('d M Y') ?: 'No start date' }} · {{ ucfirst((string) $membership->status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="panel-btn-secondary min-h-10">View cycle</button>
                        </form>
                    @endif
                </div>
            </section>

            <form id="custom-fee-form-{{ $selectedMembership->id }}" action="{{ route('web.gym.members.custom-fee.update', ['member' => $member->id] + request()->only(['gym', 'branch'])) }}" method="POST" class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(320px,360px)]" data-default-plan-price="{{ (float) $selectedMembership->default_plan_price }}" data-amount-paid="{{ (float) $selectedMembership->amount_paid }}">
                @csrf
                <input type="hidden" name="member_membership_id" value="{{ $selectedMembership->id }}">
                <section class="panel-card p-5 sm:p-6" aria-labelledby="price-editor-title">
                    <div class="border-b border-slate-200 pb-5 dark:border-slate-800">
                        <h3 id="price-editor-title" class="panel-section-title">Edit this cycle’s pricing</h3>
                        <p class="panel-section-copy">Changes apply only to this membership. The plan’s standard price stays the same.</p>
                    </div>
                    <div class="space-y-6 pt-6">
                        <fieldset class="space-y-4">
                            <legend class="text-sm font-semibold text-slate-950 dark:text-white">Membership price</legend>
                            <label class="panel-card-muted flex cursor-pointer items-center justify-between gap-4 p-4">
                                <span><span class="block font-medium text-slate-950 dark:text-white">Set a price for this member</span><span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">Standard price: ₹{{ number_format((float) $selectedMembership->default_plan_price, 2) }}</span></span>
                                <input type="hidden" name="custom_fee_enabled" value="0">
                                <input id="custom_fee_enabled_{{ $selectedMembership->id }}" type="checkbox" name="custom_fee_enabled" value="1" class="h-5 w-5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked($customFeeEnabled) @disabled(! $canEdit)>
                            </label>
                            <div id="custom_amount_group_{{ $selectedMembership->id }}" @class(['max-w-sm', 'hidden' => ! $customFeeEnabled])>
                                <label for="custom_fee_amount_{{ $selectedMembership->id }}" class="panel-label">Member price (₹)</label>
                                <input id="custom_fee_amount_{{ $selectedMembership->id }}" name="custom_fee_amount" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('custom_fee_amount', $selectedMembership->custom_fee_amount) }}" class="panel-input" @disabled(! $canEdit)>
                                @error('custom_fee_amount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </fieldset>
                        <fieldset class="space-y-4 border-t border-slate-200 pt-6 dark:border-slate-800">
                            <legend class="text-sm font-semibold text-slate-950 dark:text-white">Discount</legend>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="discount_type_{{ $selectedMembership->id }}" class="panel-label">Discount type</label>
                                    <select id="discount_type_{{ $selectedMembership->id }}" name="discount_type" class="panel-select" @disabled(! $canEdit)>
                                        <option value="none" @selected($discountType === 'none')>No discount</option>
                                        <option value="fixed" @selected($discountType === 'fixed')>Fixed amount</option>
                                        <option value="percentage" @selected($discountType === 'percentage')>Percentage</option>
                                    </select>
                                </div>
                                <div id="discount_amount_group_{{ $selectedMembership->id }}" @class(['max-w-sm', 'hidden' => $discountType === 'none'])>
                                    <label for="discount_amount_{{ $selectedMembership->id }}" class="panel-label">Discount amount <span id="discount_unit_{{ $selectedMembership->id }}">{{ $discountType === 'percentage' ? '(%)' : '(₹)' }}</span></label>
                                    <input id="discount_amount_{{ $selectedMembership->id }}" name="discount_amount" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('discount_amount', $selectedMembership->discount_amount) }}" class="panel-input" @disabled(! $canEdit)>
                                    @error('discount_amount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </fieldset>
                        <details class="rounded-2xl border border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-950/40" @if($hasAdjustments) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 text-sm font-semibold text-slate-950 marker:hidden dark:text-white"><span>Joining fee and other charges <span class="ml-1 font-normal text-slate-500 dark:text-slate-400">(optional)</span></span><i class="ti ti-chevron-down text-slate-500" aria-hidden="true"></i></summary>
                            <div class="grid gap-4 border-t border-slate-200 px-4 py-5 dark:border-slate-800 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-800 dark:text-slate-200"><input type="hidden" name="joining_fee_waived" value="0"><input id="joining_fee_waived_{{ $selectedMembership->id }}" type="checkbox" name="joining_fee_waived" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked($joiningFeeWaived) @disabled(! $canEdit)> Waive the joining fee</label>
                                </div>
                                <div>
                                    <label for="custom_joining_fee_{{ $selectedMembership->id }}" class="panel-label">Joining fee (₹)</label>
                                    <input id="custom_joining_fee_{{ $selectedMembership->id }}" name="custom_joining_fee" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('custom_joining_fee', $selectedMembership->custom_joining_fee) }}" class="panel-input" @disabled(! $canEdit)>
                                    <p class="mt-1 text-xs text-slate-500">Standard: ₹{{ number_format((float) $selectedMembership->default_joining_fee, 2) }}</p>
                                    @error('custom_joining_fee') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="partial_month_fee_{{ $selectedMembership->id }}" class="panel-label">Partial month fee (₹)</label>
                                    <input id="partial_month_fee_{{ $selectedMembership->id }}" name="partial_month_fee" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('partial_month_fee', $selectedMembership->partial_month_fee) }}" class="panel-input" @disabled(! $canEdit)>
                                    @error('partial_month_fee') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="pt_custom_fee_{{ $selectedMembership->id }}" class="panel-label">Personal training fee (₹)</label>
                                    <input id="pt_custom_fee_{{ $selectedMembership->id }}" name="pt_custom_fee" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('pt_custom_fee', $selectedMembership->pt_custom_fee) }}" class="panel-input" @disabled(! $canEdit)>
                                    @error('pt_custom_fee') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </details>
                        <div class="grid gap-4 border-t border-slate-200 pt-6 dark:border-slate-800 sm:grid-cols-2">
                            <div>
                                <label for="due_date_{{ $selectedMembership->id }}" class="panel-label">Payment due date</label>
                                <input id="due_date_{{ $selectedMembership->id }}" type="date" name="due_date" value="{{ old('due_date', optional($selectedMembership->due_date)->toDateString()) }}" class="panel-input" @disabled(! $canEdit)>
                                @error('due_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="custom_fee_reason_{{ $selectedMembership->id }}" class="panel-label">Reason for change</label>
                                <textarea id="custom_fee_reason_{{ $selectedMembership->id }}" name="custom_fee_reason" rows="3" class="panel-textarea" placeholder="For example, retention offer or approved fee adjustment" @if($canEdit) required @endif @disabled(! $canEdit)>{{ old('custom_fee_reason', $selectedMembership->custom_fee_reason) }}</textarea>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This reason is saved in the pricing history.</p>
                                @error('custom_fee_reason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </section>
                <aside class="panel-card p-5 sm:p-6 xl:sticky xl:top-24" aria-labelledby="pricing-preview-title">
                    <h3 id="pricing-preview-title" class="panel-section-title">Price preview</h3>
                    <p class="panel-section-copy">Updates as you edit. Nothing changes until you save.</p>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">Membership price</dt><dd id="base_price_preview_{{ $selectedMembership->id }}" class="font-medium text-slate-950 dark:text-white">₹{{ number_format((float) $selectedMembership->default_plan_price, 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">Discount</dt><dd id="discount_preview_{{ $selectedMembership->id }}" class="font-medium text-slate-950 dark:text-white">−₹0.00</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">Joining fee</dt><dd id="joining_preview_{{ $selectedMembership->id }}" class="font-medium text-slate-950 dark:text-white">₹{{ number_format((float) $selectedMembership->default_joining_fee, 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">Other charges</dt><dd id="extra_preview_{{ $selectedMembership->id }}" class="font-medium text-slate-950 dark:text-white">₹0.00</dd></div>
                    </dl>
                    <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                        <div class="flex items-baseline justify-between gap-3"><span class="font-medium text-slate-700 dark:text-slate-200">Final payable</span><strong id="final_payable_preview_{{ $selectedMembership->id }}" class="text-2xl font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $selectedMembership->final_payable_amount, 2) }}</strong></div>
                        <div class="mt-3 flex justify-between gap-3 text-sm"><span class="text-slate-600 dark:text-slate-300">Already paid</span><span>₹{{ number_format((float) $selectedMembership->amount_paid, 2) }}</span></div>
                        <div class="mt-4 flex items-baseline justify-between gap-3 rounded-xl bg-brand-50 px-4 py-3 dark:bg-brand-500/10"><span id="balance_label_{{ $selectedMembership->id }}" class="font-medium text-brand-700 dark:text-brand-300">{{ (float) $selectedMembership->due_amount < 0 ? 'Credit balance' : 'Amount due' }}</span><strong id="balance_preview_{{ $selectedMembership->id }}" class="text-xl font-semibold text-slate-950 dark:text-white">₹{{ number_format(abs((float) $selectedMembership->due_amount), 2) }}</strong></div>
                    </div>
                    <p id="pricing_error_{{ $selectedMembership->id }}" class="mt-4 hidden text-sm text-rose-600" role="alert"></p>
                    <div class="mt-5 space-y-2">
                        <button type="submit" class="panel-btn-primary min-h-11 w-full text-sm disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $canEdit)>{{ $canEdit ? 'Save pricing change' : 'View only' }}</button>
                        @unless ($canEdit)
                            <p class="text-xs text-amber-700 dark:text-amber-300">Your account can view pricing, but cannot change it.</p>
                        @else
                            <p class="text-xs text-slate-500 dark:text-slate-400">Saving recalculates the balance and records this change.</p>
                        @endunless
                    </div>
                </aside>
            </form>
            <details class="panel-card px-5 py-4 sm:px-6">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-950 marker:hidden dark:text-white"><span>Pricing history <span class="ml-1 text-sm font-normal text-slate-500 dark:text-slate-400">{{ count($timeline) }} {{ count($timeline) === 1 ? 'change' : 'changes' }}</span></span><i class="ti ti-chevron-down text-slate-500" aria-hidden="true"></i></summary>
                <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800"><x-web.audit-timeline :items="$timeline" empty-title="No pricing changes yet" empty-message="Saved changes for this cycle will appear here." /></div>
            </details>
        </div>
    @else
        <x-web.empty-state title="No memberships available" message="Assign a membership before editing custom pricing." />
    @endif

    @if ($selectedMembership)
        <script>
            window.panelOnLoad(() => {
                const id = @json((string) $selectedMembership->id);
                const form = document.getElementById(`custom-fee-form-${id}`);
                const money = (amount) => `₹${amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                const number = (value) => Number.parseFloat(value || '0') || 0;
                const defaultPlanPrice = Number(form.dataset.defaultPlanPrice);
                const amountPaid = Number(form.dataset.amountPaid);
                const fields = {
                    customFeeEnabled: document.getElementById(`custom_fee_enabled_${id}`),
                    customFeeAmount: document.getElementById(`custom_fee_amount_${id}`),
                    customAmountGroup: document.getElementById(`custom_amount_group_${id}`),
                    discountType: document.getElementById(`discount_type_${id}`),
                    discountAmount: document.getElementById(`discount_amount_${id}`),
                    discountAmountGroup: document.getElementById(`discount_amount_group_${id}`),
                    discountUnit: document.getElementById(`discount_unit_${id}`),
                    customJoiningFee: document.getElementById(`custom_joining_fee_${id}`),
                    partialMonthFee: document.getElementById(`partial_month_fee_${id}`),
                    ptCustomFee: document.getElementById(`pt_custom_fee_${id}`),
                    joiningFeeWaived: document.getElementById(`joining_fee_waived_${id}`),
                    basePrice: document.getElementById(`base_price_preview_${id}`),
                    discount: document.getElementById(`discount_preview_${id}`),
                    joining: document.getElementById(`joining_preview_${id}`),
                    extra: document.getElementById(`extra_preview_${id}`),
                    finalPayable: document.getElementById(`final_payable_preview_${id}`),
                    balanceLabel: document.getElementById(`balance_label_${id}`),
                    balance: document.getElementById(`balance_preview_${id}`),
                    error: document.getElementById(`pricing_error_${id}`),
                    save: form.querySelector('button[type="submit"]'),
                };
                const update = () => {
                    const customFeeEnabled = fields.customFeeEnabled.checked;
                    const discountType = fields.discountType.value;
                    const basePrice = customFeeEnabled ? number(fields.customFeeAmount.value) : defaultPlanPrice;
                    const discountAmount = number(fields.discountAmount.value);
                    const discount = discountType === 'percentage' ? Math.round(basePrice * discountAmount) / 100 : (discountType === 'fixed' ? discountAmount : 0);
                    const joiningFee = fields.joiningFeeWaived.checked ? 0 : number(fields.customJoiningFee.value);
                    const extra = number(fields.partialMonthFee.value) + number(fields.ptCustomFee.value);
                    const finalPayable = Math.round((basePrice - discount + joiningFee + extra) * 100) / 100;
                    const balance = Math.round((finalPayable - amountPaid) * 100) / 100;
                    const invalidDiscount = discount > basePrice;
                    fields.customAmountGroup.classList.toggle('hidden', !customFeeEnabled);
                    fields.customFeeAmount.required = customFeeEnabled && !fields.customFeeAmount.disabled;
                    fields.discountAmountGroup.classList.toggle('hidden', discountType === 'none');
                    if (discountType === 'none' && fields.discountAmount.value !== '0') fields.discountAmount.value = '0';
                    fields.discountUnit.textContent = discountType === 'percentage' ? '(%)' : '(₹)';
                    fields.discountAmount.max = discountType === 'percentage' ? '100' : '';
                    fields.customJoiningFee.readOnly = fields.joiningFeeWaived.checked;
                    fields.customJoiningFee.classList.toggle('opacity-50', fields.joiningFeeWaived.checked);
                    fields.basePrice.textContent = money(basePrice);
                    fields.discount.textContent = `−${money(discount)}`;
                    fields.joining.textContent = money(joiningFee);
                    fields.extra.textContent = money(extra);
                    fields.finalPayable.textContent = money(finalPayable);
                    fields.balanceLabel.textContent = balance < 0 ? 'Credit balance' : 'Amount due';
                    fields.balance.textContent = money(Math.abs(balance));
                    fields.error.textContent = invalidDiscount ? 'The discount cannot exceed the membership price.' : '';
                    fields.error.classList.toggle('hidden', !invalidDiscount);
                    fields.discountAmount.setCustomValidity(invalidDiscount ? 'Discount exceeds the membership price.' : '');
                    if (fields.save && @json($canEdit)) fields.save.disabled = invalidDiscount;
                };
                form.addEventListener('input', update);
                form.addEventListener('change', update);
                update();
            });
        </script>
    @endif
@endsection
