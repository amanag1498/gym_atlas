<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\UpdatePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformSettingService $platformSettingService,
    ) {
    }

    public function index(): View
    {
        return view('web.admin.settings.index', [
            'pageTitle' => 'Platform Settings',
            'breadcrumbs' => ['Platform', 'Settings'],
            'settings' => $this->platformSettingService->all(),
            'settingsCount' => PlatformSetting::query()->count(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        $oldValues = $this->platformSettingService->all();
        $newValues = $this->platformSettingService->update($request->validated());

        $this->auditLogService->log(
            event: 'web.platform.settings.updated',
            action: 'update',
            request: $request,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return back()->with('status', 'Platform settings updated successfully.');
    }
}
