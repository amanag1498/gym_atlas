<?php

namespace App\Services\Gym;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\User;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffManagementService
{
    public function __construct(
        private readonly GymSettingService $gymSettingService,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function permissionToggles(): array
    {
        return [
            'view_billing' => 'View Billing',
            'collect_payment' => 'Collect Payment',
            'edit_custom_fee' => 'Edit Custom Fee',
            'manage_attendance' => 'Manage Attendance',
            'manage_members' => 'Manage Members',
            'manage_trainers' => 'Manage Trainers',
            'send_announcements' => 'Send Announcements',
            'view_reports' => 'View Reports',
            'manage_staff' => 'Manage Staff',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedRoles(Request $request): array
    {
        return match ($request->user()?->active_role) {
            RoleName::PlatformAdmin->value => [RoleName::BranchManager->value, RoleName::GymStaff->value],
            RoleName::GymOwner->value => [RoleName::BranchManager->value, RoleName::GymStaff->value],
            RoleName::BranchManager->value => [RoleName::GymStaff->value],
            RoleName::GymStaff->value => [RoleName::GymStaff->value],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public function allowedCustomPermissions(Request $request, Gym $gym, ?int $branchId = null): array
    {
        if (in_array($request->user()?->active_role, [
            RoleName::PlatformAdmin->value,
            RoleName::GymOwner->value,
        ], true)) {
            return array_keys($this->permissionToggles());
        }

        /** @var ScopedPermissionResolver $resolver */
        $resolver = app(ScopedPermissionResolver::class);

        return array_values(array_intersect(
            array_keys($this->permissionToggles()),
            $resolver->customPermissionsFor($request->user(), $gym->id, $branchId)
        ));
    }

    public function assertRoleAssignmentAllowed(Request $request, string $role): void
    {
        abort_unless(in_array($role, $this->allowedRoles($request), true), 403, 'You cannot assign that staff role.');
    }

    /**
     * @param  list<int|string>  $branchIds
     */
    public function assertRoleScopeValid(string $role, array $branchIds): void
    {
        if ($role === RoleName::BranchManager->value && $branchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => ['Branch managers must be assigned to at least one branch.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertPermissionGrantAllowed(Request $request, Gym $gym, array $permissions, ?int $branchId = null): void
    {
        $allowed = $this->allowedCustomPermissions($request, $gym, $branchId);

        foreach ($permissions as $permission) {
            abort_unless(in_array($permission, $allowed, true), 403, 'You cannot grant permissions you do not have.');
        }
    }

    public function assertStaffAccessible(Request $request, Gym $gym, User $staff, GymWebPanelService $panelService): void
    {
        abort_unless($staff->gyms()->where('gyms.id', $gym->id)->exists(), 404);

        if ($request->user()?->active_role === RoleName::GymOwner->value) {
            return;
        }

        $accessibleBranchIds = $panelService->accessibleBranchIds($request, $gym);
        $staffBranchIds = $staff->branches()->where('branches.gym_id', $gym->id)->pluck('branches.id')->map(fn ($id) => (int) $id)->all();

        abort_unless(array_intersect($accessibleBranchIds, $staffBranchIds) !== [], 403);
    }

    public function hasPhoneColumn(): bool
    {
        return Schema::hasColumn('users', 'phone');
    }

    public function baseStaffQuery(Request $request, Gym $gym, GymWebPanelService $panelService): Builder
    {
        $query = User::query()
            ->with(['branches', 'roles', 'gyms'])
            ->whereHas('gyms', fn (Builder $builder) => $builder->where('gyms.id', $gym->id))
            ->whereHas('roles', fn (Builder $builder) => $builder->whereIn('name', [
                RoleName::BranchManager->value,
                RoleName::GymStaff->value,
            ]));

        if ($branch = $panelService->resolveBranch($request, $gym)) {
            $query->whereHas('branches', fn (Builder $builder) => $builder->where('branches.id', $branch->id));
        } elseif ($request->user()?->active_role === RoleName::GymOwner->value) {
            return $query;
        } else {
            $query->whereHas('branches', fn (Builder $builder) => $builder->whereIn('branches.id', $panelService->accessibleBranchIds($request, $gym)));
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public function defaultCustomPermissions(Gym $gym): array
    {
        $defaults = $this->gymSettingService->all($gym)['staff_permission_defaults'] ?? [];

        if (! is_array($defaults)) {
            return [];
        }

        return array_values(array_filter($defaults, 'is_string'));
    }

    /**
     * @return list<string>
     */
    public function decodePermissions(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
