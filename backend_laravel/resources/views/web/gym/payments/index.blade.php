@extends('layouts.panel')

@section('content')
    @php
        $pageView = $activeTab === 'dues' ? 'dues' : (request('view') === 'ledger' && $activeTab !== 'member' ? 'ledger' : 'collections');
        $scopeQuery = request()->only(['gym', 'branch']);
    @endphp
    <div class="space-y-4">
        <section class="panel-card p-5 sm:p-6" aria-labelledby="payments-workspace-title">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <h2 id="payments-workspace-title" class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $activeTab === 'member' ? $pageTitle : ($pageView === 'ledger' ? 'Finance ledger' : ($pageView === 'dues' ? 'Pending dues' : 'Collections')) }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $pageView === 'ledger' ? 'Review money in and out, or record a finance entry.' : ($pageView === 'dues' ? 'See balances that still need collection.' : 'Find receipts and review member payments.') }}</p>
                </div>
                @if ($canCollectPayments && $pageView !== 'ledger')
                    <a href="{{ route('web.gym.payments.create', $scopeQuery) }}" class="panel-btn-primary shrink-0">Collect payment</a>
                @elseif ($canCollectPayments)
                    <a href="#record-ledger-entry" class="panel-btn-primary shrink-0" onclick="document.getElementById('record-ledger-entry').open = true">Record an entry</a>
                @endif
            </div>
            <nav class="mt-5 flex flex-wrap gap-2 border-t border-slate-200 pt-4 dark:border-slate-800" aria-label="Payment views">
                <a href="{{ $activeTab === 'member' ? url()->current().'?'.http_build_query($scopeQuery) : route('web.gym.payments.index', $scopeQuery) }}" @class(['panel-btn-secondary !px-4 !py-2 text-sm', '!border-brand-300 !bg-brand-50 !text-brand-700 dark:!bg-brand-500/10 dark:!text-brand-300' => $pageView === 'collections']) @if($pageView === 'collections') aria-current="page" @endif>Collections</a>
                @if ($activeTab !== 'member')
                    <a href="{{ route('web.gym.dues.index', $scopeQuery) }}" @class(['panel-btn-secondary !px-4 !py-2 text-sm', '!border-brand-300 !bg-brand-50 !text-brand-700 dark:!bg-brand-500/10 dark:!text-brand-300' => $pageView === 'dues']) @if($pageView === 'dues') aria-current="page" @endif>Dues</a>
                    <a href="{{ route('web.gym.payments.index', $scopeQuery + ['view' => 'ledger']) }}" @class(['panel-btn-secondary !px-4 !py-2 text-sm', '!border-brand-300 !bg-brand-50 !text-brand-700 dark:!bg-brand-500/10 dark:!text-brand-300' => $pageView === 'ledger']) @if($pageView === 'ledger') aria-current="page" @endif>Finance ledger</a>
                @endif
                <a href="{{ route('web.gym.compensation.index', $scopeQuery) }}" class="ml-auto self-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Salary & commission →</a>
            </nav>
        </section>

        @if ($pageView === 'ledger')
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Inflow" :value="'₹'.number_format((float) $ledgerSummary['inflow'], 2)" hint="Posted receipts" tone="success" />
            <x-stat-card label="Outflow" :value="'₹'.number_format((float) $ledgerSummary['outflow'], 2)" hint="Posted spend" tone="danger" />
            <x-stat-card label="Net cash" :value="((float) $ledgerSummary['net'] < 0 ? '-' : '').'₹'.number_format(abs((float) $ledgerSummary['net']), 2)" hint="Inflow minus outflow" tone="violet" />
            <x-stat-card label="Closing balance" :value="((float) $ledgerSummary['closing_balance'] < 0 ? '-' : '').'₹'.number_format(abs((float) $ledgerSummary['closing_balance']), 2)" hint="Visible ledger balance" tone="emerald" />
        </div>
        @elseif ($pageView === 'dues')
        <div class="grid gap-3 sm:grid-cols-3">
            <x-stat-card label="Open memberships" :value="$pendingDues->total()" hint="Balances to collect" tone="sky" />
            <x-stat-card label="Open dues" :value="'₹'.number_format((float) $summary['pending_due_amount'], 2)" hint="Total pending" tone="amber" />
            <x-stat-card label="Overdue" :value="'₹'.number_format((float) $summary['overdue_due_amount'], 2)" hint="Needs attention" tone="danger" />
        </div>
        @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Recorded" :value="$summary['recorded_payments']" hint="Filtered payments" tone="sky" />
            <x-stat-card label="Collected" :value="'₹'.number_format((float) $summary['collected_amount'], 2)" hint="Captured amount" tone="success" />
            <x-stat-card label="Open dues" :value="'₹'.number_format((float) $summary['pending_due_amount'], 2)" hint="Pending collection" tone="amber" />
            <x-stat-card label="Overdue" :value="'₹'.number_format((float) $summary['overdue_due_amount'], 2)" hint="Needs attention" tone="danger" />
        </div>
        @endif

        <details class="panel-card px-5 py-4 sm:px-6">
            <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-100">More finance metrics and trends</summary>
            <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="This month" :value="'₹'.number_format((float) $monthlyCollection, 2)" hint="Collection" tone="info" />
                <x-stat-card label="Manual entries" :value="$ledgerSummary['manual_entries']" hint="Owner-posted rows" tone="amber" />
                <x-stat-card label="Reversed entries" :value="$ledgerSummary['reversed_entries']" hint="Corrected rows" tone="neutral" />
            </div>
        <div class="mt-4 grid gap-4 xl:grid-cols-3">
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
        </details>

        @if ($pageView === 'collections')
        <section class="panel-card p-4 sm:p-5" aria-label="Find collections">
            <form method="GET" class="space-y-3">
                <input type="hidden" name="gym" value="{{ request('gym') }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_minmax(180px,220px)_auto] xl:items-end">
                    <x-form-input name="member_search" label="Search member" :value="request('member_search')" placeholder="Name, email, or phone" />
                    <x-form-select name="payment_status" label="Payment status" :selected="request('payment_status')" :options="['' => 'All statuses', 'paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue']" />
                    <div class="flex items-end gap-2"><x-action-button type="submit">Search</x-action-button><a href="{{ $activeTab === 'member' ? url()->current().'?'.http_build_query($scopeQuery) : route('web.gym.payments.index', $scopeQuery) }}" class="panel-btn-secondary">Reset</a></div>
                </div>
                <details @if(request()->filled('branch_id') || request()->filled('payment_mode') || request()->filled('start_date') || request()->filled('end_date')) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-brand-600 dark:text-brand-400">More filters</summary>
                    <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-4">
                        <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                        <x-form-select name="payment_mode" label="Payment mode" :selected="request('payment_mode')" :options="['' => 'All modes', 'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank']" />
                        <x-form-input name="start_date" label="From date" type="date" :value="request('start_date')" />
                        <x-form-input name="end_date" label="To date" type="date" :value="request('end_date')" />
                    </div>
                </details>
            </form>
        </section>
        @endif

        @if ($pageView === 'ledger')
        <div class="space-y-4">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Finance entries</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Money in, money out, and the running balance.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['ledger_export' => 'csv']) }}">Export Ledger</x-action-button>
                </div>
                <form method="GET" class="grid gap-3 border-b border-slate-200/80 px-4 py-4 md:grid-cols-2 xl:grid-cols-5 dark:border-slate-800">
                    <input type="hidden" name="gym" value="{{ request('gym') }}">
                    <input type="hidden" name="view" value="ledger">
                    @if (request('branch'))
                        <input type="hidden" name="branch" value="{{ request('branch') }}">
                    @endif
                    <x-form-input name="ledger_search" label="Search Ledger" :value="request('ledger_search')" />
                    <x-form-select name="ledger_direction" label="Direction" :selected="request('ledger_direction')" :options="['' => 'All', 'inflow' => 'Inflow', 'outflow' => 'Outflow']" />
                    <x-form-select name="ledger_status" label="Ledger State" :selected="request('ledger_status')" :options="['' => 'All', 'posted' => 'Posted', 'reversed' => 'Reversed']" />
                    <x-form-select name="ledger_entry_type" label="Entry Type" :selected="request('ledger_entry_type')" :options="['' => 'All', 'membership_collection' => 'Membership collection', 'expense' => 'Expense', 'other_income' => 'Other income', 'refund' => 'Refund', 'adjustment' => 'Adjustment']" />
                    <div class="flex items-end gap-2">
                        <x-action-button type="submit">Apply</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', $scopeQuery + ['view' => 'ledger']) }}">Reset</x-action-button>
                    </div>
                </form>

                @if ($ledgerEntries->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table w-full min-w-[760px]">
                            <thead>
                                <tr>
                                    <th>Entry</th>
                                    <th>When</th>
                                    <th>Amount</th>
                                    <th>Running balance</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgerEntries as $entry)
                                    @php
                                        $runningBalance = (float) ($entry->running_balance ?? 0);
                                        $isManual = $entry->source_type === 'manual';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $entry->title }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($entry->entry_type)->replace('_', ' ')->title() }} · {{ str($entry->category)->replace('_', ' ')->title() }} @if($entry->reference) · {{ $entry->reference }} @endif</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $isManual ? 'Manual' : 'Payment sync' }} · By {{ $entry->creator?->name ?? 'System' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ optional($entry->occurred_at)->format('d M Y') ?: 'No date' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $entry->branch?->name ?? 'Gym-wide' }} · {{ strtoupper((string) ($entry->payment_mode ?: 'No mode')) }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold {{ $entry->direction === 'inflow' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">
                                                {{ $entry->direction === 'outflow' ? '-' : '+' }}₹{{ number_format((float) $entry->amount, 2) }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($entry->direction)->title() }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $runningBalance < 0 ? '-' : '' }}₹{{ number_format(abs($runningBalance), 2) }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($entry->status)->replace('_', ' ')->title() }}</div>
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

            <div class="grid gap-4 xl:grid-cols-2">
                <details id="record-ledger-entry" class="panel-card p-5" @if($errors->any()) open @endif>
                    <summary class="cursor-pointer text-base font-semibold text-slate-950 dark:text-white">Record an expense, income, refund, or adjustment</summary>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Add a manual finance entry with its date and reference.</p>
                    @if ($canCollectPayments)
                        <form action="{{ route('web.gym.payments.ledger-entries.store', request()->only(['gym', 'branch'])) }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <x-form-select id="ledger_entry_branch_id" name="branch_id" label="Branch" :selected="old('branch_id', request('branch_id', request('branch')))" :options="['' => 'Gym-wide'] + $branches->pluck('name', 'id')->all()" />
                            <x-form-select name="entry_type" label="Entry Type" :selected="old('entry_type', 'expense')" :options="['expense' => 'Expense', 'other_income' => 'Other income', 'refund' => 'Refund', 'adjustment' => 'Adjustment']" />
                            <x-form-select name="adjustment_direction" label="Adjustment Direction" :selected="old('adjustment_direction', 'outflow')" :options="['inflow' => 'Inflow', 'outflow' => 'Outflow']" />
                            <x-form-select name="category" label="Category" :selected="old('category', 'rent')" :options="$ledgerCategoryOptions" />
                            <x-form-input name="title" label="Title" :value="old('title')" placeholder="July rent, treadmill repair, owner deposit" />
                            <x-form-input name="amount" label="Amount" type="number" step="0.01" :value="old('amount')" />
                            <x-form-select id="ledger_entry_payment_mode" name="payment_mode" label="Payment Mode" :selected="old('payment_mode')" :options="['' => 'Not specified', 'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank']" />
                            <x-form-input name="reference" label="Reference" :value="old('reference')" placeholder="Invoice no, bank ref, voucher" />
                            <x-form-input name="occurred_at" label="Occurred At" type="datetime-local" :value="old('occurred_at', now()->format('Y-m-d\\TH:i'))" />
                            <div class="sm:col-span-2">
                                <label for="description" class="panel-label">Description</label>
                                <textarea id="description" name="description" class="panel-textarea" rows="4" placeholder="Optional note for why this spend or adjustment was recorded">{{ old('description') }}</textarea>
                            </div>
                            <x-action-button type="submit" class="justify-center sm:col-span-2">Record ledger entry</x-action-button>
                        </form>
                    @else
                        <x-empty-state title="Manual finance entry locked" message="You have view access to ledger data, but posting spend or adjustments requires billing management access." />
                    @endif
                </details>

                <details class="panel-card p-5">
                    <summary class="cursor-pointer text-base font-semibold text-slate-950 dark:text-white">Category breakdown</summary>
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
                </details>
            </div>
        </div>
        @endif

        @if ($pageView === 'collections')
        <div class="space-y-4">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Recent collections</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $payments->total() }} {{ $payments->total() === 1 ? 'payment' : 'payments' }} in this view</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                </div>
                @if ($payments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table w-full min-w-[760px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Payment</th>
                                    <th>Amount</th>
                                    <th>Receipt</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                            @if ($payment->member)
                                                <a href="{{ route('web.gym.members.payments', array_merge($scopeQuery, ['member' => $payment->member_id])) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ $payment->member->name }}</a>
                                            @else
                                                <span class="font-semibold text-slate-950 dark:text-white">Member</span>
                                            @endif
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->branch?->name ?? 'Branch missing' }}</div>
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $payment->membership?->membershipPlan?->name ?? 'Plan' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($payment->paid_at)->format('d M Y, h:i A') ?: 'No payment date' }} · {{ strtoupper((string) ($payment->payment_mode ?? 'Unknown mode')) }}</div>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                <x-status-badge :label="str($payment->membership?->payment_status ?? $payment->status)->replace('_', ' ')->title()" :tone="match((string) ($payment->membership?->payment_status ?? '')) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', default => 'neutral' }" />
                                            </div>
                                        </td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $payment->receipt_number ?? $payment->receipt?->receipt_number ?? 'No receipt' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">By {{ $payment->collector?->name ?? $payment->receiver?->name ?? 'System' }}</div>
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-2 whitespace-nowrap">
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.show', array_merge(request()->only(['gym', 'branch']), ['payment' => $payment->id])) }}">View</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.invoice', array_merge(request()->only(['gym', 'branch']), ['payment' => $payment->id])) }}">Invoice PDF</x-action-button>
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

            <details class="panel-card p-5">
                <summary class="cursor-pointer text-sm font-semibold text-slate-950 dark:text-white">Collection breakdown by mode and branch</summary>
                <div class="mt-4 grid gap-4 border-t border-slate-200 pt-4 dark:border-slate-800 sm:grid-cols-2">
                <section>
                    <h3 class="font-semibold text-slate-950 dark:text-white">Payment mode</h3>
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
                </section>

                <section>
                    <h3 class="font-semibold text-slate-950 dark:text-white">Branch collection</h3>
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
                </section>
                </div>
            </details>
        </div>
        @endif

        @if ($pageView === 'dues')
        <div class="space-y-4">
            <section class="panel-card p-4 sm:p-5" aria-label="Find dues">
                <form method="GET" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_minmax(170px,220px)_minmax(170px,220px)_auto] xl:items-end">
                    @foreach ($scopeQuery as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <x-form-input name="member_search" label="Search member" :value="request('member_search')" placeholder="Name, email, or phone" />
                    <x-form-select name="payment_status" label="Status" :selected="request('payment_status')" :options="['' => 'All open dues', 'overdue' => 'Overdue', 'partial' => 'Partially paid', 'unpaid' => 'Unpaid']" />
                    <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                    <div class="flex items-end gap-2"><x-action-button type="submit">Search</x-action-button><a href="{{ route('web.gym.dues.index', $scopeQuery) }}" class="panel-btn-secondary">Reset</a></div>
                </form>
            </section>
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Pending dues</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $pendingDues->total() }} {{ $pendingDues->total() === 1 ? 'membership' : 'memberships' }} with a balance due</p>
                </div>
                @if ($pendingDues->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table w-full min-w-[720px]">
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
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'Branch missing' }} · Membership #{{ $membership->id }}</div>
                                        </td>
                                        <td>{{ $membership->membershipPlan?->name ?? 'Plan' }}</td>
                                        <td><x-status-badge :label="'₹'.number_format((float) $membership->due_amount, 2)" :tone="($membership->payment_status ?? '') === 'overdue' ? 'danger' : 'warning'" /></td>
                                        <td>{{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                @if ($canCollectPayments)
                                                    <x-action-button as="a" href="{{ route('web.gym.payments.create', array_merge($scopeQuery, ['member_membership_id' => $membership->id])) }}">Collect payment</x-action-button>
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
                @if ($pendingDues->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $pendingDues->links() }}</div>
                @endif
            </x-table-wrapper>
        </div>
        @endif

        <details class="panel-card p-5">
            <summary class="cursor-pointer text-sm font-semibold text-slate-950 dark:text-white">Payment edit history</summary>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Collection and status changes with their actor and time.</p>
            <div class="mt-4"><x-web.audit-timeline :items="$paymentAuditTimeline" empty-title="No payment edit history yet" empty-message="Payment changes will appear here once collections or status edits happen." /></div>
        </details>
    </div>
@endsection
