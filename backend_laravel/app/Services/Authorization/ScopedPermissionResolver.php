<?php

namespace App\Services\Authorization;

use App\Enums\PermissionName;
use App\Models\User;

class ScopedPermissionResolver
{
    /**
     * @return array<string, list<string>>
     */
    private const CUSTOM_PERMISSION_MAP = [
        'view_billing' => [
            PermissionName::PaymentsView->value,
            PermissionName::MembershipsView->value,
            PermissionName::MembershipPlansView->value,
        ],
        'collect_payment' => [
            PermissionName::PaymentsManage->value,
        ],
        'edit_custom_fee' => [
            PermissionName::EditCustomFee->value,
        ],
        'manage_attendance' => [
            PermissionName::AttendanceManage->value,
        ],
        'manage_members' => [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ],
        'manage_trainers' => [
            PermissionName::TrainersManage->value,
        ],
        'manage_staff' => [
            PermissionName::StaffManage->value,
        ],
        'view_reports' => [
            PermissionName::GymDashboardView->value,
        ],
        'send_announcements' => [
            PermissionName::AnnouncementsManage->value,
        ],
    ];

    public function hasPermission(User $user, string $permission, ?int $gymId = null, ?int $branchId = null): bool
    {
        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        if (! $gymId && ! $branchId) {
            return false;
        }

        foreach ($this->customPermissionsFor($user, $gymId, $branchId) as $customPermission) {
            $mappedPermissions = self::CUSTOM_PERMISSION_MAP[$customPermission] ?? [];

            if (in_array($permission, $mappedPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasCustomPermission(User $user, string $customPermission, ?int $gymId = null, ?int $branchId = null): bool
    {
        return in_array($customPermission, $this->customPermissionsFor($user, $gymId, $branchId), true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(User $user, array $permissions, ?int $gymId = null, ?int $branchId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission, $gymId, $branchId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function customPermissionsFor(User $user, ?int $gymId = null, ?int $branchId = null): array
    {
        $permissions = [];

        if ($gymId) {
            $gym = $user->gyms->firstWhere('id', $gymId);
            $permissions = array_merge($permissions, $this->normalizePermissions($gym?->pivot?->custom_permissions));
        }

        if ($branchId) {
            $branch = $user->branches->firstWhere('id', $branchId);
            $permissions = array_merge($permissions, $this->normalizePermissions($branch?->pivot?->custom_permissions));
        }

        return array_values(array_unique(array_filter($permissions, 'is_string')));
    }

    /**
     * @return list<string>
     */
    private function normalizePermissions(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    }
}
