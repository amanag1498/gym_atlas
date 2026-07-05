@extends('layouts.panel')

@section('content')
    @php
        $latestPayment = $membership->payments->where('status', 'recorded')->sortByDesc('paid_at')->first();
        $paymentModes = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank'];
        $isCancelled = $membership->status === 'cancelled';
        $isFrozen = $membership->status === 'frozen';
        $hasDue = (float) $membership->due_amount > 0;
        $hasCredit = (float) $membership->due_amount < 0;
        $creditAmount = abs((float) $membership->due_amount);
        $plan = $membership->membershipPlan;
        $focusLifecycle = request('flow') === 'lifecycle';
        $renewalStartDate = optional($membership->expiry_date)->copy()?->addDay() ?? now();
        $renewalProjectedEnd = $renewalStartDate->copy()->addDays((int) ($plan?->duration_days ?? 0));
        $overviewHref = route('web.gym.memberships.show', ['membership' => $membership->id] + request()->except('flow'));
        $workspaceHref = route('web.gym.memberships.show', ['membership' => $membership->id] + request()->query() + ['flow' => 'lifecycle']);
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-[30px] border border-slate-200/80 bg-white shadow-[0_30px_90px_-55px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950">
            <div class="grid gap-6 border-b border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 px-5 py-6 text-white dark:border-slate-800 lg:grid-cols-[minmax(0,1.15fr)_360px] lg:px-6">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        {{ $focusLifecycle ? 'Membership Action Workspace' : 'Membership Detail Record' }}
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $plan?->name ?? 'Membership' }} #{{ $membership->id }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                        {{ $membership->member?->name ?? 'Member' }} · {{ $membership->branch?->name ?? 'Gym-wide' }} ·
                        @if ($isCancelled)
                            archived cycle with history preserved
                        @elseif ($isFrozen)
                            paused cycle awaiting reactivation
                        @elseif ($hasDue)
                            active cycle with outstanding due
                        @elseif ($hasCredit)
                            active cycle with member credit on account
                        @else
                            active cycle with billing aligned
                        @endif
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-status-badge :label="ucfirst((string) $membership->status)" :tone="$isCancelled ? 'danger' : ($isFrozen ? 'warning' : 'success')" />
                        <x-status-badge :label="ucfirst((string) $membership->payment_status)" :tone="$hasDue ? 'warning' : 'info'" />
                        <x-status-badge :label="'Cycle '.optional($membership->start_date)->format('d M').' - '.optional($membership->expiry_date)->format('d M')" tone="neutral" />
                        @if ($membership->joining_fee_waived)
                            <x-status-badge label="Joining waived on this cycle" tone="success" />
                        @endif
                    </div>
                    <div class="mt-5 inline-flex rounded-2xl border border-white/10 bg-slate-950/40 p-1">
                        <a href="{{ $overviewHref }}" class="{{ $focusLifecycle ? 'text-slate-300' : 'bg-white text-slate-950' }} rounded-xl px-3 py-2 text-sm font-medium transition">Detail</a>
                        <a href="{{ $workspaceHref }}" class="{{ $focusLifecycle ? 'bg-white text-slate-950' : 'text-slate-300' }} rounded-xl px-3 py-2 text-sm font-medium transition">Workspace</a>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Billing checkpoint</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</div>
                        <p class="mt-1 text-sm text-slate-300">Due date now tracks the cycle end unless you override it.</p>
                    </div>
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Collected vs payable</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">₹{{ number_format((float) $membership->amount_paid, 2) }} / ₹{{ number_format((float) $membership->final_payable_amount, 2) }}</div>
                        <p class="mt-1 text-sm text-slate-300">
                            @if ($hasCredit)
                                Credit balance ₹{{ number_format($creditAmount, 2) }}
                            @else
                                Outstanding ₹{{ number_format((float) $membership->due_amount, 2) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:px-6">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Start</p>
                        <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">End</p>
                        <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Due</p>
                        <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Next renewal end</p>
                        <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ $renewalProjectedEnd->format('d M Y') }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 lg:justify-end">
                    <a href="{{ route('web.gym.memberships.index', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary">Back to Memberships</a>
                    @if ($membership->member_id)
                        <a href="{{ route('web.gym.members.show', ['member' => $membership->member_id] + request()->query()) }}" class="panel-btn-secondary">Member Profile</a>
                    @endif
                    @if ($canEditCustomFee)
                        <a href="{{ route('web.gym.members.custom-fee', ['member' => $membership->member_id, 'member_membership_id' => $membership->id] + request()->query()) }}" class="panel-btn-primary">Custom Fee</a>
                    @endif
                    @if ($canCollectPayments && $hasDue)
                        <a href="{{ route('web.gym.payments.create', ['member_membership_id' => $membership->id] + request()->query()) }}" class="panel-btn-primary">Collect Payment</a>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Lifecycle" :value="ucfirst((string) $membership->status)" hint="Current membership state" tone="sky" />
            <x-stat-card label="Payment Status" :value="ucfirst((string) $membership->payment_status)" hint="Current billing state" tone="amber" />
            <x-stat-card label="Payable" :value="number_format((float) $membership->final_payable_amount, 2)" hint="Final commercial amount" tone="violet" />
            <x-stat-card label="Paid" :value="number_format((float) $membership->amount_paid, 2)" hint="Recorded collections" tone="emerald" />
            <x-stat-card :label="$hasCredit ? 'Credit' : 'Due'" :value="number_format($hasCredit ? $creditAmount : (float) $membership->due_amount, 2)" :hint="$hasCredit ? 'Member has extra paid amount' : 'Outstanding balance'" :tone="$hasCredit ? 'emerald' : 'warning'" />
            <x-stat-card label="Payments" :value="$membership->payments->where('status', 'recorded')->count()" hint="Recorded payment entries" tone="info" />
        </div>

        <div class="grid gap-6 {{ $focusLifecycle ? 'xl:grid-cols-[0.86fr_1.14fr]' : 'xl:grid-cols-[1.05fr_0.95fr]' }}">
            <div class="space-y-6 {{ $focusLifecycle ? 'xl:order-2' : '' }}">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Membership Snapshot</h3>
                    <p class="panel-section-copy">{{ $focusLifecycle ? 'Reference summary for the operator while actions are being taken.' : 'Readable cycle, commercial structure, and renewal-safe pricing context.' }}</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Member</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $membership->member?->email ?? 'No email' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Branch</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $membership->branch?->name ?? 'Gym-wide' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Approver: {{ $membership->approver?->name ?? 'System' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Cycle integrity</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Due checkpoint {{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Commercial base</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">Base ₹{{ number_format((float) $membership->default_plan_price, 2) }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                @if ($membership->joining_fee_waived)
                                    Joining fee waived on this cycle
                                @else
                                    Joining ₹{{ number_format((float) $membership->default_joining_fee, 2) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($membership->custom_fee_enabled || (float) $membership->discount_amount > 0 || (float) $membership->partial_month_fee > 0 || (float) $membership->pt_custom_fee > 0 || $membership->joining_fee_waived)
                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <h4 class="text-sm font-semibold text-slate-950 dark:text-white">Custom commercial adjustments</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($membership->custom_fee_enabled)
                                    <x-status-badge :label="'Custom fee ₹'.number_format((float) $membership->custom_fee_amount, 2)" tone="info" />
                                @endif
                                @if ((float) $membership->discount_amount > 0)
                                    <x-status-badge :label="'Discount ₹'.number_format((float) $membership->discount_amount, 2)" tone="warning" />
                                @endif
                                @if ((float) $membership->partial_month_fee > 0)
                                    <x-status-badge :label="'Partial ₹'.number_format((float) $membership->partial_month_fee, 2)" tone="verified" />
                                @endif
                                @if ((float) $membership->pt_custom_fee > 0)
                                    <x-status-badge :label="'PT ₹'.number_format((float) $membership->pt_custom_fee, 2)" tone="featured" />
                                @endif
                                @if ($membership->joining_fee_waived)
                                    <x-status-badge label="Joining fee waived" tone="success" />
                                @endif
                            </div>
                            @if ($membership->custom_fee_reason)
                                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $membership->custom_fee_reason }}</p>
                            @endif
                        </div>
                    @endif
                </x-premium-card>

                @if (! $focusLifecycle)
                <x-table-wrapper class="overflow-hidden p-0">
                    <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Payment Ledger</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every payment recorded against this membership, including invoice access and reversal control.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[980px]">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Mode</th>
                                    <th>Collector</th>
                                    <th>Receipt</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($membership->payments->sortByDesc('paid_at') as $payment)
                                    <tr>
                                        <td>
                                            <div class="font-medium text-slate-950 dark:text-white">{{ optional($payment->paid_at)->format('d M Y, h:i A') ?: 'No payment date' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ ucfirst((string) $payment->status) }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->notes ?: 'No notes' }}</div>
                                        </td>
                                        <td>{{ strtoupper((string) $payment->payment_mode) }}</td>
                                        <td>{{ $payment->collector?->name ?? 'System' }}</td>
                                        <td>{{ $payment->receipt_number ?? $payment->receipt?->receipt_number ?? 'Pending' }}</td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.show', ['payment' => $payment->id] + request()->query()) }}">View</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.invoice', ['payment' => $payment->id] + request()->query()) }}">Invoice PDF</x-action-button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state title="No payment history yet" message="Payments recorded against this membership will appear here." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-table-wrapper>
                @endif
            </div>

            <div class="space-y-6 {{ $focusLifecycle ? 'xl:order-1' : '' }}">
                <x-premium-card class="p-6 {{ $focusLifecycle ? 'ring-1 ring-sky-300/70 dark:ring-sky-500/30' : '' }}" id="lifecycle-workspace">
                    <div class="border-b border-slate-200/80 pb-5 dark:border-slate-800">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="max-w-3xl">
                                <h3 class="panel-section-title">Lifecycle Workspace</h3>
                                <p class="panel-section-copy">Operate this membership from one control desk: collect or reverse money, prep the next cycle, switch the next plan, update pricing, and manage hold or closure states.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($membership->member_id)
                                    <a href="{{ route('web.gym.members.show', ['member' => $membership->member_id] + request()->query()) }}" class="panel-btn-secondary">Member</a>
                                    <a href="{{ route('web.gym.members.assign-membership', ['member' => $membership->member_id] + request()->query()) }}" class="panel-btn-secondary">Change Plan</a>
                                @endif
                                @if ($canEditCustomFee && $membership->member_id)
                                    <a href="{{ route('web.gym.members.custom-fee', ['member' => $membership->member_id, 'member_membership_id' => $membership->id] + request()->query()) }}" class="panel-btn-primary">Pricing Desk</a>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">State</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ ucfirst((string) $membership->status) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Billing</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ ucfirst((string) $membership->payment_status) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Due / Credit</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">
                                    @if ($hasCredit)
                                        Credit ₹{{ number_format($creditAmount, 2) }}
                                    @else
                                        Due ₹{{ number_format((float) $membership->due_amount, 2) }}
                                    @endif
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current due date</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Next cycle start</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ $renewalStartDate->format('d M Y') }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current next step</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">
                                    @if ($isCancelled)
                                        This membership is closed. Renew to start a fresh cycle.
                                    @elseif ($isFrozen)
                                        This membership is paused. Reactivate it to resume billing.
                                    @elseif ($hasDue)
                                        This membership has an outstanding due. Collect or settle payment first.
                                    @elseif ($hasCredit)
                                        This membership is carrying a credit. Review reversal or absorb it in the next cycle.
                                    @else
                                        This membership is healthy. Use renewal, plan change, or extension when the next cycle changes.
                                    @endif
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Action map</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                    <a href="#payment-actions" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-700 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200 dark:hover:border-sky-500/30 dark:hover:text-sky-300">1. Payment</a>
                                    <a href="#renew-membership" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-700 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200 dark:hover:border-sky-500/30 dark:hover:text-sky-300">2. Next Cycle</a>
                                    <a href="#status-control" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-700 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200 dark:hover:border-sky-500/30 dark:hover:text-sky-300">3. Status</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @if ($canCollectPayments)
                            <details class="group rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60" id="payment-actions" open>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">1. Payment actions</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use this block when money is due, fully settled, or a payment needs reversal.</p>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500 transition group-open:rotate-180 dark:text-slate-400">⌄</span>
                                </summary>

                                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                    @if ($hasDue && ! $isCancelled)
                                        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            <p class="text-sm font-semibold text-slate-950 dark:text-white">Collect outstanding due</p>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Best when you want a complete payment record with receipt, invoice, and collector attribution.</p>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                <a href="{{ route('web.gym.payments.create', ['member_membership_id' => $membership->id] + request()->query()) }}" class="panel-btn-primary">Open Payment Form</a>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('web.gym.payments.mark-paid', ['memberMembership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Quick settle without full collection form</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use only when you want to mark the remaining due as fully paid immediately.</p>
                                            </div>
                                            <x-form-select name="payment_mode" label="Payment Mode" :options="$paymentModes" />
                                            <x-form-input type="datetime-local" name="paid_at" label="Payment Date" :value="now()->format('Y-m-d\\TH:i')" />
                                            <div>
                                                <label class="panel-label">Notes</label>
                                                <textarea name="notes" class="panel-textarea" placeholder="Optional settlement note"></textarea>
                                            </div>
                                            <x-action-button type="submit">Mark Full Due Paid</x-action-button>
                                        </form>
                                    @endif

                                    @if ((float) $membership->amount_paid > 0)
                                        <form method="POST" action="{{ route('web.gym.payments.mark-unpaid', ['memberMembership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Reset payment status</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use when the membership should move back to unpaid or partial while keeping audit history.</p>
                                            </div>
                                            <div>
                                                <label class="panel-label">Reason</label>
                                                <textarea name="reason" class="panel-textarea" placeholder="Why the membership payment state is being reset"></textarea>
                                            </div>
                                            <x-action-button type="submit" variant="secondary">Mark Unpaid</x-action-button>
                                        </form>
                                    @endif

                                    @if ($latestPayment && $latestPayment->status === 'recorded')
                                        <form method="POST" action="{{ route('web.gym.payments.reverse', ['payment' => $latestPayment->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10 xl:col-span-2">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">Reverse latest payment entry</p>
                                                <p class="mt-1 text-sm text-rose-700/90 dark:text-rose-200/80">This creates a payment reversal event for the most recent recorded payment.</p>
                                            </div>
                                            <div>
                                                <label class="panel-label !text-rose-900 dark:!text-rose-100">Reason</label>
                                                <textarea name="reason" class="panel-textarea" placeholder="Why this payment entry is being reversed"></textarea>
                                            </div>
                                            <x-action-button type="submit" variant="danger">Reverse Latest Payment</x-action-button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @endif

                        @if ($canManageMemberships)
                            <details class="group rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60" id="renew-membership" @if (request('flow') === 'lifecycle') open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">2. Next cycle and renewal</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use this when the member is starting a fresh billing cycle. If the next plan should be different, use the Change Plan action above first.</p>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500 transition group-open:rotate-180 dark:text-slate-400">⌄</span>
                                </summary>

                                <form method="POST" action="{{ route('web.gym.memberships.renew', ['membership' => $membership->id] + request()->query()) }}" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70 md:grid-cols-2">
                                    @csrf
                                    <div class="md:col-span-2 rounded-2xl border border-sky-200/70 bg-sky-50/80 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100">
                                        Renewal creates a fresh cycle based on the plan. Joining fee is automatically excluded here, and the due date now defaults to the projected cycle end.
                                    </div>
                                    <div class="md:col-span-2 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Renewal start</p>
                                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $renewalStartDate->format('d M Y') }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Projected end</p>
                                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $renewalProjectedEnd->format('d M Y') }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Joining fee</p>
                                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">Not charged on renewal</p>
                                        </div>
                                    </div>
                                    <x-form-input type="date" name="start_date" label="New Cycle Start" :value="optional($membership->expiry_date)->addDay()?->format('Y-m-d') ?? now()->toDateString()" required />
                                    <x-form-input type="date" name="due_date" label="Due Date" :value="$renewalProjectedEnd->format('Y-m-d')" />
                                    <x-form-input type="number" step="0.01" min="0" name="amount_paid" label="Initial Amount Paid" value="0" />
                                    <x-form-select name="initial_payment_mode" label="Initial Payment Mode" :options="$paymentModes" />
                                    <x-form-input type="datetime-local" name="paid_at" label="Payment Date" :value="now()->format('Y-m-d\\TH:i')" />
                                    <div>
                                        <label class="panel-label">External Reference</label>
                                        <input type="text" name="external_reference" class="panel-input" placeholder="Txn / receipt reference">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="panel-label">Renewal Notes</label>
                                        <textarea name="payment_notes" class="panel-textarea" placeholder="Optional payment or renewal note"></textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-action-button type="submit">Renew Membership</x-action-button>
                                    </div>
                                </form>
                            </details>

                            <details class="group rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60" id="status-control">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">3. Cycle and status controls</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pause, reactivate, extend, or close the membership without creating a new cycle or altering the commercial setup.</p>
                                    </div>
                                    <span class="text-xs font-medium text-slate-500 transition group-open:rotate-180 dark:text-slate-400">⌄</span>
                                </summary>

                                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                    @if ($isFrozen)
                                        <form method="POST" action="{{ route('web.gym.memberships.reactivate', ['membership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Reactivate membership</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use when a paused member is resuming the current cycle.</p>
                                            </div>
                                            <x-form-input name="due_date" label="Due Date" type="date" :value="optional($membership->due_date)->format('Y-m-d')" />
                                            <div>
                                                <label class="panel-label">Reason</label>
                                                <textarea name="notes" class="panel-textarea" placeholder="Why this membership is being reactivated"></textarea>
                                            </div>
                                            <x-action-button type="submit">Reactivate Membership</x-action-button>
                                        </form>
                                    @elseif (! $isCancelled)
                                        <form method="POST" action="{{ route('web.gym.memberships.freeze', ['membership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Pause current cycle</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use when the member is temporarily holding the current membership.</p>
                                            </div>
                                            <div>
                                                <label class="panel-label">Reason</label>
                                                <textarea name="notes" class="panel-textarea" placeholder="Why this membership is being frozen"></textarea>
                                            </div>
                                            <x-action-button type="submit" variant="secondary">Freeze Membership</x-action-button>
                                        </form>
                                    @endif

                                    @if (! $isCancelled)
                                        <form method="POST" action="{{ route('web.gym.memberships.extend', ['membership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Extend current cycle</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use for goodwill, service recovery, or manual cycle extension without renewal.</p>
                                            </div>
                                            <x-form-input name="extra_days" label="Extra Days" type="number" value="7" min="1" max="365" />
                                            <x-form-input name="due_date" label="Updated Due Date" type="date" :value="optional($membership->due_date)->format('Y-m-d')" />
                                            <div>
                                                <label class="panel-label">Reason</label>
                                                <textarea name="notes" class="panel-textarea" placeholder="Why this cycle is being extended"></textarea>
                                            </div>
                                            <x-action-button type="submit" variant="secondary">Extend Membership</x-action-button>
                                        </form>
                                    @endif

                                    @if (! $isCancelled)
                                        <form method="POST" action="{{ route('web.gym.memberships.cancel', ['membership' => $membership->id] + request()->query()) }}" class="grid gap-3 rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10 xl:col-span-2">
                                            @csrf
                                            <div>
                                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">Cancel membership</p>
                                                <p class="mt-1 text-sm text-rose-700/90 dark:text-rose-200/80">Use only when this cycle should be closed and no longer remain active.</p>
                                            </div>
                                            <div>
                                                <label class="panel-label !text-rose-900 dark:!text-rose-100">Reason</label>
                                                <textarea name="notes" class="panel-textarea" placeholder="Why this membership is being cancelled"></textarea>
                                            </div>
                                            <x-action-button type="submit" variant="danger">Cancel Membership</x-action-button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </x-premium-card>

                @if ($focusLifecycle)
                    <x-table-wrapper class="overflow-hidden p-0">
                        <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                            <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Recent Payments</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Compact payment reference while you operate the lifecycle workspace.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[920px]">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Collector</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($membership->payments->sortByDesc('paid_at')->take(5) as $payment)
                                        <tr>
                                            <td>
                                                <div class="font-medium text-slate-950 dark:text-white">{{ optional($payment->paid_at)->format('d M Y, h:i A') ?: 'No payment date' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ ucfirst((string) $payment->status) }}</div>
                                            </td>
                                            <td class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }}</td>
                                            <td>{{ strtoupper((string) $payment->payment_mode) }}</td>
                                            <td>{{ $payment->collector?->name ?? 'System' }}</td>
                                            <td>
                                                <div class="flex justify-end gap-2">
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.show', ['payment' => $payment->id] + request()->query()) }}">View</x-action-button>
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.invoice', ['payment' => $payment->id] + request()->query()) }}">Invoice</x-action-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">
                                                <x-empty-state title="No payment history yet" message="Payments recorded against this membership will appear here." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-table-wrapper>
                @endif

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Lifecycle Audit</h3>
                    <p class="panel-section-copy">{{ $focusLifecycle ? 'Recent operational history for quick verification while you act.' : 'Membership status, renewals, extensions, payments, and reversals in one timeline.' }}</p>
                    <div class="mt-5">
                        <x-web.audit-timeline :items="$focusLifecycle ? collect($activityTimeline)->take(6) : $activityTimeline" empty-title="No lifecycle activity yet" empty-message="Membership and payment actions will appear here." />
                    </div>
                </x-premium-card>

                @if (! $focusLifecycle)
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Custom Fee Audit</h3>
                    <p class="panel-section-copy">Member-specific pricing changes with reason and accountable actor.</p>
                    <div class="mt-5">
                        <x-web.audit-timeline :items="$customFeeTimeline" empty-title="No custom fee audit yet" empty-message="Custom pricing updates will appear here once recorded." />
                    </div>
                </x-premium-card>
                @endif
            </div>
        </div>
    </div>
@endsection
