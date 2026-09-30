@extends('layouts.panel')

@section('content')
    @php
        $remaining = max(0, (float)$statement->net_payable_amount - (float)$statement->paid_amount);
        $scopeQuery = request()->only(['gym', 'branch']);
    @endphp
    <div class="space-y-5">
        <x-premium-card class="p-5 sm:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <a href="{{ route('web.gym.compensation.index', $scopeQuery + ['month' => $statement->period_start->format('Y-m')]) }}#payouts" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">← Salary & Commission</a>
                    <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $statement->period_start->format('F Y') }} statement</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $statement->user?->name ?? 'Unavailable user' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $statement->branch?->name ?? 'Gym-wide' }} · generated {{ optional($statement->generated_at)->format('d M Y, h:i A') ?? 'date unavailable' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3"><x-status-badge :label="str($statement->status)->replace('_', ' ')->title()" :tone="$statement->status === 'paid' ? 'success' : 'warning'" /><x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', $scopeQuery) }}">Finance Ledger</x-action-button></div>
            </div>
        </x-premium-card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Fixed salary" :value="'₹'.number_format((float)$statement->salary_amount, 2)" hint="Profile amount" tone="sky" />
            <x-stat-card label="Commission" :value="'₹'.number_format((float)$statement->commission_amount, 2)" :hint="$earnings->count().' earning rows'" tone="violet" />
            <x-stat-card label="Net payable" :value="'₹'.number_format((float)$statement->net_payable_amount, 2)" hint="After adjustments" tone="amber" />
            <x-stat-card label="Paid" :value="'₹'.number_format((float)$statement->paid_amount, 2)" :hint="$statement->payments->count().' payouts'" tone="emerald" />
            <x-stat-card label="Remaining" :value="'₹'.number_format($remaining, 2)" hint="Still owed" tone="rose" />
        </div>

        @if($canManage && $remaining > 0)
            <x-premium-card class="p-5">
                <h2 class="panel-section-title">Record payout</h2><p class="panel-section-copy">Record a full or partial payment. A linked payroll outflow will be posted to the finance ledger.</p>
                <form method="POST" action="{{ route('web.gym.compensation.statements.pay', ['statement' => $statement->id, 'gym' => $gym->id]) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">@csrf
                    <div><label class="panel-label">Amount</label><input name="amount" type="number" min="0.01" step="0.01" max="{{ $remaining }}" value="{{ $remaining }}" class="panel-input" required></div>
                    <div><label class="panel-label">Payment mode</label><select name="payment_mode" class="panel-select"><option value="bank">Bank</option><option value="upi">UPI</option><option value="cash">Cash</option><option value="card">Card</option></select></div>
                    <div><label class="panel-label">Reference</label><input name="reference" class="panel-input" placeholder="Bank / UPI reference"></div>
                    <div><label class="panel-label">Paid at</label><input name="paid_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="panel-input" required></div>
                    <div class="flex items-end"><x-action-button type="submit" class="w-full justify-center">Record Payout</x-action-button></div>
                    <div class="md:col-span-2 xl:col-span-5"><label class="panel-label">Notes</label><textarea name="notes" class="panel-textarea" placeholder="Optional payout note"></textarea></div>
                </form>
            </x-premium-card>
        @endif

        <div class="grid gap-5 xl:grid-cols-2">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="panel-section-title">Commission sources</h2><p class="panel-section-copy">Membership collections included in this statement.</p></div>
                <div class="overflow-x-auto"><table class="panel-table min-w-[700px]"><thead><tr><th>Date</th><th>Member</th><th>Collected extra</th><th>Commission</th><th>Membership</th></tr></thead><tbody>
                    @forelse($earnings as $earning)<tr><td>{{ optional($earning->earned_at)->format('d M Y') }}</td><td>{{ $earning->allocation?->membership?->member?->name ?? 'Unavailable member' }}</td><td>₹{{ number_format((float)$earning->commissionable_collected_amount, 2) }}</td><td class="font-semibold">₹{{ number_format((float)$earning->amount, 2) }}</td><td>@if($earning->allocation?->membership)<a class="text-sm font-semibold text-indigo-600 dark:text-indigo-400" href="{{ route('web.gym.memberships.show', ['membership' => $earning->allocation->membership->id] + $scopeQuery) }}">Open</a>@else—@endif</td></tr>@empty<tr><td colspan="5"><x-empty-state title="No commission sources" message="This statement contains fixed salary only." /></td></tr>@endforelse
                </tbody></table></div>
            </x-table-wrapper>

            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="panel-section-title">Payout history</h2><p class="panel-section-copy">Every row is linked to its finance ledger entry.</p></div>
                <div class="overflow-x-auto"><table class="panel-table min-w-[650px]"><thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Reference</th><th>Recorded by</th></tr></thead><tbody>
                    @forelse($statement->payments as $payment)<tr><td>{{ optional($payment->paid_at)->format('d M Y, h:i A') }}</td><td class="font-semibold">₹{{ number_format((float)$payment->amount, 2) }}</td><td>{{ strtoupper($payment->payment_mode) }}</td><td>{{ $payment->reference ?: '—' }}</td><td>{{ $payment->paidBy?->name ?? 'System' }}@if($payment->ledgerEntry)<div class="text-xs text-emerald-600">Ledger #{{ $payment->ledgerEntry->id }}</div>@endif</td></tr>@empty<tr><td colspan="5"><x-empty-state title="No payouts recorded" message="Record the first full or partial payout above." /></td></tr>@endforelse
                </tbody></table></div>
            </x-table-wrapper>
        </div>
    </div>
@endsection
