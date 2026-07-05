<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\StoreStaffRequest;
use App\Http\Requests\Gym\Admin\UpdateStaffRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\AuditTimelineService;
use App\Services\Gym\StaffManagementService;
use App\Services\Users\ManagedUserService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ManagedUserService $managedUserService,
        private readonly AuditLogService $auditLogService,
        private readonly AuditTimelineService $auditTimelineService,
        private readonly StaffManagementService $staffManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $query = $this->staffManagementService
            ->baseStaffQuery($request, $gym, $this->gymWebPanelService)
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $hasPhoneColumn = $this->staffManagementService->hasPhoneColumn();
            $query->where(function (Builder $builder) use ($search, $hasPhoneColumn): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($hasPhoneColumn) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        $staffMembers = $query->paginate(12)->withQueryString();
        $staffIds = $staffMembers->getCollection()->pluck('id')->all();
        $staffActivityLogs = ActivityLog::query()
            ->with('actor')
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $this->gymWebPanelService->selectedBranchIds($request, $gym))
            ->whereIn('actor_user_id', $staffIds)
            ->latest('occurred_at')
            ->take(40)
            ->get()
            ->groupBy('actor_user_id');

        return view('web.gym.staff.index', [
            'pageTitle' => 'Staff',
            'breadcrumbs' => ['Gym', 'Staff'],
            'gym' => $gym,
            'staffMembers' => $staffMembers,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'permissionToggles' => $this->staffManagementService->permissionToggles(),
            'staffActivityTimeline' => $staffActivityLogs->map(
                fn ($logs) => $this->auditTimelineService->forActivityLogs($logs->take(6))
            ),
            'allowedRoles' => $this->staffManagementService->allowedRoles($request),
            'allowedCustomPermissions' => $this->staffManagementService->allowedCustomPermissions($request, $gym),
            'hasPhoneColumn' => $this->staffManagementService->hasPhoneColumn(),
            'summary' => [
                'total' => $staffMembers->total(),
                'active' => $staffMembers->getCollection()->where('is_active', true)->count(),
                'branch_managers' => $staffMembers->getCollection()->filter(fn (User $user) => $user->hasRole(RoleName::BranchManager->value))->count(),
                'custom_permission_grants' => $staffMembers->getCollection()->sum(function (User $user) use ($gym): int {
                    $raw = $user->gyms->firstWhere('id', $gym->id)?->pivot?->custom_permissions ?? [];
                    $decoded = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

                    return count(array_filter($decoded, 'is_string'));
                }),
            ],
            'existingUsers' => User::query()
                ->where('is_active', true)
                ->whereDoesntHave('gyms', fn (Builder $builder) => $builder->where('gyms.id', $gym->id))
                ->orderBy('name')
                ->limit(50)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);

        return view('web.gym.staff.create', [
            'pageTitle' => 'Create Staff',
            'breadcrumbs' => ['Gym', 'Staff', 'Create'],
            'gym' => $gym,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'permissionToggles' => $this->staffManagementService->permissionToggles(),
            'allowedRoles' => $this->staffManagementService->allowedRoles($request),
            'allowedCustomPermissions' => $this->staffManagementService->allowedCustomPermissions($request, $gym),
            'defaultCustomPermissions' => $this->staffManagementService->defaultCustomPermissions($gym),
            'hasPhoneColumn' => $this->staffManagementService->hasPhoneColumn(),
            'existingUsers' => User::query()
                ->where('is_active', true)
                ->whereDoesntHave('gyms', fn (Builder $builder) => $builder->where('gyms.id', $gym->id))
                ->orderBy('name')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $payload = $this->normalizedPayload($request, $gym);
        $this->assertBranchesInScope($request, $gym, $payload['branch_ids'] ?? []);
        $this->staffManagementService->assertRoleAssignmentAllowed($request, $payload['role']);
        $this->staffManagementService->assertRoleScopeValid($payload['role'], $payload['branch_ids'] ?? []);
        $this->staffManagementService->assertPermissionGrantAllowed($request, $gym, $payload['custom_permissions'] ?? []);

        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;
        $user = $this->managedUserService->upsertStaff($existingUser, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.staff.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            newValues: $user->fresh(['roles', 'branches', 'gyms'])->toArray(),
        );

        return redirect()
            ->route('web.gym.staff.show', ['staff' => $user->id, 'gym' => $gym->id])
            ->with('status', 'Staff member created successfully.');
    }

    public function show(Request $request, User $staff): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        return view('web.gym.staff.show', [
            'pageTitle' => 'Staff Detail',
            'breadcrumbs' => ['Gym', 'Staff', $staff->name],
            'gym' => $gym,
            'staff' => $staff->load(['roles', 'branches', 'gyms', 'activityLogs.actor']),
            'permissionToggles' => $this->staffManagementService->permissionToggles(),
            'currentPermissions' => $this->currentCustomPermissions($staff, $gym),
            'activityTimeline' => $this->auditTimelineService->forActivityLogs(
                ActivityLog::query()
                    ->where('gym_id', $gym->id)
                    ->where('actor_user_id', $staff->id)
                    ->latest('occurred_at')
                    ->take(12)
                    ->get()
            ),
            'canManageStaff' => $this->canManageTarget($request, $gym, $staff),
        ]);
    }

    public function edit(Request $request, User $staff): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        return view('web.gym.staff.edit', [
            'pageTitle' => 'Edit Staff',
            'breadcrumbs' => ['Gym', 'Staff', $staff->name, 'Edit'],
            'gym' => $gym,
            'staff' => $staff->load(['roles', 'branches', 'gyms']),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'permissionToggles' => $this->staffManagementService->permissionToggles(),
            'allowedRoles' => $this->staffManagementService->allowedRoles($request),
            'allowedCustomPermissions' => $this->staffManagementService->allowedCustomPermissions($request, $gym),
            'defaultCustomPermissions' => $this->staffManagementService->defaultCustomPermissions($gym),
            'hasPhoneColumn' => $this->staffManagementService->hasPhoneColumn(),
            'currentPermissions' => $this->currentCustomPermissions($staff, $gym),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        $payload = $this->normalizedPayload($request, $gym, $staff);
        $this->assertBranchesInScope($request, $gym, $payload['branch_ids'] ?? []);
        $this->staffManagementService->assertRoleAssignmentAllowed($request, $payload['role']);
        $this->staffManagementService->assertRoleScopeValid($payload['role'], $payload['branch_ids'] ?? []);
        $this->staffManagementService->assertPermissionGrantAllowed($request, $gym, $payload['custom_permissions'] ?? []);

        $oldValues = $staff->load(['roles', 'branches', 'gyms'])->toArray();
        $user = $this->managedUserService->upsertStaff($staff, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.staff.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $user->fresh(['roles', 'branches', 'gyms'])->toArray(),
        );

        return redirect()
            ->route('web.gym.staff.show', ['staff' => $user->id, 'gym' => $gym->id])
            ->with('status', 'Staff member updated successfully.');
    }

    public function activate(Request $request, User $staff): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        $oldValues = $staff->only(['is_active']);
        $staff = $this->managedUserService->setStaffActive($staff, $gym, true);

        $this->auditLogService->log(
            event: 'web.gym.staff.status.updated',
            action: 'update',
            request: $request,
            subject: $staff,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $staff->fresh()->only(['is_active']),
        );

        return back()->with('status', 'Staff account activated successfully.');
    }

    public function deactivate(Request $request, User $staff): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        $oldValues = $staff->only(['is_active']);
        $staff = $this->managedUserService->setStaffActive($staff, $gym, false);

        $this->auditLogService->log(
            event: 'web.gym.staff.status.updated',
            action: 'update',
            request: $request,
            subject: $staff,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $staff->fresh()->only(['is_active']),
        );

        return back()->with('status', 'Staff account deactivated successfully.');
    }

    public function toggleActive(Request $request, User $staff): RedirectResponse
    {
        return $staff->is_active
            ? $this->deactivate($request, $staff)
            : $this->activate($request, $staff);
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::StaffManage->value, $gym);
        $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

        $oldValues = $staff->load(['roles', 'branches', 'gyms'])->toArray();
        $user = $this->managedUserService->removeStaff($staff, $gym);

        $this->auditLogService->log(
            event: 'web.gym.staff.removed',
            action: 'delete',
            request: $request,
            subject: $staff,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $user->fresh(['roles', 'branches', 'gyms'])->toArray(),
        );

        return redirect()
            ->route('web.gym.staff.index', ['gym' => $gym->id])
            ->with('status', 'Staff member removed from this gym successfully.');
    }

    /**
     * @param  array<int, mixed>  $branchIds
     */
    private function assertBranchesInScope(Request $request, $gym, array $branchIds): void
    {
        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);

        foreach ($branchIds as $branchId) {
            abort_unless(in_array((int) $branchId, $accessibleBranchIds, true), 422);
        }
    }

    private function currentStaffRole(User $staff): string
    {
        foreach ([RoleName::BranchManager->value, RoleName::GymStaff->value] as $role) {
            if ($staff->hasRole($role)) {
                return $role;
            }
        }

        return RoleName::GymStaff->value;
    }

    /**
     * @return list<string>
     */
    private function decodeCustomPermissions(mixed $value): array
    {
        return $this->staffManagementService->decodePermissions($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, $gym, ?User $staff = null): array
    {
        $payload = $request->validated();
        $existingUser = $request->filled('existing_user_id')
            ? User::query()->find($request->validated('existing_user_id'))
            : null;
        $gymPivot = $staff?->gyms()->where('gyms.id', $gym->id)->first()?->pivot;

        if ($existingUser) {
            $payload['name'] = $existingUser->name;
            $payload['email'] = $existingUser->email;
            if ($this->staffManagementService->hasPhoneColumn()) {
                $payload['phone'] = $existingUser->phone;
            }
        } else {
            $payload['name'] = $request->validated('name', $staff?->name);
            $payload['email'] = $request->validated('email', $staff?->email);
            $payload['avatar'] = $request->validated('avatar', $staff?->avatar);
            if ($this->staffManagementService->hasPhoneColumn()) {
                $payload['phone'] = $request->validated('phone', $staff?->phone);
            }
            if ($request->filled('password')) {
                $payload['password'] = $request->validated('password');
            }
        }

        $payload['role'] = $request->validated('role', $staff ? $this->currentStaffRole($staff) : RoleName::GymStaff->value);
        $payload['branch_ids'] = $request->has('branch_ids')
            ? $request->validated('branch_ids', [])
            : ($staff ? $staff->branches()->where('branches.gym_id', $gym->id)->pluck('branches.id')->all() : []);
        $payload['custom_permissions'] = $request->boolean('custom_permissions_present') || $request->has('custom_permissions')
            ? $request->validated('custom_permissions', [])
            : ($staff
                ? $this->decodeCustomPermissions($gymPivot?->custom_permissions)
                : $this->staffManagementService->defaultCustomPermissions($gym));
        $payload['is_active'] = $request->filled('status')
            ? $request->validated('status') === 'active'
            : ($staff?->is_active ?? true);

        return $payload;
    }

    private function currentCustomPermissions(User $staff, $gym): array
    {
        $pivot = $staff->gyms()->where('gyms.id', $gym->id)->first()?->pivot;

        return $this->decodeCustomPermissions($pivot?->custom_permissions);
    }

    private function canManageTarget(Request $request, $gym, User $staff): bool
    {
        try {
            $this->staffManagementService->assertStaffAccessible($request, $gym, $staff, $this->gymWebPanelService);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
