<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\UpdateGymSettingsRequest;
use App\Models\NotificationPreference;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Gym\GymSettingService;
use App\Services\Notification\NotificationPreferenceCatalogService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly GymSettingService $gymSettingService,
        private readonly NotificationPreferenceCatalogService $notificationPreferenceCatalogService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request, true);
        $branch = $this->scopeResolver->resolveBranch($request, false);
        $branchScopeId = $branch?->id ?? $this->singleBranchScopeId($request, $gym->id);
        $this->assertAccess($request, $gym->id, $branchScopeId);

        return $this->success([
            ...$this->gymSettingService->all($gym),
            'notification_preferences' => $this->notificationPreferenceCatalogService->forUser($request->user()),
        ], 'Gym settings fetched successfully.');
    }

    public function update(UpdateGymSettingsRequest $request)
    {
        $gym = $this->scopeResolver->resolveGym($request, true);
        $branch = $this->scopeResolver->resolveBranch($request, false);
        $branchScopeId = $branch?->id ?? $this->singleBranchScopeId($request, $gym->id);
        $this->assertAccess($request, $gym->id, $branchScopeId);

        $oldValues = $this->gymSettingService->all($gym);
        $newValues = $this->gymSettingService->update($gym, $request->safe()->except(['notification_preferences', 'gym_id', 'branch_id']));

        if ($request->filled('notification_preferences')) {
            foreach ($request->validated('notification_preferences') as $preference) {
                NotificationPreference::query()->updateOrCreate([
                    'user_id' => $request->user()->id,
                    'gym_id' => null,
                    'branch_id' => null,
                    'notification_type' => $preference['notification_type'],
                ], [
                    'is_enabled' => $preference['is_enabled'],
                ]);
            }
        }

        $payload = [
            ...$newValues,
            'notification_preferences' => $this->notificationPreferenceCatalogService->forUser($request->user()),
        ];

        $this->auditLogService->log(
            event: 'gym.settings.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $payload,
        );

        return $this->success($payload, 'Gym settings updated successfully.');
    }

    private function assertAccess(Request $request, int $gymId, ?int $branchId): void
    {
        abort_unless(
            $this->scopedPermissionResolver->hasAnyPermission($request->user(), [
                PermissionName::GymDashboardView->value,
                PermissionName::StaffManage->value,
            ], $gymId, $branchId),
            403,
            'You do not have permission to manage settings.'
        );

        if ($request->user()->hasRole(RoleName::GymStaff->value)) {
            abort_unless(
                $this->scopedPermissionResolver->hasAnyPermission($request->user(), [
                    PermissionName::GymDashboardView->value,
                    PermissionName::StaffManage->value,
                ], $gymId, $branchId),
                403,
                'You do not have permission to manage settings.'
            );
        }
    }

    private function singleBranchScopeId(Request $request, int $gymId): ?int
    {
        $branchIds = $this->scopeResolver->branchesQuery($request->user())
            ->where('gym_id', $gymId)
            ->pluck('branches.id');

        return $branchIds->count() === 1 ? (int) $branchIds->first() : null;
    }
}
