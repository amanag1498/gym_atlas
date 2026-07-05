@extends('layouts.panel')

@section('content')
    <div class="space-y-4">
        <x-premium-card class="p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700">Finance operations</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Payments</h1>
                        <span class="text-sm text-slate-500">Collections, spends, owner ledger, dues, and billing audit in one workspace.</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" :variant="($activeTab ?? 'all') === 'all' ? 'primary' : 'secondary'" href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}">All</x-action-button>
                    <x-action-button as="a" :variant="($activeTab ?? '') === 'dues' ? 'primary' : 'secondary'" href="{{ route('web.gym.dues.index', request()->only(['gym', 'branch'])) }}">Dues</x-action-button>
                    <x-action-button as="a" :variant="request('payment_status') === 'overdue' ? 'primary' : 'secondary'" href="{{ route('web.gym.payments.index', array_merge(request()->only(['gym', 'branch']), ['payment_status' => 'overdue'])) }}">Overdue</x-action-button>
                    @if ($canCollectPayments)
                        <x-action-button as="a" href="{{ route('web.gym.payments.create', request()->only(['gym', 'branch'])) }}">Collect Payment</x-action-button>
                    @endif
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-11">
            <x-stat-card label="Recorded" :value="$summary['recorded_payments']" hint="Filtered payments" tone="sky" />
            <x-stat-card label="Collected" :value="number_format((float) $summary['collected_amount'], 2)" hint="Captured amount" tone="success" />
            <x-stat-card label="Monthly" :value="number_format((float) $monthlyCollection, 2)" hint="Current month" tone="info" />
            <x-stat-card label="Ledger Inflow" :value="number_format((float) $ledgerSummary['inflow'], 2)" hint="Posted inflow" tone="sky" />
            <x-stat-card label="Ledger Outflow" :value="number_format((float) $ledgerSummary['outflow'], 2)" hint="Posted spend" tone="danger" />
            <x-stat-card label="Net Cash" :value="number_format((float) $ledgerSummary['net'], 2)" hint="Inflow minus outflow" tone="violet" />
            <x-stat-card label="Closing Balance" :value="number_format((float) $ledgerSummary['closing_balance'], 2)" hint="Visible ledger balance" tone="emerald" />
            <x-stat-card label="Manual Entries" :value="$ledgerSummary['manual_entries']" hint="Owner-posted finance rows" tone="amber" />
            <x-stat-card label="Reversed Rows" :value="$ledgerSummary['reversed_entries']" hint="Corrected ledger entries" tone="neutral" />
            <x-stat-card label="Open Dues" :value="number_format((float) $summary['pending_due_amount'], 2)" hint="Pending collection" tone="amber" />
            <x-stat-card label="Overdue Dues" :value="number_format((float) $summary['overdue_due_amount'], 2)" hint="Highest risk" tone="danger" />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            @foreach ($auditWindows as $window)
                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ $window['label'] }}</p>
                            <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">{{ $window['range'] }}</h3>
                        </div>
                        <x-status-badge :label="$window['payments_count'].' payments'" tone="info" />
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Collected</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $window['collected_amount'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Spent</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $window['spent_amount'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Net</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) ($window['collected_amount'] - $window['spent_amount']), 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Avg ticket</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $window['avg_ticket'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Open due</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $window['open_due'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Overdue due</span>
                            <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $window['overdue_due'], 2) }}</span>
                        </div>
                    </div>
                </x-premium-card>
            @endforeach
        </div>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 bg-slate-50/90 px-4 py-3">
                <h3 class="text-lg font-semibold tracking-tight text-slate-950">Payment filters</h3>
            </div>
            <form method="GET" class="grid gap-3 px-4 py-4 md:grid-cols-2 xl:grid-cols-7">
                <input type="hidden" name="gym" value="{{ request('gym') }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <x-form-input name="member_search" label="Search Member" :value="request('member_search')" />
                <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                <x-form-select name="payment_status" label="Payment State" :selected="request('payment_status')" :options="['' => 'All states', 'paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue']" />
                <x-form-select name="payment_mode" label="Payment Mode" :selected="request('payment_mode')" :options="['' => 'All modes', 'cash' => 'CASH', 'upi' => 'UPI', 'card' => 'CARD', 'bank' => 'BANK']" />
                <x-form-input name="start_date" label="Start Date" type="date" :value="request('start_date')" />
                <x-form-input name="end_date" label="End Date" type="date" :value="request('end_date')" />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(340px,0.75fr)]">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Owner Finance Ledger</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Unified inflow and outflow trail with running balance for gym owners and operators.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['ledger_export' => 'csv']) }}">Export Ledger</x-action-button>
                </div>
                <form method="GET" class="grid gap-3 border-b border-slate-200/80 px-4 py-4 md:grid-cols-2 xl:grid-cols-5 dark:border-slate-800">
                    <input type="hidden" name="gym" value="{{ request('gym') }}">
                    @if (request('branch'))
                        <input type="hidden" name="branch" value="{{ request('branch') }}">
                    @endif
                    <x-form-input name="ledger_search" label="Search Ledger" :value="request('ledger_search')" />
                    <x-form-select name="ledger_direction" label="Direction" :selected="request('ledger_direction')" :options="['' => 'All', 'inflow' => 'Inflow', 'outflow' => 'Outflow']" />
                    <x-form-select name="ledger_status" label="Ledger State" :selected="request('ledger_status')" :options="['' => 'All', 'posted' => 'Posted', 'reversed' => 'Reversed']" />
                    <x-form-select name="ledger_entry_type" label="Entry Type" :selected="request('ledger_entry_type')" :options="['' => 'All', 'membership_collection' => 'Membership collection', 'expense' => 'Expense', 'other_income' => 'Other income', 'refund' => 'Refund', 'adjustment' => 'Adjustment']" />
                    <div class="flex items-end gap-2">
                        <x-action-button type="submit">Apply</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                    </div>
                </form>

                @if ($ledgerEntries->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[1260px]">
                            <thead>
                                <tr>
                                    <th>Entry</th>
                                    <th>Type</th>
                                    <th>Direction</th>
                                    <th>Amount</th>
                                    <th>Balance</th>
                                    <th>Context</th>
                                    <th>Recorded By</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgerEntries as $entry)
                                    @php
                                        $tone = $entry->direction === 'inflow' ? 'success' : 'danger';
                                        $impact = (float) ($entry->impact_amount ?? 0);
                                        $runningBalance = (float) ($entry->running_balance ?? 0);
                                        $isManual = $entry->source_type === 'manual';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $entry->title }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $entry->description ?: 'No description added' }}</div>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <x-status-badge :label="$isManual ? 'Manual' : 'Payment Sync'" tone="neutral" />
                                                @if ($entry->reference)
                                                    <x-status-badge :label="$entry->reference" tone="info" />
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ str($entry->entry_type)->replace('_', ' ')->title() }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($entry->category)->replace('_', ' ')->title() }}</div>
                                        </td>
                                        <td>
                                            <x-status-badge :label="str($entry->direction)->title()" :tone="$tone" />
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($entry->occurred_at)->format('d M Y h:i A') }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold {{ $entry->direction === 'inflow' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">
                                                {{ $entry->direction === 'outflow' ? '-' : '+' }}₹{{ number_format((float) $entry->amount, 2) }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Impact {{ $impact >= 0 ? '+' : '-' }}₹{{ number_format(abs($impact), 2) }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format($runningBalance, 2) }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($entry->status)->replace('_', ' ')->title() }}</div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $entry->branch?->name ?? 'Gym-wide' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ strtoupper((string) ($entry->payment_mode ?: 'n/a')) }}</div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $entry->creator?->name ?? 'System' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $entry->creator?->email ?? 'No email' }}</div>
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                @if ($entry->source_type === \App\Models\Payment::class && $entry->source_id)
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.show', array_merge(request()->only(['gym', 'branch']), ['payment' => $entry->source_id])) }}">Source</x-action-button>
                                                @endif
                                                @if ($entry->source_type === 'manual' && $entry->status === 'posted' && $canCollectPayments)
                                                    <form method="POST" action="{{ route('web.gym.payments.ledger-entries.reverse', array_merge(request()->only(['gym', 'branch']), ['ledgerEntry' => $entry->id])) }}" class="contents" data-confirm-submit data-confirm-title="Reverse ledger entry?" data-confirm-message="This will keep the record for audit but remove its impact from the active cash ledger." data-confirm-button="Reverse entry">
                                                        @csrf
                                                        <div data-confirm-payload>
                                                            <input type="hidden" name="reason" value="Reversed from gym payments ledger">
                                                        </div>
                                                        <x-action-button type="submit" variant="secondary">Reverse</x-action-button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        <x-empty-state title="No ledger entries yet" message="Collections and manual spends will appear here once finance activity starts." />
                    </div>
                @endif

                @if ($ledgerEntries->hasPages())
                    <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        {{ $ledgerEntries->links() }}
                    </div>
                @endif
            </x-table-wrapper>

            <div class="space-y-4">
                <x-premium-card class="p-4">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Record Spend / Adjustment</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Owner-side entries for rent, payroll, utility bills, refunds, and manual corrections.</p>
                    @if ($canCollectPayments)
                        <form action="{{ route('web.gym.payments.ledger-entries.store', request()->only(['gym', 'branch'])) }}" method="POST" class="mt-4 grid gap-4">
                            @csrf
                            <x-form-select name="branch_id" label="Branch" :selected="old('branch_id', request('branch_id', request('branch')))" :options="['' => 'Gym-wide'] + $branches->pluck('name', 'id')->all()" />
                            <x-form-select name="entry_type" label="Entry Type" :selected="old('entry_type', 'expense')" :options="['expense' => 'Expense', 'other_income' => 'Other income', 'refund' => 'Refund', 'adjustment' => 'Adjustment']" />
                            <x-form-select name="adjustment_direction" label="Adjustment Direction" :selected="old('adjustment_direction', 'outflow')" :options="['inflow' => 'Inflow', 'outflow' => 'Outflow']" />
                            <x-form-select name="category" label="Category" :selected="old('category', 'rent')" :options="$ledgerCategoryOptions" />
                            <x-form-input name="title" label="Title" :value="old('title')" placeholder="July rent, treadmill repair, owner deposit" />
                            <x-form-input name="amount" label="Amount" type="number" step="0.01" :value="old('amount')" />
                            <x-form-select name="payment_mode" label="Payment Mode" :selected="old('payment_mode')" :options="['' => 'Not specified', 'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank']" />
                            <x-form-input name="reference" label="Reference" :value="old('reference')" placeholder="Invoice no, bank ref, voucher" />
                            <x-form-input name="occurred_at" label="Occurred At" type="datetime-local" :value="old('occurred_at', now()->format('Y-m-d\\TH:i'))" />
                            <div>
                                <label for="description" class="panel-label">Description</label>
                                <textarea id="description" name="description" class="panel-textarea" rows="4" placeholder="Optional note for why this spend or adjustment was recorded">{{ old('description') }}</textarea>
                            </div>
                            <x-action-button type="submit" class="w-full justify-center">Record Ledger Entry</x-action-button>
                        </form>
                    @else
                        <x-empty-state title="Manual finance entry locked" message="You have view access to ledger data, but posting spend or adjustments requires billing management access." />
                    @endif
                </x-premium-card>

                <x-premium-card class="p-4">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Ledger Category Breakdown</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($ledgerCategoryBreakdown as $row)
                            <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ str($row->category ?: 'other')->replace('_', ' ')->title() }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ str($row->direction)->title() }} • {{ $row->entries_count }} entries</div>
                                </div>
                                <div class="font-semibold {{ $row->direction === 'inflow' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">₹{{ number_format((float) $row->total_amount, 2) }}</div>
                            </div>
                        @empty
                            <x-empty-state title="No category data" message="Spend and collection categories will appear here once ledger entries exist." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Collections Ledger</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Member payment receipts, collector identity, and invoice references.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                </div>
                @if ($payments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[1180px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Mode</th>
                                    <th>Collector</th>
                                    <th>Receipt</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $payment->member?->name ?? 'Member' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->branch?->name ?? 'Branch missing' }}</div>
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $payment->membership?->membershipPlan?->name ?? 'Plan' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($payment->paid_at)->format('d M Y h:i A') ?: 'No payment date' }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                <x-status-badge :label="str($payment->membership?->payment_status ?? $payment->status)->replace('_', ' ')->title()" :tone="match((string) ($payment->membership?->payment_status ?? '')) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', default => 'neutral' }" />
                                            </div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ strtoupper((string) ($payment->payment_mode ?? '-')) }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->collector?->name ?? $payment->receiver?->name ?? 'System' }}</div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $payment->receipt_number ?? $payment->receipt?->receipt_number ?? 'No receipt' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->external_reference ?? 'No external ref' }}</div>
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.show', array_merge(request()->only(['gym', 'branch']), ['payment' => $payment->id])) }}">View</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.invoice', array_merge(request()->only(['gym', 'branch']), ['payment' => $payment->id])) }}">Invoice PDF</x-action-button>
                                                @if ($payment->member)
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.payments', array_merge(request()->only(['gym', 'branch']), ['member' => $payment->member_id])) }}">History</x-action-button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        @if ($canCollectPayments)
                            <x-empty-state title="No payments recorded" message="Collect the first payment for this scope to start building the ledger." :action-href="route('web.gym.payments.create', request()->query())" action-label="Collect Payment" />
                        @else
                            <x-empty-state title="No payments recorded" message="No payments have been recorded in the current scope yet." />
                        @endif
                    </div>
                @endif

                @if ($payments->hasPages())
                    <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        {{ $payments->links() }}
                    </div>
                @endif
            </x-table-wrapper>

            <div class="space-y-4">
                <x-premium-card class="p-4">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Mode Breakdown</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($paymentModeBreakdown as $row)
                            <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ strtoupper((string) ($row->payment_mode ?: 'unknown')) }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $row->payments_count }} payments</div>
                                </div>
                                <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $row->total_amount, 2) }}</div>
                            </div>
                        @empty
                            <x-empty-state title="No mode data" message="Payment mode mix will appear here once transactions exist." />
                        @endforelse
                    </div>
                </x-premium-card>

                <x-premium-card class="p-4">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Branch Collection</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($branchCollections as $row)
                            <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $row->branch?->name ?? 'Gym-wide' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $row->payments_count }} payments</div>
                                </div>
                                <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $row->total_amount, 2) }}</div>
                            </div>
                        @empty
                            <x-empty-state title="No branch collection data" message="Branch-wise collections will appear here once payments exist." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Pending Dues</h3>
                </div>
                @if ($pendingDues->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[900px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Due</th>
                                    <th>Due Date</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingDues as $membership)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'Branch missing' }}</div>
                                        </td>
                                        <td>{{ $membership->membershipPlan?->name ?? 'Plan' }}</td>
                                        <td><x-status-badge :label="'₹'.number_format((float) $membership->due_amount, 2)" :tone="($membership->payment_status ?? '') === 'overdue' ? 'danger' : 'warning'" /></td>
                                        <td>{{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                @if ($canCollectPayments)
                                                    <form action="{{ route('web.gym.payments.mark-paid', $membership) }}" method="POST" data-confirm-submit data-confirm-title="Mark membership paid?" data-confirm-message="This will record a paid state for the selected membership." data-confirm-button="Mark paid">
                                                        @csrf
                                                        <div data-confirm-payload>
                                                            <input type="hidden" name="payment_mode" value="cash">
                                                            <input type="hidden" name="paid_at" value="{{ now()->toIso8601String() }}">
                                                            <input type="hidden" name="notes" value="Marked paid from web payments dashboard">
                                                        </div>
                                                        <x-action-button type="submit">Mark Paid</x-action-button>
                                                    </form>
                                                @endif
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.payments', array_merge(request()->only(['gym', 'branch']), ['member' => $membership->member_id])) }}">History</x-action-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        <x-empty-state title="No pending dues" message="No pending collections remain in the current scope." />
                    </div>
                @endif
            </x-table-wrapper>

            <x-premium-card class="p-4">
                <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Payment Edit History</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Collection and payment-status edits are recorded with actor and timestamp.</p>
                <div class="mt-4">
                    <x-web.audit-timeline :items="$paymentAuditTimeline" empty-title="No payment edit history yet" empty-message="Payment trust history will appear here once collections or status edits happen." />
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
