<?php

namespace App\Services\Billing;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompensationTeamService
{
    /** @return Collection<int, User> */
    public function eligibleRecipients(int $gymId, ?int $branchId = null): Collection
    {
        return $this->eligibleQuery($gymId, $branchId)
            ->with([
                'managedTrainerProfile.branch',
                'roles',
                'branches' => fn ($query) => $query->where('branches.gym_id', $gymId),
            ])
            ->orderBy('name')
            ->get()
            ->each(function (User $user) use ($gymId): void {
                $profile = $user->managedTrainerProfile;
                $user->setAttribute('compensation_role', $profile
                    && (int) $profile->gym_id === $gymId
                    && $profile->is_active
                    && $profile->status === 'active'
                        ? 'trainer'
                        : 'staff');
            });
    }

    public function eligibleQuery(int $gymId, ?int $branchId = null): Builder
    {
        return User::query()
            ->where('users.is_active', true)
            ->where(function (Builder $query) use ($gymId, $branchId): void {
                $query->whereHas('managedTrainerProfile', function (Builder $profile) use ($gymId, $branchId): void {
                    $profile->where('gym_id', $gymId)
                        ->where('is_active', true)
                        ->where('status', 'active')
                        ->when($branchId, fn (Builder $branch) => $branch->where(fn (Builder $scope) => $scope
                            ->whereNull('branch_id')
                            ->orWhere('branch_id', $branchId)));
                })->whereHas('gyms', fn (Builder $gyms) => $gyms->where('gyms.id', $gymId)
                    ->where(fn (Builder $status) => $status->where('gym_user.status', 'active')->orWhereNull('gym_user.status')))
                    ->orWhere(function (Builder $staff) use ($gymId, $branchId): void {
                        $staff->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', [
                            RoleName::GymStaff->value,
                            RoleName::BranchManager->value,
                        ]))->whereHas('gyms', function (Builder $gyms) use ($gymId): void {
                            $gyms->where('gyms.id', $gymId)
                                ->where(fn (Builder $status) => $status
                                    ->where('gym_user.status', 'active')
                                    ->orWhereNull('gym_user.status'))
                                ->where(fn (Builder $role) => $role
                                    ->whereIn('gym_user.role_name', [RoleName::GymStaff->value, RoleName::BranchManager->value])
                                    ->orWhereNull('gym_user.role_name'));
                        });

                        if ($branchId) {
                            $staff->where(function (Builder $branchScope) use ($gymId, $branchId): void {
                                $branchScope->whereHas('branches', fn (Builder $branches) => $branches
                                    ->where('branches.gym_id', $gymId)
                                    ->where('branches.id', $branchId))
                                    ->orWhereDoesntHave('branches', fn (Builder $branches) => $branches->where('branches.gym_id', $gymId));
                            });
                        }
                    });
            });
    }
}
