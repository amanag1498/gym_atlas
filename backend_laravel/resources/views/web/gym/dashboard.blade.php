@extends('layouts.panel')

@section('content')
    @php
        $visibleQuickActions = collect($quickActions)->filter(fn ($action) => $action['visible'])->values();
        $trainerCoverage = $stats['total_members'] > 0
            ? (int) round((($stats['total_members'] - $stats['members_without_trainer_count']) / $stats['total_members']) * 100)
            : 0;
        $collectionRisk = (float) $stats['pending_dues'] > 0
            ? (int) min(100, round(((float) $stats['overdue_dues'] / (float) $stats['pending_dues']) * 100))
            : 0;
        $topMetrics = [
            ['label' => 'Monthly Collection', 'value' => '₹'.number_format((float) $stats['monthly_collection'], 2), 'hint' => 'Collected this month', 'tone' => 'emerald'],
            ['label' => 'Open Dues', 'value' => '₹'.number_format((float) $stats['pending_dues'], 2), 'hint' => $collectionRisk > 0 ? $collectionRisk.'% overdue risk' : 'No overdue pressure', 'tone' => $collectionRisk > 0 ? 'rose' : 'sky'],
            ['label' => 'Active Members', 'value' => $stats['active_members'].' / '.$stats['total_members'], 'hint' => 'Live member base', 'tone' => 'sky'],
            ['label' => 'Trainer Coverage', 'value' => $trainerCoverage.'%', 'hint' => $stats['members_without_trainer_count'].' without trainer', 'tone' => $stats['members_without_trainer_count'] > 0 ? 'amber' : 'emerald'],
            ['label' => 'Today Check-ins', 'value' => $stats['today_check_ins'], 'hint' => 'Attendance pulse', 'tone' => 'violet'],
            ['label' => 'Pending Trials', 'value' => $stats['pending_trial_requests'], 'hint' => 'Lead follow-up queue', 'tone' => $stats['pending_trial_requests'] > 0 ? 'amber' : 'sky'],
        ];
        $pulseRows = [
            ['label' => 'Expiring Soon', 'value' => $stats['expiring_soon'], 'hint' => 'Memberships due within 7 days', 'tone' => 'warning'],
            ['label' => 'Overdue Memberships', 'value' => $stats['overdue_memberships'], 'hint' => 'High-priority collections', 'tone' => 'danger'],
            ['label' => 'Custom Fee Reviews', 'value' => $stats['pending_custom_fee_reviews'], 'hint' => 'Pricing exceptions awaiting review', 'tone' => 'warning'],
            ['label' => 'Inactive Members', 'value' => $stats['inactive_members_count'], 'hint' => 'No recent activity or disabled state', 'tone' => 'danger'],
        ];
        $paymentMix = [
            ['label' => 'Paid', 'value' => $paymentHealth['paid'], 'tone' => 'success'],
            ['label' => 'Partial', 'value' => $paymentHealth['partial'], 'tone' => 'warning'],
            ['label' => 'Unpaid', 'value' => $paymentHealth['unpaid'], 'tone' => 'neutral'],
            ['label' => 'Overdue', 'value' => $paymentHealth['overdue'], 'tone' => 'danger'],
        ];
    @endphp

    <div class="space-y-4">
        <section class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 text-white shadow-[0_24px_80px_-36px_rgba(15,23,42,0.75)] dark:border-slate-800">
            <div class="grid gap-6 px-5 py-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)] lg:px-6">
                <div>
                    <div class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        Gym Command Center
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $gym->name }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                        Daily operations across members, collections, trainer load, renewals, attendance, and follow-up work in the current scope.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @forelse ($visibleQuickActions->take(5) as $action)
                            <x-action-button as="a" :variant="$loop->first ? 'primary' : 'secondary'" href="{{ $action['route'] }}">{{ $action['label'] }}</x-action-button>
                        @empty
                            <x-action-button as="a" href="{{ route('web.gym.members.index', request()->query()) }}">Open Members</x-action-button>
                        @endforelse
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Member Health</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $stats['excellent_engagement_count'] + $stats['good_engagement_count'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">strong engagement profiles</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Collection Risk</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $collectionRisk }}%</div>
                        <div class="mt-1 text-sm text-slate-300">of open dues currently overdue</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Trainer Load</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $stats['total_trainers'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">trainers • ratio {{ $stats['trainer_member_ratio'] ?? 'N/A' }}</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Override Pricing</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $stats['custom_fee_members_count'] }}</div>
                        <div class="mt-1 text-sm text-slate-300">members with custom commercial setup</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($topMetrics as $metric)
                <x-stat-card :label="$metric['label']" :value="$metric['value']" :hint="$metric['hint']" :tone="$metric['tone']" />
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Operational Pulse</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The shortest path to the decisions that affect daily member experience.</p>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pulseRows as $row)
                        <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)_auto] md:items-center">
                            <div class="font-medium text-slate-950 dark:text-white">{{ $row['label'] }}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">{{ $row['hint'] }}</div>
                            <x-status-badge :label="$row['value']" :tone="$row['tone']" />
                        </div>
                    @endforeach
                </div>
            </x-premium-card>

            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Billing Health Mix</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Payment-state balance and engagement distribution in one view.</p>
                </div>
                <div class="grid gap-3 p-5 md:grid-cols-2">
                    <div class="space-y-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Payment states</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($paymentMix as $chip)
                                <x-status-badge :label="$chip['label'].' '.$chip['value']" :tone="$chip['tone']" />
                            @endforeach
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Engagement tiers</div>
                        <div class="flex flex-wrap gap-2">
                            <x-status-badge :label="'Excellent '.$stats['excellent_engagement_count']" tone="success" />
                            <x-status-badge :label="'Good '.$stats['good_engagement_count']" tone="info" />
                            <x-status-badge :label="'Needs Attention '.$stats['needs_attention_engagement_count']" tone="warning" />
                            <x-status-badge :label="'High Risk '.$stats['high_risk_engagement_count']" tone="danger" />
                        </div>
                    </div>
                </div>
            </x-premium-card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Collections focus</p>
                <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Renewals and due recovery</h3>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Expiring soon</span>
                        <x-status-badge :label="$stats['expiring_soon']" tone="warning" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Overdue memberships</span>
                        <x-status-badge :label="$stats['overdue_memberships']" tone="danger" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Pending dues</span>
                        <x-status-badge :label="'₹'.number_format((float) $stats['pending_dues'], 2)" tone="info" />
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">People coverage</p>
                <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Trainer and engagement pressure</h3>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Members without trainer</span>
                        <x-status-badge :label="$stats['members_without_trainer_count']" tone="warning" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Inactive members</span>
                        <x-status-badge :label="$stats['inactive_members_count']" tone="danger" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">High risk engagement</span>
                        <x-status-badge :label="$stats['high_risk_engagement_count']" tone="danger" />
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Front desk lane</p>
                <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Daily activity signals</h3>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Today check-ins</span>
                        <x-status-badge :label="$stats['today_check_ins']" tone="success" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Pending trials</span>
                        <x-status-badge :label="$stats['pending_trial_requests']" tone="info" />
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Custom fee reviews</span>
                        <x-status-badge :label="$stats['pending_custom_fee_reviews']" tone="warning" />
                    </div>
                </div>
            </x-premium-card>
        </div>

        @if (!($onboarding['completed'] ?? false))
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Setup Checklist</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Unfinished setup steps that still affect operations and listing quality.</p>
                    </div>
                    <x-status-badge :label="($onboarding['completed_count'] ?? 0).' / '.($onboarding['total_steps'] ?? 7).' completed'" tone="info" />
                </div>
                <div class="h-1.5 overflow-hidden bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-sky-500" style="width: {{ $onboarding['progress_percent'] ?? 0 }}%"></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Step</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($onboarding['steps'] ?? []) as $step)
                                @php
                                    $route = match($step['key']) {
                                        'gym_profile' => route('web.gym.profile.edit', request()->query()),
                                        'first_branch' => route('web.gym.branches.index', request()->query()),
                                        'membership_plans' => route('web.gym.membership-plans.index', request()->query()),
                                        'trainers' => route('web.gym.trainers.index', request()->query()),
                                        'first_member' => route('web.gym.members.index', request()->query()),
                                        'public_listing' => route('web.gym.public-listing.edit', request()->query()),
                                        default => route('web.gym.dashboard', request()->query()),
                                    };
                                @endphp
                                <tr>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $step['label'] }}</td>
                                    <td><x-status-badge :label="($step['completed'] ?? false) ? 'Done' : 'Pending'" :tone="($step['completed'] ?? false) ? 'success' : 'warning'" /></td>
                                    <td class="text-right">
                                        @if (!($step['completed'] ?? false))
                                            <x-action-button as="a" variant="secondary" href="{{ $route }}">Open</x-action-button>
                                        @else
                                            <span class="text-sm text-slate-500 dark:text-slate-400">Complete</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-table-wrapper>
        @endif

        @if ($visibility['billing'])
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Collections Desk</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Renewals, due amounts, and overdue balances that need action first.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', request()->query()) }}">Open Payments</x-action-button>
                </div>
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th>Member</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expiringMemberships as $membership)
                                <tr>
                                    <td><x-status-badge label="Expiring" tone="warning" /></td>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</td>
                                    <td>{{ $membership->membershipPlan?->name ?? 'Membership' }}</td>
                                    <td>₹{{ number_format((float) $membership->due_amount, 2) }}</td>
                                    <td>{{ optional($membership->expiry_date)->format('d M Y') ?: 'No expiry' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td><x-status-badge label="Expiring" tone="warning" /></td>
                                    <td colspan="4" class="text-sm text-slate-500 dark:text-slate-400">No memberships expiring within the next seven days.</td>
                                </tr>
                            @endforelse
                            @forelse ($pendingMemberships as $membership)
                                <tr>
                                    <td><x-status-badge label="Due" tone="warning" /></td>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</td>
                                    <td>{{ $membership->membershipPlan?->name ?? 'Membership' }}</td>
                                    <td>₹{{ number_format((float) $membership->due_amount, 2) }}</td>
                                    <td>{{ optional($membership->due_date)->format('d M Y') ?: 'No due date' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td><x-status-badge label="Due" tone="warning" /></td>
                                    <td colspan="4" class="text-sm text-slate-500 dark:text-slate-400">No pending collections in the selected scope.</td>
                                </tr>
                            @endforelse
                            @forelse ($overdueMemberships as $membership)
                                <tr>
                                    <td><x-status-badge label="Overdue" tone="danger" /></td>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $membership->member?->name ?? 'Member' }}</td>
                                    <td>{{ $membership->membershipPlan?->name ?? 'Membership' }}</td>
                                    <td>₹{{ number_format((float) $membership->due_amount, 2) }}</td>
                                    <td>{{ optional($membership->due_date)->format('d M Y') ?: 'No due date' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td><x-status-badge label="Overdue" tone="danger" /></td>
                                    <td colspan="4" class="text-sm text-slate-500 dark:text-slate-400">No overdue balances in the selected scope.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-table-wrapper>
        @endif

        <div class="grid gap-4 2xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            @if ($visibility['members_view'] || $visibility['billing'])
                <x-table-wrapper class="overflow-hidden p-0">
                    <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Recent Movement</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Latest member signups and payment activity in the current scope.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[980px]">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Subject</th>
                                    <th>Detail</th>
                                    <th>Status / Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($visibility['members_view'])
                                    @forelse ($recentMembers as $member)
                                        <tr>
                                            <td><x-status-badge label="New Member" tone="info" /></td>
                                            <td class="font-medium text-slate-950 dark:text-white">{{ $member->user?->name ?? 'Member' }}</td>
                                            <td>{{ $member->fitness_goal ?: 'No fitness goal yet' }}</td>
                                            <td>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <x-status-badge :label="ucfirst($member->membership_status ?? 'active')" />
                                                    @if ($member->engagement_score)
                                                        <x-status-badge :label="$member->engagement_score['category']" :tone="match($member->engagement_score['category']) { 'Excellent' => 'success', 'Good' => 'info', 'Needs Attention' => 'warning', default => 'danger' }" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td><x-status-badge label="New Member" tone="info" /></td><td colspan="3" class="text-slate-500 dark:text-slate-400">No recent members.</td></tr>
                                    @endforelse
                                @endif
                                @if ($visibility['billing'])
                                    @forelse ($recentPayments as $payment)
                                        <tr>
                                            <td><x-status-badge label="Payment" tone="success" /></td>
                                            <td class="font-medium text-slate-950 dark:text-white">{{ $payment->member?->name ?? 'Member' }}</td>
                                            <td>{{ $payment->membership?->membershipPlan?->name ?? 'Membership' }} • {{ optional($payment->paid_at)->format('d M Y, h:i A') }}</td>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }}</div>
                                                <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ strtoupper((string) $payment->payment_mode) }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td><x-status-badge label="Payment" tone="success" /></td><td colspan="3" class="text-slate-500 dark:text-slate-400">No recent payments.</td></tr>
                                    @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                </x-table-wrapper>
            @endif

            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Live Ops Feed</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Attendance and audit activity that operators notice first.</p>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @if ($visibility['attendance'])
                        @forelse ($recentAttendance as $log)
                            <div class="flex items-start justify-between gap-3 px-5 py-4">
                                <div>
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $log->member?->name ?? 'Member' }}</div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $log->branch?->name ?? 'Gym' }} • {{ optional($log->checked_in_at)->format('d M Y, h:i A') }}</div>
                                </div>
                                <x-status-badge :label="str($log->check_in_method)->replace('_', ' ')->title()" :tone="$log->check_in_method === 'biometric' ? 'info' : 'warning'" />
                            </div>
                        @empty
                            <div class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No recent attendance.</div>
                        @endforelse
                    @endif

                    @if ($visibility['members_view'])
                        @forelse ($recentActivity as $item)
                            <div class="flex items-start justify-between gap-3 px-5 py-4">
                                <div>
                                    <div class="font-medium text-slate-950 dark:text-white">{{ str($item->event)->replace('_', ' ')->title() }}</div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $item->branch?->name ?? 'Gym-wide' }} • {{ $item->actor?->name ?? 'System' }}</div>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ optional($item->occurred_at)->diffForHumans() ?? 'Recent' }}</div>
                            </div>
                        @empty
                            <div class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No recent activity.</div>
                        @endforelse
                    @endif
                </div>
            </x-premium-card>
        </div>

        @if ($visibility['members_view'])
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Branch Performance</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Members, trainers, trials, attendance, dues, and collection by branch.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.branches.index', request()->query()) }}">Manage Branches</x-action-button>
                </div>
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Members</th>
                                <th>Trainers</th>
                                <th>Trials</th>
                                <th>Today Check-ins</th>
                                <th>Pending Dues</th>
                                <th>Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($branchSnapshots as $branch)
                                <tr>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $branch['name'] }}</td>
                                    <td>{{ $branch['members'] }}</td>
                                    <td>{{ $branch['trainers'] }}</td>
                                    <td>{{ $branch['trials'] }}</td>
                                    <td>{{ $branch['today_check_ins'] }}</td>
                                    <td>₹{{ number_format((float) $branch['pending_dues'], 2) }}</td>
                                    <td>₹{{ number_format((float) $branch['monthly_collection'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7"><x-empty-state title="No branches available" message="Branch performance will appear here once branches are configured." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-table-wrapper>
        @endif
    </div>
@endsection
