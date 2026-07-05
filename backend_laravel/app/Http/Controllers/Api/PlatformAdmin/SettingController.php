<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\UpdatePlatformSettingsRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformSettingService;

class SettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettingService $platformSettingService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index()
    {
        return $this->success($this->platformSettingService->all(), 'Platform settings fetched successfully.');
    }

    public function update(UpdatePlatformSettingsRequest $request)
    {
        $oldValues = $this->platformSettingService->all();
        $newValues = $this->platformSettingService->update($request->validated());

        $this->auditLogService->log(
            event: 'platform.settings.updated',
            action: 'update',
            request: $request,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return $this->success($newValues, 'Platform settings updated successfully.');
    }
}
