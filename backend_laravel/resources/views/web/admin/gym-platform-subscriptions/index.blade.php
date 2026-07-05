@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        @section('page_actions')
            <x-action-button as="a" href="{{ route('web.admin.gym-platform-subscriptions.create') }}">Assign Billing</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.reports.platform-billing') }}">Billing Report</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.platform-subscription-plans.index') }}">Platform Plans</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.audit-logs.index', ['action' => 'gym-subscription']) }}">Billing Audit</x-action-button>
        @endsection

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Records" :value="$totalSubscriptionsCount" hint="Subscription contracts" tone="sky" />
            <x-stat-card label="Active" :value="$activeSubscriptionsCount" hint="Live platform accounts" tone="emerald" />
            <x-stat-card label="Trialing" :value="$trialingSubscriptionsCount" hint="Trial platform accounts" tone="amber" />
            <x-stat-card label="Past Due" :value="$pastDueSubscriptionsCount" hint="Service risk" tone="violet" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Monthly Due" :value="'₹'.number_format($monthlyRevenueDue, 0)" hint="$monthlyDueCount.' invoices due this month'" tone="sky" />
            <x-stat-card label="Collected" :value="'₹'.number_format($realizedRevenue, 0)" hint="$paidInvoiceCount.' invoices paid this month'" tone="emerald" />
            <x-stat-card label="Overdue Exposure" :value="'₹'.number_format($overdueRevenueExposure, 0)" hint="$overdueCount.' invoices overdue'" tone="amber" />
            <x-stat-card label="Run Rate" :value="'₹'.number_format($runRateRevenue, 0)" hint="Current recurring base" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-3">
                    <x-form-input name="search" label="Search Gym or Plan" :value="request('search')" placeholder="Gym, owner, or plan" />
                </div>
                <div class="xl:col-span-2">
                    <label class="panel-label" for="gym_id">Gym</label>
                    <select id="gym_id" name="gym_id" class="panel-select">
                        <option value="">All gyms</option>
                        @foreach ($gyms as $gym)
                            <option value="{{ $gym->id }}" @selected((string) request('gym_id') === (string) $gym->id)>{{ $gym->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-form-select name="status" label="Status" :selected="request('status')" :options="['' => 'All statuses', 'trialing' => 'Trialing', 'active' => 'Active', 'past_due' => 'Past Due', 'cancelled' => 'Cancelled', 'expired' => 'Expired']" />
                </div>
                <div class="xl:col-span-6 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Collection Queue</h3>
                    <p class="panel-section-copy">Invoices due this month or already overdue, with direct payment and renewal controls.</p>
                </div>
                <x-status-badge :label="$dueSubscriptions->count().' queued'" tone="warning" />
            </div>

            @if ($dueSubscriptions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1180px]">
                        <thead>
                            <tr>
                                <th>Gym</th>
                                <th>Invoice</th>
                                <th>Plan</th>
                                <th>Cycle</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dueSubscriptions as $invoice)
                                @php($subscription = $invoice->subscription)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $subscription?->gym?->name ?? 'Unknown gym' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription?->gym?->owner?->email ?? 'No owner email' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $invoice->invoice_number }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Due {{ optional($invoice->due_at)->format('d M Y') ?: 'n/a' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $subscription?->plan?->name ?? ($subscription?->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription?->plan_snapshot['cadence_label'] ?? $subscription?->plan?->cadence_label ?? 'Custom cadence' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ optional($invoice->period_starts_at)->format('d M Y') ?: 'n/a' }} to {{ optional($invoice->period_ends_at)->format('d M Y') ?: 'n/a' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $invoice->status === 'overdue' ? 'Immediate collection' : 'Current cycle due' }}</div>
                                    </td>
                                    <td class="text-sm font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $invoice->total_amount, 0) }}</td>
                                    <td><x-status-badge :label="$invoice->status" /></td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($invoice->status !== 'paid')
                                                <form method="POST" action="{{ route('web.admin.gym-platform-subscription-invoices.mark-paid', $invoice) }}" data-confirm-submit data-confirm-title="Record platform payment?" data-confirm-message="This will mark {{ $invoice->invoice_number }} as paid." data-confirm-button="Mark Paid">
                                                    @csrf
                                                    <x-action-button type="submit">Mark Paid</x-action-button>
                                                </form>
                                            @endif
                                            @if ($subscription)
                                                <form method="POST" action="{{ route('web.admin.gym-platform-subscriptions.renew', $subscription) }}" data-confirm-submit data-confirm-title="Renew subscription?" data-confirm-message="This will open the next billing cycle for {{ $subscription->gym?->name ?? 'this gym' }}." data-confirm-button="Renew">
                                                    @csrf
                                                    <x-action-button type="submit" variant="secondary">Renew</x-action-button>
                                                </form>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $subscription) }}">Ledger</x-action-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No collections due right now" message="Invoices due this month or already overdue will surface here automatically." />
                </div>
            @endif
        </x-table-wrapper>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Billing Ledger</h3>
                    <p class="panel-section-copy">Recent invoices across all gyms, including payment state and cycle coverage.</p>
                </div>
                <x-status-badge :label="$invoiceLedger->total().' invoices'" tone="info" />
            </div>

            @if ($invoiceLedger->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1240px]">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Gym</th>
                                <th>Cycle</th>
                                <th>Amounts</th>
                                <th>Collection</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoiceLedger as $invoice)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $invoice->plan?->name ?? ($invoice->subscription?->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $invoice->subscription?->gym?->name ?? 'Unknown gym' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $invoice->subscription?->gym?->owner?->email ?? 'No owner email' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ optional($invoice->period_starts_at)->format('d M Y') ?: 'n/a' }} to {{ optional($invoice->period_ends_at)->format('d M Y') ?: 'n/a' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Due {{ optional($invoice->due_at)->format('d M Y') ?: 'n/a' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $invoice->total_amount, 0) }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Base ₹{{ number_format((float) $invoice->subtotal_amount, 0) }} • Setup ₹{{ number_format((float) $invoice->setup_fee_amount, 0) }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-2">
                                            <div class="flex flex-wrap gap-2">
                                                <x-status-badge :label="$invoice->status" />
                                                @if ($invoice->paid_at)
                                                    <x-status-badge :label="'Paid '.optional($invoice->paid_at)->format('d M Y')" tone="success" />
                                                @endif
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ $invoice->generatedBy?->name ?? 'System' }}
                                                @if ($invoice->paidBy)
                                                    • Collected by {{ $invoice->paidBy->name }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($invoice->status !== 'paid')
                                                <form method="POST" action="{{ route('web.admin.gym-platform-subscription-invoices.mark-paid', $invoice) }}" data-confirm-submit data-confirm-title="Record platform payment?" data-confirm-message="This will mark {{ $invoice->invoice_number }} as paid." data-confirm-button="Mark Paid">
                                                    @csrf
                                                    <x-action-button type="submit">Mark Paid</x-action-button>
                                                </form>
                                            @endif
                                            @if ($invoice->subscription)
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $invoice->subscription) }}">View Ledger</x-action-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No invoices generated yet" message="Invoices will appear here after subscriptions are assigned or renewed." />
                </div>
            @endif

            @if ($invoiceLedger->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $invoiceLedger->links() }}
                </div>
            @endif
        </x-table-wrapper>

        <x-table-wrapper class="overflow-hidden">
            @if ($subscriptions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1320px]">
                        <thead>
                            <tr>
                                <th>Gym</th>
                                <th>Plan</th>
                                <th>Commercials</th>
                                <th>Services</th>
                                <th>Lifecycle</th>
                                <th>Latest Invoice</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscriptions as $subscription)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $subscription->gym?->name ?? 'Unknown gym' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription->gym?->owner?->email ?? 'No owner email' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $subscription->plan?->name ?? ($subscription->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription->plan_snapshot['cadence_label'] ?? $subscription->plan?->cadence_label ?? 'Custom cadence' }}</div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-slate-900 dark:text-slate-100">₹{{ number_format((float) $subscription->billing_amount, 0) }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Setup ₹{{ number_format((float) $subscription->setup_fee_amount, 0) }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription->auto_renew ? 'Auto renew enabled' : 'Manual renewal' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @forelse (array_slice($subscription->included_services ?? [], 0, 3) as $service)
                                                <x-status-badge :label="$service" tone="info" />
                                            @empty
                                                <x-status-badge label="No services" tone="neutral" />
                                            @endforelse
                                            @if (count($subscription->included_services ?? []) > 3)
                                                <x-status-badge :label="'+' . (count($subscription->included_services ?? []) - 3) . ' more'" tone="neutral" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-2">
                                            <div class="flex flex-wrap gap-2">
                                                <x-status-badge :label="$subscription->status" />
                                                @if ($subscription->trial_ends_at)
                                                    <x-status-badge :label="'Trial until '.$subscription->trial_ends_at->format('d M Y')" tone="warning" />
                                                @endif
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                Starts {{ optional($subscription->starts_at)->format('d M Y') ?? 'n/a' }}
                                                @if ($subscription->renews_at)
                                                    • Renews {{ $subscription->renews_at->format('d M Y') }}
                                                @endif
                                                @if ($subscription->ends_at)
                                                    • Ends {{ $subscription->ends_at->format('d M Y') }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        @if ($subscription->latestInvoice)
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $subscription->latestInvoice->invoice_number }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">₹{{ number_format((float) $subscription->latestInvoice->total_amount, 0) }} • {{ $subscription->latestInvoice->status }}</div>
                                        @else
                                            <div class="text-xs text-slate-500 dark:text-slate-400">No invoice yet</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($subscription->gym)
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $subscription->gym) }}">Gym</x-action-button>
                                            @endif
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $subscription) }}">Ledger</x-action-button>
                                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.edit', $subscription) }}">Edit</x-action-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-6">
                    <x-empty-state
                        title="No gym billing records found"
                        message="Assign a platform subscription to a gym so the platform revenue model is visible and manageable from admin."
                        action-label="Assign Billing"
                        :action-href="route('web.admin.gym-platform-subscriptions.create')"
                    />
                </div>
            @endif

            @if ($subscriptions->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $subscriptions->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
