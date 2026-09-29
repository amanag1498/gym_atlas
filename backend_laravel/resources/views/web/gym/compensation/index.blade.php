@extends('layouts.panel')

@section('content')
    @php
        $salaryTotal = $statements->sum('salary_amount');
        $commissionTotal = $statements->sum('commission_amount');
        $payableTotal = $statements->sum('net_payable_amount') - $statements->sum('paid_amount');
    @endphp
    <div class="space-y-5">
        <x-premium-card class="p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">Team finance</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Salary & Commission</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Fixed salaries and membership commissions, reconciled against collections before payout.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}">Finance Ledger</x-action-button>
                    <form method="GET" action="{{ route('web.gym.compensation.index') }}" class="flex gap-2">
                        <input type="hidden" name="gym" value="{{ $gym->id }}">
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="panel-input !w-auto">
                        <x-action-button type="submit" variant="secondary">View</x-action-button>
                    </form>
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Fixed Salary" :value="'₹'.number_format((float) $salaryTotal, 2)" :hint="$month->format('F Y')" tone="sky" />
            <x-stat-card label="Commission Earned" :value="'₹'.number_format((float) $commissionTotal, 2)" hint="Collected membership extras" tone="violet" />
            <x-stat-card label="Remaining Payable" :value="'₹'.number_format((float) $payableTotal, 2)" hint="After recorded payouts" tone="amber" />
            <x-stat-card label="Statements" :value="$statements->count()" hint="Draft, partial, and paid" tone="emerald" />
        </div>

        @if ($canManage)
            <div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                <x-premium-card class="p-5">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Compensation profile</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set the fixed monthly salary. Saving the same person updates their profile.</p>
                    <form method="POST" action="{{ route('web.gym.compensation.profiles.store', ['gym' => $gym->id]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <div class="md:col-span-2"><label class="panel-label">Team member</label><select name="user_id" class="panel-select" required><option value="">Select trainer or staff</option>@foreach($teamMembers as $person)<option value="{{ $person->id }}">{{ $person->name }} · {{ str($person->roles->pluck('name')->first())->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                        <div><label class="panel-label">Worker type</label><select name="worker_type" class="panel-select"><option value="trainer">Trainer</option><option value="staff">Staff</option></select></div>
                        <div><label class="panel-label">Branch</label><select name="branch_id" class="panel-select"><option value="">Gym-wide</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                        <div><label class="panel-label">Monthly salary</label><input name="monthly_salary" type="number" min="0" step="0.01" class="panel-input" required></div>
                        <div><label class="panel-label">Payout day</label><input name="payout_day" type="number" min="1" max="28" value="1" class="panel-input" required></div>
                        <div><label class="panel-label">Effective from</label><input name="effective_from" type="date" class="panel-input"></div>
                        <div><label class="panel-label">Effective until</label><input name="effective_until" type="date" class="panel-input"></div>
                        <label class="md:col-span-2 flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Active compensation profile</label>
                        <x-action-button type="submit" class="md:col-span-2 justify-center">Save Profile</x-action-button>
                    </form>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Generate monthly statements</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Refreshes draft statements from active salaries and collected commission earnings.</p></div>
                        <x-status-badge :label="$month->format('M Y')" tone="info" />
                    </div>
                    <form method="POST" action="{{ route('web.gym.compensation.generate', ['gym' => $gym->id]) }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
                        @csrf
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="panel-input" required>
                        <x-action-button type="submit">Generate / Refresh</x-action-button>
                    </form>
                    <div class="mt-5 space-y-2">
                        @forelse($profiles as $profile)
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                                <div><p class="font-medium text-slate-950 dark:text-white">{{ $profile->user?->name }}</p><p class="text-xs text-slate-500">{{ ucfirst($profile->worker_type) }} · payout day {{ $profile->payout_day }}</p></div>
                                <p class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float)$profile->monthly_salary, 2) }}</p>
                            </div>
                        @empty
                            <x-empty-state title="No salary profiles" message="Add a trainer or staff salary profile to include fixed cost in monthly statements." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>
        @endif

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $month->format('F Y') }} payout statements</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Each payout posts one linked payroll expense to the finance ledger.</p></div>
            <div class="overflow-x-auto"><table class="panel-table min-w-[1080px]"><thead><tr><th>Team member</th><th>Salary</th><th>Commission</th><th>Net payable</th><th>Paid</th><th>Status</th><th class="text-right">Record payout</th></tr></thead><tbody>
                @forelse($statements as $statement)
                    @php($remaining = max(0, (float)$statement->net_payable_amount - (float)$statement->paid_amount))
                    <tr><td><div class="font-medium text-slate-950 dark:text-white">{{ $statement->user?->name }}</div><div class="text-xs text-slate-500">{{ $statement->branch?->name ?? 'Gym-wide' }}</div></td><td>₹{{ number_format((float)$statement->salary_amount, 2) }}</td><td>₹{{ number_format((float)$statement->commission_amount, 2) }}</td><td class="font-semibold">₹{{ number_format((float)$statement->net_payable_amount, 2) }}</td><td>₹{{ number_format((float)$statement->paid_amount, 2) }}</td><td><x-status-badge :label="str($statement->status)->replace('_',' ')->title()" :tone="$statement->status === 'paid' ? 'success' : 'warning'" /></td><td>
                        @if($canManage && $remaining > 0)<form method="POST" action="{{ route('web.gym.compensation.statements.pay', ['statement' => $statement->id, 'gym' => $gym->id]) }}" class="ml-auto grid max-w-lg grid-cols-5 gap-2">@csrf<input name="amount" type="number" step="0.01" max="{{ $remaining }}" value="{{ $remaining }}" class="panel-input col-span-1" required><select name="payment_mode" class="panel-select"><option value="bank">Bank</option><option value="upi">UPI</option><option value="cash">Cash</option><option value="card">Card</option></select><input name="reference" class="panel-input" placeholder="Reference"><input name="paid_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="panel-input" required><x-action-button type="submit">Pay</x-action-button></form>@else<span class="text-sm text-slate-500">Complete</span>@endif
                    </td></tr>
                @empty<tr><td colspan="7"><x-empty-state title="No statements generated" message="Generate this month after salary profiles and member collections are recorded." /></td></tr>@endforelse
            </tbody></table></div>
        </x-table-wrapper>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Commission audit</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Only the collected portion above the base membership contributes commission.</p></div>
            <div class="overflow-x-auto"><table class="panel-table min-w-[900px]"><thead><tr><th>Date</th><th>Recipient</th><th>Member</th><th>Collected extra</th><th>Commission</th><th>Rule</th></tr></thead><tbody>
                @forelse($earnings as $earning)<tr><td>{{ optional($earning->earned_at)->format('d M Y') }}</td><td>{{ $earning->recipient?->name }}</td><td>{{ $earning->allocation?->membership?->member?->name }}</td><td>₹{{ number_format((float)$earning->commissionable_collected_amount, 2) }}</td><td class="font-semibold">₹{{ number_format((float)$earning->amount, 2) }}</td><td>{{ ucfirst($earning->allocation?->calculation_type) }} {{ $earning->allocation?->calculation_type === 'percentage' ? number_format((float)$earning->allocation?->value, 2).'%' : '₹'.number_format((float)$earning->allocation?->value, 2) }}</td></tr>@empty<tr><td colspan="6"><x-empty-state title="No commission earned" message="Earnings appear when a membership with a commission split collects money above its base amount." /></td></tr>@endforelse
            </tbody></table></div>
        </x-table-wrapper>
    </div>
@endsection
