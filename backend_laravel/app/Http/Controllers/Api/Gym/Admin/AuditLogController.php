<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\GymAuditLogResource;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Gym\GymAuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly GymAuditLogService $gymAuditLogService,
    ) {
    }

    public function index(Request $request)
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
            $this->scopedPermissionResolver->hasPermission($request->user(), PermissionName::GymDashboardView->value, $gym->id, $branchScopeId),
            403,
            'You do not have permission to view audit logs.'
        );

        if ($request->user()->hasRole(RoleName::GymStaff->value)) {
            abort_unless(
                $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'view_reports', $gym->id, $branchScopeId),
                403,
                'You do not have permission to view audit logs.'
            );
        }

        $filters = $this->gymAuditLogService->parseFilters($request, $branch ? [(int) $branch->id] : $branchIds);
        $paginator = $this->gymAuditLogService->query($gym, $filters, $branch ? [(int) $branch->id] : $branchIds)
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated(
            $paginator,
            GymAuditLogResource::collection($paginator->getCollection()),
            'Gym audit logs fetched successfully.',
        );
    }
}
