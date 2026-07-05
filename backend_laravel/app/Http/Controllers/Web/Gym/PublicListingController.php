<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Gym\UpdatePublicListingSettingsRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Gym\GymProfileManagementService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicListingController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly AuditLogService $auditLogService,
        private readonly GymProfileManagementService $gymProfileManagementService,
    ) {
    }

    public function edit(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymProfileManage->value, $gym);

        return view('web.gym.public-listing.edit', [
            'pageTitle' => 'Public Listing Settings',
            'breadcrumbs' => ['Gym', 'Public Listing'],
            'gym' => $gym->load(['facilities', 'gymPhotos']),
            'canBePubliclyListed' => $this->gymProfileManagementService->canBePubliclyListed($gym),
        ]);
    }

    public function update(UpdatePublicListingSettingsRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymProfileManage->value, $gym);

        $oldValues = $gym->only(['public_listing_enabled', 'show_pricing', 'pricing_visible', 'trial_available', 'contact_visible']);
        $result = $this->gymProfileManagementService->updatePublicListingSettings($gym, $request->validated());
        $gym = $result['gym'];

        $this->auditLogService->log(
            event: 'web.gym.public_listing.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->only(['public_listing_enabled', 'show_pricing', 'pricing_visible', 'trial_available', 'contact_visible']),
        );

        $message = $result['forced_private']
            ? 'Public listing settings saved. This gym will remain private until it becomes active and approved.'
            : 'Public listing settings updated successfully.';

        return back()->with('status', $message);
    }
}
