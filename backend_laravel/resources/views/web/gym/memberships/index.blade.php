@extends('layouts.panel')

@section('content')
    <div class="space-y-4">
        <section class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 text-white shadow-[0_24px_80px_-36px_rgba(15,23,42,0.75)] dark:border-slate-800">
            <div class="grid gap-6 px-5 py-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)] lg:px-6">
                <div>
                    <div class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        Lifecycle and Billing
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $pageTitle }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Lifecycle control, billing pressure, renewal cadence, and pricing exceptions in a lighter renewal desk.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-action-button as="a" href="{{ route('web.gym.members.index', request()->query()) }}">Open Members</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.membership-plans.index', request()->query()) }}">Membership Plans</x-action-button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Open Dues</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">₹{{ number_format((float) $summary['due_amount'], 2) }}</div>
                        <div class="mt-1 text-sm text-slate-300">receivable across filtered memberships</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Expiring Soon</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $summary['expiring_soon'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">memberships within seven days</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Overdue</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $summary['overdue'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">immediate collection risk</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Custom Fee</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $summary['custom_fee'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">memberships with override billing</div>
                    </div>
                </div>
            </div>
        </section>

        <x-premium-card class="p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">Lifecycle views</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h2 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white">Quick segments</h2>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Move between active, expiring, and expired views.</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" :variant="$activeFilter === null ? 'primary' : 'secondary'" href="{{ route('web.gym.memberships.index', request()->only(['gym', 'branch'])) }}">All</x-action-button>
                    <x-action-button as="a" :variant="$activeFilter === 'active' ? 'primary' : 'secondary'" href="{{ route('web.gym.memberships.active', request()->only(['gym', 'branch'])) }}">Active</x-action-button>
                    <x-action-button as="a" :variant="$activeFilter === 'expiring-soon' ? 'primary' : 'secondary'" href="{{ route('web.gym.memberships.expiring-soon', request()->only(['gym', 'branch'])) }}">Expiring</x-action-button>
                    <x-action-button as="a" :variant="$activeFilter === 'expired' ? 'primary' : 'secondary'" href="{{ route('web.gym.memberships.expired', request()->only(['gym', 'branch'])) }}">Expired</x-action-button>
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Memberships" :value="$summary['memberships']" hint="Filtered records" tone="sky" />
            <x-stat-card label="Due Amount" :value="number_format((float) $summary['due_amount'], 2)" hint="Open receivable" tone="amber" />
            <x-stat-card label="Overdue" :value="$summary['overdue']" hint="Immediate follow-up" tone="danger" />
            <x-stat-card label="Frozen" :value="$summary['frozen']" hint="Paused lifecycle" tone="violet" />
            <x-stat-card label="Expiring" :value="$summary['expiring_soon']" hint="Within 7 days" tone="warning" />
            <x-stat-card label="Custom Fee" :value="$summary['custom_fee']" hint="Override billing" tone="info" />
        </div>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 bg-slate-50/90 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/80">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Membership filter</p>
                        <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Scope, cadence, payment state, and due filters</h3>
                    </div>
                    <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        {{ $memberships->total() }} memberships in scope
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('web.gym.memberships.index') }}" class="grid gap-3 px-4 py-4 md:grid-cols-2 xl:grid-cols-7">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                @if (request()->filled('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <x-form-input name="member_search" label="Search Member" :value="request('member_search')" placeholder="Name, email, phone" />
                <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                <x-form-select name="plan_id" label="Plan" :selected="request('plan_id')" :options="['' => 'All plans'] + $plans->pluck('name', 'id')->all()" />
                <x-form-select name="status" label="Lifecycle" :selected="$activeFilter ?? request('status')" :options="['' => 'All', 'active' => 'Active', 'expired' => 'Expired', 'expiring-soon' => 'Expiring Soon', 'frozen' => 'Frozen', 'cancelled' => 'Cancelled']" />
                <x-form-select name="payment_status" label="Payment" :selected="request('payment_status')" :options="['' => 'All payments', 'paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue']" />
                <x-form-select name="billing_period" label="Cadence" :selected="request('billing_period')" :options="['' => 'All cadence'] + $billingPeriods->mapWithKeys(fn ($period) => [$period => str($period)->replace('_', ' ')->title()])->all()" />
                <div class="flex items-end gap-2">
                    <label class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <input type="checkbox" name="custom_fee_only" value="1" @checked(request()->boolean('custom_fee_only'))>
                        Custom fee only
                    </label>
                    <label class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <input type="checkbox" name="due_only" value="1" @checked(request()->boolean('due_only'))>
                        Due only
                    </label>
                </div>
                <div class="xl:col-span-7 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.memberships.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Membership ledger</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A dense billing table for renewals, extensions, freezes, and pricing review.</p>
                </div>
                <div class="hidden md:flex md:flex-wrap md:gap-2">
                    <x-status-badge :label="'Due ₹'.number_format((float) $summary['due_amount'], 2)" tone="warning" />
                    <x-status-badge :label="'Overdue '.$summary['overdue']" tone="danger" />
                    <x-status-badge :label="'Custom fee '.$summary['custom_fee']" tone="info" />
                </div>
            </div>
            @if ($memberships->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1340px]">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Cycle</th>
                                <th>Billing</th>
                                <th>Collections</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($memberships as $membership)
                                @php
                                    $plan = $membership->membershipPlan;
                                    $cadenceLabel = $plan
                                        ? (($plan->billing_type === 'free' ? 'Free' : strtoupper((string) $plan->billing_type)).' • '.str($plan->billing_period)->replace('_', ' ')->title().' x '.(int) $plan->billing_interval_count)
                                        : 'No cadence';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="min-w-[14rem]">
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->member?->email ?? 'No email' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->member?->memberProfile?->branch?->name ?? 'Branch missing' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[13rem]">
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $plan?->name ?? 'Plan' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $cadenceLabel }}</div>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @if ($membership->custom_fee_enabled)
                                                    <x-status-badge label="Custom Fee" tone="info" />
                                                @endif
                                                @if ($membership->approved_by_admin_id)
                                                    <x-status-badge label="Reviewed" tone="success" />
                                                @elseif ($membership->custom_fee_enabled)
                                                    <x-status-badge label="Pending Review" tone="warning" />
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Start {{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }}</div>
                                        <div class="mt-1">Expiry {{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Due {{ optional($membership->due_date)->format('d M Y') ?: 'Not set' }}</div>
                                    </td>
                                    <td>
                                        <div class="min-w-[12rem] text-sm text-slate-600 dark:text-slate-300">
                                            <div class="font-semibold text-slate-950 dark:text-white">Payable ₹{{ number_format((float) $membership->final_payable_amount, 2) }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Base ₹{{ number_format((float) $membership->default_plan_price, 2) }} • Joining ₹{{ number_format((float) $membership->default_joining_fee, 2) }}</div>
                                            @if ($membership->custom_fee_enabled || (float) $membership->discount_amount > 0 || (float) $membership->partial_month_fee > 0 || (float) $membership->pt_custom_fee > 0)
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    @if ((float) $membership->discount_amount > 0)
                                                        <x-status-badge :label="'Discount ₹'.number_format((float) $membership->discount_amount, 2)" tone="warning" />
                                                    @endif
                                                    @if ((float) $membership->partial_month_fee > 0)
                                                        <x-status-badge :label="'Partial ₹'.number_format((float) $membership->partial_month_fee, 2)" tone="info" />
                                                    @endif
                                                    @if ((float) $membership->pt_custom_fee > 0)
                                                        <x-status-badge :label="'PT ₹'.number_format((float) $membership->pt_custom_fee, 2)" tone="verified" />
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-[11rem] text-sm text-slate-600 dark:text-slate-300">
                                            <div class="font-semibold text-slate-950 dark:text-white">Paid ₹{{ number_format((float) $membership->amount_paid, 2) }}</div>
                                            <div class="mt-1">Due ₹{{ number_format((float) $membership->due_amount, 2) }}</div>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <x-status-badge :label="ucfirst((string) $membership->payment_status)" :tone="match((string) $membership->payment_status) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', 'overpaid' => 'verified', default => 'neutral' }" />
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex min-w-[10rem] flex-wrap gap-1.5">
                                            <x-status-badge :label="ucfirst((string) $membership->status)" :tone="match((string) $membership->status) { 'active' => 'success', 'frozen' => 'warning', 'cancelled' => 'danger', 'expired' => 'neutral', default => 'info' }" />
                                            @if ($membership->expiry_date && $membership->expiry_date->isPast() && $membership->status === 'active')
                                                <x-status-badge label="Expired by date" tone="danger" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex min-w-[22rem] justify-end flex-wrap gap-2">
                                            @if ($membership->member)
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.memberships.show', ['membership' => $membership->id] + request()->query() + ['flow' => 'lifecycle']) }}">Workspace</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.show', ['member' => $membership->member->id] + request()->query()) }}">Member</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.custom-fee', ['member' => $membership->member->id, 'member_membership_id' => $membership->id] + request()->query()) }}">Pricing</x-action-button>
                                            @endif
                                            @if ($canCollectPayments && (float) $membership->due_amount > 0 && $membership->status !== 'cancelled')
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.create', array_merge(request()->only(['gym', 'branch']), ['member_membership_id' => $membership->id])) }}">Collect</x-action-button>
                                            @endif
                                            @if ($canManageMemberships)
                                                <x-action-button as="a" href="{{ route('web.gym.memberships.show', ['membership' => $membership->id] + request()->query() + ['flow' => 'lifecycle']) }}">Open Workspace</x-action-button>
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
                    <x-empty-state title="No memberships found" message="Assign the first membership to start tracking lifecycle, due amounts, and renewal cadence here." />
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
