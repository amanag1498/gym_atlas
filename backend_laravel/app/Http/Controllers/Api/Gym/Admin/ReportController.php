<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Gym\GymReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly GymReportService $reportService,
    ) {
    }

    public function index(Request $request)
    {
        return $this->respond($request, 'overview');
    }

    public function revenue(Request $request)
    {
        return $this->respond($request, 'revenue');
    }

    public function dues(Request $request)
    {
        return $this->respond($request, 'dues');
    }

    public function memberships(Request $request)
    {
        return $this->respond($request, 'memberships');
    }

    public function attendance(Request $request)
    {
        return $this->respond($request, 'attendance');
    }

    public function trainers(Request $request)
    {
        return $this->respond($request, 'trainers');
    }

    public function customFees(Request $request)
    {
        return $this->respond($request, 'custom-fees');
    }

    public function leads(Request $request)
    {
        return $this->respond($request, 'leads');
    }

    private function respond(Request $request, string $type)
    {
        $gym = $this->scopeResolver->resolveGym($request, true);
        $branch = $this->scopeResolver->resolveBranch($request, false);
        $branchIds = $this->scopeResolver->branchesQuery($request->user())
            ->where('gym_id', $gym->id)
            ->pluck('branches.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $branchScopeId = $branch?->id ?? (count($branchIds) === 1 ? $branchIds[0] : null);

        abort_unless(
            $this->scopedPermissionResolver->hasPermission(
                $request->user(),
                PermissionName::GymDashboardView->value,
                $gym->id,
                $branchScopeId
            ),
            403,
            'You do not have permission to view reports.'
        );

        if ($request->user()->hasRole(RoleName::GymStaff->value)) {
            abort_unless(
                $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'view_reports', $gym->id, $branchScopeId),
                403,
                'You do not have permission to view reports.'
            );
        }

        $filters = $this->reportService->parseFilters($request, $gym, $branch ? [(int) $branch->id] : $branchIds);
        $dataset = $this->reportService->build($type, $gym, $filters);

        return $this->success([
            'report_key' => $dataset['key'],
            'report_title' => $dataset['title'],
            'report_description' => $dataset['description'],
            'summary_cards' => $dataset['summary_cards'],
            'chart_cards' => $dataset['chart_cards'] ?? [],
            'columns' => $dataset['columns'],
            'rows' => $this->reportService->normalizeRows($dataset['rows']),
            'empty_state' => $dataset['empty_state'],
            'report_options' => $this->reportService->reportOptions(),
            'filters' => [
                'start_date' => $filters['start_date']->toDateString(),
                'end_date' => $filters['end_date']->toDateString(),
                'branch_id' => $filters['branch_id'],
                'trainer_id' => $filters['trainer_id'],
                'plan_id' => $filters['plan_id'],
                'status' => $filters['status'],
            ],
        ], 'Gym report fetched successfully.');
    }
}
