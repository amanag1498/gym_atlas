<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Gym\UpdateGymProfileWebRequest;
use App\Models\City;
use App\Models\Facility;
use App\Services\Audit\AuditLogService;
use App\Services\Gym\GymProfileManagementService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GymProfileController extends Controller
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

        return view('web.gym.profile.edit', [
            'pageTitle' => 'Gym Profile',
            'breadcrumbs' => ['Gym', 'Profile'],
            'gym' => $gym->load(['facilities', 'gymPhotos']),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'cities' => City::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateGymProfileWebRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymProfileManage->value, $gym);

        $oldValues = $gym->load('facilities')->toArray();
        $payload = $request->validated();
        $gym = $this->gymProfileManagementService->updateProfile($request, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.profile.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $gym->toArray(),
        );

        return back()->with('status', 'Gym profile updated successfully.');
    }
}
