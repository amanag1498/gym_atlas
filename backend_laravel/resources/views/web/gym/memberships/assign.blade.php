@extends('layouts.panel')

@section('content')
    @php
        $hasExistingMembership = $latestMembership !== null;
        $defaultStartDate = old('start_date', $hasExistingMembership
            ? optional($latestMembership->expiry_date)->copy()?->addDay()?->toDateString()
            : now()->toDateString());
        $defaultPlanId = old('membership_plan_id', $latestMembership?->membership_plan_id);
    @endphp
    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-4">
            <x-stat-card label="Member" :value="$member->name" :hint="$hasExistingMembership ? 'Prepare the next cycle or switch plan safely' : 'Assign membership in current gym scope'" tone="sky" />
            <x-stat-card label="Current Gym" :value="$member->memberProfile?->gym?->name ?? 'Independent'" hint="Backend-controlled gym context" tone="emerald" />
            <x-stat-card label="Branch" :value="$member->memberProfile?->branch?->name ?? 'Gym-wide'" hint="Billing and attendance scope" tone="violet" />
            <x-stat-card :label="$hasExistingMembership ? 'Current Plan' : 'Plans'" :value="$hasExistingMembership ? ($latestMembership->membershipPlan?->name ?? 'Membership') : $plans->count()" :hint="$hasExistingMembership ? 'Latest active or most recent membership' : 'Available membership plans'" tone="amber" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.7fr_1.3fr]">
            <x-premium-card class="p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Member summary</p>
                <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">{{ $member->name }}</h3>
                <p class="mt-2 text-sm text-slate-400">{{ $member->email }}</p>
                <div class="mt-5 space-y-3">
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Assigned Trainer</p>
                        <p class="mt-1 font-medium text-white">{{ $member->memberProfile?->trainer?->user?->name ?? 'No trainer assigned' }}</p>
                    </div>
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Fitness Goal</p>
                        <p class="mt-1 font-medium text-white">{{ $member->memberProfile?->fitness_goal ?? 'Not set' }}</p>
                    </div>
                    <div class="panel-card-muted px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Membership flow</p>
                        <p class="mt-1 text-sm text-slate-300">
                            @if ($hasExistingMembership)
                                This member already has a membership. Use this screen to prepare the next cycle or switch the next plan without editing the old record.
                            @else
                                Custom Fee stays on this membership only. The plan base price remains unchanged platform-wide.
                            @endif
                        </p>
                    </div>
                    @if ($hasExistingMembership)
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Current membership</p>
                            <p class="mt-1 font-medium text-white">{{ $latestMembership->membershipPlan?->name ?? 'Membership' }}</p>
                            <p class="mt-1 text-sm text-slate-300">
                                {{ optional($latestMembership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($latestMembership->expiry_date)->format('d M Y') ?: 'n/a' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Due {{ optional($latestMembership->due_date)->format('d M Y') ?: 'Not set' }} • {{ ucfirst((string) $latestMembership->payment_status) }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-premium-card>

            <x-premium-card class="p-6 max-w-5xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">{{ $hasExistingMembership ? 'Membership change' : 'Membership assignment' }}</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">{{ $hasExistingMembership ? 'Change Membership Plan' : 'Assign Membership' }}</h3>
                        <p class="mt-2 text-sm text-slate-400">
                            @if ($hasExistingMembership)
                                Create the next membership cycle for this member. The current membership remains in history, and the new cycle can use the same plan or a different plan.
                            @else
                                Configure start date, due date, initial payment, and optional custom fee overrides in one billing-safe form.
                            @endif
                        </p>
                    </div>
                    <x-status-badge :label="$hasExistingMembership ? 'Next Cycle Setup' : 'Custom Fee Aware'" tone="info" />
                </div>

                @if ($hasExistingMembership)
                    <div class="mt-5 grid gap-3 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current plan</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $latestMembership->membershipPlan?->name ?? 'Membership' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current cycle ends</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ optional($latestMembership->expiry_date)->format('d M Y') ?: 'n/a' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Suggested next start</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $defaultStartDate ? \Illuminate\Support\Carbon::parse($defaultStartDate)->format('d M Y') : 'n/a' }}</p>
                        </div>
                    </div>
                @endif

                <form action="{{ route('web.gym.members.assign-membership.store', $member) }}" method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
            @csrf
            <input type="hidden" name="gym_id" value="{{ $member->memberProfile?->gym_id }}">
            <input type="hidden" name="branch_id" value="{{ $member->memberProfile?->branch_id }}">
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            <div class="md:col-span-2">
                <label class="panel-label">Membership Plan</label>
                <select name="membership_plan_id" class="panel-select" required>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((int) $defaultPlanId === $plan->id)>{{ $plan->name }} • {{ $plan->duration_label }} • {{ $plan->price_label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="panel-label">Start Date</label>
                <input type="date" name="start_date" value="{{ $defaultStartDate }}" class="panel-input" required>
            </div>
            <div>
                <label class="panel-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="panel-input">
                <p class="mt-1 text-xs text-slate-500">Leave empty to align due date with the cycle end automatically.</p>
            </div>
            <div><label class="panel-label">Expiry Date</label><input type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="panel-input"></div>
            <div>
                <label class="panel-label">Initial Payment</label>
                <input name="amount_paid" class="panel-input" value="{{ old('amount_paid', 0) }}">
                <p class="mt-1 text-xs text-slate-500">Recorded as a payment entry using cash mode by default.</p>
            </div>
            <div><label class="panel-label">Initial Payment Mode</label><select name="initial_payment_mode" class="panel-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank">Bank</option></select></div>
            <div><label class="panel-label">Payment Date</label><input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="panel-input"></div>
            <div><label class="panel-label">External Reference</label><input name="external_reference" value="{{ old('external_reference') }}" class="panel-input" placeholder="UPI / bank / receipt reference"></div>
            <div class="md:col-span-2"><label class="panel-label">Payment Notes</label><textarea name="payment_notes" class="panel-textarea" placeholder="Optional notes for initial payment">{{ old('payment_notes') }}</textarea></div>
            <div class="md:col-span-2"><label class="panel-label">Status</label><select name="status" class="panel-select"><option value="active">Active</option><option value="frozen">Frozen</option><option value="cancelled">Cancelled</option></select></div>
            <div class="md:col-span-2 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-4">
                <div class="flex items-center gap-3"><input type="hidden" name="custom_fee_enabled" value="0"><label class="text-sm text-slate-200"><input type="checkbox" name="custom_fee_enabled" value="1"> Enable custom fee now</label></div>
                <p class="mt-2 text-xs text-slate-500">When enabled, provide a reason and member-specific pricing adjustments below.</p>
            </div>
            <div><label class="panel-label">Custom Fee Amount</label><input name="custom_fee_amount" class="panel-input"></div>
            <div><label class="panel-label">Custom Joining Fee</label><input name="custom_joining_fee" class="panel-input"></div>
            <div><label class="panel-label">Discount Type</label><select name="discount_type" class="panel-select"><option value="none">None</option><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select></div>
            <div><label class="panel-label">Discount Amount</label><input name="discount_amount" class="panel-input"></div>
            <div><label class="panel-label">Partial Month Fee</label><input name="partial_month_fee" class="panel-input"></div>
            <div><label class="panel-label">PT Custom Fee</label><input name="pt_custom_fee" class="panel-input"></div>
            <div class="md:col-span-2"><label class="panel-label">Custom Fee Reason</label><textarea name="custom_fee_reason" class="panel-textarea"></textarea></div>
            <div class="md:col-span-2"><x-action-button type="submit" variant="primary">{{ $hasExistingMembership ? 'Create Next Membership Cycle' : 'Assign Membership' }}</x-action-button></div>
                </form>
            </x-premium-card>
        </div>
    </div>
@endsection
