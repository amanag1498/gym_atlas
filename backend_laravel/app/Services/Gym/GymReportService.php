<?php

namespace App\Services\Gym;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\CustomFeeAuditLog;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GymReportService
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @return array<string, mixed>
     */
    public function parseFilters(Request $request, Gym $gym, array $accessibleBranchIds): array
    {
        $selectedBranchId = $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
        if ($selectedBranchId !== null && ! in_array($selectedBranchId, $accessibleBranchIds, true)) {
            $selectedBranchId = null;
        }

        $selectedTrainerId = $request->filled('trainer_id') ? (int) $request->integer('trainer_id') : null;
        $selectedPlanId = $request->filled('plan_id') ? (int) $request->integer('plan_id') : null;

        return [
            'start_date' => $request->date('start_date')?->startOfDay() ?? now()->startOfMonth(),
            'end_date' => $request->date('end_date')?->endOfDay() ?? now()->endOfDay(),
            'branch_id' => $selectedBranchId,
            'branch_ids' => $selectedBranchId ? [$selectedBranchId] : $accessibleBranchIds,
            'trainer_id' => $selectedTrainerId,
            'plan_id' => $selectedPlanId,
            'status' => $request->string('status')->trim()->toString() ?: null,
        ];
    }

    public function navigation(): array
    {
        return [
            'overview' => ['label' => 'Overview', 'route' => 'web.gym.reports.index'],
            'revenue' => ['label' => 'Revenue', 'route' => 'web.gym.reports.revenue'],
            'dues' => ['label' => 'Dues', 'route' => 'web.gym.reports.dues'],
            'memberships' => ['label' => 'Memberships', 'route' => 'web.gym.reports.memberships'],
            'attendance' => ['label' => 'Attendance', 'route' => 'web.gym.reports.attendance'],
            'trainers' => ['label' => 'Trainers', 'route' => 'web.gym.reports.trainers'],
            'custom-fees' => ['label' => 'Custom Fees', 'route' => 'web.gym.reports.custom-fees'],
            'leads' => ['label' => 'Leads', 'route' => 'web.gym.reports.leads'],
        ];
    }

    public function reportOptions(): array
    {
        return [
            'overview' => 'Reports Overview',
            'revenue' => 'Revenue Report',
            'dues' => 'Pending / Overdue Dues Report',
            'memberships' => 'Membership Lifecycle Report',
            'attendance' => 'Attendance Report',
            'trainers' => 'Trainer Performance Report',
            'custom-fees' => 'Custom Fee / Discount Report',
            'leads' => 'Lead / Trial Conversion Report',
        ];
    }

    /**
     * @param  list<int>  $accessibleBranchIds
     */
    public function filterOptions(Gym $gym, array $accessibleBranchIds): array
    {
        return [
            'branches' => Branch::query()
                ->where('gym_id', $gym->id)
                ->whereIn('id', $accessibleBranchIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'trainers' => TrainerProfile::query()
                ->with('user:id,name')
                ->where('gym_id', $gym->id)
                ->whereIn('branch_id', $accessibleBranchIds)
                ->orderBy('user_id')
                ->get(),
            'plans' => $gym->membershipPlans()
                ->where(function (Builder $query) use ($accessibleBranchIds): void {
                    $query->whereNull('branch_id')
                        ->orWhereIn('branch_id', $accessibleBranchIds);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id']),
            'statuses' => [
                '' => 'All statuses',
                'active' => 'Active',
                'expired' => 'Expired',
                'expiring-soon' => 'Expiring Soon',
                'frozen' => 'Frozen',
                'cancelled' => 'Cancelled',
                'unpaid' => 'Unpaid',
                'partial' => 'Partial',
                'overdue' => 'Overdue',
                'pending' => 'Pending',
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
                'completed' => 'Completed',
                'converted' => 'Converted',
                'inactive' => 'Inactive',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(string $type, Gym $gym, array $filters): array
    {
        return match ($type) {
            'revenue' => $this->revenueReport($gym, $filters),
            'dues' => $this->duesReport($gym, $filters),
            'memberships' => $this->membershipsReport($gym, $filters),
            'attendance' => $this->attendanceReport($gym, $filters),
            'trainers' => $this->trainerPerformanceReport($gym, $filters),
            'custom-fees' => $this->customFeeReport($gym, $filters),
            'leads' => $this->leadConversionReport($gym, $filters),
            'inactive-members' => $this->inactiveMembersReport($gym, $filters),
            'branch-comparison' => $this->branchComparisonReport($gym, $filters),
            default => $this->overviewReport($gym, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildExport(string $type, Gym $gym, array $filters): array
    {
        return match ($type) {
            'members' => $this->inactiveMembersReport($gym, $filters),
            'payments' => $this->revenueReport($gym, $filters),
            'dues' => $this->duesReport($gym, $filters),
            'attendance' => $this->attendanceReport($gym, $filters),
            'expired-members' => $this->membershipsReport($gym, array_merge($filters, ['status' => 'expired'])),
            'expiring-members' => $this->membershipsReport($gym, array_merge($filters, ['status' => 'expiring-soon'])),
            'trial-requests' => $this->leadConversionReport($gym, $filters),
            'custom-fee-report', 'custom-fees' => $this->customFeeReport($gym, $filters),
            default => $this->overviewReport($gym, $filters),
        };
    }

    public function normalizeRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn ($cell): string => (string) $cell, $row),
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function overviewReport(Gym $gym, array $filters): array
    {
        $revenue = $this->revenueReport($gym, $filters);
        $dues = $this->duesReport($gym, $filters);
        $memberships = $this->membershipsReport($gym, $filters);
        $attendance = $this->attendanceReport($gym, $filters);
        $trainers = $this->trainerPerformanceReport($gym, $filters);
        $customFees = $this->customFeeReport($gym, $filters);
        $leads = $this->leadConversionReport($gym, $filters);
        $inactiveMembers = $this->inactiveMembersReport($gym, $filters);
        $branchComparison = $this->branchComparisonReport($gym, $filters);

        return [
            'key' => 'overview',
            'title' => 'Gym Reports Overview',
            'description' => 'Operational reporting dashboard across revenue, dues, memberships, attendance, trainers, custom pricing, and lead conversion.',
            'summary_cards' => [
                ['label' => 'Monthly Collection', 'value' => $revenue['summary_cards'][0]['value'], 'hint' => 'Recorded collections'],
                ['label' => 'Pending Dues', 'value' => $dues['summary_cards'][0]['value'], 'hint' => 'Open dues balance'],
                ['label' => 'Overdue Dues', 'value' => $dues['summary_cards'][1]['value'], 'hint' => 'Past due balances'],
                ['label' => 'Expiring Memberships', 'value' => $memberships['summary_cards'][0]['value'], 'hint' => 'In selected window'],
                ['label' => 'Expired Memberships', 'value' => $memberships['summary_cards'][1]['value'], 'hint' => 'In selected window'],
                ['label' => 'Today Check-ins', 'value' => $attendance['summary_cards'][1]['value'], 'hint' => 'Attendance today'],
                ['label' => 'Trainer Ratio', 'value' => $trainers['summary_cards'][2]['value'], 'hint' => 'Members per trainer'],
                ['label' => 'Custom Fee Members', 'value' => $customFees['summary_cards'][1]['value'], 'hint' => 'Member-specific pricing'],
                ['label' => 'Pending Trials', 'value' => $leads['summary_cards'][1]['value'], 'hint' => 'Awaiting follow-up'],
                ['label' => 'Inactive Members', 'value' => $inactiveMembers['summary_cards'][0]['value'], 'hint' => 'Need attention'],
            ],
            'chart_cards' => [
                ['label' => 'Revenue', 'value' => $revenue['summary_cards'][0]['value'], 'hint' => 'Selected period'],
                ['label' => 'Check-ins', 'value' => $attendance['summary_cards'][0]['value'], 'hint' => 'Selected period'],
                ['label' => 'Trials', 'value' => $leads['summary_cards'][0]['value'], 'hint' => 'Lead funnel volume'],
                ['label' => 'Branches', 'value' => $branchComparison['summary_cards'][0]['value'], 'hint' => 'Branches in scope'],
            ],
            'columns' => $branchComparison['columns'],
            'rows' => $branchComparison['rows'],
            'empty_state' => $branchComparison['empty_state'],
            'export_columns' => $branchComparison['columns'],
            'export_rows' => $this->normalizeRows($branchComparison['rows']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function revenueReport(Gym $gym, array $filters): array
    {
        $payments = $this->paymentsQuery($gym, $filters)
            ->with(['membership.member', 'branch'])
            ->where('status', PaymentRecordStatus::Recorded->value)
            ->whereBetween('paid_at', [$filters['start_date'], $filters['end_date']])
            ->latest('paid_at')
            ->get();

        return [
            'key' => 'revenue',
            'title' => 'Revenue Report',
            'description' => 'Recorded collections across the selected date range and scope.',
            'summary_cards' => [
                ['label' => 'Collection', 'value' => $this->money($payments->sum('amount'))],
                ['label' => 'Payments', 'value' => (string) $payments->count()],
                ['label' => 'Average Payment', 'value' => $this->money($payments->avg('amount'))],
            ],
            'columns' => ['Date', 'Member', 'Branch', 'Mode', 'Amount', 'Receipt'],
            'rows' => $payments->map(fn (Payment $payment): array => [
                optional($payment->paid_at)->format('d M Y'),
                $payment->membership?->member?->name ?? 'Member',
                $payment->branch?->name ?? 'Branch',
                strtoupper((string) $payment->payment_mode),
                $this->money($payment->amount),
                $payment->receipt_number ?: 'Pending',
            ])->all(),
            'empty_state' => [
                'title' => 'No revenue records',
                'message' => 'Recorded payments in the selected window will appear here.',
            ],
            'export_columns' => ['Date', 'Member', 'Branch', 'Mode', 'Amount', 'Receipt'],
            'export_rows' => $this->normalizeRows($payments->map(fn (Payment $payment): array => [
                optional($payment->paid_at)->format('Y-m-d'),
                $payment->membership?->member?->name ?? 'Member',
                $payment->branch?->name ?? 'Branch',
                strtoupper((string) $payment->payment_mode),
                $this->money($payment->amount),
                $payment->receipt_number ?: 'Pending',
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function duesReport(Gym $gym, array $filters): array
    {
        $query = $this->membershipsQuery($gym, $filters)
            ->with(['member', 'membershipPlan', 'branch'])
            ->where('due_amount', '>', 0)
            ->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Partial->value,
                PaymentStatus::Overdue->value,
            ]);

        if (in_array($filters['status'], ['unpaid', 'partial', 'overdue'], true)) {
            $query->where('payment_status', $filters['status']);
        }

        $memberships = $query->orderBy('due_date')->get();
        $overdueAmount = $memberships
            ->filter(fn (MemberMembership $membership): bool => $this->isOverdueMembership($membership))
            ->sum(fn (MemberMembership $membership): float => (float) $membership->due_amount);

        return [
            'key' => 'dues',
            'title' => 'Pending / Overdue Dues Report',
            'description' => 'Outstanding member dues, overdue balances, and payment health for the selected scope.',
            'summary_cards' => [
                ['label' => 'Pending Dues', 'value' => $this->money($memberships->sum('due_amount'))],
                ['label' => 'Overdue Dues', 'value' => $this->money($overdueAmount)],
                ['label' => 'Open Memberships', 'value' => (string) $memberships->count()],
                ['label' => 'Partial Payments', 'value' => (string) $memberships->where('payment_status', PaymentStatus::Partial->value)->count()],
            ],
            'columns' => ['Member', 'Plan', 'Branch', 'Payment Status', 'Due Date', 'Due Amount'],
            'rows' => $memberships->map(fn (MemberMembership $membership): array => [
                $membership->member?->name ?? 'Member',
                $membership->membershipPlan?->name ?? 'Plan',
                $membership->branch?->name ?? 'Branch',
                ucfirst($this->resolveDueStatus($membership)),
                optional($membership->due_date)->format('d M Y'),
                $this->money($membership->due_amount),
            ])->all(),
            'empty_state' => [
                'title' => 'No pending dues',
                'message' => 'Outstanding balances in this filter scope will appear here.',
            ],
            'export_columns' => ['Member', 'Plan', 'Branch', 'Payment Status', 'Due Date', 'Due Amount'],
            'export_rows' => $this->normalizeRows($memberships->map(fn (MemberMembership $membership): array => [
                $membership->member?->name ?? 'Member',
                $membership->membershipPlan?->name ?? 'Plan',
                $membership->branch?->name ?? 'Branch',
                ucfirst($this->resolveDueStatus($membership)),
                optional($membership->due_date)->format('Y-m-d'),
                $this->money($membership->due_amount),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function membershipsReport(Gym $gym, array $filters): array
    {
        $query = $this->membershipsQuery($gym, $filters)
            ->with(['member', 'membershipPlan', 'branch']);

        $status = $filters['status'];
        if ($status === 'expiring-soon') {
            $query->whereBetween('expiry_date', [$filters['start_date']->toDateString(), $filters['end_date']->toDateString()])
                ->where('status', 'active');
        } elseif (in_array($status, ['active', 'expired', 'frozen', 'cancelled'], true)) {
            $query->where('status', $status)
                ->whereBetween('expiry_date', [$filters['start_date']->toDateString(), $filters['end_date']->toDateString()]);
        } else {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder
                    ->whereBetween('expiry_date', [$filters['start_date']->toDateString(), $filters['end_date']->toDateString()])
                    ->orWhere(function (Builder $expired): void {
                        $expired->where('status', 'expired');
                    });
            });
        }

        $memberships = $query->orderBy('expiry_date')->get();
        $expiringCount = $memberships->filter(fn (MemberMembership $membership): bool => $this->membershipLifecycleLabel($membership) === 'expiring-soon')->count();
        $expiredCount = $memberships->filter(fn (MemberMembership $membership): bool => $this->membershipLifecycleLabel($membership) === 'expired')->count();

        return [
            'key' => 'memberships',
            'title' => 'Membership Lifecycle Report',
            'description' => 'Expiring, expired, frozen, and cancelled memberships in the selected window.',
            'summary_cards' => [
                ['label' => 'Expiring Soon', 'value' => (string) $expiringCount],
                ['label' => 'Expired', 'value' => (string) $expiredCount],
                ['label' => 'Frozen', 'value' => (string) $memberships->where('status', 'frozen')->count()],
                ['label' => 'Cancelled', 'value' => (string) $memberships->where('status', 'cancelled')->count()],
            ],
            'columns' => ['Member', 'Plan', 'Branch', 'Lifecycle', 'Expiry Date', 'Due Amount'],
            'rows' => $memberships->map(fn (MemberMembership $membership): array => [
                $membership->member?->name ?? 'Member',
                $membership->membershipPlan?->name ?? 'Plan',
                $membership->branch?->name ?? 'Branch',
                ucwords(str_replace('-', ' ', $this->membershipLifecycleLabel($membership))),
                optional($membership->expiry_date)->format('d M Y'),
                $this->money($membership->due_amount),
            ])->all(),
            'empty_state' => [
                'title' => 'No membership lifecycle records',
                'message' => 'Expiring and expired memberships will appear here for the selected range.',
            ],
            'export_columns' => ['Member', 'Plan', 'Branch', 'Lifecycle', 'Expiry Date', 'Due Amount'],
            'export_rows' => $this->normalizeRows($memberships->map(fn (MemberMembership $membership): array => [
                $membership->member?->name ?? 'Member',
                $membership->membershipPlan?->name ?? 'Plan',
                $membership->branch?->name ?? 'Branch',
                ucwords(str_replace('-', ' ', $this->membershipLifecycleLabel($membership))),
                optional($membership->expiry_date)->format('Y-m-d'),
                $this->money($membership->due_amount),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function attendanceReport(Gym $gym, array $filters): array
    {
        $attendance = $this->attendanceQuery($gym, $filters)
            ->with(['member', 'branch'])
            ->whereBetween('checked_in_at', [$filters['start_date'], $filters['end_date']])
            ->latest('checked_in_at')
            ->get();

        return [
            'key' => 'attendance',
            'title' => 'Attendance Report',
            'description' => 'Check-ins across the selected date range and branch scope.',
            'summary_cards' => [
                ['label' => 'Total Check-ins', 'value' => (string) $attendance->count()],
                ['label' => 'Today Check-ins', 'value' => (string) $attendance->where('checked_in_at', '>=', now()->startOfDay())->count()],
                ['label' => 'Biometric Check-ins', 'value' => (string) $attendance->where('check_in_method', 'biometric')->count()],
                ['label' => 'Manual Check-ins', 'value' => (string) $attendance->where('check_in_method', 'manual')->count()],
            ],
            'columns' => ['Check-in At', 'Member', 'Branch', 'Method', 'Checked In By'],
            'rows' => $attendance->map(fn (AttendanceLog $log): array => [
                optional($log->checked_in_at)->format('d M Y H:i'),
                $log->member?->name ?? 'Member',
                $log->branch?->name ?? 'Branch',
                strtoupper((string) $log->check_in_method),
                (string) ($log->checked_in_by ?: 'System'),
            ])->all(),
            'empty_state' => [
                'title' => 'No attendance data',
                'message' => 'Check-ins in the selected range will appear here.',
            ],
            'export_columns' => ['Check-in At', 'Member', 'Branch', 'Method', 'Checked In By'],
            'export_rows' => $this->normalizeRows($attendance->map(fn (AttendanceLog $log): array => [
                optional($log->checked_in_at)->format('Y-m-d H:i:s'),
                $log->member?->name ?? 'Member',
                $log->branch?->name ?? 'Branch',
                strtoupper((string) $log->check_in_method),
                (string) ($log->checked_in_by ?: 'System'),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function trainerPerformanceReport(Gym $gym, array $filters): array
    {
        $trainers = $this->trainersQuery($gym, $filters)
            ->with('user', 'branch')
            ->withCount([
                'assignedMembers as assigned_members_count' => fn (Builder $query): Builder => $query
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $filters['branch_ids']),
                'memberNotes as follow_up_notes_count' => fn (Builder $query): Builder => $query
                    ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]),
                'assignedTrialRequests as trial_assignments_count' => fn (Builder $query): Builder => $query
                    ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]),
            ])
            ->orderByDesc('assigned_members_count')
            ->get();

        $assignedMembers = (int) $trainers->sum('assigned_members_count');
        $ratio = $trainers->count() > 0 ? round($assignedMembers / $trainers->count(), 2) : null;

        return [
            'key' => 'trainers',
            'title' => 'Trainer Performance Report',
            'description' => 'Trainer workload, follow-up activity, and lead ownership in the selected scope.',
            'summary_cards' => [
                ['label' => 'Trainers', 'value' => (string) $trainers->count()],
                ['label' => 'Assigned Members', 'value' => (string) $assignedMembers],
                ['label' => 'Trainer-Member Ratio', 'value' => $ratio !== null ? (string) $ratio : 'N/A'],
                ['label' => 'Follow-up Notes', 'value' => (string) $trainers->sum('follow_up_notes_count')],
            ],
            'columns' => ['Trainer', 'Branch', 'Assigned Members', 'Follow-ups', 'Trial Assignments'],
            'rows' => $trainers->map(fn (TrainerProfile $trainer): array => [
                $trainer->user?->name ?? 'Trainer',
                $trainer->branch?->name ?? 'Branch',
                (string) $trainer->assigned_members_count,
                (string) $trainer->follow_up_notes_count,
                (string) $trainer->trial_assignments_count,
            ])->all(),
            'empty_state' => [
                'title' => 'No trainer performance data',
                'message' => 'Trainer activity in the selected range will appear here.',
            ],
            'export_columns' => ['Trainer', 'Branch', 'Assigned Members', 'Follow-ups', 'Trial Assignments'],
            'export_rows' => $this->normalizeRows($trainers->map(fn (TrainerProfile $trainer): array => [
                $trainer->user?->name ?? 'Trainer',
                $trainer->branch?->name ?? 'Branch',
                (string) $trainer->assigned_members_count,
                (string) $trainer->follow_up_notes_count,
                (string) $trainer->trial_assignments_count,
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function customFeeReport(Gym $gym, array $filters): array
    {
        $audits = $this->customFeeAuditQuery($gym, $filters)
            ->with(['member', 'membership.membershipPlan', 'membership.branch'])
            ->whereBetween('changed_at', [$filters['start_date'], $filters['end_date']])
            ->latest('changed_at')
            ->get();

        $discountValue = $audits->sum(function (CustomFeeAuditLog $audit): float {
            $old = (float) data_get($audit->old_values, 'final_payable_amount', 0);
            $new = (float) data_get($audit->new_values, 'final_payable_amount', 0);

            return max($old - $new, 0);
        });

        return [
            'key' => 'custom-fees',
            'title' => 'Custom Fee / Discount Report',
            'description' => 'Member-level custom fee activity, reasons, and discount impact in the selected window.',
            'summary_cards' => [
                ['label' => 'Audit Entries', 'value' => (string) $audits->count()],
                ['label' => 'Members Affected', 'value' => (string) $audits->pluck('member_id')->unique()->count()],
                ['label' => 'Discount Value', 'value' => $this->money($discountValue)],
                ['label' => 'Reasoned Changes', 'value' => (string) $audits->filter(fn (CustomFeeAuditLog $audit): bool => filled($audit->reason))->count()],
            ],
            'columns' => ['Changed At', 'Member', 'Branch', 'Plan', 'Reason', 'Changed By'],
            'rows' => $audits->map(fn (CustomFeeAuditLog $audit): array => [
                optional($audit->changed_at)->format('d M Y H:i'),
                $audit->member?->name ?? 'Member',
                $audit->membership?->branch?->name ?? 'Branch',
                $audit->membership?->membershipPlan?->name ?? 'Plan',
                $audit->reason ?: 'No reason',
                (string) $audit->changed_by,
            ])->all(),
            'empty_state' => [
                'title' => 'No custom fee changes',
                'message' => 'Custom fee and discount activity will appear here.',
            ],
            'export_columns' => ['Changed At', 'Member', 'Branch', 'Plan', 'Reason', 'Changed By'],
            'export_rows' => $this->normalizeRows($audits->map(fn (CustomFeeAuditLog $audit): array => [
                optional($audit->changed_at)->format('Y-m-d H:i:s'),
                $audit->member?->name ?? 'Member',
                $audit->membership?->branch?->name ?? 'Branch',
                $audit->membership?->membershipPlan?->name ?? 'Plan',
                $audit->reason ?: 'No reason',
                (string) $audit->changed_by,
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function leadConversionReport(Gym $gym, array $filters): array
    {
        $trials = $this->trialRequestsQuery($gym, $filters)
            ->with(['branch', 'assignedTrainer'])
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->latest('created_at')
            ->get();

        $converted = $trials->where('status', 'converted')->count();
        $conversionRate = $trials->count() > 0 ? round(($converted / $trials->count()) * 100, 1) : 0.0;

        return [
            'key' => 'leads',
            'title' => 'Lead / Trial Conversion Report',
            'description' => 'Trial-request funnel, trainer ownership, and conversion outcomes in the selected range.',
            'summary_cards' => [
                ['label' => 'Trial Requests', 'value' => (string) $trials->count()],
                ['label' => 'Pending', 'value' => (string) $trials->where('status', 'pending')->count()],
                ['label' => 'Converted', 'value' => (string) $converted],
                ['label' => 'Conversion Rate', 'value' => $conversionRate.'%'],
            ],
            'columns' => ['Lead', 'Branch', 'Preferred Date', 'Status', 'Assigned Trainer'],
            'rows' => $trials->map(fn (TrialRequest $trial): array => [
                $trial->name ?? 'Lead',
                $trial->branch?->name ?? 'Branch',
                optional($trial->preferred_date)->format('d M Y'),
                ucfirst((string) $trial->status),
                $trial->assignedTrainer?->name ?? 'Unassigned',
            ])->all(),
            'empty_state' => [
                'title' => 'No trial requests',
                'message' => 'Lead and conversion activity will appear here.',
            ],
            'export_columns' => ['Lead', 'Branch', 'Preferred Date', 'Status', 'Assigned Trainer'],
            'export_rows' => $this->normalizeRows($trials->map(fn (TrialRequest $trial): array => [
                $trial->name ?? 'Lead',
                $trial->branch?->name ?? 'Branch',
                optional($trial->preferred_date)->format('Y-m-d'),
                ucfirst((string) $trial->status),
                $trial->assignedTrainer?->name ?? 'Unassigned',
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function inactiveMembersReport(Gym $gym, array $filters): array
    {
        $members = $this->memberProfilesQuery($gym, $filters)
            ->with(['user', 'branch', 'assignedTrainer'])
            ->where(function (Builder $query) use ($filters): void {
                $query
                    ->where('is_active', false)
                    ->orWhereDoesntHave('attendanceLogs', fn (Builder $attendance): Builder => $attendance
                        ->whereDate('checked_in_at', '>=', $filters['start_date']->toDateString()));
            })
            ->latest('updated_at')
            ->get();

        return [
            'key' => 'inactive-members',
            'title' => 'Inactive Members Report',
            'description' => 'Members with inactive status or no recent check-ins in the selected period.',
            'summary_cards' => [
                ['label' => 'Inactive Members', 'value' => (string) $members->count()],
                ['label' => 'No Trainer', 'value' => (string) $members->whereNull('assigned_trainer_user_id')->count()],
                ['label' => 'Expired Status', 'value' => (string) $members->where('membership_status', 'expired')->count()],
            ],
            'columns' => ['Member', 'Branch', 'Membership Status', 'Assigned Trainer', 'Expiry'],
            'rows' => $members->map(fn (MemberProfile $member): array => [
                $member->user?->name ?? 'Member',
                $member->branch?->name ?? 'Branch',
                ucfirst((string) $member->membership_status),
                $member->assignedTrainer?->name ?? 'Unassigned',
                optional($member->membership_expires_on)->format('d M Y'),
            ])->all(),
            'empty_state' => [
                'title' => 'No inactive members',
                'message' => 'Inactivity and low-engagement members will appear here.',
            ],
            'export_columns' => ['Member', 'Branch', 'Membership Status', 'Assigned Trainer', 'Expiry'],
            'export_rows' => $this->normalizeRows($members->map(fn (MemberProfile $member): array => [
                $member->user?->name ?? 'Member',
                $member->branch?->name ?? 'Branch',
                ucfirst((string) $member->membership_status),
                $member->assignedTrainer?->name ?? 'Unassigned',
                optional($member->membership_expires_on)->format('Y-m-d'),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function branchComparisonReport(Gym $gym, array $filters): array
    {
        $branches = Branch::query()
            ->where('gym_id', $gym->id)
            ->whereIn('id', $filters['branch_ids'])
            ->withCount('memberProfiles', 'trainerProfiles')
            ->withSum(['payments as revenue_sum' => fn (Builder $query): Builder => $query
                ->where('status', PaymentRecordStatus::Recorded->value)
                ->whereBetween('paid_at', [$filters['start_date'], $filters['end_date']])], 'amount')
            ->withCount(['trialRequests as trials_count' => fn (Builder $query): Builder => $query
                ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])])
            ->get();

        return [
            'key' => 'branch-comparison',
            'title' => 'Branch Comparison Report',
            'description' => 'Compare branch performance across revenue, members, trainers, and trial demand.',
            'summary_cards' => [
                ['label' => 'Branches', 'value' => (string) $branches->count()],
                ['label' => 'Revenue', 'value' => $this->money($branches->sum('revenue_sum'))],
                ['label' => 'Trials', 'value' => (string) $branches->sum('trials_count')],
            ],
            'columns' => ['Branch', 'Members', 'Trainers', 'Revenue', 'Trials'],
            'rows' => $branches->map(fn (Branch $branch): array => [
                $branch->name,
                (string) $branch->member_profiles_count,
                (string) $branch->trainer_profiles_count,
                $this->money($branch->revenue_sum),
                (string) $branch->trials_count,
            ])->all(),
            'empty_state' => [
                'title' => 'No branch comparison data',
                'message' => 'Branch-level metrics will appear here once branches have activity.',
            ],
            'export_columns' => ['Branch', 'Members', 'Trainers', 'Revenue', 'Trials'],
            'export_rows' => $this->normalizeRows($branches->map(fn (Branch $branch): array => [
                $branch->name,
                (string) $branch->member_profiles_count,
                (string) $branch->trainer_profiles_count,
                $this->money($branch->revenue_sum),
                (string) $branch->trials_count,
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentsQuery(Gym $gym, array $filters): Builder
    {
        $query = Payment::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['trainer_id']) {
            $query->whereHas('membership', fn (Builder $builder): Builder => $builder
                ->whereHas('memberProfile', fn (Builder $profile): Builder => $profile
                    ->where('assigned_trainer_user_id', $filters['trainer_id'])));
        }

        if ($filters['plan_id']) {
            $query->whereHas('membership', fn (Builder $builder): Builder => $builder
                ->where('membership_plan_id', $filters['plan_id']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function membershipsQuery(Gym $gym, array $filters): Builder
    {
        $query = MemberMembership::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['plan_id']) {
            $query->where('membership_plan_id', $filters['plan_id']);
        }

        if ($filters['trainer_id']) {
            $query->whereHas('memberProfile', fn (Builder $profile): Builder => $profile
                ->where('assigned_trainer_user_id', $filters['trainer_id']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function attendanceQuery(Gym $gym, array $filters): Builder
    {
        $query = AttendanceLog::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['trainer_id']) {
            $query->whereHas('memberProfile', fn (Builder $profile): Builder => $profile
                ->where('gym_id', $gym->id)
                ->where('assigned_trainer_user_id', $filters['trainer_id']));
        }

        if ($filters['plan_id']) {
            $query->whereHas('memberMemberships', fn (Builder $membership): Builder => $membership
                ->where('gym_id', $gym->id)
                ->where('membership_plan_id', $filters['plan_id']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function trainersQuery(Gym $gym, array $filters): Builder
    {
        $query = TrainerProfile::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['trainer_id']) {
            $query->where('user_id', $filters['trainer_id']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function trialRequestsQuery(Gym $gym, array $filters): Builder
    {
        $query = TrialRequest::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['trainer_id']) {
            $query->where('assigned_trainer_id', $filters['trainer_id']);
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function customFeeAuditQuery(Gym $gym, array $filters): Builder
    {
        $query = CustomFeeAuditLog::query()
            ->where('gym_id', $gym->id)
            ->whereHas('membership', fn (Builder $builder): Builder => $builder
                ->whereIn('branch_id', $filters['branch_ids']));

        if ($filters['plan_id']) {
            $query->whereHas('membership', fn (Builder $builder): Builder => $builder
                ->where('membership_plan_id', $filters['plan_id']));
        }

        if ($filters['trainer_id']) {
            $query->whereHas('membership.memberProfile', fn (Builder $profile): Builder => $profile
                ->where('assigned_trainer_user_id', $filters['trainer_id']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function memberProfilesQuery(Gym $gym, array $filters): Builder
    {
        $query = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $filters['branch_ids']);

        if ($filters['trainer_id']) {
            $query->where('assigned_trainer_user_id', $filters['trainer_id']);
        }

        if ($filters['status']) {
            if ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            } else {
                $query->where('membership_status', $filters['status']);
            }
        }

        return $query;
    }

    private function money($amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function isOverdueMembership(MemberMembership $membership): bool
    {
        return $membership->payment_status === PaymentStatus::Overdue->value
            || ($membership->due_date && $membership->due_date->isPast() && (float) $membership->due_amount > 0);
    }

    private function resolveDueStatus(MemberMembership $membership): string
    {
        return $this->isOverdueMembership($membership)
            ? PaymentStatus::Overdue->value
            : (string) $membership->payment_status;
    }

    private function membershipLifecycleLabel(MemberMembership $membership): string
    {
        if ($membership->status === 'expired' || ($membership->expiry_date && $membership->expiry_date->isPast() && $membership->status !== 'cancelled')) {
            return 'expired';
        }

        if ($membership->status === 'frozen') {
            return 'frozen';
        }

        if ($membership->status === 'cancelled') {
            return 'cancelled';
        }

        return 'expiring-soon';
    }
}
