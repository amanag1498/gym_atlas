<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\TrainerSpecializationResource;
use App\Http\Resources\Gym\BranchResource;
use App\Http\Resources\User\TrainerProfileResource;
use App\Http\Resources\User\UserResource;
use App\Models\TrainerSpecialization;
use Illuminate\Http\Request;

class TrainerContextController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load([
            'branches',
            'gyms',
            'managedTrainerProfile.user',
            'managedTrainerProfile.gym',
            'managedTrainerProfile.branch',
            'managedTrainerProfile.assignedMembers',
        ]);
        $trainerPhotoUrl = $user->managedTrainerProfile?->profile_photo_url;

        return $this->success([
            'user' => [
                ...UserResource::make($user)->resolve($request),
                'avatar' => $trainerPhotoUrl ?: $user->avatar,
                'profile_photo_url' => $trainerPhotoUrl ?: $user->avatar,
            ],
            'trainer_profile' => TrainerProfileResource::make($user->managedTrainerProfile),
            'trainer_photo_url' => $trainerPhotoUrl,
            'trainer_specializations' => TrainerSpecializationResource::collection(
                TrainerSpecialization::query()->active()->ordered()->get()
            ),
            'branches' => BranchResource::collection($user->branches),
            'assigned_gym' => \App\Http\Resources\Gym\GymResource::make($user->gyms->first()),
        ]);
    }
}
