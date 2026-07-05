@extends('layouts.panel')

@section('content')
    @php
        $currentMembership = $membershipHistory->first();
        $collectedAmount = (float) $paymentHistory->sum('amount');
        $engagement = $memberProfile->engagement_score ?? null;
        $assignedTrainer = $memberProfile->assignedTrainer;
        $trainerProfile = $assignedTrainer?->managedTrainerProfile;
        $recentTimeline = collect($membershipTimeline)->take(6);
        $hasCurrentMembership = $currentMembership !== null;
        $dueAmount = (float) ($currentMembership?->due_amount ?? 0);
        $creditAmount = abs(min($dueAmount, 0));
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-[30px] border border-slate-200/80 bg-white shadow-[0_30px_90px_-55px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950">
            <div class="grid gap-6 border-b border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 px-5 py-6 text-white dark:border-slate-800 lg:grid-cols-[minmax(0,1.1fr)_340px] lg:px-6">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        Member Overview
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $member->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        {{ $member->email }} · {{ $memberProfile->branch?->name ?? 'Gym-wide' }} ·
                        {{ $currentMembership?->membershipPlan?->name ?? 'No active plan' }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-status-badge :label="ucfirst($memberProfile->membership_status ?? 'active')" tone="info" />
                        @if ($hasCurrentMembership)
                            <x-status-badge :label="ucfirst((string) $currentMembership->payment_status)" :tone="match((string) $currentMembership->payment_status) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', 'overpaid' => 'verified', default => 'neutral' }" />
                        @endif
                        @if ($assignedTrainer)
                            <x-status-badge :label="'Trainer '.$assignedTrainer->name" tone="neutral" />
                        @endif
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Current billing state</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">
                            @if (! $hasCurrentMembership)
                                No membership
                            @elseif ($dueAmount < 0)
                                Credit ₹{{ number_format($creditAmount, 2) }}
                            @else
                                Due ₹{{ number_format($dueAmount, 2) }}
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-300">
                            @if ($hasCurrentMembership)
                                {{ optional($currentMembership->due_date)->format('d M Y') ?: 'No due date set' }}
                            @else
                                Assign a plan to start billing
                            @endif
                        </p>
                    </div>
                    <div class="rounded-[24px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Risk and activity</p>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $engagement['score'] ?? 0 }} / 100</div>
                        <p class="mt-1 text-sm text-slate-300">{{ $engagement['summary'] ?? 'No engagement signal available yet.' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 px-5 py-4 lg:px-6">
                <a href="{{ route('web.gym.members.edit', $member) }}" class="panel-btn-secondary">Edit / Trainer</a>
                @if ($canManageMemberships)
                    <a href="{{ route('web.gym.members.assign-membership', $member) }}" class="panel-btn-secondary">{{ $hasCurrentMembership ? 'Change Membership Plan' : 'Assign Membership' }}</a>
                    <a href="{{ route('web.gym.members.custom-fee', ['member' => $member->id, 'member_membership_id' => $currentMembership?->id] + request()->query()) }}" class="panel-btn-primary">Custom Fee</a>
                @endif
                @if ($canCollectPayments)
                    <a href="{{ route('web.gym.payments.create', ['member_id' => $member->id] + request()->query()) }}" class="panel-btn-secondary">Collect Payment</a>
                @endif
                <form method="POST" action="{{ route('web.gym.members.remove-from-gym', ['member' => $member->id] + request()->query()) }}" data-confirm-submit data-confirm-title="Remove member from gym?" data-confirm-message="This will cancel active gym access and make the member independent. Payment, attendance, membership, and workout history stay available for audit." data-confirm-button="Remove From Gym">
                    @csrf
                    <x-action-button type="submit" variant="danger">Remove From Gym</x-action-button>
                </form>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Current Plan" :value="$currentMembership?->membershipPlan?->name ?? 'No Plan'" hint="Current operational membership" tone="sky" />
            <x-stat-card label="Collected" :value="number_format($collectedAmount, 2)" hint="Recent payment history total" tone="emerald" />
            <x-stat-card label="Workouts" :value="$workoutSummary['total_sessions']" hint="Total recorded sessions" tone="violet" />
            <x-stat-card label="Attendance 30d" :value="$engagement['attendance_last_30_days'] ?? 0" hint="Recent check-ins" tone="amber" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
            <div class="space-y-6">
                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Profile Snapshot</h3>
                            <p class="panel-section-copy">Only the operational details needed most often.</p>
                        </div>
                        <a href="{{ route('web.gym.members.edit', $member) }}" class="panel-btn-secondary">Open Full Edit</a>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Goal</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $memberProfile->fitness_goal ?: 'Not set' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $memberProfile->experience_level ?: 'Experience level pending' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Body</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $memberProfile->height_cm ?: 'n/a' }} cm • {{ $memberProfile->weight_kg ?: 'n/a' }} kg</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $memberProfile->gender ?: 'Gender not set' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Emergency</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $memberProfile->emergency_contact_name ?: 'Not set' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $memberProfile->emergency_contact_phone ?: 'No emergency phone' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Health notes</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $memberProfile->medical_notes ?: $memberProfile->injury_notes ?: 'No medical or injury notes added.' }}</p>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Biometric attendance</p>
                            <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $memberProfile->biometric_enabled ? 'Enabled' : 'Disabled' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $memberProfile->biometric_identifier ?: 'No biometric identifier enrolled' }}</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Membership Summary</h3>
                            <p class="panel-section-copy">Current plan first, followed by only the most recent membership records.</p>
                        </div>
                        @if ($hasCurrentMembership)
                            <a href="{{ route('web.gym.memberships.show', ['membership' => $currentMembership->id] + request()->query()) }}" class="panel-btn-secondary">Open Membership</a>
                        @endif
                    </div>

                    @if ($hasCurrentMembership)
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <div class="panel-card-muted p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current plan</p>
                                <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $currentMembership->membershipPlan?->name ?? 'Membership' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ optional($currentMembership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($currentMembership->expiry_date)->format('d M Y') ?: 'n/a' }}</p>
                            </div>
                            <div class="panel-card-muted p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Billing</p>
                                <p class="mt-2 font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $currentMembership->amount_paid, 2) }} paid / ₹{{ number_format((float) $currentMembership->final_payable_amount, 2) }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Due {{ optional($currentMembership->due_date)->format('d M Y') ?: 'Not set' }} • {{ ucfirst((string) $currentMembership->payment_status) }}</p>
                            </div>
                        </div>
                    @else
                        <div class="mt-5">
                            <x-web.empty-state title="No memberships yet" message="Assign the first membership for this member." />
                        </div>
                    @endif

                    @if ($membershipHistory->count() > 1)
                        <div class="mt-5 space-y-3">
                            @foreach ($membershipHistory->slice(1, 2) as $membership)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-semibold text-slate-950 dark:text-white">{{ $membership->membershipPlan?->name ?? 'Membership' }}</p>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ optional($membership->start_date)->format('d M Y') ?: 'n/a' }} to {{ optional($membership->expiry_date)->format('d M Y') ?: 'n/a' }}</p>
                                        </div>
                                        <a href="{{ route('web.gym.memberships.show', ['membership' => $membership->id] + request()->query()) }}" class="panel-btn-secondary !px-3 !py-2 !text-xs">Open</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-premium-card>

                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Recent Payments</h3>
                            <p class="panel-section-copy">Only the latest payment records needed for support and billing checks.</p>
                        </div>
                        <a href="{{ route('web.gym.members.payments', ['member' => $member->id] + request()->query()) }}" class="panel-btn-secondary">Payment History</a>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse ($paymentHistory->take(4) as $payment)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $payment->amount, 2) }} • {{ strtoupper((string) $payment->payment_mode) }}</div>
                                        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $payment->membership?->membershipPlan?->name ?? 'Membership' }} • {{ optional($payment->paid_at)->format('d M Y, h:i A') ?: 'No payment date' }}</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('web.gym.payments.show', ['payment' => $payment->id] + request()->query()) }}" class="panel-btn-secondary !px-3 !py-2 !text-xs">View Payment</a>
                                        <a href="{{ route('web.gym.payments.invoice', ['payment' => $payment->id] + request()->query()) }}" class="panel-btn-secondary !px-3 !py-2 !text-xs">Invoice PDF</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-web.empty-state title="No payment history yet" message="Recorded member payments will appear here." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>

            <div class="space-y-6">
                @if ($engagement)
                    <x-premium-card class="p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="panel-section-title">Engagement Signal</h3>
                                <p class="panel-section-copy">Quick risk read instead of a full analytics block.</p>
                            </div>
                            <x-status-badge :label="$engagement['category']" :tone="match($engagement['category']) { 'Excellent' => 'success', 'Good' => 'info', 'Needs Attention' => 'warning', default => 'danger' }" />
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="panel-card-muted p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Score</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $engagement['score'] }}/100</p>
                            </div>
                            <div class="panel-card-muted p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Last check-in</p>
                                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-white">{{ ($engagement['days_since_last_check_in'] ?? null) !== null ? ($engagement['days_since_last_check_in'].' days ago') : 'No check-in' }}</p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300">
                            {{ $engagement['summary'] }}
                        </div>
                    </x-premium-card>
                @endif

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Assigned Trainer</h3>
                    <p class="panel-section-copy">Only the trainer context useful for operations.</p>
                    @if ($assignedTrainer && $trainerProfile)
                        <div class="mt-5 space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900">
                                    @if (filled($trainerProfile->profile_photo_url) || filled($assignedTrainer->avatar))
                                        <img src="{{ $trainerProfile->profile_photo_url ?: $assignedTrainer->avatar }}" alt="{{ $assignedTrainer->name }}" class="h-full w-full object-cover">
                                    @else
                                        <span class="text-lg font-semibold text-slate-950 dark:text-white">{{ strtoupper(substr($assignedTrainer->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $assignedTrainer->name }}</h4>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $trainerProfile->branch?->name ?? 'Gym-wide coverage' }}</p>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ collect($trainerProfile->specializations ?? [])->take(3)->implode(' • ') ?: 'Specialization pending' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <x-web.empty-state title="No trainer assigned" message="Assign a trainer to show coaching context here." />
                    @endif
                </x-premium-card>

                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Recent Activity</h3>
                            <p class="panel-section-copy">Single trusted feed instead of multiple parallel timelines.</p>
                        </div>
                    </div>
                    <div class="mt-5">
                        <x-web.audit-timeline
                            :items="$recentTimeline"
                            empty-title="No recent activity yet"
                            empty-message="Membership, payment, attendance, and workout activity will appear here."
                        />
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Recent Attendance</h3>
                    <p class="panel-section-copy">Latest check-ins only.</p>
                    <div class="mt-5 overflow-x-auto">
                        <table class="panel-table min-w-[520px]">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Checked In By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendanceHistory->take(5) as $log)
                                    <tr>
                                        <td>{{ optional($log->checked_in_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $log->branch?->name ?? 'N/A' }}</td>
                                        <td>{{ $log->checkedInByUser?->name ?? 'System' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No attendance history found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
