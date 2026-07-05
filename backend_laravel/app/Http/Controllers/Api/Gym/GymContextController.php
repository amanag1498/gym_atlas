<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymResource;
use App\Http\Resources\User\UserResource;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Http\Request;

class GymContextController extends Controller
{
    public function __construct(
        private readonly OnboardingProgressService $onboardingProgressService,
    ) {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user()->load([
            'gyms.branches',
            'branches',
        ]);

        $gyms = $user->gyms->map(function ($gym) {
            return $this->onboardingProgressService->syncGymProgress($gym);
        });

        return $this->success([
            'user' => UserResource::make($user),
            'gyms' => GymResource::collection($gyms),
        ]);
    }
}
