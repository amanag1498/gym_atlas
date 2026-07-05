<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\RoleName;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BiometricAttendanceRequest;
use App\Http\Requests\Attendance\ManualAttendanceRequest;
use App\Http\Requests\Attendance\ReviewAttendanceCorrectionRequest;
use App\Http\Requests\Attendance\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\AttendanceService;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Web\CsvStreamService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceCorrectionService $attendanceCorrectionService,
        private readonly AuditLogService $auditLogService,
        private readonly CsvStreamService $csvStreamService,
    ) {
    }

    public function index(Request $request): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceView->value, $gym);
        $this->assertAttendanceViewAccess($request, $gym, $request->integer('branch_id') ?: null);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
        $query = $this->buildAttendanceQuery($request, $gym, $branchIds);
        $summaryQuery = clone $query;
        $allScopeQuery = AttendanceLog::query()
            ->with(['member', 'checkedInByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds);
        $allScopeLogs = (clone $allScopeQuery)->get();
        $dailyTrend = collect(range(6, 0))->map(function (int $offset) use ($allScopeLogs): array {
            $day = now()->copy()->subDays($offset);
            $count = $allScopeLogs->filter(fn (AttendanceLog $log) => optional($log->checked_in_at)?->isSameDay($day))->count();

            return [
                'label' => $day->format('D'),
                'date' => $day->format('d M'),
                'count' => $count,
            ];
        });
        $hourlyHeatmap = collect(range(5, 22))->map(function (int $hour) use ($allScopeLogs): array {
            $count = $allScopeLogs->filter(fn (AttendanceLog $log) => (int) optional($log->checked_in_at)->format('G') === $hour)->count();

            return [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'count' => $count,
            ];
        })->filter(fn (array $slot) => $slot['count'] > 0)->values();
        $peakHour = $hourlyHeatmap->sortByDesc('count')->first();
        $correctionRequests = AttendanceCorrectionRequest::query()
            ->with(['attendanceLog', 'member', 'requestedByUser', 'reviewedByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->latest('created_at')
            ->take(8)
            ->get();
        $pendingCorrectionsCount = AttendanceCorrectionRequest::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'pending')
            ->count();

        if ($request->string('export')->toString() === 'csv') {
            return $this->csvStreamService->download(
                'gym-attendance-'.$gym->id.'-'.now()->format('Ymd-His').'.csv',
                ['Member', 'Branch', 'Check-in Method', 'Checked In At', 'Checked In By', 'Notes', 'Source Device'],
                $query->get()->map(fn (AttendanceLog $log) => [
                    $log->member?->name ?? '',
                    $log->branch?->name ?? '',
                    $log->check_in_method,
                    optional($log->checked_in_at)->format('Y-m-d H:i:s') ?? '',
                    $log->checkedInByUser?->name ?? 'System',
                    $log->notes ?? '',
                    $log->source_device ?? '',
                ]),
            );
        }

        return view('web.gym.attendance.index', [
            'pageTitle' => 'Attendance',
            'breadcrumbs' => ['Gym', 'Attendance'],
            'gym' => $gym,
            'logs' => $query->paginate(15)->withQueryString(),
            'todayCount' => AttendanceLog::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->whereDate('checked_in_at', now()->toDateString())->count(),
            'todayLogs' => AttendanceLog::query()
                ->with(['member', 'checkedInByUser', 'branch'])
                ->where('gym_id', $gym->id)
                ->whereIn('branch_id', $branchIds)
                ->whereDate('checked_in_at', now()->toDateString())
                ->latest('checked_in_at')
                ->take(6)
                ->get(),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'members' => User::query()->whereHas('memberProfile', fn ($builder) => $builder->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds))->orderBy('name')->get(),
            'selectedMember' => $request->filled('member_id') ? User::query()->find($request->integer('member_id')) : null,
            'canManageAttendance' => $this->canManageAttendance($request, $gym, $request->integer('branch_id') ?: null),
            'duplicateProtectionEnabled' => (bool) $gym->prevent_duplicate_same_day_checkins,
            'summary' => [
                'visible_logs' => (clone $summaryQuery)->count(),
                'manual_logs' => (clone $summaryQuery)->where('check_in_method', 'manual')->count(),
                'biometric_logs' => (clone $summaryQuery)->where('check_in_method', 'biometric')->count(),
                'unique_members' => (clone $summaryQuery)->distinct('member_id')->count('member_id'),
                'avg_daily_logs' => round($allScopeLogs->count() / max(1, $dailyTrend->filter(fn (array $day) => $day['count'] > 0)->count()), 1),
                'pending_corrections' => $pendingCorrectionsCount,
            ],
            'methodBreakdown' => (clone $summaryQuery)
                ->selectRaw('check_in_method, COUNT(*) as total_logs')
                ->groupBy('check_in_method')
                ->orderByDesc('total_logs')
                ->get(),
            'branchBreakdown' => (clone $summaryQuery)
                ->selectRaw('branch_id, COUNT(*) as total_logs')
                ->with('branch:id,name')
                ->groupBy('branch_id')
                ->orderByDesc('total_logs')
                ->get(),
            'dailyTrend' => $dailyTrend,
            'hourlyHeatmap' => $hourlyHeatmap,
            'peakHour' => $peakHour,
            'correctionRequests' => $correctionRequests,
        ]);
    }

    public function today(Request $request): View|StreamedResponse
    {
        $request->merge(['today' => 1]);

        return $this->index($request);
    }

    public function memberHistory(Request $request, User $member): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);
        abort_unless(
            in_array((int) $member->memberProfile?->branch_id, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true),
            404
        );

        $request->merge(['member_id' => $member->id]);

        return $this->index($request);
    }

    public function manualForm(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym);
        $this->assertAttendanceManageAccess($request, $gym, $request->integer('branch_id') ?: null);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);

        return view('web.gym.attendance.manual', [
            'pageTitle' => 'Manual Attendance',
            'breadcrumbs' => ['Gym', 'Attendance', 'Manual'],
            'gym' => $gym,
            'branches' => $gym->branches()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'members' => User::query()->whereHas('memberProfile', fn ($builder) => $builder->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds))->orderBy('name')->get(),
        ]);
    }

    public function storeManual(ManualAttendanceRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym, true);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym, $branch?->id);
        $this->assertAttendanceManageAccess($request, $gym, $branch?->id);

        $member = User::query()->findOrFail($request->validated('member_id'));
        $log = $this->attendanceService->recordManualCheckIn(
            gym: $gym,
            branch: $branch,
            member: $member,
            checkedInBy: $request->user(),
            notes: $request->validated('notes'),
            sourceDevice: $request->validated('source_device', 'web-admin'),
            checkedInAt: $request->validated('checked_in_at'),
        );

        $this->auditLogService->log(
            event: 'web.gym.attendance.manual.created',
            action: 'create',
            request: $request,
            subject: $log,
            gym: $gym,
            branch: $branch,
            newValues: $log->toArray(),
        );

        return redirect()->route('web.gym.attendance.index', [
            'gym' => $gym->id,
            'branch' => $branch?->id,
        ])->with('status', 'Manual attendance recorded successfully.');
    }

    public function storeCorrection(StoreAttendanceCorrectionRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym, true);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym, $branch?->id);
        $this->assertAttendanceManageAccess($request, $gym, $branch?->id);

        $member = User::query()->findOrFail($request->validated('member_id'));
        $log = $request->filled('attendance_log_id')
            ? AttendanceLog::query()->findOrFail($request->validated('attendance_log_id'))
            : null;

        $correction = $this->attendanceCorrectionService->request(
            gym: $gym,
            branch: $branch,
            member: $member,
            requestedBy: $request->user(),
            requestedCheckInAt: Carbon::parse($request->validated('requested_check_in_at')),
            reason: $request->validated('reason'),
            attendanceLog: $log,
        );

        $this->auditLogService->log(
            event: 'web.gym.attendance.correction.requested',
            action: 'create',
            request: $request,
            subject: $correction,
            gym: $gym,
            branch: $branch,
            newValues: $correction->toArray(),
        );

        return back()->with('status', 'Attendance correction request submitted.');
    }

    public function approveCorrection(ReviewAttendanceCorrectionRequest $request, AttendanceCorrectionRequest $correction): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($correction->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym, $correction->branch_id);
        $this->assertAttendanceManageAccess($request, $gym, $correction->branch_id);

        $oldValues = $correction->toArray();
        $correction = $this->attendanceCorrectionService->approve($correction, $request->user(), $request->validated('notes'));

        $this->auditLogService->log(
            event: 'web.gym.attendance.correction.approved',
            action: 'update',
            request: $request,
            subject: $correction,
            gym: $gym,
            branch: $correction->branch,
            oldValues: $oldValues,
            newValues: $correction->toArray(),
        );

        return back()->with('status', 'Attendance correction approved.');
    }

    public function rejectCorrection(ReviewAttendanceCorrectionRequest $request, AttendanceCorrectionRequest $correction): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($correction->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym, $correction->branch_id);
        $this->assertAttendanceManageAccess($request, $gym, $correction->branch_id);

        $oldValues = $correction->toArray();
        $correction = $this->attendanceCorrectionService->reject($correction, $request->user(), $request->validated('notes'));

        $this->auditLogService->log(
            event: 'web.gym.attendance.correction.rejected',
            action: 'update',
            request: $request,
            subject: $correction,
            gym: $gym,
            branch: $correction->branch,
            oldValues: $oldValues,
            newValues: $correction->toArray(),
        );

        return back()->with('status', 'Attendance correction rejected.');
    }

    public function biometricScan(BiometricAttendanceRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym, true);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AttendanceManage->value, $gym, $branch?->id);
        $this->assertAttendanceManageAccess($request, $gym, $branch?->id);

        $log = $this->attendanceService->biometricCheckIn(
            gym: $gym,
            branch: $branch,
            biometricIdentifier: $request->validated('biometric_identifier'),
            checkedInBy: $request->user(),
            notes: $request->validated('notes'),
            sourceDevice: $request->validated('source_device', 'web-biometric-desk'),
        );

        $this->auditLogService->log(
            event: 'web.gym.attendance.biometric.created',
            action: 'create',
            request: $request,
            subject: $log,
            gym: $gym,
            branch: $branch,
            newValues: $log->toArray(),
        );

        return redirect()->route('web.gym.attendance.index', [
            'gym' => $gym->id,
            'branch' => $branch?->id,
        ])->with('status', 'Biometric attendance recorded successfully.');
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function buildAttendanceQuery(Request $request, $gym, array $branchIds)
    {
        $query = AttendanceLog::query()
            ->with(['member', 'checkedInByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->latest('checked_in_at');

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->integer('member_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('check_in_method')) {
            $query->where('check_in_method', $request->string('check_in_method'));
        }

        if ($request->boolean('today')) {
            $query->whereDate('checked_in_at', now()->toDateString());
        } elseif ($request->boolean('this_week')) {
            $query->whereBetween('checked_in_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($request->boolean('this_month')) {
            $query->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('checked_in_at', '>=', $request->date('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('checked_in_at', '<=', $request->date('end_date'));
            }
        }

        return $query;
    }

    private function assertAttendanceViewAccess(Request $request, $gym, ?int $branchId = null): void
    {
        $user = $request->user();

        if ($user->active_role === RoleName::GymStaff->value
            && ! $this->scopedPermissionResolver->hasCustomPermission($user, 'manage_attendance', $gym->id, $branchId)) {
            abort(403, 'You do not have attendance access for this scope.');
        }
    }

    private function assertAttendanceManageAccess(Request $request, $gym, ?int $branchId = null): void
    {
        $this->assertAttendanceViewAccess($request, $gym, $branchId);
    }

    private function canManageAttendance(Request $request, $gym, ?int $branchId = null): bool
    {
        if (! $this->gymWebPanelService->canPermission($request, PermissionName::AttendanceManage->value, $gym, $branchId)) {
            return false;
        }

        $user = $request->user();

        if ($user->active_role !== RoleName::GymStaff->value) {
            return true;
        }

        return $this->scopedPermissionResolver->hasCustomPermission($user, 'manage_attendance', $gym->id, $branchId);
    }
}
