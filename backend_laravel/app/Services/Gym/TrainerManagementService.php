<?php

namespace App\Services\Gym;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TrainerManagementService
{
    public function hasPhoneColumn(): bool
    {
        return Schema::hasColumn('users', 'phone');
    }

    public function baseTrainerQuery(Request $request, Gym $gym, GymWebPanelService $gymWebPanelService): Builder
    {
        $query = User::query()
            ->with([
                'managedTrainerProfile.gym',
                'managedTrainerProfile.branch',
                'assignedMembers.user',
            ])
            ->withCount([
                'assignedMembers as assigned_members_count' => fn (Builder $builder) => $builder->where('gym_id', $gym->id),
            ])
            ->whereHas('managedTrainerProfile', fn (Builder $builder) => $builder->where('gym_id', $gym->id))
            ->latest('id');

        $branch = $gymWebPanelService->resolveBranch($request, $gym);
        if ($branch) {
            $query->whereHas('managedTrainerProfile', fn (Builder $builder) => $builder->where('branch_id', $branch->id));
        } else {
            $query->whereHas('managedTrainerProfile', fn (Builder $builder) => $builder->whereIn('branch_id', $gymWebPanelService->accessibleBranchIds($request, $gym)));
        }

        return $query;
    }

    public function assertTrainerAccessible(Request $request, Gym $gym, User $trainer, GymWebPanelService $gymWebPanelService): TrainerProfile
    {
        $profile = TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->where('gym_id', $gym->id)
            ->firstOrFail();

        if ($profile->branch_id !== null) {
            abort_unless(in_array((int) $profile->branch_id, $gymWebPanelService->accessibleBranchIds($request, $gym), true), 403);
        }

        return $profile;
    }

    public function existingUsersQuery(Gym $gym): Builder
    {
        return User::query()
            ->role(RoleName::Trainer->value)
            ->where('is_active', true)
            ->whereDoesntHave('managedTrainerProfile', fn (Builder $builder) => $builder->where('gym_id', $gym->id))
            ->orderBy('name');
    }

    /**
     * @return Collection<int, User>
     */
    public function assignableMembers(Request $request, Gym $gym, ?TrainerProfile $trainerProfile, GymWebPanelService $gymWebPanelService): Collection
    {
        $query = User::query()
            ->with('memberProfile')
            ->whereHas('memberProfile', function (Builder $builder) use ($gym, $trainerProfile, $gymWebPanelService, $request): void {
                $builder->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $gymWebPanelService->accessibleBranchIds($request, $gym));

                if ($trainerProfile?->branch_id) {
                    $builder->where('branch_id', $trainerProfile->branch_id);
                }
            })
            ->orderBy('name');

        return $query->limit(100)->get();
    }

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, MemberProfile>
     */
    public function scopedMemberProfilesForAssignment(Gym $gym, ?TrainerProfile $trainerProfile, array $memberIds, array $accessibleBranchIds): Collection
    {
        $query = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->whereIn('user_id', $memberIds)
            ->whereIn('branch_id', $accessibleBranchIds);

        if ($trainerProfile?->branch_id) {
            $query->where('branch_id', $trainerProfile->branch_id);
        }

        return $query->get();
    }
}
