@extends('layouts.panel')

@section('content')
    @php
        $requestedMembershipId = (int) old('member_membership_id', request('member_membership_id'));
        $selectedMembership = $requestedMembershipId > 0
            ? $memberships->firstWhere('id', $requestedMembershipId)
            : null;
        $scopeQuery = request()->only(['gym', 'branch']);
        $showExtraDetails = $errors->hasAny(['payment_date', 'external_reference', 'notes', 'allow_overpayment'])
            || filled(old('payment_date')) || filled(old('external_reference')) || filled(old('notes')) || (bool) old('allow_overpayment');
    @endphp

    <div class="space-y-5">
        <section class="panel-card px-5 py-5 sm:px-6" aria-labelledby="collection-page-title">
            <a href="{{ route('web.gym.payments.index', $scopeQuery) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"><i class="ti ti-arrow-left" aria-hidden="true"></i> Payments</a>
            <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="collection-page-title" class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Record a payment</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose an open membership, confirm the amount, then save the receipt.</p>
                </div>
                @if ($selectedMemberId)
                    <a href="{{ route('web.gym.payments.create', $scopeQuery) }}" class="panel-btn-secondary">Show all members</a>
                @endif
            </div>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ $memberships->count() }} open {{ $memberships->count() === 1 ? 'membership' : 'memberships' }} · ₹{{ number_format((float) $memberships->sum('due_amount'), 2) }} to collect</p>
        </section>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" role="alert">
                <p class="font-semibold">Review the payment details</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($requestedMembershipId > 0 && ! $selectedMembership)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" role="status">That membership has no open balance in this scope. Choose another membership below.</div>
        @endif

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(300px,360px)]">
            <section class="panel-card p-5 sm:p-6" aria-labelledby="payment-form-title">
                <h3 id="payment-form-title" class="panel-section-title">Payment details</h3>
                @if ($memberships->isEmpty())
                    <div class="mt-5"><x-web.empty-state title="No open memberships" message="There are no unpaid, partial, or overdue memberships to collect from in this scope." /></div>
                @else
                    <form id="collect-payment-form" action="{{ route('web.gym.payments.store', $scopeQuery) }}" method="POST" class="mt-5 space-y-6">
                        @csrf
                        <div>
                            <label for="member_membership_id" class="panel-label">Membership to collect from</label>
                            <select id="member_membership_id" name="member_membership_id" class="panel-select" required>
                                @if (! $selectedMembership)<option value="" selected>Choose a membership</option>@endif
                                @foreach ($memberships as $membership)
                                    <option value="{{ $membership->id }}" data-due="{{ number_format((float) $membership->due_amount, 2, '.', '') }}" data-member="{{ $membership->member?->name ?? 'Member' }}" data-plan="{{ $membership->membershipPlan?->name ?? 'Membership' }}" data-branch="{{ $membership->branch?->name ?? 'Branch' }}" data-status="{{ ucfirst((string) $membership->payment_status) }}" @selected($selectedMembership && (int) $membership->id === (int) $selectedMembership->id)>{{ $membership->member?->name ?? 'Member' }} · {{ $membership->membershipPlan?->name ?? 'Membership' }} · #{{ $membership->id }} · ₹{{ number_format((float) $membership->due_amount, 2) }} due</option>
                                @endforeach
                            </select>
                            @error('member_membership_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div id="selected-membership-summary" @class(['rounded-xl border border-brand-200 bg-brand-50/70 px-4 py-4 dark:border-brand-500/20 dark:bg-brand-500/10', 'hidden' => ! $selectedMembership]) aria-live="polite">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p id="selected-member" class="font-semibold text-slate-950 dark:text-white">{{ $selectedMembership?->member?->name ?? 'Choose a membership above' }}</p>
                                    <p id="selected-plan" class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $selectedMembership?->membershipPlan?->name ?? '' }} @if($selectedMembership) · #{{ $selectedMembership->id }} · {{ $selectedMembership->branch?->name ?? 'Branch' }} @endif</p>
                                </div>
                                <div class="text-left sm:text-right"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Current due</p><p id="selected-due" class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) ($selectedMembership?->due_amount ?? 0), 2) }}</p></div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="payment_amount" class="panel-label">Amount received (₹)</label>
                                <input id="payment_amount" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01" value="{{ old('amount', $selectedMembership ? number_format((float) $selectedMembership->due_amount, 2, '.', '') : '') }}" class="panel-input" required>
                                @error('amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="payment_mode" class="panel-label">Payment mode</label>
                                <select id="payment_mode" name="payment_mode" class="panel-select" required>
                                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_mode', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('payment_mode')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div id="balance-preview" @class(['flex flex-wrap items-center justify-between gap-3 border-y border-slate-200 py-4 text-sm dark:border-slate-800', 'hidden' => ! $selectedMembership])>
                            <span id="remaining-label" class="font-medium text-slate-600 dark:text-slate-300">Balance after payment</span>
                            <strong id="remaining-amount" class="text-lg font-semibold text-slate-950 dark:text-white">₹0.00</strong>
                        </div>
                        <p id="payment-warning" class="hidden text-sm text-rose-600" role="alert"></p>

                        <details id="payment-extra-details" class="rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-950/40" @if($showExtraDetails) open @endif>
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Date, reference, notes, and overpayment</summary>
                            <div class="grid gap-4 border-t border-slate-200 px-4 py-4 dark:border-slate-800 sm:grid-cols-2">
                                <div>
                                    <label for="payment_date" class="panel-label">Payment date and time</label>
                                    <input id="payment_date" name="payment_date" type="datetime-local" value="{{ old('payment_date') }}" class="panel-input">
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to use the current time.</p>
                                </div>
                                <div>
                                    <label for="external_reference" class="panel-label">External reference</label>
                                    <input id="external_reference" name="external_reference" type="text" value="{{ old('external_reference') }}" class="panel-input" placeholder="Transaction or bank reference">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="payment_notes" class="panel-label">Notes</label>
                                    <textarea id="payment_notes" name="notes" rows="2" class="panel-textarea" placeholder="Optional collection notes">{{ old('notes') }}</textarea>
                                </div>
                                <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2"><input type="hidden" name="allow_overpayment" value="0"><input id="allow_overpayment" type="checkbox" name="allow_overpayment" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600" @checked((bool) old('allow_overpayment'))><span>Allow an amount above the current due</span></label>
                            </div>
                        </details>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Saving records the payment and creates a receipt.</p>
                            <button id="record-payment-button" type="submit" class="panel-btn-primary min-h-11 disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $selectedMembership)>Record payment</button>
                        </div>
                    </form>
                @endif
            </section>

            @if ($memberships->isNotEmpty())
                <aside class="panel-card p-5 sm:p-6" aria-labelledby="open-balances-title">
                    <h3 id="open-balances-title" class="panel-section-title">Open balances</h3>
                    <p class="panel-section-copy">Choose the membership cycle you are collecting for.</p>
                    <div class="mt-4 space-y-2">
                        @foreach ($memberships as $membership)
                            <a href="{{ route('web.gym.payments.create', array_merge($scopeQuery, array_filter(['member_id' => $selectedMemberId]), ['member_membership_id' => $membership->id])) }}" @class(['block rounded-xl border px-4 py-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:hover:bg-brand-500/10', 'border-brand-300 bg-brand-50/70 dark:border-brand-500/40 dark:bg-brand-500/10' => $selectedMembership && (int) $selectedMembership->id === (int) $membership->id, 'border-slate-200 dark:border-slate-800' => ! $selectedMembership || (int) $selectedMembership->id !== (int) $membership->id])>
                                <span class="flex items-start justify-between gap-3"><span class="min-w-0"><span class="block font-semibold text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</span><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $membership->membershipPlan?->name ?? 'Membership' }} · #{{ $membership->id }}</span></span><span class="shrink-0 font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $membership->due_amount, 2) }}</span></span>
                                <span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'Branch' }} · {{ ucfirst((string) $membership->payment_status) }} · Due {{ optional($membership->due_date)->format('d M Y') ?: 'date not set' }}</span>
                            </a>
                        @endforeach
                    </div>
                </aside>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.panelOnLoad(() => {
            const form = document.getElementById('collect-payment-form');
            if (!form) return;
            const select = document.getElementById('member_membership_id');
            const amount = document.getElementById('payment_amount');
            const overpayment = document.getElementById('allow_overpayment');
            const extras = document.getElementById('payment-extra-details');
            const warning = document.getElementById('payment-warning');
            const save = document.getElementById('record-payment-button');
            const money = value => `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const update = () => {
                const option = select.selectedOptions[0];
                const due = Number(option?.dataset.due || 0);
                const paid = Number(amount.value || 0);
                const remaining = Math.round((due - paid) * 100) / 100;
                const exceedsDue = option?.value && paid > due;
                document.getElementById('selected-membership-summary').classList.toggle('hidden', !option?.value);
                document.getElementById('balance-preview').classList.toggle('hidden', !option?.value);
                document.getElementById('selected-member').textContent = option?.dataset.member || 'Choose a membership above';
                document.getElementById('selected-plan').textContent = option?.value ? `${option.dataset.plan} · #${option.value} · ${option.dataset.branch}` : '';
                document.getElementById('selected-due').textContent = money(due);
                document.getElementById('remaining-label').textContent = remaining < 0 ? 'Credit after payment' : 'Balance after payment';
                document.getElementById('remaining-amount').textContent = money(Math.abs(remaining));
                if (exceedsDue) extras.open = true;
                const blocked = exceedsDue && !overpayment.checked;
                warning.textContent = blocked ? 'This amount exceeds the current due. Confirm overpayment below to continue.' : '';
                warning.classList.toggle('hidden', !blocked);
                save.disabled = !option?.value || blocked;
            };
            select.addEventListener('change', () => {
                amount.value = select.selectedOptions[0]?.dataset.due || '';
                overpayment.checked = false;
                update();
            });
            amount.addEventListener('input', update);
            overpayment.addEventListener('change', update);
            update();
        });
    </script>
@endpush
