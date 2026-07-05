<?php

namespace App\Services\Web;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Services\Authorization\ActiveRoleManager;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WebPanelContext
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ActiveRoleManager $activeRoleManager,
    ) {
    }

    /**
     * @return Collection<int, Gym>
     */
    public function accessibleGyms(User $user): Collection
    {
        return $this->scopeResolver->gymsQuery($user)
            ->with('branches')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(User $user, ?Gym $gym = null): Collection
    {
        return $this->scopeResolver->branchesQuery($user)
            ->when($gym, fn ($query) => $query->where('gym_id', $gym->id))
            ->orderBy('name')
            ->get();
    }

    public function resolveGym(Request $request, User $user): Gym
    {
        $sessionGymId = $request->session()->get('web_panel.gym_id');
        $requestedGymId = $request->query('gym') ?: $sessionGymId;
        $gyms = $this->accessibleGyms($user);

        if ($gyms->isEmpty()) {
            throw ValidationException::withMessages([
                'gym' => ['No gym access is configured for this account.'],
            ]);
        }

        $gym = $requestedGymId
            ? $gyms->firstWhere('id', (int) $requestedGymId)
            : $gyms->first();

        if (! $gym) {
            throw ValidationException::withMessages([
                'gym' => ['You do not have access to the requested gym scope.'],
            ]);
        }

        if ($sessionGymId && (int) $sessionGymId !== (int) $gym->id) {
            $request->session()->forget('web_panel.branch_id');
        }

        $request->session()->put('web_panel.gym_id', $gym->id);

        return $gym;
    }

    public function resolveBranch(Request $request, User $user, Gym $gym, bool $required = false): ?Branch
    {
        $branches = $this->accessibleBranches($user, $gym);
        $hasExplicitBranchScope = $request->query->has('branch');
        $requestedBranchId = $hasExplicitBranchScope
            ? $request->query('branch')
            : $request->session()->get('web_panel.branch_id');

        if ($hasExplicitBranchScope && blank($requestedBranchId)) {
            $request->session()->forget('web_panel.branch_id');

            if ($required && $branches->isNotEmpty()) {
                $branch = $branches->first();
                $request->session()->put('web_panel.branch_id', $branch?->id);

                return $branch;
            }

            if ($required && $branches->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch' => ['No branch access is configured for this account.'],
                ]);
            }

            return null;
        }

        if (! $requestedBranchId) {
            if ($required && $branches->isNotEmpty()) {
                $branch = $branches->first();
                $request->session()->put('web_panel.branch_id', $branch?->id);

                return $branch;
            }

            if ($required && $branches->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch' => ['No branch access is configured for this account.'],
                ]);
            }

            return null;
        }

        $branch = $branches->firstWhere('id', (int) $requestedBranchId);

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch' => ['You do not have access to the requested branch scope.'],
            ]);
        }

        $request->session()->put('web_panel.branch_id', $branch->id);

        return $branch;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSharedViewData(Request $request, User $user, string $panel): array
    {
        $gyms = $panel === 'gym' ? $this->accessibleGyms($user) : collect();
        $currentGym = $panel === 'gym' && $gyms->isNotEmpty() ? $this->resolveGym($request, $user) : null;
        $branches = $panel === 'gym' && $currentGym ? $this->accessibleBranches($user, $currentGym) : collect();
        $currentBranch = $panel === 'gym' && $currentGym
            ? $this->resolveBranch($request, $user, $currentGym, false)
            : null;

        return [
            'panelContext' => [
                'panel' => $panel,
                'user' => $user,
                'active_role' => $user->active_role,
                'gyms' => $gyms,
                'current_gym' => $currentGym,
                'branches' => $branches,
                'current_branch' => $currentBranch,
            ],
        ];
    }

    public function activateRole(User $user, string $role): void
    {
        if ($user->active_role !== $role && $user->hasRole($role)) {
            $this->activeRoleManager->setActiveRole($user, $role);
        }
    }

    /**
     * @return list<string>
     */
    public function allowedGymPanelRoles(): array
    {
        return [
            RoleName::PlatformAdmin->value,
            RoleName::GymOwner->value,
            RoleName::BranchManager->value,
            RoleName::GymStaff->value,
        ];
    }
}
