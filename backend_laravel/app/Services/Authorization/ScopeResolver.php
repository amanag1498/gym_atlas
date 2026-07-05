<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScopeResolver
{
    public function gymsQuery(User $user): Builder
    {
        if ($user->hasRole(RoleName::PlatformAdmin->value)) {
            return Gym::query();
        }

        $query = Gym::query()->distinct();

        if ($user->active_role === RoleName::GymOwner->value) {
            return $query->where('owner_user_id', $user->id);
        }

        return $query->whereHas('users', function (Builder $builder) use ($user): void {
            $builder->where('users.id', $user->id);
        });
    }

    public function branchesQuery(User $user): Builder
    {
        if ($user->hasRole(RoleName::PlatformAdmin->value)) {
            return Branch::query();
        }

        if ($user->active_role === RoleName::GymOwner->value) {
            return Branch::query()->whereHas('gym', function (Builder $builder) use ($user): void {
                $builder->where('owner_user_id', $user->id);
            });
        }

        return Branch::query()->whereHas('users', function (Builder $builder) use ($user): void {
            $builder->where('users.id', $user->id);
        });
    }

    public function canAccessGym(User $user, int|string|Gym $gym): bool
    {
        $gymId = $gym instanceof Gym ? $gym->getKey() : $gym;

        return $this->gymsQuery($user)->whereKey($gymId)->exists();
    }

    public function canAccessBranch(User $user, int|string|Branch $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $this->branchesQuery($user)->whereKey($branchId)->exists();
    }

    public function resolveGym(Request $request, bool $required = true): ?Gym
    {
        /** @var User|null $user */
        $user = $request->user();
        $gymId = $request->route('gym') ?? $request->header('X-Gym-Id') ?? $request->input('gym_id');

        if (! $gymId) {
            if (! $required) {
                return null;
            }

            $gymIds = $this->gymsQuery($user)->pluck('gyms.id');

            if ($gymIds->count() === 1) {
                return Gym::query()->findOrFail($gymIds->first());
            }

            throw ValidationException::withMessages([
                'gym_id' => ['A gym scope is required for this request.'],
            ]);
        }

        $gym = Gym::query()->findOrFail($gymId);

        if ($user && ! $this->canAccessGym($user, $gym)) {
            throw ValidationException::withMessages([
                'gym_id' => ['You do not have access to this gym.'],
            ]);
        }

        return $gym;
    }

    public function resolveBranch(Request $request, bool $required = false): ?Branch
    {
        /** @var User|null $user */
        $user = $request->user();
        $branchId = $request->route('branch') ?? $request->header('X-Branch-Id') ?? $request->input('branch_id');

        if (! $branchId) {
            if (! $required) {
                return null;
            }

            throw ValidationException::withMessages([
                'branch_id' => ['A branch scope is required for this request.'],
            ]);
        }

        $branch = Branch::query()->findOrFail($branchId);

        if ($user && ! $this->canAccessBranch($user, $branch)) {
            throw ValidationException::withMessages([
                'branch_id' => ['You do not have access to this branch.'],
            ]);
        }

        return $branch;
    }
}
