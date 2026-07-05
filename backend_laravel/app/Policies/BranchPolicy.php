<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;

class BranchPolicy
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->scopeResolver->canAccessBranch($user, $branch);
    }

    public function manage(User $user, Branch $branch): bool
    {
        if ($user->hasRole(RoleName::PlatformAdmin->value)) {
            return true;
        }

        if (! $this->scopeResolver->canAccessBranch($user, $branch)) {
            return false;
        }

        return in_array($user->active_role, [RoleName::GymOwner->value, RoleName::BranchManager->value], true);
    }
}
