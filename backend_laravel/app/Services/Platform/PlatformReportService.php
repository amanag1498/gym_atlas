<?php

namespace App\Services\Platform;

use App\Enums\PaymentRecordStatus;
use App\Enums\RoleName;
use App\Models\AttendanceLog;
use App\Models\CustomFeeAuditLog;
use App\Models\Gym;
use App\Models\GymPlatformSubscriptionInvoice;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformReportService
{
    public function parseFilters(Request $request): array
    {
        return [
            'start_date' => $request->date('start_date')?->startOfDay() ?? now()->startOfMonth(),
            'end_date' => $request->date('end_date')?->endOfDay() ?? now()->endOfDay(),
            'city' => $request->string('city')->trim()->toString() ?: null,
            'gym_id' => $request->filled('gym') ? (int) $request->integer('gym') : null,
            'status' => $request->string('status')->trim()->toString() ?: null,
        ];
    }

    public function navigation(): array
    {
        return [
            'overview' => ['label' => 'Overview', 'route' => 'web.admin.reports.index'],
            'gyms' => ['label' => 'Gyms', 'route' => 'web.admin.reports.gyms'],
            'users' => ['label' => 'Users', 'route' => 'web.admin.reports.users'],
            'payments' => ['label' => 'Payments', 'route' => 'web.admin.reports.payments'],
            'platform-billing' => ['label' => 'Platform Billing', 'route' => 'web.admin.reports.platform-billing'],
            'attendance' => ['label' => 'Attendance', 'route' => 'web.admin.reports.attendance'],
            'custom-fees' => ['label' => 'Custom Fees', 'route' => 'web.admin.reports.custom-fees'],
        ];
    }

    public function reportOptions(): array
    {
        return [
            'overview' => 'Overview',
            'gyms' => 'Gym Growth',
            'users' => 'User Growth',
            'payments' => 'Payments Summary',
            'platform-billing' => 'Platform Billing',
            'attendance' => 'Attendance Summary',
            'custom-fees' => 'Custom Fee Usage',
        ];
    }

    public function filterOptions(): array
    {
        return [
            'cities' => Gym::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->values()
                ->all(),
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                '' => 'All statuses',
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'overdue' => 'Overdue',
                'paid' => 'Paid',
                'unpaid' => 'Unpaid',
                'due' => 'Due',
                'void' => 'Void',
                'converted' => 'Converted',
            ],
        ];
    }

    public function build(string $type, array $filters): array
    {
        return match ($type) {
            'gyms' => $this->gymGrowthReport($filters),
            'users' => $this->userGrowthReport($filters),
            'payments' => $this->paymentsSummaryReport($filters),
            'platform-billing' => $this->platformBillingReport($filters),
            'attendance' => $this->attendanceSummaryReport($filters),
            'custom-fees' => $this->customFeesReport($filters),
            default => $this->overviewReport($filters),
        };
    }

    public function normalizeRows(array $rows): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn ($cell): string => (string) $cell, $row),
            $rows,
        );
    }

    private function overviewReport(array $filters): array
    {
        $gyms = $this->gymGrowthReport($filters);
        $users = $this->userGrowthReport($filters);
        $payments = $this->paymentsSummaryReport($filters);
        $platformBilling = $this->platformBillingReport($filters);
        $attendance = $this->attendanceSummaryReport($filters);
        $customFees = $this->customFeesReport($filters);

        $trialsQuery = $this->applyTrialFilters(TrialRequest::query(), $filters);
        $totalTrials = (clone $trialsQuery)->count();
        $pendingTrials = (clone $trialsQuery)->where('status', 'pending')->count();
        $convertedTrials = (clone $trialsQuery)->where('status', 'converted')->count();
        $conversionRate = $totalTrials > 0 ? round(($convertedTrials / $totalTrials) * 100, 1) : 0.0;

        return [
            'key' => 'overview',
            'title' => 'Platform Reports Overview',
            'description' => 'Platform-wide reporting dashboard for growth, users, payments, attendance, custom pricing, and trial conversion.',
            'summary_cards' => [
                ['label' => 'Total Gyms', 'value' => $gyms['summary_cards'][0]['value'], 'hint' => 'Filtered platform gyms'],
                ['label' => 'Total Users', 'value' => $users['summary_cards'][0]['value'], 'hint' => 'Across platform roles'],
                ['label' => 'Total Collection', 'value' => $payments['summary_cards'][0]['value'], 'hint' => 'Recorded payments'],
                ['label' => 'Platform Billing Due', 'value' => $platformBilling['summary_cards'][0]['value'], 'hint' => 'SaaS billing receivable'],
                ['label' => 'Today Check-ins', 'value' => $attendance['summary_cards'][1]['value'], 'hint' => 'Attendance today'],
                ['label' => 'Custom Fee Gyms', 'value' => $customFees['summary_cards'][0]['value'], 'hint' => 'Gyms using custom fee'],
                ['label' => 'Trial Conversion', 'value' => $conversionRate.'%', 'hint' => $convertedTrials.' converted'],
            ],
            'columns' => ['Trial Metric', 'Value'],
            'rows' => [
                ['Total Trials', (string) $totalTrials],
                ['Pending Trials', (string) $pendingTrials],
                ['Converted Trials', (string) $convertedTrials],
                ['Conversion Rate', $conversionRate.'%'],
            ],
            'chart_cards' => [
                ['label' => 'Gym Growth Trend', 'value' => $gyms['chart_cards'][0]['value'] ?? 'No data', 'hint' => 'Latest month'],
                ['label' => 'User Growth Trend', 'value' => $users['chart_cards'][0]['value'] ?? 'No data', 'hint' => 'Latest month'],
                ['label' => 'Collection Trend', 'value' => $payments['chart_cards'][0]['value'] ?? 'No data', 'hint' => 'Latest month'],
                ['label' => 'Billing Trend', 'value' => $platformBilling['chart_cards'][0]['value'] ?? 'No data', 'hint' => 'Latest due cycle'],
                ['label' => 'Attendance Trend', 'value' => $attendance['chart_cards'][0]['value'] ?? 'No data', 'hint' => 'Top city'],
            ],
            'empty_state' => [
                'title' => 'No report data available',
                'message' => 'Once filtered platform activity exists, the reporting overview will populate here.',
            ],
            'export_columns' => ['Trial Metric', 'Value'],
            'export_rows' => $this->normalizeRows([
                ['Total Trials', $totalTrials],
                ['Pending Trials', $pendingTrials],
                ['Converted Trials', $convertedTrials],
                ['Conversion Rate', $conversionRate.'%'],
            ]),
        ];
    }

    private function gymGrowthReport(array $filters): array
    {
        $allGymsQuery = $this->applyGymFilters(Gym::query(), $filters);
        $allGyms = (clone $allGymsQuery)->get(['id', 'is_active', 'approval_status']);
        $cityRows = (clone $allGymsQuery)
            ->select('city', DB::raw('COUNT(*) as gyms_count'))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->groupBy('city')
            ->orderByDesc('gyms_count')
            ->get();
        $monthlyRows = $this->applyGymFilters(Gym::query(), $filters)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->get(['created_at'])
            ->groupBy(fn (Gym $gym) => optional($gym->created_at)->format('Y-m'))
            ->filter(fn ($items, $month) => $month !== null)
            ->map(fn ($items, $month) => (object) ['month_key' => $month, 'gyms_count' => $items->count()])
            ->sortBy('month_key')
            ->values();

        return [
            'key' => 'gyms',
            'title' => 'Gym Growth',
            'description' => 'Total gyms, active and pending inventory, city-wise distribution, and month-wise new gyms.',
            'summary_cards' => [
                ['label' => 'Total Gyms', 'value' => (string) $allGyms->count(), 'hint' => 'Matching filters'],
                ['label' => 'Active Gyms', 'value' => (string) $allGyms->where('is_active', true)->count(), 'hint' => 'Operational gyms'],
                ['label' => 'Pending Gyms', 'value' => (string) $allGyms->where('approval_status', 'pending')->count(), 'hint' => 'Awaiting review'],
                ['label' => 'Cities', 'value' => (string) $cityRows->count(), 'hint' => 'Distinct gym cities'],
            ],
            'columns' => ['City', 'Gyms'],
            'rows' => $cityRows->map(fn ($row) => [$row->city, (string) $row->gyms_count])->all(),
            'chart_cards' => $monthlyRows->map(fn ($row) => [
                'label' => $row->month_key,
                'value' => (string) $row->gyms_count,
                'hint' => 'New gyms',
            ])->all(),
            'empty_state' => [
                'title' => 'No gym growth data',
                'message' => 'Gym distribution and month-wise growth will appear here for the selected filters.',
            ],
            'export_columns' => ['City', 'Gyms'],
            'export_rows' => $this->normalizeRows($cityRows->map(fn ($row) => [$row->city, $row->gyms_count])->all()),
        ];
    }

    private function userGrowthReport(array $filters): array
    {
        $users = $this->applyUserFilters(User::query(), $filters)->get(['id', 'is_active']);
        $roleCounts = [
            'Members' => $this->applyUserFilters(User::query()->role(RoleName::Member->value), $filters)->count(),
            'Trainers' => $this->applyUserFilters(User::query()->role(RoleName::Trainer->value), $filters)->count(),
            'Gym Owners' => $this->applyUserFilters(User::query()->role(RoleName::GymOwner->value), $filters)->count(),
            'Platform Admins' => $this->applyUserFilters(User::query()->role(RoleName::PlatformAdmin->value), $filters)->count(),
        ];
        $monthlyRows = $this->applyUserFilters(User::query(), $filters)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->get(['created_at'])
            ->groupBy(fn (User $user) => optional($user->created_at)->format('Y-m'))
            ->filter(fn ($items, $month) => $month !== null)
            ->map(fn ($items, $month) => (object) ['month_key' => $month, 'users_count' => $items->count()])
            ->sortBy('month_key')
            ->values();

        $rows = collect($roleCounts)
            ->merge([
                'Active Users' => $users->where('is_active', true)->count(),
                'Inactive Users' => $users->where('is_active', false)->count(),
            ])
            ->map(fn ($count, $label) => [$label, (string) $count])
            ->values()
            ->all();

        return [
            'key' => 'users',
            'title' => 'User Growth',
            'description' => 'Role-wise user totals with active and inactive account split plus month-wise new users.',
            'summary_cards' => [
                ['label' => 'Total Users', 'value' => (string) $users->count(), 'hint' => 'Matching filters'],
                ['label' => 'Members', 'value' => (string) $roleCounts['Members'], 'hint' => 'Member role'],
                ['label' => 'Trainers', 'value' => (string) $roleCounts['Trainers'], 'hint' => 'Trainer role'],
                ['label' => 'Gym Owners', 'value' => (string) $roleCounts['Gym Owners'], 'hint' => 'Owner role'],
                ['label' => 'Active Users', 'value' => (string) $users->where('is_active', true)->count(), 'hint' => 'Enabled accounts'],
                ['label' => 'Inactive Users', 'value' => (string) $users->where('is_active', false)->count(), 'hint' => 'Disabled accounts'],
            ],
            'columns' => ['User Segment', 'Count'],
            'rows' => $rows,
            'chart_cards' => $monthlyRows->map(fn ($row) => [
                'label' => $row->month_key,
                'value' => (string) $row->users_count,
                'hint' => 'New users',
            ])->all(),
            'empty_state' => [
                'title' => 'No user growth data',
                'message' => 'Role distribution and user growth will appear here for the selected filters.',
            ],
            'export_columns' => ['User Segment', 'Count'],
            'export_rows' => $this->normalizeRows($rows),
        ];
    }

    private function paymentsSummaryReport(array $filters): array
    {
        $paymentsQuery = $this->applyPaymentFilters(
            Payment::query()->where('status', PaymentRecordStatus::Recorded->value),
            $filters
        );
        $totalCollection = (clone $paymentsQuery)->sum('amount');
        $modeRows = (clone $paymentsQuery)
            ->select('payment_mode', DB::raw('COUNT(*) as payments_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_mode')
            ->orderByDesc('total_amount')
            ->get();
        $monthlyRows = (clone $paymentsQuery)
            ->get(['amount', 'payment_date', 'paid_at', 'created_at'])
            ->groupBy(function (Payment $payment): ?string {
                $date = $payment->payment_date ?? $payment->paid_at ?? $payment->created_at;

                return $date?->format('Y-m');
            })
            ->filter(fn ($items, $month) => $month !== null)
            ->map(fn ($items, $month) => (object) [
                'month_key' => $month,
                'total_amount' => $items->sum(fn (Payment $payment) => (float) $payment->amount),
            ])
            ->sortBy('month_key')
            ->values();

        $membershipsQuery = $this->applyMembershipFilters(MemberMembership::query(), $filters);
        $pendingDues = (clone $membershipsQuery)->where('due_amount', '>', 0)->sum('due_amount');
        $overdueDues = (clone $membershipsQuery)
            ->where(function (Builder $builder): void {
                $builder
                    ->where('payment_status', 'overdue')
                    ->orWhere(function (Builder $nested): void {
                        $nested
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', now()->toDateString())
                            ->where('due_amount', '>', 0);
                    });
            })
            ->sum('due_amount');

        return [
            'key' => 'payments',
            'title' => 'Payments Summary',
            'description' => 'Collection totals, dues health, monthly collection, and payment mode summary across the platform.',
            'summary_cards' => [
                ['label' => 'Total Collection', 'value' => $this->money($totalCollection), 'hint' => 'Recorded payments'],
                ['label' => 'Monthly Collection', 'value' => $this->money($monthlyRows->sum('total_amount')), 'hint' => 'Selected period'],
                ['label' => 'Pending Dues', 'value' => $this->money($pendingDues), 'hint' => 'Open balances'],
                ['label' => 'Overdue Dues', 'value' => $this->money($overdueDues), 'hint' => 'Past due balances'],
            ],
            'columns' => ['Payment Mode', 'Payments', 'Collected Amount'],
            'rows' => $modeRows->map(fn ($row) => [
                ucfirst((string) ($row->payment_mode ?: 'unknown')),
                (string) $row->payments_count,
                $this->money($row->total_amount),
            ])->all(),
            'chart_cards' => $monthlyRows->map(fn ($row) => [
                'label' => $row->month_key,
                'value' => $this->money($row->total_amount),
                'hint' => 'Collection',
            ])->all(),
            'empty_state' => [
                'title' => 'No payment summary data',
                'message' => 'Payments and dues metrics will appear here for the selected filters.',
            ],
            'export_columns' => ['Payment Mode', 'Payments', 'Collected Amount'],
            'export_rows' => $this->normalizeRows($modeRows->map(fn ($row) => [
                ucfirst((string) ($row->payment_mode ?: 'unknown')),
                $row->payments_count,
                $this->money($row->total_amount),
            ])->all()),
        ];
    }

    private function attendanceSummaryReport(array $filters): array
    {
        $attendanceQuery = $this->applyAttendanceFilters(AttendanceLog::query(), $filters);
        $totalCheckIns = (clone $attendanceQuery)->count();
        $todayCheckIns = (clone $attendanceQuery)->whereDate('checked_in_at', now()->toDateString())->count();
        $gymRows = (clone $attendanceQuery)
            ->select('gym_id', DB::raw('COUNT(*) as check_ins_count'))
            ->with('gym:id,name')
            ->groupBy('gym_id')
            ->orderByDesc('check_ins_count')
            ->get();
        $cityRows = (clone $attendanceQuery)
            ->join('gyms', 'gyms.id', '=', 'attendance_logs.gym_id')
            ->select('gyms.city', DB::raw('COUNT(*) as check_ins_count'))
            ->whereNotNull('gyms.city')
            ->where('gyms.city', '!=', '')
            ->groupBy('gyms.city')
            ->orderByDesc('check_ins_count')
            ->get();

        return [
            'key' => 'attendance',
            'title' => 'Attendance Summary',
            'description' => 'Check-in totals, today volume, gym-wise check-ins, and city-wise attendance distribution.',
            'summary_cards' => [
                ['label' => 'Total Check-ins', 'value' => (string) $totalCheckIns, 'hint' => 'Matching filters'],
                ['label' => 'Today Check-ins', 'value' => (string) $todayCheckIns, 'hint' => 'Today only'],
                ['label' => 'Gym-wise Rows', 'value' => (string) $gymRows->count(), 'hint' => 'Gyms with check-ins'],
                ['label' => 'City-wise Rows', 'value' => (string) $cityRows->count(), 'hint' => 'Cities with check-ins'],
            ],
            'columns' => ['Gym', 'Check-ins'],
            'rows' => $gymRows->map(fn ($row) => [
                $row->gym?->name ?? 'Gym',
                (string) $row->check_ins_count,
            ])->all(),
            'chart_cards' => $cityRows->map(fn ($row) => [
                'label' => $row->city ?: 'Unknown',
                'value' => (string) $row->check_ins_count,
                'hint' => 'City check-ins',
            ])->all(),
            'empty_state' => [
                'title' => 'No attendance data',
                'message' => 'Attendance summaries will appear here for the selected filters.',
            ],
            'export_columns' => ['Gym', 'Check-ins'],
            'export_rows' => $this->normalizeRows($gymRows->map(fn ($row) => [$row->gym?->name ?? 'Gym', $row->check_ins_count])->all()),
        ];
    }

    private function platformBillingReport(array $filters): array
    {
        $invoiceQuery = $this->applyPlatformInvoiceFilters(
            GymPlatformSubscriptionInvoice::query()->with(['gym:id,name,city', 'subscription.plan']),
            $filters
        );

        $invoices = (clone $invoiceQuery)->get();
        $dueRevenue = (float) $invoices->whereIn('status', ['due', 'overdue'])->sum('total_amount');
        $collectedRevenue = (float) $invoices->where('status', 'paid')->sum('total_amount');
        $overdueRevenue = (float) $invoices->where('status', 'overdue')->sum('total_amount');
        $avgInvoice = $invoices->count() > 0 ? round(((float) $invoices->sum('total_amount')) / $invoices->count(), 2) : 0.0;

        $gymRows = $this->applyPlatformInvoiceFilters(GymPlatformSubscriptionInvoice::query(), $filters)
            ->select(
                'gym_id',
                DB::raw('COUNT(*) as invoices_count'),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as collected_amount"),
                DB::raw("SUM(CASE WHEN status IN ('due', 'overdue') THEN total_amount ELSE 0 END) as outstanding_amount"),
                DB::raw("SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_amount")
            )
            ->with('gym:id,name,city')
            ->groupBy('gym_id')
            ->orderByDesc('outstanding_amount')
            ->get();

        $monthlyRows = $this->applyPlatformInvoiceFilters(GymPlatformSubscriptionInvoice::query(), $filters)
            ->get(['status', 'total_amount', 'due_at', 'paid_at'])
            ->groupBy(function (GymPlatformSubscriptionInvoice $invoice): ?string {
                $date = $invoice->paid_at ?? $invoice->due_at;

                return $date?->format('Y-m');
            })
            ->filter(fn ($items, $month) => $month !== null)
            ->map(function ($items, $month): object {
                $typed = $items instanceof \Illuminate\Support\Collection ? $items : collect($items);

                return (object) [
                    'month_key' => $month,
                    'due_amount' => (float) $typed->whereIn('status', ['due', 'overdue'])->sum('total_amount'),
                    'collected_amount' => (float) $typed->where('status', 'paid')->sum('total_amount'),
                    'overdue_amount' => (float) $typed->where('status', 'overdue')->sum('total_amount'),
                ];
            })
            ->sortBy('month_key')
            ->values();

        return [
            'key' => 'platform-billing',
            'title' => 'Platform Billing',
            'description' => 'Gym SaaS billing intelligence across invoices, collections, overdue exposure, and gym-wise revenue responsibility.',
            'summary_cards' => [
                ['label' => 'Revenue Due', 'value' => $this->money($dueRevenue), 'hint' => 'Open platform invoices'],
                ['label' => 'Collected', 'value' => $this->money($collectedRevenue), 'hint' => 'Paid platform invoices'],
                ['label' => 'Overdue', 'value' => $this->money($overdueRevenue), 'hint' => 'Past due platform billing'],
                ['label' => 'Avg Invoice', 'value' => $this->money($avgInvoice), 'hint' => 'Average invoice size'],
            ],
            'columns' => ['Gym', 'Invoices', 'Collected', 'Outstanding', 'Overdue'],
            'rows' => $gymRows->map(fn ($row) => [
                $row->gym?->name ?? 'Unknown gym',
                (string) $row->invoices_count,
                $this->money($row->collected_amount),
                $this->money($row->outstanding_amount),
                $this->money($row->overdue_amount),
            ])->all(),
            'chart_cards' => $monthlyRows->map(fn ($row) => [
                'label' => $row->month_key,
                'value' => $this->money($row->due_amount + $row->collected_amount),
                'hint' => 'Due '.$this->money($row->due_amount).' • Collected '.$this->money($row->collected_amount),
            ])->all(),
            'empty_state' => [
                'title' => 'No platform billing data',
                'message' => 'Platform billing invoices will appear here for the selected finance filters.',
            ],
            'export_columns' => ['Gym', 'Invoices', 'Collected', 'Outstanding', 'Overdue'],
            'export_rows' => $this->normalizeRows($gymRows->map(fn ($row) => [
                $row->gym?->name ?? 'Unknown gym',
                $row->invoices_count,
                $this->money($row->collected_amount),
                $this->money($row->outstanding_amount),
                $this->money($row->overdue_amount),
            ])->all()),
        ];
    }

    private function customFeesReport(array $filters): array
    {
        $usageRows = $this->applyMembershipFilters(
            MemberMembership::query()->where('custom_fee_enabled', true),
            $filters
        )
            ->select(
                'gym_id',
                DB::raw('COUNT(*) as members_count'),
                DB::raw("SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END) as discounted_members_count"),
                DB::raw("SUM(CASE
                    WHEN final_payable_amount < (COALESCE(default_plan_price, 0) + COALESCE(default_joining_fee, 0) + COALESCE(pt_custom_fee, 0) + COALESCE(partial_month_fee, 0))
                    THEN ((COALESCE(default_plan_price, 0) + COALESCE(default_joining_fee, 0) + COALESCE(pt_custom_fee, 0) + COALESCE(partial_month_fee, 0)) - final_payable_amount)
                    ELSE 0
                END) as estimated_discount_value")
            )
            ->with('gym:id,name,city')
            ->groupBy('gym_id')
            ->orderByDesc('members_count')
            ->get();

        $auditCount = $this->applyCustomFeeAuditFilters(CustomFeeAuditLog::query(), $filters)->count();

        $latestReasonByGym = $this->applyCustomFeeAuditFilters(CustomFeeAuditLog::query(), $filters)
            ->whereNotNull('reason')
            ->orderByDesc('changed_at')
            ->get(['gym_id', 'reason'])
            ->unique('gym_id')
            ->mapWithKeys(fn (CustomFeeAuditLog $log) => [$log->gym_id => $log->reason]);

        return [
            'key' => 'custom-fees',
            'title' => 'Custom Fee Usage',
            'description' => 'Gyms using custom fee logic, discounted member count, estimated discount value, and top gyms by custom fee usage.',
            'summary_cards' => [
                ['label' => 'Gyms Using Custom Fee', 'value' => (string) $usageRows->count(), 'hint' => 'Matching filters'],
                ['label' => 'Discounted Members', 'value' => (string) $usageRows->sum('discounted_members_count'), 'hint' => 'Members with discount'],
                ['label' => 'Estimated Discount Value', 'value' => $this->money($usageRows->sum('estimated_discount_value')), 'hint' => 'If calculable'],
                ['label' => 'Audit Entries', 'value' => (string) $auditCount, 'hint' => 'Custom fee changes logged'],
            ],
            'columns' => ['Gym', 'Custom Fee Members', 'Discounted Members', 'Estimated Discount Value', 'Latest Reason'],
            'rows' => $usageRows->map(fn ($row) => [
                $row->gym?->name ?? 'Gym',
                (string) $row->members_count,
                (string) $row->discounted_members_count,
                $this->money($row->estimated_discount_value),
                $latestReasonByGym->get($row->gym_id, 'N/A'),
            ])->all(),
            'chart_cards' => $usageRows->take(6)->map(fn ($row) => [
                'label' => $row->gym?->name ?? 'Gym',
                'value' => (string) $row->members_count,
                'hint' => 'Custom fee members',
            ])->all(),
            'empty_state' => [
                'title' => 'No custom fee usage data',
                'message' => 'Custom fee usage metrics will appear here for the selected filters.',
            ],
            'export_columns' => ['Gym', 'Custom Fee Members', 'Discounted Members', 'Estimated Discount Value', 'Latest Reason'],
            'export_rows' => $this->normalizeRows($usageRows->map(fn ($row) => [
                $row->gym?->name ?? 'Gym',
                $row->members_count,
                $row->discounted_members_count,
                $this->money($row->estimated_discount_value),
                $latestReasonByGym->get($row->gym_id, 'N/A'),
            ])->all()),
        ];
    }

    private function applyGymFilters(Builder $query, array $filters): Builder
    {
        if ($filters['city']) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }

        if ($filters['gym_id']) {
            $query->whereKey($filters['gym_id']);
        }

        if ($filters['status']) {
            match ($filters['status']) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'pending', 'approved', 'rejected' => $query->where('approval_status', $filters['status']),
                default => null,
            };
        }

        return $query;
    }

    private function applyUserFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['status']) {
            match ($filters['status']) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        if ($filters['gym_id']) {
            $gymId = $filters['gym_id'];
            $query->where(function (Builder $builder) use ($gymId): void {
                $builder
                    ->whereHas('memberProfile', fn (Builder $nested) => $nested->where('gym_id', $gymId))
                    ->orWhereHas('managedTrainerProfile', fn (Builder $nested) => $nested->where('gym_id', $gymId))
                    ->orWhereHas('ownedGyms', fn (Builder $nested) => $nested->whereKey($gymId));
            });
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->where(function (Builder $builder) use ($city): void {
                $builder
                    ->whereHas('memberProfile.gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'))
                    ->orWhereHas('managedTrainerProfile.gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'))
                    ->orWhereHas('ownedGyms', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
            });
        }

        return $query;
    }

    private function applyPaymentFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween(DB::raw('COALESCE(payment_date, paid_at, created_at)'), [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        if ($filters['status']) {
            match ($filters['status']) {
                'active' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', true)),
                'inactive' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', false)),
                default => null,
            };
        }

        return $query;
    }

    private function applyMembershipFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        if ($filters['status']) {
            match ($filters['status']) {
                'active', 'inactive', 'pending', 'approved', 'rejected' => $query->whereHas('gym', function (Builder $nested) use ($filters): void {
                    match ($filters['status']) {
                        'active' => $nested->where('is_active', true),
                        'inactive' => $nested->where('is_active', false),
                        default => $nested->where('approval_status', $filters['status']),
                    };
                }),
                'overdue' => $query->where('payment_status', 'overdue'),
                'paid', 'unpaid' => $query->where('payment_status', $filters['status']),
                default => null,
            };
        }

        return $query;
    }

    private function applyAttendanceFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('checked_in_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        if ($filters['status']) {
            match ($filters['status']) {
                'active' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', true)),
                'inactive' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', false)),
                default => null,
            };
        }

        return $query;
    }

    private function applyPlatformInvoiceFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween(DB::raw('COALESCE(paid_at, due_at, issued_at, created_at)'), [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        if ($filters['status']) {
            match ($filters['status']) {
                'paid', 'due', 'overdue', 'void' => $query->where('status', $filters['status']),
                'unpaid' => $query->whereIn('status', ['due', 'overdue']),
                'active' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', true)),
                'inactive' => $query->whereHas('gym', fn (Builder $nested) => $nested->where('is_active', false)),
                default => null,
            };
        }

        return $query;
    }

    private function applyTrialFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        if ($filters['status'] && in_array($filters['status'], ['pending', 'converted', 'accepted', 'rejected', 'completed'], true)) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function applyCustomFeeAuditFilters(Builder $query, array $filters): Builder
    {
        $query->whereBetween('changed_at', [$filters['start_date'], $filters['end_date']]);

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['city']) {
            $city = $filters['city'];
            $query->whereHas('gym', fn (Builder $nested) => $nested->where('city', 'like', '%'.$city.'%'));
        }

        return $query;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
