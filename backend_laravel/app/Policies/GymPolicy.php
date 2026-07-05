<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;

class GymPolicy
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function view(User $user, Gym $gym): bool
    {
        return $this->scopeResolver->canAccessGym($user, $gym);
    }

    public function manage(User $user, Gym $gym): bool
    {
        if ($user->hasRole(RoleName::PlatformAdmin->value)) {
            return true;
        }

        if (! $this->scopeResolver->canAccessGym($user, $gym)) {
            return false;
        }

        return in_array($user->active_role, [RoleName::GymOwner->value, RoleName::BranchManager->value], true);
    }
}
