<?php

namespace App\Http\Controllers\Api\Realtime;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Services\Authorization\ScopeResolver;
use App\Services\Member\MemberAppService;
use App\Services\Members\GymMemberAccessService;
use App\Services\Trainer\IndependentCoachingAccessService;
use Illuminate\Http\Request;

class RealtimeContextController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
        private readonly GymMemberAccessService $gymMemberAccessService,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing(['gyms:id', 'branches:id,gym_id']);
        $gymIds = $this->scopeResolver->gymsQuery($user)->pluck('gyms.id');
        $branchScopes = $user->branches
            ->filter(fn ($branch): bool => $this->scopeResolver->canAccessBranch($user, $branch->id))
            ->map(fn ($branch): array => [
                'gym_id' => (int) $branch->gym_id,
                'branch_id' => (int) $branch->id,
            ]);
        $assignedMemberIds = collect();
        $assignedTrainerId = null;
        $assignedTrainerIds = collect();

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = TrainerProfile::query()
                ->where('user_id', $user->id)
                ->first();

            if ($trainerProfile
                && $trainerProfile->is_active
                && $trainerProfile->status === 'active'
                && ($trainerProfile->gym_id === null || $this->scopeResolver->canAccessGym($user, $trainerProfile->gym_id))) {
                $gymIds->push($trainerProfile->gym_id);
                if ($trainerProfile->branch_id) {
                    $branchScopes->push([
                        'gym_id' => (int) $trainerProfile->gym_id,
                        'branch_id' => (int) $trainerProfile->branch_id,
                    ]);
                }

                $assignedMemberQuery = MemberProfile::query()
                    ->where('assigned_trainer_user_id', $user->id)
                    ->where('gym_id', $trainerProfile->gym_id)
                    ->when(
                        $trainerProfile->branch_id,
                        fn ($query) => $query->where('branch_id', $trainerProfile->branch_id)
                    );
                $assignedMemberIds = $this->gymMemberAccessService
                    ->scopeAccessibleProfiles($assignedMemberQuery)
                    ->pluck('user_id');
                if ($trainerProfile->gym_id === null
                    && $trainerProfile->branch_id === null
                    && $trainerProfile->is_active
                    && $trainerProfile->status === 'active'
                    && $trainerProfile->verification_status === 'verified') {
                    $assignedMemberIds = $assignedMemberIds->merge(
                        $this->independentCoachingAccessService
                            ->activeMemberIdsForTrainer($user, 'chat'),
                    );
                }
            }
        }

        if ($user->active_role === RoleName::Member->value) {
            $gymRelationships = $this->memberAppService->activeGymProfilesFor($user);
            $memberProfile = $this->memberAppService->memberProfileForChat($user);
            foreach ($gymRelationships as $gymRelationship) {
                $gymIds->push($gymRelationship->gym_id);
                if ($gymRelationship->branch_id) {
                    $branchScopes->push([
                        'gym_id' => (int) $gymRelationship->gym_id,
                        'branch_id' => (int) $gymRelationship->branch_id,
                    ]);
                }
            }
            if ($memberProfile) {
                $assignedTrainerId = $memberProfile->assigned_trainer_user_id;
            }
            $assignedTrainerIds = $gymRelationships
                ->pluck('assigned_trainer_user_id')
                ->merge($this->independentCoachingAccessService->activeTrainerIdsForMember($user, 'chat'))
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
        }

        $branchScopes = $branchScopes
            ->unique(fn (array $scope): string => $scope['gym_id'].':'.$scope['branch_id'])
            ->values();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active_role' => $user->active_role,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'gym_ids' => $gymIds->filter()->map(fn ($id): int => (int) $id)->unique()->values(),
            'branch_ids' => $branchScopes->pluck('branch_id')->unique()->values(),
            'branch_scopes' => $branchScopes,
            'assigned_member_ids' => $assignedMemberIds->map(fn ($id): int => (int) $id)->unique()->values(),
            'assigned_trainer_id' => $assignedTrainerId ? (int) $assignedTrainerId : null,
            'assigned_trainer_ids' => $assignedTrainerIds,
        ]);
    }
}
