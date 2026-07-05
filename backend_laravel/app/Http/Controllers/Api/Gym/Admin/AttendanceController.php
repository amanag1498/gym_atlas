<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BiometricAttendanceRequest;
use App\Http\Requests\Attendance\ManualAttendanceRequest;
use App\Http\Resources\Attendance\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\Gym;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly AttendanceService $attendanceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->resolveGym($request);
        $this->assertAttendanceViewAccess($request, $gym);
        $query = AttendanceLog::query()
            ->with(['member', 'checkedInByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $this->accessibleBranchIds($request, $gym))
            ->when($request->filled('branch_id'), fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('member_id'), fn ($builder) => $builder->where('member_id', $request->integer('member_id')))
            ->when($request->filled('check_in_method'), fn ($builder) => $builder->where('check_in_method', $request->string('check_in_method')))
            ->latest('checked_in_at');

        $this->applyAttendanceDateFilters($query, $request);

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, AttendanceLogResource::collection($paginator->getCollection()), 'Attendance history fetched successfully.');
    }

    public function today(Request $request)
    {
        $gym = $this->resolveGym($request);
        $branch = $this->scopeResolver->resolveBranch($request);
        $this->assertAttendanceViewAccess($request, $gym, $branch?->id);

        $query = AttendanceLog::query()
            ->with(['member', 'checkedInByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->whereDate('checked_in_at', now()->toDateString());

        if ($branch) {
            $query->where('branch_id', $branch->id);
        } else {
            $query->whereIn('branch_id', $this->accessibleBranchIds($request, $gym));
        }

        $logs = $query->latest('checked_in_at')->get();

        return $this->success([
            'count' => $logs->count(),
            'items' => AttendanceLogResource::collection($logs),
        ], 'Today check-ins fetched successfully.');
    }

    public function manual(ManualAttendanceRequest $request)
    {
        $gym = $this->resolveGym($request);
        $branch = $this->resolveBranchForGym($request, $gym);
        $this->assertAttendanceManageAccess($request, $gym, $branch->id);
        $member = User::query()->findOrFail($request->integer('member_id'));

        $log = $this->attendanceService->recordManualCheckIn(
            gym: $gym,
            branch: $branch,
            member: $member,
            checkedInBy: $request->user(),
            notes: $request->validated('notes'),
            sourceDevice: $request->validated('source_device'),
            checkedInAt: $request->validated('checked_in_at'),
        );

        $this->auditLogService->log(
            event: 'attendance.manual.created',
            action: 'create',
            request: $request,
            subject: $log,
            gym: $gym,
            branch: $branch,
            newValues: $log->toArray(),
        );

        return $this->success(AttendanceLogResource::make($log->load(['member', 'checkedInByUser'])), 'Manual attendance recorded successfully.', 201);
    }

    public function biometricScan(BiometricAttendanceRequest $request)
    {
        $gym = $this->resolveGym($request);
        $branch = $this->resolveBranchForGym($request, $gym);
        $this->assertAttendanceManageAccess($request, $gym, $branch->id);

        $log = $this->attendanceService->biometricCheckIn(
            gym: $gym,
            branch: $branch,
            biometricIdentifier: $request->validated('biometric_identifier'),
            checkedInBy: $request->user(),
            notes: $request->validated('notes'),
            sourceDevice: $request->validated('source_device'),
        );

        $this->auditLogService->log(
            event: 'attendance.biometric.created',
            action: 'create',
            request: $request,
            subject: $log,
            gym: $gym,
            branch: $branch,
            newValues: $log->toArray(),
        );

        return $this->success(AttendanceLogResource::make($log->load(['member', 'checkedInByUser'])), 'Biometric attendance recorded successfully.', 201);
    }

    public function memberHistory(Request $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $this->assertAttendanceViewAccess($request, $gym, $request->integer('branch_id') ?: null);
        abort_unless(
            $member->memberProfile?->gym_id === $gym->id
                && in_array((int) $member->memberProfile?->branch_id, $this->accessibleBranchIds($request, $gym), true),
            404,
            'Member not found in accessible scope.'
        );

        $query = AttendanceLog::query()
            ->with(['checkedInByUser', 'branch'])
            ->where('gym_id', $gym->id)
            ->where('member_id', $member->id)
            ->latest('checked_in_at');

        $query->whereIn('branch_id', $this->accessibleBranchIds($request, $gym));
        $query->when($request->filled('branch_id'), fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')));
        $query->when($request->filled('check_in_method'), fn ($builder) => $builder->where('check_in_method', $request->string('check_in_method')));
        $this->applyAttendanceDateFilters($query, $request);

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, AttendanceLogResource::collection($paginator->getCollection()), 'Member attendance history fetched successfully.');
    }

    public function branchWise(Request $request)
    {
        $gym = $this->resolveGym($request);
        $this->assertAttendanceViewAccess($request, $gym, $request->integer('branch_id') ?: null);
        $date = $request->date('date', now()->toDateString());

        $summary = AttendanceLog::query()
            ->selectRaw('branch_id, count(*) as total_check_ins')
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $this->accessibleBranchIds($request, $gym))
            ->whereDate('checked_in_at', $date)
            ->groupBy('branch_id')
            ->get();

        return $this->success($summary, 'Branch attendance summary fetched successfully.');
    }

    private function resolveGym(Request $request): Gym
    {
        /** @var Gym $gym */
        $gym = $this->scopeResolver->resolveGym($request, true);

        return $gym;
    }

    private function resolveBranchForGym(Request $request, Gym $gym)
    {
        $branch = $this->scopeResolver->resolveBranch($request, true);
        abort_unless($branch->gym_id === $gym->id, 422, 'Selected branch does not belong to the selected gym.');

        return $branch;
    }

    private function applyAttendanceDateFilters($query, Request $request): void
    {
        if ($request->boolean('today')) {
            $query->whereDate('checked_in_at', now()->toDateString());
            return;
        }

        if ($request->boolean('this_week')) {
            $query->whereBetween('checked_in_at', [now()->startOfWeek(), now()->endOfWeek()]);
            return;
        }

        if ($request->boolean('this_month')) {
            $query->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfMonth()]);
            return;
        }

        if ($request->filled('start_date')) {
            $query->whereDate('checked_in_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('checked_in_at', '<=', $request->date('end_date'));
        }
    }

    private function assertAttendanceViewAccess(Request $request, Gym $gym, ?int $branchId = null): void
    {
        $user = $request->user();

        if ($user->active_role === RoleName::GymStaff->value
            && ! $this->scopedPermissionResolver->hasCustomPermission($user, 'manage_attendance', $gym->id, $branchId)) {
            throw new HttpException(403, 'You do not have attendance access for this scope.');
        }
    }

    private function assertAttendanceManageAccess(Request $request, Gym $gym, ?int $branchId = null): void
    {
        $this->assertAttendanceViewAccess($request, $gym, $branchId);
    }

    /**
     * @return list<int>
     */
    private function accessibleBranchIds(Request $request, Gym $gym): array
    {
        return $this->scopeResolver->branchesQuery($request->user())
            ->where('gym_id', $gym->id)
            ->pluck('branches.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
