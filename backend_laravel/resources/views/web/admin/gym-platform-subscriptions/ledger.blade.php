@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">All Gym Billing</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.edit', $subscription) }}">Edit Subscription</x-action-button>
        <form method="POST" action="{{ route('web.admin.gym-platform-subscriptions.renew', $subscription) }}">
            @csrf
            <x-action-button type="submit">Renew Now</x-action-button>
        </form>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="$subscription->status" />
                        <x-status-badge :label="$subscription->auto_renew ? 'Auto Renew' : 'Manual Renew'" tone="verified" />
                    </div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $subscription->gym?->name ?? 'Gym billing ledger' }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $subscription->plan?->name ?? ($subscription->plan_snapshot['name'] ?? 'Custom billing') }}
                        • {{ $subscription->plan_snapshot['cadence_label'] ?? $subscription->plan?->cadence_label ?? 'Custom cadence' }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Owner</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $subscription->gym?->owner?->name ?? 'Unknown owner' }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subscription->gym?->owner?->email ?? 'No owner email' }}</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current Cycle</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ optional($subscription->starts_at)->format('d M Y') ?: 'n/a' }} to {{ optional($subscription->renews_at)->format('d M Y') ?: 'n/a' }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Billing ₹{{ number_format((float) $subscription->billing_amount, 0) }}</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Invoiced" :value="'₹'.number_format($invoiceSummary['total_invoiced'], 0)" hint="Lifetime platform billing" tone="violet" />
            <x-stat-card label="Collected" :value="'₹'.number_format($invoiceSummary['paid_revenue'], 0)" hint="Paid invoices" tone="emerald" />
            <x-stat-card label="Open Balance" :value="'₹'.number_format($invoiceSummary['open_balance'], 0)" hint="Due and overdue invoices" tone="amber" />
            <x-stat-card label="Overdue" :value="'₹'.number_format($invoiceSummary['overdue_balance'], 0)" hint="Past due exposure" tone="rose" />
        </div>

        <x-table-wrapper class="overflow-hidden">
            @if ($invoices->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1240px]">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Cycle</th>
                                <th>Amounts</th>
                                <th>Status</th>
                                <th>Audit</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($invoice->issued_at)->format('d M Y h:i A') ?: 'Issued automatically' }}</div>
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
                                            @if ($invoice->payment_reference)
                                                <div class="text-xs text-slate-500 dark:text-slate-400">Ref {{ $invoice->payment_reference }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $invoice->generatedBy?->name ?? 'System' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            @if ($invoice->paidBy)
                                                Collected by {{ $invoice->paidBy->name }}
                                            @else
                                                Awaiting collection
                                            @endif
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
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-6">
                    <x-empty-state title="No invoices available" message="This subscription does not have any billing ledger entries yet." />
                </div>
            @endif

            @if ($invoices->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $invoices->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
