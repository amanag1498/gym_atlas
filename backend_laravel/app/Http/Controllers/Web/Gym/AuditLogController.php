<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Gym\GymAuditLogService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly GymAuditLogService $gymAuditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $branchScopeId = $request->filled('branch') ? (int) $request->integer('branch') : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym, $branchScopeId);
        $this->assertGymStaffAuditAccess($request, $gym->id, $branchScopeId);

        $filters = $this->gymAuditLogService->parseFilters($request, $accessibleBranchIds);
        $logs = $this->gymAuditLogService->query($gym, $filters, $accessibleBranchIds)
            ->paginate(20)
            ->withQueryString();

        return view('web.gym.audit-logs.index', [
            'pageTitle' => 'Gym Audit Logs',
            'breadcrumbs' => ['Gym', 'Audit Logs'],
            'gym' => $gym,
            'auditLogs' => $logs,
            'filters' => [
                'actor' => $filters['actor'],
                'action' => $filters['action'],
                'subject_type' => $filters['subject_type'],
                'branch_id' => $filters['branch_id'],
                'start_date' => $filters['start_date']?->toDateString(),
                'end_date' => $filters['end_date']?->toDateString(),
            ],
            'subjectTypeOptions' => $this->gymAuditLogService->subjectTypeOptions($gym, $accessibleBranchIds),
            'branches' => $this->gymAuditLogService->branchOptions($gym, $accessibleBranchIds),
            'sanitizer' => $this->gymAuditLogService,
        ]);
    }

    private function assertGymStaffAuditAccess(Request $request, int $gymId, ?int $branchId): void
    {
        $user = $request->user();

        if (! $user->hasRole(RoleName::GymStaff->value)) {
            return;
        }

        abort_unless(
            app(\App\Services\Authorization\ScopedPermissionResolver::class)->hasCustomPermission($user, 'view_reports', $gymId, $branchId),
            403,
            'You do not have permission to view audit logs.'
        );
    }
}
