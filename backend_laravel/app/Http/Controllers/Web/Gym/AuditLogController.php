<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Gym\GymAuditLogService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly GymAuditLogService $gymAuditLogService,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $branchScopeId = $request->filled('branch') ? (int) $request->integer('branch') : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym, $branchScopeId);
        $this->assertGymStaffAuditAccess($request, $gym->id, $branchScopeId);

        $filters = $this->gymAuditLogService->parseFilters($request, $accessibleBranchIds);
        $query = $this->gymAuditLogService->query($gym, $filters, $accessibleBranchIds);
        $logs = (clone $query)
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
            'summary' => [
                'total' => (clone $query)->count(),
                'system' => (clone $query)->whereNull('actor_user_id')->count(),
                'people' => (clone $query)->whereNotNull('actor_user_id')->count(),
                'branches' => (clone $query)->whereNotNull('branch_id')->distinct()->count('branch_id'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $branchScopeId = $request->filled('branch') ? (int) $request->integer('branch') : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym, $branchScopeId);
        $this->assertGymStaffAuditAccess($request, $gym->id, $branchScopeId);
        $filters = $this->gymAuditLogService->parseFilters($request, $accessibleBranchIds);
        $query = $this->gymAuditLogService->query($gym, $filters, $accessibleBranchIds);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date / Time', 'Event', 'Action', 'Actor', 'Actor Role', 'Branch', 'Subject', 'Subject ID', 'Old Values', 'New Values', 'Context', 'IP Address']);

            foreach ($query->lazy(500) as $log) {
                fputcsv($handle, [
                    optional($log->occurred_at ?? $log->created_at)->format('Y-m-d H:i:s'),
                    $log->event,
                    $log->action,
                    $log->actor?->name ?? 'System',
                    $log->actor_role,
                    $log->branch?->name ?? 'Gym-wide',
                    $log->subject_type ? class_basename($log->subject_type) : '',
                    $log->subject_id,
                    json_encode($this->gymAuditLogService->sanitizeValue($log->old_values ?? []), JSON_UNESCAPED_SLASHES),
                    json_encode($this->gymAuditLogService->sanitizeValue($log->new_values ?? []), JSON_UNESCAPED_SLASHES),
                    json_encode($this->gymAuditLogService->sanitizeValue($log->context ?? []), JSON_UNESCAPED_SLASHES),
                    $log->ip_address,
                ]);
            }

            fclose($handle);
        }, 'gym-audit-logs-'.$gym->id.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function assertGymStaffAuditAccess(Request $request, int $gymId, ?int $branchId): void
    {
        $user = $request->user();

        if (! $user->hasRole(RoleName::GymStaff->value)) {
            return;
        }

        abort_unless(
            app(ScopedPermissionResolver::class)->hasCustomPermission($user, 'view_reports', $gymId, $branchId),
            403,
            'You do not have permission to view audit logs.'
        );
    }
}
