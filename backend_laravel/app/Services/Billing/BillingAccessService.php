<?php

namespace App\Services\Billing;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Validation\ValidationException;

class BillingAccessService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function assertGymAccess(User $user, int $gymId): Gym
    {
        $gym = Gym::query()->findOrFail($gymId);

        if (! $this->scopeResolver->canAccessGym($user, $gym)) {
            throw ValidationException::withMessages([
                'gym_id' => ['You do not have access to this gym.'],
            ]);
        }

        return $gym;
    }

    public function assertBranchAccess(User $user, int $gymId, ?int $branchId): ?Branch
    {
        if ($branchId === null) {
            return null;
        }

        $branch = Branch::query()->findOrFail($branchId);

        if ((int) $branch->gym_id !== $gymId) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch does not belong to the selected gym.'],
            ]);
        }

        if (! $this->scopeResolver->canAccessBranch($user, $branch)) {
            throw ValidationException::withMessages([
                'branch_id' => ['You do not have access to this branch.'],
            ]);
        }

        return $branch;
    }

    public function assertPlanBelongsToScope(MembershipPlan $plan, int $gymId, ?int $branchId): void
    {
        if ((int) $plan->gym_id !== $gymId) {
            throw ValidationException::withMessages([
                'membership_plan_id' => ['The selected plan does not belong to the selected gym.'],
            ]);
        }

        if ($plan->branch_id !== null && $branchId !== null && (int) $plan->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'membership_plan_id' => ['The selected plan does not belong to the selected branch.'],
            ]);
        }
    }

    public function assertMembershipAccess(User $user, MemberMembership $membership): void
    {
        $this->assertGymAccess($user, $membership->gym_id);
        $this->assertBranchAccess($user, $membership->gym_id, $membership->branch_id);
    }
}
