<?php

namespace App\Http\Controllers\Api\Gym;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\GymResource;
use App\Http\Resources\User\UserResource;
use App\Services\Authorization\ScopeResolver;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Http\Request;

class GymContextController extends Controller
{
    public function __construct(
        private readonly OnboardingProgressService $onboardingProgressService,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        $gyms = $this->scopeResolver->gymsQuery($user)
            ->with('branches')
            ->get()
            ->map(function ($gym) {
                return $this->onboardingProgressService->syncGymProgress($gym);
            });

        return $this->success([
            'user' => UserResource::make($user),
            'gyms' => GymResource::collection($gyms),
        ]);
    }
}
