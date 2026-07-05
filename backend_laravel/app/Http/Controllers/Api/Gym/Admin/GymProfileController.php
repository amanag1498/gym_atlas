<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\UpdateGymPublicListingSettingsRequest;
use App\Http\Requests\Gym\Admin\UpdateGymProfileRequest;
use App\Http\Resources\Gym\GymResource;
use App\Models\Gym;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Gym\GymProfileManagementService;
use Illuminate\Http\Request;

class GymProfileController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly AuditLogService $auditLogService,
        private readonly GymProfileManagementService $gymProfileManagementService,
    ) {
    }

    public function show(Request $request)
    {
        $gym = $this->resolveGym($request);

        return $this->success(
            GymResource::make($gym->load(['owner', 'branches.facilities', 'facilities', 'cityRecord', 'gymPhotos'])),
        );
    }

    public function update(UpdateGymProfileRequest $request)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);

        $oldValues = $gym->load('facilities')->toArray();
        $gym = $this->gymProfileManagementService->updateProfile($request, $gym, $request->validated());

        $this->auditLogService->log(
            event: 'gym.profile.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->toArray(),
        );

        return $this->success(
            GymResource::make($gym->fresh(['owner', 'branches.facilities', 'facilities', 'cityRecord', 'gymPhotos'])),
            'Gym profile updated successfully.',
        );
    }

    public function publicListingSettings(Request $request)
    {
        $gym = $this->resolveGym($request);

        return $this->success(
            GymResource::make($gym->load(['owner', 'branches.facilities', 'facilities', 'cityRecord', 'gymPhotos'])),
        );
    }

    public function updatePublicListingSettings(UpdateGymPublicListingSettingsRequest $request)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);

        $oldValues = $gym->only(['public_listing_enabled', 'show_pricing', 'pricing_visible', 'trial_available', 'contact_visible']);
        $result = $this->gymProfileManagementService->updatePublicListingSettings($gym, $request->validated());
        $gym = $result['gym'];

        $this->auditLogService->log(
            event: 'gym.public_listing.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_enabled', 'show_pricing', 'pricing_visible', 'trial_available', 'contact_visible']),
        );

        $message = $result['forced_private']
            ? 'Public listing settings saved. This gym remains private until it is active and approved.'
            : 'Public listing settings updated successfully.';

        return $this->success(
            GymResource::make($gym->fresh(['owner', 'branches.facilities', 'facilities', 'cityRecord', 'gymPhotos'])),
            $message,
        );
    }

    private function resolveGym(Request $request): Gym
    {
        /** @var Gym $gym */
        $gym = $this->scopeResolver->resolveGym($request, true);

        return $gym;
    }
}
