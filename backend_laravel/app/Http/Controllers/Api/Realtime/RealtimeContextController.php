<?php

namespace App\Http\Controllers\Api\Realtime;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;

class RealtimeContextController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing(['gyms:id', 'branches:id,gym_id']);
        $gymIds = $user->gyms->pluck('id');
        $branchScopes = $user->branches
            ->map(fn ($branch): array => [
                'gym_id' => (int) $branch->gym_id,
                'branch_id' => (int) $branch->id,
            ]);
        $assignedMemberIds = collect();
        $assignedTrainerId = null;

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = TrainerProfile::query()
                ->where('user_id', $user->id)
                ->first();

            if ($trainerProfile) {
                $gymIds->push($trainerProfile->gym_id);
                if ($trainerProfile->branch_id) {
                    $branchScopes->push([
                        'gym_id' => (int) $trainerProfile->gym_id,
                        'branch_id' => (int) $trainerProfile->branch_id,
                    ]);
                }

                $assignedMemberIds = MemberProfile::query()
                    ->where('assigned_trainer_user_id', $user->id)
                    ->where('gym_id', $trainerProfile->gym_id)
                    ->when(
                        $trainerProfile->branch_id,
                        fn ($query) => $query->where('branch_id', $trainerProfile->branch_id)
                    )
                    ->pluck('user_id');
            }
        }

        if ($user->active_role === RoleName::Member->value) {
            $memberProfile = $this->memberAppService->memberProfileForChat($user);
            if ($memberProfile) {
                $gymIds->push($memberProfile->gym_id);
                if ($memberProfile->branch_id) {
                    $branchScopes->push([
                        'gym_id' => (int) $memberProfile->gym_id,
                        'branch_id' => (int) $memberProfile->branch_id,
                    ]);
                }
                $assignedTrainerId = $memberProfile->assigned_trainer_user_id;
            }
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
        ]);
    }
}
