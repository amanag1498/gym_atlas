@extends('layouts.panel')

@section('content')
    @php($selectedMembershipId = (int) request('member_membership_id'))
    <div class="space-y-6">
        @if ($selectedMemberId)
            <x-premium-card class="p-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Member-scoped collection</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Only open memberships for the selected member are shown here.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.create', request()->only(['gym', 'branch'])) }}">Show All Open Memberships</x-action-button>
                </div>
            </x-premium-card>
        @endif

        <div class="grid gap-4 lg:grid-cols-4">
            <x-stat-card label="Collectible Memberships" :value="$memberships->count()" hint="Unpaid, partial, or overdue" tone="sky" />
            <x-stat-card label="Overdue" :value="$memberships->where('payment_status', 'overdue')->count()" hint="Immediate collection focus" tone="rose" />
            <x-stat-card label="Partial" :value="$memberships->where('payment_status', 'partial')->count()" hint="Top-up opportunities" tone="amber" />
            <x-stat-card label="Total Due" :value="number_format((float) $memberships->sum('due_amount'), 2)" hint="Visible due queue" tone="emerald" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <x-table-wrapper class="p-0">
                <div class="border-b border-white/10 px-6 py-5">
                    <h3 class="text-lg font-semibold text-white">Collection Queue</h3>
                    <p class="mt-1 text-sm text-slate-400">Confirm the live due state before recording a payment.</p>
                </div>
                <table class="panel-table">
                    <thead><tr><th>Member</th><th>Plan</th><th>Due</th><th>Status</th><th>Due Date</th></tr></thead>
                    <tbody>
                    @forelse ($memberships as $membership)
                        <tr>
                            <td>{{ $membership->member?->name }}</td>
                            <td>{{ $membership->membershipPlan?->name }}</td>
                            <td>{{ number_format((float) $membership->due_amount, 2) }}</td>
                            <td><x-status-badge :label="str($membership->payment_status)->replace('_', ' ')->title()" /></td>
                            <td>{{ optional($membership->due_date)->format('d M Y') ?: 'No due date' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="No pending collections" message="There are no unpaid or overdue memberships in the current scope." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-table-wrapper>

            <x-premium-card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Collection desk</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">Record Payment</h3>
                        <p class="mt-2 text-sm text-slate-400">Supports cash, UPI, card, bank, notes, external reference, and optional overpayment handling.</p>
                    </div>
                    <x-status-badge label="Billing" tone="info" />
                </div>

                <form action="{{ route('web.gym.payments.store') }}" method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div class="md:col-span-2">
                        <label for="member_membership_id" class="panel-label">Membership</label>
                        <select id="member_membership_id" name="member_membership_id" class="panel-select" required>
                        @foreach ($memberships as $membership)
                            <option value="{{ $membership->id }}" data-due="{{ number_format((float) $membership->due_amount, 2, '.', '') }}" @selected($selectedMembershipId === $membership->id || ($selectedMembershipId === 0 && $selectedMemberId && (int) $membership->member_id === (int) $selectedMemberId && $loop->first))>{{ $membership->member?->name }} • {{ $membership->membershipPlan?->name }} • {{ $membership->branch?->name ?? 'Branch' }} • Due {{ number_format((float) $membership->due_amount, 2) }}</option>
                        @endforeach
                    </select>
                </div>
                    <x-form-input name="amount" label="Amount" placeholder="0.00" required />
                    <x-form-select name="payment_mode" label="Payment Mode" :options="['cash' => 'CASH', 'upi' => 'UPI', 'card' => 'CARD', 'bank' => 'BANK']" />
                    <x-form-input type="datetime-local" name="payment_date" label="Payment Date" />
                    <x-form-input name="external_reference" label="External Reference" placeholder="Txn or receipt reference" />
                    <div class="md:col-span-2">
                        <label for="notes" class="panel-label">Notes</label>
                        <textarea id="notes" name="notes" class="panel-textarea" placeholder="Optional collection notes"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-slate-200">
                            <input type="hidden" name="allow_overpayment" value="0">
                            <input type="checkbox" name="allow_overpayment" value="1" class="h-4 w-4 rounded border-white/20 bg-slate-950/60 text-sky-400">
                            Allow overpayment when intentionally collecting above the current due amount
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <x-action-button type="submit" variant="primary" class="w-full justify-center">Record Payment</x-action-button>
                    </div>
                </form>
            </x-premium-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const membershipSelect = document.getElementById('member_membership_id');
            const amountInput = document.querySelector('input[name="amount"]');

            if (!membershipSelect || !amountInput) {
                return;
            }

            const syncDueAmount = () => {
                const option = membershipSelect.options[membershipSelect.selectedIndex];
                const dueAmount = option?.dataset?.due ?? '';

                if (dueAmount !== '' && (!amountInput.value || amountInput.dataset.autofilled === '1')) {
                    amountInput.value = dueAmount;
                    amountInput.dataset.autofilled = '1';
                }
            };

            amountInput.addEventListener('input', () => {
                amountInput.dataset.autofilled = '0';
            });

            membershipSelect.addEventListener('change', syncDueAmount);
            syncDueAmount();
        });
    </script>
@endpush
