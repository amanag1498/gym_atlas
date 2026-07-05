<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;

class MembershipPlanPolicy
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            PermissionName::MembershipPlansView->value,
            PermissionName::MembershipPlansManage->value,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::MembershipPlansManage->value);
    }

    public function view(User $user, MembershipPlan $membershipPlan): bool
    {
        if (! $this->viewAny($user) || ! $this->scopeResolver->canAccessGym($user, $membershipPlan->gym_id)) {
            return false;
        }

        return $membershipPlan->branch_id === null
            || $this->scopeResolver->canAccessBranch($user, $membershipPlan->branch_id);
    }

    public function update(User $user, MembershipPlan $membershipPlan): bool
    {
        return $this->create($user) && $this->view($user, $membershipPlan);
    }
}
