<?php

namespace App\Services\Web;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GymWebPanelService
{
    public function __construct(
        private readonly WebPanelContext $webPanelContext,
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
    ) {
    }

    public function resolveGym(Request $request): Gym
    {
        /** @var User $user */
        $user = $request->user();

        return $this->webPanelContext->resolveGym($request, $user);
    }

    public function resolveBranch(Request $request, Gym $gym, bool $required = false): ?Branch
    {
        /** @var User $user */
        $user = $request->user();

        return $this->webPanelContext->resolveBranch($request, $user, $gym, $required);
    }

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(Request $request, Gym $gym): array
    {
        /** @var User $user */
        $user = $request->user();

        return $this->webPanelContext
            ->accessibleBranches($user, $gym)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function selectedBranchIds(Request $request, Gym $gym): array
    {
        $branch = $this->resolveBranch($request, $gym);

        if ($branch) {
            return [$branch->id];
        }

        return $this->accessibleBranchIds($request, $gym);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(Request $request, Gym $gym): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $this->webPanelContext->accessibleBranches($user, $gym);
    }

    public function assertBranchBelongsToGym(Branch $branch, Gym $gym): void
    {
        abort_unless($branch->gym_id === $gym->id, 404);
    }

    public function assertBranchAccessible(Branch $branch, Request $request, Gym $gym): void
    {
        $this->assertBranchBelongsToGym($branch, $gym);
        abort_unless(in_array($branch->id, $this->accessibleBranchIds($request, $gym), true), 404);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertAnyPermission(Request $request, array $permissions, Gym $gym, ?int $branchId = null): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('platform_admin')) {
            return;
        }

        if (! $this->scopeResolver->canAccessGym($user, $gym)) {
            throw new HttpException(403, 'You do not have access to this gym.');
        }

        if ($branchId !== null && ! $this->scopeResolver->canAccessBranch($user, $branchId)) {
            throw new HttpException(403, 'You do not have access to this branch.');
        }

        if (! $this->scopedPermissionResolver->hasAnyPermission($user, $permissions, $gym->id, $branchId)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
    }

    public function assertPermission(Request $request, string $permission, Gym $gym, ?int $branchId = null): void
    {
        $this->assertAnyPermission($request, [$permission], $gym, $branchId);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function canAnyPermission(Request $request, array $permissions, Gym $gym, ?int $branchId = null): bool
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('platform_admin')) {
            return true;
        }

        if (! $this->scopeResolver->canAccessGym($user, $gym)) {
            return false;
        }

        if ($branchId !== null && ! $this->scopeResolver->canAccessBranch($user, $branchId)) {
            return false;
        }

        return $this->scopedPermissionResolver->hasAnyPermission($user, $permissions, $gym->id, $branchId);
    }

    public function canPermission(Request $request, string $permission, Gym $gym, ?int $branchId = null): bool
    {
        return $this->canAnyPermission($request, [$permission], $gym, $branchId);
    }

    public function branchScopedQuery(Builder $query, string $column, Request $request, Gym $gym): Builder
    {
        $branch = $this->resolveBranch($request, $gym);

        if ($branch) {
            return $query->where($column, $branch->id);
        }

        return $query->whereIn($column, $this->accessibleBranchIds($request, $gym));
    }
}
