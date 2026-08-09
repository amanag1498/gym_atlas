<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\TrainerSpecializationResource;
use App\Http\Resources\Gym\BranchResource;
use App\Http\Resources\Gym\GymResource;
use App\Http\Resources\User\TrainerProfileResource;
use App\Http\Resources\User\UserResource;
use App\Models\TrainerSpecialization;
use App\Services\Authorization\ScopeResolver;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;

class TrainerContextController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function __invoke(Request $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $user = $request->user()->load([
            'managedTrainerProfile.user',
            'managedTrainerProfile.gym',
            'managedTrainerProfile.branch',
            'managedTrainerProfile.assignedMembers',
        ]);
        $trainerPhotoUrl = $profile->profile_photo_url;
        $assignedGym = $profile->gym_id !== null
            ? $this->scopeResolver->gymsQuery($user)->whereKey($profile->gym_id)->first()
            : null;
        $branches = $profile->gym_id !== null
            ? $this->scopeResolver->branchesQuery($user)->where('gym_id', $profile->gym_id)->get()
            : collect();

        return $this->success([
            'user' => [
                ...UserResource::make($user)->resolve($request),
                'avatar' => $trainerPhotoUrl ?: $user->avatar,
                'profile_photo_url' => $trainerPhotoUrl ?: $user->avatar,
            ],
            'trainer_profile' => TrainerProfileResource::make($profile),
            'trainer_photo_url' => $trainerPhotoUrl,
            'trainer_specializations' => TrainerSpecializationResource::collection(
                TrainerSpecialization::query()->active()->ordered()->get()
            ),
            'branches' => BranchResource::collection($branches),
            'assigned_gym' => $assignedGym
                ? GymResource::make($assignedGym)
                : null,
            'capabilities' => [
                'food_catalog' => true,
            ],
        ]);
    }
}
