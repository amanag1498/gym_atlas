@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Billing detail</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">Payment #{{ $payment->id }}</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Receipt, collector, member, and membership balance context for this recorded payment.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary">Back to Payments</a>
                    <a href="{{ route('web.gym.payments.invoice', array_merge(request()->only(['gym', 'branch']), ['payment' => $payment->id])) }}" class="panel-btn-primary">Download Invoice PDF</a>
                    @if ($payment->member_membership_id)
                        <a href="{{ route('web.gym.memberships.show', array_merge(request()->only(['gym', 'branch']), ['membership' => $payment->member_membership_id])) }}" class="panel-btn-secondary">Membership Detail</a>
                    @endif
                    @if ($payment->member_id)
                        <a href="{{ route('web.gym.members.payments', array_merge(request()->only(['gym', 'branch']), ['member' => $payment->member_id])) }}" class="panel-btn-secondary">Member History</a>
                        <a href="{{ route('web.gym.members.show', array_merge(request()->only(['gym', 'branch']), ['member' => $payment->member_id])) }}" class="panel-btn-secondary">Member Profile</a>
                    @endif
                    @if ($payment->membership?->due_amount > 0)
                        <a href="{{ route('web.gym.payments.create', array_merge(request()->only(['gym', 'branch']), ['member_membership_id' => $payment->member_membership_id])) }}" class="panel-btn-primary">Collect Remaining Due</a>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="panel-card p-6">
                <h3 class="panel-section-title">Payment profile</h3>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Member</p>
                        <p class="mt-2 font-semibold text-white">{{ $payment->member?->name ?? 'Unknown member' }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $payment->member?->email ?: ($payment->member?->phone ?: 'No contact details') }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Membership</p>
                        <p class="mt-2 font-semibold text-white">{{ $payment->membership?->membershipPlan?->name ?? 'Unknown plan' }}</p>
                        <p class="mt-1 text-sm text-slate-400">Branch: {{ $payment->branch?->name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Amount</p>
                        <p class="mt-2 font-semibold text-white">{{ number_format((float) $payment->amount, 2) }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Payment Mode</p>
                        <p class="mt-2 font-semibold text-white">{{ strtoupper((string) $payment->payment_mode) }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Collected By</p>
                        <p class="mt-2 font-semibold text-white">{{ $payment->collector?->name ?? 'System' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Payment Date</p>
                        <p class="mt-2 font-semibold text-white">{{ optional($payment->paid_at)->format('d M Y H:i') ?: 'Unknown' }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="panel-card p-6">
                    <h3 class="panel-section-title">Receipt & reference</h3>
                    <div class="mt-5 space-y-3">
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-slate-300">Receipt Number</span>
                            <span class="font-semibold text-white">{{ $payment->receipt_number ?? 'Pending' }}</span>
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-slate-300">Payment Record Status</span>
                            <x-status-badge :label="str($payment->status)->replace('_', ' ')->title()" tone="info" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-slate-300">Membership Payment Status</span>
                            <x-status-badge :label="str($payment->membership?->payment_status ?? 'unknown')->replace('_', ' ')->title()" tone="{{ ($payment->membership?->payment_status ?? '') === 'overdue' ? 'danger' : (($payment->membership?->payment_status ?? '') === 'partial' ? 'warning' : (($payment->membership?->payment_status ?? '') === 'overpaid' ? 'verified' : 'success')) }}" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-slate-300">External Reference</span>
                            <span class="font-semibold text-white">{{ $payment->external_reference ?: 'No external ref' }}</span>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Notes</p>
                            <p class="mt-2 text-sm text-slate-200">{{ $payment->notes ?: 'No notes added.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="panel-card p-6">
                    <h3 class="panel-section-title">Membership balance snapshot</h3>
                    <div class="mt-5 grid gap-3 md:grid-cols-3">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Final Payable</p>
                            <p class="mt-2 font-semibold text-white">{{ number_format((float) ($payment->membership?->final_payable_amount ?? 0), 2) }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Amount Paid</p>
                            <p class="mt-2 font-semibold text-white">{{ number_format((float) ($payment->membership?->amount_paid ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-3xl border border-amber-400/15 bg-amber-400/10 p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-amber-100">Due Amount</p>
                            <p class="mt-2 font-semibold text-white">{{ number_format((float) ($payment->membership?->due_amount ?? 0), 2) }}</p>
                        </div>
                    </div>
                    @if (($payment->membership?->amount_paid ?? 0) > 0)
                        <form method="POST" action="{{ route('web.gym.payments.mark-unpaid', ['memberMembership' => $payment->member_membership_id] + request()->only(['gym', 'branch'])) }}" class="mt-5 space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            @csrf
                            <p class="text-sm font-semibold text-slate-950 dark:text-white">Reset membership to unpaid</p>
                            <textarea name="reason" class="panel-textarea" placeholder="Reason for reversing collected status"></textarea>
                            <x-action-button type="submit" variant="secondary">Mark Membership Unpaid</x-action-button>
                        </form>
                    @endif
                    @if ($payment->status === 'recorded')
                        <form method="POST" action="{{ route('web.gym.payments.reverse', ['payment' => $payment->id] + request()->only(['gym', 'branch'])) }}" class="mt-5 space-y-3 rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
                            @csrf
                            <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Reverse this payment only</p>
                            <textarea name="reason" class="panel-textarea" placeholder="Why this payment entry is being reversed"></textarea>
                            <x-action-button type="submit" variant="danger">Reverse Payment Entry</x-action-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
