<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Gym\GymReportService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly GymReportService $reportService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderReport($request, 'overview');
    }

    public function revenue(Request $request): View
    {
        return $this->renderReport($request, 'revenue');
    }

    public function dues(Request $request): View
    {
        return $this->renderReport($request, 'dues');
    }

    public function memberships(Request $request): View
    {
        return $this->renderReport($request, 'memberships');
    }

    public function attendance(Request $request): View
    {
        return $this->renderReport($request, 'attendance');
    }

    public function trainers(Request $request): View
    {
        return $this->renderReport($request, 'trainers');
    }

    public function customFees(Request $request): View
    {
        return $this->renderReport($request, 'custom-fees');
    }

    public function leads(Request $request): View
    {
        return $this->renderReport($request, 'leads');
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $branchScopeId = $request->filled('branch_id')
            ? (int) $request->integer('branch_id')
            : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym, $branchScopeId);
        $this->assertGymStaffReportAccess($request, $gym->id, $branchScopeId);
        $filters = $this->reportService->parseFilters($request, $gym, $accessibleBranchIds);
        $dataset = $this->reportService->buildExport($type, $gym, $filters);

        return response()->streamDownload(function () use ($dataset): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $dataset['export_columns'] ?? $dataset['columns']);
            foreach ($dataset['export_rows'] ?? $dataset['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'gym-'.$dataset['key'].'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function renderReport(Request $request, string $type): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $branchScopeId = $request->filled('branch_id')
            ? (int) $request->integer('branch_id')
            : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym, $branchScopeId);
        $this->assertGymStaffReportAccess($request, $gym->id, $branchScopeId);
        $filters = $this->reportService->parseFilters($request, $gym, $accessibleBranchIds);
        $dataset = $this->reportService->build($type, $gym, $filters);

        return view('web.gym.reports.index', [
            'pageTitle' => 'Gym Reports',
            'breadcrumbs' => ['Gym', 'Reports'],
            'gym' => $gym,
            'reportKey' => $dataset['key'],
            'reportTitle' => $dataset['title'],
            'reportDescription' => $dataset['description'],
            'summaryCards' => $dataset['summary_cards'],
            'chartCards' => $dataset['chart_cards'] ?? [],
            'columns' => $dataset['columns'],
            'rows' => $dataset['rows'],
            'emptyState' => $dataset['empty_state'],
            'reportOptions' => $this->reportService->reportOptions(),
            'reportNavigation' => $this->reportService->navigation(),
            'filterOptions' => $this->reportService->filterOptions($gym, $accessibleBranchIds),
            'filters' => [
                'start_date' => $filters['start_date']->toDateString(),
                'end_date' => $filters['end_date']->toDateString(),
                'branch_id' => $filters['branch_id'],
                'trainer_id' => $filters['trainer_id'],
                'plan_id' => $filters['plan_id'],
                'status' => $filters['status'],
            ],
        ]);
    }

    private function assertGymStaffReportAccess(Request $request, int $gymId, ?int $branchId): void
    {
        $user = $request->user();

        if (! $user->hasRole(RoleName::GymStaff->value)) {
            return;
        }

        abort_unless(
            $this->scopedPermissionResolver->hasCustomPermission($user, 'view_reports', $gymId, $branchId),
            403,
            'You do not have permission to view reports.'
        );
    }
}
