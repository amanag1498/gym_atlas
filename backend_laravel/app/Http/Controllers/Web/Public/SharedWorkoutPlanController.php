<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingService;
use App\Services\Workout\WorkoutPortabilityService;
use Illuminate\View\View;

class SharedWorkoutPlanController extends Controller
{
    public function __construct(
        private readonly WorkoutPortabilityService $portabilityService,
        private readonly PlatformSettingService $platformSettingService,
    ) {}

    public function show(string $token): View
    {
        $share = $this->portabilityService->resolvePublicShare($token);
        $settings = $this->platformSettingService->all();

        return view('public.workouts.shared', [
            'share' => $share,
            'snapshot' => $share->snapshot ?? [],
            'token' => $token,
            'androidStoreUrl' => $settings['member_android_store_url'] ?? null,
            'iosStoreUrl' => $settings['member_ios_store_url'] ?? null,
        ]);
    }
}
