@extends('layouts.panel')

@section('content')
    @php
        $visibleQuickActions = collect($quickActions)->filter(fn ($action) => $action['visible'])->values();
        $trainerCoverage = $stats['trainer_coverage_base'] > 0
            ? (int) round((($stats['trainer_coverage_base'] - $stats['members_without_trainer_count']) / $stats['trainer_coverage_base']) * 100)
            : 0;
        $collectionRisk = (float) $stats['pending_dues'] > 0
            ? (int) min(100, round(((float) $stats['overdue_dues'] / (float) $stats['pending_dues']) * 100))
            : 0;
        $topMetrics = [
            ['label' => 'Monthly Collection', 'value' => '₹'.number_format((float) $stats['monthly_collection'], 2), 'hint' => 'Collected this month', 'tone' => 'emerald'],
            ['label' => 'Open Dues', 'value' => '₹'.number_format((float) $stats['pending_dues'], 2), 'hint' => $collectionRisk > 0 ? $collectionRisk.'% overdue risk' : 'No overdue pressure', 'tone' => $collectionRisk > 0 ? 'rose' : 'sky'],
            ['label' => 'Active Members', 'value' => $stats['active_members'].' / '.$stats['total_members'], 'hint' => 'Live member base', 'tone' => 'sky'],
            ['label' => 'Renewals Due', 'value' => $stats['renewal_candidates'], 'hint' => $stats['expired_yesterday'].' expired yesterday', 'tone' => $stats['renewal_candidates'] > 0 ? 'amber' : 'emerald'],
            ['label' => 'Members in Gym', 'value' => $stats['members_in_gym'], 'hint' => $stats['today_unique_members'].' unique today · '.$stats['today_check_ins'].' visits', 'tone' => 'violet'],
            ['label' => 'Trainer Coverage', 'value' => $trainerCoverage.'%', 'hint' => $stats['members_without_trainer_count'].' without trainer', 'tone' => $stats['members_without_trainer_count'] > 0 ? 'amber' : 'emerald'],
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

        @if ($visibility['memberships_view'] || $visibility['billing'] || $visibility['members_view'])
            <section aria-labelledby="daily-action-center-heading" class="overflow-hidden rounded-[26px] border border-slate-200/80 bg-white shadow-[0_20px_60px_-42px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-col gap-3 border-b border-slate-200/80 px-5 py-5 dark:border-slate-800 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-400">Today’s action center</p>
                        <h2 id="daily-action-center-heading" class="mt-1 text-xl font-semibold tracking-tight text-slate-950 dark:text-white">Members who need a decision</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Renew expired access, recover outstanding balances, and close member-service gaps.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="$stats['expired_yesterday'].' expired yesterday'" :tone="$stats['expired_yesterday'] > 0 ? 'danger' : 'success'" />
                        <x-status-badge :label="$stats['scheduled_renewals'].' renewals scheduled'" tone="info" />
                        <x-status-badge :label="$stats['members_with_dues'].' members owe payment'" :tone="$stats['members_with_dues'] > 0 ? 'warning' : 'success'" />
                        @if ($visibility['billing'] && $stats['pending_custom_fee_reviews'] > 0)
                            <a href="{{ route('web.gym.custom-fees.index', request()->query()) }}"><x-status-badge :label="$stats['pending_custom_fee_reviews'].' pricing reviews'" tone="warning" /></a>
                        @endif
                        @if ($visibility['trainers'] && $stats['overloaded_trainers_count'] > 0)
                            <a href="{{ route('web.gym.trainers.index', request()->query()) }}"><x-status-badge :label="$stats['overloaded_trainers_count'].' overloaded trainers'" tone="danger" /></a>
                        @endif
                    </div>
                </div>

                <div class="grid divide-y divide-slate-200/80 dark:divide-slate-800 xl:grid-cols-3 xl:divide-x xl:divide-y-0">
                    @if ($visibility['memberships_view'])
                        <div class="min-w-0 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-950 dark:text-white">Renewal follow-up</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Expired members without a replacement cycle.</p>
                                </div>
                                <a href="{{ route('web.gym.memberships.expired', request()->query()) }}" class="shrink-0 text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">View all</a>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                @forelse ($renewalCandidates->take(5) as $profile)
                                    @php
                                        $latestMembership = $profile->user?->memberMemberships?->first();
                                        $expiredOn = $profile->membership_expires_on;
                                        $expiryLabel = $expiredOn?->isSameDay(now()->subDay())
                                            ? 'Expired yesterday'
                                            : ($expiredOn ? 'Expired '.$expiredOn->format('d M') : 'Expired');
                                    @endphp
                                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 dark:border-slate-800 dark:bg-slate-900/70">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <a href="{{ route('web.gym.members.show', ['member' => $profile->user_id] + request()->query()) }}" class="truncate font-semibold text-slate-950 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $profile->user?->name ?? 'Member' }}</a>
                                                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $latestMembership?->membershipPlan?->name ?? 'Previous plan' }} · {{ $profile->branch?->name ?? 'Branch' }}</p>
                                            </div>
                                            <x-status-badge :label="$expiryLabel" tone="danger" />
                                        </div>
                                        @if ($visibility['manage_memberships_action'])
                                            <div class="mt-3 flex justify-end">
                                                <a href="{{ route('web.gym.members.assign-membership', ['member' => $profile->user_id] + request()->query()) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Review and renew →</a>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <x-empty-state title="No renewals waiting" message="Members who expire without a replacement cycle will appear here." />
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if ($visibility['billing'])
                        <div class="min-w-0 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-950 dark:text-white">Collection follow-up</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Current cycles ₹{{ number_format((float) $stats['current_cycle_dues'], 2) }} · expired cycles ₹{{ number_format((float) $stats['expired_cycle_dues'], 2) }}</p>
                                </div>
                                <a href="{{ route('web.gym.dues.index', request()->query()) }}" class="shrink-0 text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Open dues</a>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                @forelse ($openDueMemberships->take(5) as $membership)
                                    @php
                                        $cycleExpired = $membership->status === 'expired'
                                            || ($membership->expiry_date && $membership->expiry_date->lt(today()));
                                    @endphp
                                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 dark:border-slate-800 dark:bg-slate-900/70">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <a href="{{ route('web.gym.members.show', ['member' => $membership->member_id] + request()->query()) }}" class="truncate font-semibold text-slate-950 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $membership->member?->name ?? 'Member' }}</a>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $cycleExpired ? 'Expired cycle' : 'Current cycle' }} · due {{ optional($membership->due_date)->format('d M Y') ?: 'date not set' }}</p>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $membership->due_amount, 2) }}</div>
                                                <x-status-badge :label="$cycleExpired ? 'Expired' : ucfirst((string) $membership->payment_status)" :tone="$cycleExpired || $membership->payment_status === 'overdue' ? 'danger' : 'warning'" />
                                            </div>
                                        </div>
                                        @if ($visibility['collect_payment_action'])
                                            <div class="mt-3 flex justify-end">
                                                <a href="{{ route('web.gym.payments.create', ['member_membership_id' => $membership->id] + request()->query()) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Collect payment →</a>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <x-empty-state title="No open dues" message="Outstanding current and expired-cycle balances will appear here." />
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if ($visibility['members_view'])
                        <div class="min-w-0 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-950 dark:text-white">Member service gaps</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Coverage and engagement issues that need staff follow-up.</p>
                                </div>
                                <a href="{{ route('web.gym.members.index', request()->query()) }}" class="shrink-0 text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Members</a>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                @foreach ($membersWithoutTrainer->take(3) as $profile)
                                    <a href="{{ route('web.gym.members.show', ['member' => $profile->user_id] + request()->query()) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 transition hover:border-indigo-300 hover:bg-white dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-indigo-500/40">
                                        <div class="min-w-0"><div class="truncate font-semibold text-slate-950 dark:text-white">{{ $profile->user?->name ?? 'Member' }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Trainer assignment missing</div></div>
                                        <x-status-badge label="Assign" tone="warning" />
                                    </a>
                                @endforeach
                                @php
                                    $membersWithoutTrainerIds = $membersWithoutTrainer->take(3)->pluck('id');
                                @endphp
                                @foreach ($inactiveMembers->reject(fn ($profile) => $membersWithoutTrainerIds->contains($profile->id))->take(3) as $profile)
                                    <a href="{{ route('web.gym.members.show', ['member' => $profile->user_id] + request()->query()) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3.5 transition hover:border-indigo-300 hover:bg-white dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-indigo-500/40">
                                        <div class="min-w-0"><div class="truncate font-semibold text-slate-950 dark:text-white">{{ $profile->user?->name ?? 'Member' }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">No recent attendance activity</div></div>
                                        <x-status-badge label="Follow up" tone="danger" />
                                    </a>
                                @endforeach
                                @if ($membersWithoutTrainer->isEmpty() && $inactiveMembers->isEmpty())
                                    <x-empty-state title="Member coverage is clear" message="Trainer gaps and inactive members will appear here." />
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($visibility['attendance'])
            <x-premium-card class="overflow-hidden p-0">
                <div class="flex flex-col gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Members currently in gym</h2>
                            <x-status-badge :label="$membersInGym->count().' present'" :tone="$membersInGym->isNotEmpty() ? 'success' : 'neutral'" />
                        </div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Live open visits across the selected gym and branch scope.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :label="$stats['attendance_exceptions_count'].' Attendance Exceptions'" :tone="$stats['attendance_exceptions_count'] > 0 ? 'danger' : 'success'" />
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.attendance.index', request()->query()) }}">Open attendance desk</x-action-button>
                    </div>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                    @forelse ($membersInGym->take(8) as $presence)
                        <a href="{{ route('web.gym.members.show', ['member' => $presence->member_id] + request()->query()) }}" class="group rounded-2xl border border-slate-200 bg-slate-50/80 p-4 transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-white hover:shadow-lg hover:shadow-sky-950/5 dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-sky-500/40 dark:hover:bg-slate-900">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-slate-950 dark:text-white">{{ $presence->member?->name ?? 'Member' }}</div>
                                    <div class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $presence->branch?->name ?? 'Branch not set' }}</div>
                                </div>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]" aria-label="In gym"></span>
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                                <span>Since {{ $presence->checked_in_at?->format('h:i A') }}</span>
                                <span>{{ $presence->checked_in_at?->diffForHumans(now(), true) }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-4"><x-empty-state title="Nobody is currently checked in" message="Open visits from the attendance desk and Smart Attendance will appear here." /></div>
                    @endforelse
                </div>
                @if ($membersInGym->count() > 8)
                    <div class="border-t border-slate-200/80 px-5 py-3 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">And {{ $membersInGym->count() - 8 }} more members currently in the gym.</div>
                @endif
            </x-premium-card>
        @endif

        @if ($visibility['billing'] || $visibility['attendance'] || $visibility['members_view'])
            <section aria-labelledby="dashboard-trends-heading" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">Performance analytics</p>
                        <h2 id="dashboard-trends-heading" class="mt-1 text-xl font-semibold tracking-tight text-slate-950 dark:text-white">Trends that need attention</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Thirty-day movement and current member health for the selected gym and branch scope.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', request()->query()) }}">Open detailed reports</x-action-button>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    @if ($visibility['billing'])
                        <x-web.line-chart-card
                            title="Collection trend"
                            subtitle="Recorded payments by paid date"
                            :series="$charts['collections']"
                            value-prefix="₹"
                            accent="#12b76a"
                        />
                    @endif
                    @if ($visibility['attendance'])
                        <x-web.line-chart-card
                            title="Attendance trend"
                            subtitle="Daily member check-ins"
                            :series="$charts['attendance']"
                            unit=" check-ins"
                            accent="#465fff"
                        />
                    @endif
                </div>

                @if ($visibility['members_view'])
                    <div class="grid gap-4 lg:grid-cols-3">
                        <x-web.distribution-chart-card
                            title="Membership lifecycle"
                            subtitle="Current membership cycles by status"
                            :series="$charts['membership_health']"
                        />
                        <x-web.distribution-chart-card
                            title="Member engagement"
                            subtitle="Activity-based engagement categories"
                            :series="$charts['engagement']"
                            :colors="['#12b76a', '#2e90fa', '#f79009', '#f04438']"
                        />
                        <x-web.distribution-chart-card
                            title="Members by branch"
                            subtitle="Current member allocation across accessible branches"
                            :series="$charts['branch_members']"
                            :colors="['#465fff', '#7a5af8', '#2e90fa', '#12b76a']"
                        />
                    </div>
                @endif
            </section>
        @endif

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

        @if ($visibility['memberships_view'] || $visibility['trials'])
            <section aria-labelledby="forward-planning-heading" class="grid gap-4 xl:grid-cols-2">
                @if ($visibility['memberships_view'])
                    <x-table-wrapper class="overflow-hidden p-0">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-400">Next seven days</p>
                                <h2 id="forward-planning-heading" class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Upcoming membership expiries</h2>
                            </div>
                            <a href="{{ route('web.gym.memberships.index', request()->query()) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">All memberships</a>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($expiringMemberships as $membership)
                                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <a href="{{ route('web.gym.members.show', ['member' => $membership->member_id] + request()->query()) }}" class="truncate font-semibold text-slate-950 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $membership->member?->name ?? 'Member' }}</a>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->membershipPlan?->name ?? 'Membership' }} · expires {{ optional($membership->expiry_date)->format('d M Y') }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <x-status-badge :label="$membership->expiry_date?->isToday() ? 'Expires today' : $membership->expiry_date?->diffForHumans()" tone="warning" />
                                        @if ($visibility['manage_memberships_action'])
                                            <a href="{{ route('web.gym.members.assign-membership', ['member' => $membership->member_id] + request()->query()) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Plan renewal →</a>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="p-5"><x-empty-state title="No upcoming expiries" message="Active memberships ending within seven days will appear here." /></div>
                            @endforelse
                        </div>
                    </x-table-wrapper>
                @endif

                @if ($visibility['trials'])
                    <x-premium-card class="overflow-hidden p-0">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">Lead pipeline</p>
                                <h2 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Trials awaiting contact</h2>
                            </div>
                            <a href="{{ route('web.gym.trial-requests.index', ['status' => 'pending'] + request()->query()) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Open leads</a>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($waitingTrials as $trial)
                                <a href="{{ route('web.gym.trial-requests.show', ['trial' => $trial->id] + request()->query()) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-900/70">
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-slate-950 dark:text-white">{{ $trial->name ?: 'Unnamed lead' }}</div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $trial->branch?->name ?? 'Branch' }} · {{ $trial->assignedTrainer?->name ?? 'Trainer unassigned' }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <x-status-badge :label="$trial->preferred_date?->isToday() ? 'Today' : ($trial->preferred_date?->format('d M') ?? 'Date pending')" :tone="$trial->preferred_date?->isPast() ? 'danger' : 'warning'" />
                                        <div class="mt-1 text-xs text-slate-400">{{ $trial->phone ?: 'No phone' }}</div>
                                    </div>
                                </a>
                            @empty
                                <div class="p-5"><x-empty-state title="No pending trial leads" message="New public enquiries and trial requests will appear here." /></div>
                            @endforelse
                        </div>
                    </x-premium-card>
                @endif
            </section>
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
