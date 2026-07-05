@extends('layouts.panel')

@section('content')
    <div class="space-y-4">
        <x-premium-card class="p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700">Pricing override desk</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Custom Fees</h1>
                        <span class="text-sm text-slate-500">Override pricing, discount approvals, joining-fee exceptions, and PT add-on billing.</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.custom-fees.audit-logs', request()->query()) }}">Audit Logs</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->only(['gym', 'branch']), ['report' => 'custom_fee'])) }}">Custom Fee Report</x-action-button>
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Memberships" :value="$summary['memberships']" hint="Override scope" tone="sky" />
            <x-stat-card label="Discounted" :value="$summary['discounted_memberships']" hint="Waived or reduced" tone="warning" />
            <x-stat-card label="Due Amount" :value="number_format((float) $summary['due_amount'], 2)" hint="Open custom-fee dues" tone="amber" />
            <x-stat-card label="Pending Review" :value="$summary['pending_approvals']" hint="Needs approval trace" tone="danger" />
            <x-stat-card label="Joining Waived" :value="$summary['waived_joining_fee']" hint="Fee exception count" tone="violet" />
            <x-stat-card label="PT Overrides" :value="$summary['pt_overrides']" hint="Personal training custom fee" tone="info" />
        </div>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 bg-slate-50/90 px-4 py-3">
                <h3 class="text-lg font-semibold tracking-tight text-slate-950">Custom fee filters</h3>
            </div>
            <form method="GET" class="grid gap-3 px-4 py-4 md:grid-cols-2 xl:grid-cols-7">
                <input type="hidden" name="gym" value="{{ request('gym') }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <x-form-input name="member_search" label="Search Member" :value="request('member_search')" placeholder="Name, email, phone" />
                <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                <x-form-select name="plan_id" label="Plan" :selected="request('plan_id')" :options="['' => 'All plans'] + $plans->pluck('name', 'id')->all()" />
                <x-form-select name="payment_status" label="Payment" :selected="request('payment_status')" :options="['' => 'All states', 'paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue']" />
                <x-form-select name="approval_state" label="Review State" :selected="request('approval_state')" :options="['' => 'All reviews', 'pending' => 'Pending', 'approved' => 'Approved']" />
                <label class="flex items-end">
                    <span class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                        <input type="checkbox" name="discounted_only" value="1" @checked(request()->boolean('discounted_only'))>
                        Discounted only
                    </span>
                </label>
                <label class="flex items-end">
                    <span class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                        <input type="checkbox" name="due_only" value="1" @checked(request()->boolean('due_only'))>
                        Due only
                    </span>
                </label>
                <div class="xl:col-span-7 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.custom-fees.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            @if ($memberships->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1400px]">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Override Mix</th>
                                <th>Commercials</th>
                                <th>Review</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($memberships as $membership)
                                @php
                                    $latestAudit = $membership->latestCustomFeeAuditLog;
                                    $member = $membership->member;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="min-w-[14rem]">
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $member?->name ?? 'Member removed' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $member?->email ?: ($member?->phone ?: 'No contact details') }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'Branch missing' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[12rem]">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $membership->membershipPlan?->name ?? 'Plan not set' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}</div>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <x-status-badge :label="ucfirst((string) $membership->status)" />
                                                <x-status-badge :label="ucfirst((string) $membership->payment_status)" :tone="match((string) $membership->payment_status) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', default => 'neutral' }" />
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[14rem] flex flex-wrap gap-1.5">
                                            @if ($membership->custom_fee_enabled)
                                                <x-status-badge :label="'Custom price ₹'.number_format((float) $membership->custom_fee_amount, 2)" tone="info" />
                                            @endif
                                            @if ((float) $membership->discount_amount > 0)
                                                <x-status-badge :label="'Discount ₹'.number_format((float) $membership->discount_amount, 2)" tone="warning" />
                                            @endif
                                            @if ($membership->joining_fee_waived)
                                                <x-status-badge label="Joining waived" tone="warning" />
                                            @endif
                                            @if ((float) $membership->partial_month_fee > 0)
                                                <x-status-badge :label="'Partial ₹'.number_format((float) $membership->partial_month_fee, 2)" tone="verified" />
                                            @endif
                                            @if ((float) $membership->pt_custom_fee > 0)
                                                <x-status-badge :label="'PT ₹'.number_format((float) $membership->pt_custom_fee, 2)" tone="verified" />
                                            @endif
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $membership->custom_fee_reason ?: 'No custom fee reason captured yet.' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="font-semibold text-slate-950 dark:text-white">Final ₹{{ number_format((float) $membership->final_payable_amount, 2) }}</div>
                                        <div class="mt-1">Paid ₹{{ number_format((float) $membership->amount_paid, 2) }}</div>
                                        <div class="mt-1">Due ₹{{ number_format((float) $membership->due_amount, 2) }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Original ₹{{ number_format((float) $membership->default_plan_price, 2) }} • Joining ₹{{ number_format((float) $membership->default_joining_fee, 2) }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($membership->approved_by_admin_id)
                                                <x-status-badge label="Reviewed" tone="success" />
                                            @else
                                                <x-status-badge label="Pending review" tone="warning" />
                                            @endif
                                        </div>
                                        <div class="mt-2">{{ $latestAudit?->changer?->name ?? 'System' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($latestAudit?->changed_at)->format('d M Y H:i') ?: 'Never changed' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex min-w-[18rem] justify-end flex-wrap gap-2">
                                            @if ($member)
                                                <x-action-button as="a" href="{{ route('web.gym.members.custom-fee', ['member' => $member->id, 'member_membership_id' => $membership->id]) }}">{{ $canEditCustomFee ? 'Edit' : 'View' }}</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.show', ['member' => $member->id]) }}">Member</x-action-button>
                                            @else
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.custom-fees.audit-logs', request()->query()) }}">Audit</x-action-button>
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
                    <x-empty-state title="No custom fee memberships" message="Member-specific pricing overrides will appear here once custom fee, discount, PT, or joining-fee exceptions are applied." />
                </div>
            @endif

            @if ($memberships->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    {{ $memberships->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
