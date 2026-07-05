<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\MemberMembership;
use App\Models\User;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Authorization\ScopeResolver;

class MemberMembershipPolicy
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            PermissionName::MembershipsView->value,
            PermissionName::MembershipsManage->value,
            PermissionName::PaymentsView->value,
            PermissionName::PaymentsManage->value,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::MembershipsManage->value);
    }

    public function view(User $user, MemberMembership $membership): bool
    {
        return $this->viewAny($user)
            && $this->scopeResolver->canAccessGym($user, $membership->gym_id)
            && $this->scopeResolver->canAccessBranch($user, $membership->branch_id);
    }

    public function update(User $user, MemberMembership $membership): bool
    {
        return $this->scopeResolver->canAccessGym($user, $membership->gym_id)
            && $this->scopeResolver->canAccessBranch($user, $membership->branch_id)
            && $this->scopedPermissionResolver->hasAnyPermission($user, [
                PermissionName::MembershipsManage->value,
                PermissionName::PaymentsManage->value,
            ], $membership->gym_id, $membership->branch_id);
    }

    public function updateCustomFee(User $user, MemberMembership $membership): bool
    {
        if (! $this->scopeResolver->canAccessGym($user, $membership->gym_id)) {
            return false;
        }

        if (! $this->scopeResolver->canAccessBranch($user, $membership->branch_id)) {
            return false;
        }

        return in_array($user->active_role, [
            RoleName::PlatformAdmin->value,
            RoleName::GymOwner->value,
            RoleName::BranchManager->value,
            RoleName::GymStaff->value,
        ], true) && $this->scopedPermissionResolver->hasPermission(
            $user,
            PermissionName::EditCustomFee->value,
            $membership->gym_id,
            $membership->branch_id,
        );
    }
}
