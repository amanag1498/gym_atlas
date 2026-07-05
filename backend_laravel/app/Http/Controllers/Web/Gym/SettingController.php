<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Gym\UpdateGymSettingsRequest;
use App\Models\NotificationPreference;
use App\Services\Audit\AuditLogService;
use App\Services\Gym\GymSettingService;
use App\Services\Notification\NotificationPreferenceCatalogService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly GymSettingService $gymSettingService,
        private readonly NotificationPreferenceCatalogService $notificationPreferenceCatalogService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(\Illuminate\Http\Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branchScopeId = $request->filled('branch') ? (int) $request->integer('branch') : null;
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::GymDashboardView->value,
            PermissionName::StaffManage->value,
        ], $gym, $branchScopeId);
        $this->assertGymStaffSettingsAccess($request, $gym->id, $branchScopeId);

        return view('web.gym.settings.index', [
            'pageTitle' => 'Gym Settings',
            'breadcrumbs' => ['Gym', 'Settings'],
            'gym' => $gym,
            'settings' => $this->gymSettingService->all($gym),
            'notificationPreferences' => $this->notificationPreferenceCatalogService->forUser($request->user()),
            'staffPermissionOptions' => $this->staffPermissionOptions(),
        ]);
    }

    public function update(UpdateGymSettingsRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branchScopeId = $request->filled('branch') ? (int) $request->integer('branch') : null;
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::GymDashboardView->value,
            PermissionName::StaffManage->value,
        ], $gym, $branchScopeId);
        $this->assertGymStaffSettingsAccess($request, $gym->id, $branchScopeId);

        $oldValues = $this->gymSettingService->all($gym);
        $newValues = $this->gymSettingService->update($gym, $request->safe()->except(['notification_preferences']));

        if ($request->filled('notification_preferences')) {
            foreach ($request->validated('notification_preferences') as $preference) {
                NotificationPreference::query()->updateOrCreate([
                    'user_id' => $request->user()->id,
                    'gym_id' => null,
                    'branch_id' => null,
                    'notification_type' => $preference['notification_type'],
                ], [
                    'is_enabled' => $preference['is_enabled'],
                ]);
            }
        }

        $this->auditLogService->log(
            event: 'web.gym.settings.updated',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            oldValues: $oldValues,
            newValues: array_merge($newValues, [
                'notification_preferences' => $request->validated('notification_preferences', []),
            ]),
        );

        return back()->with('status', 'Gym settings updated successfully.');
    }

    private function assertGymStaffSettingsAccess(\Illuminate\Http\Request $request, int $gymId, ?int $branchId): void
    {
        $user = $request->user();

        if (! $user->hasRole(RoleName::GymStaff->value)) {
            return;
        }

        abort_unless(
            app(\App\Services\Authorization\ScopedPermissionResolver::class)->hasAnyPermission($user, [
                PermissionName::GymDashboardView->value,
                PermissionName::StaffManage->value,
            ], $gymId, $branchId),
            403,
            'You do not have permission to manage settings.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function staffPermissionOptions(): array
    {
        return [
            'view_billing' => 'View billing',
            'collect_payment' => 'Collect payment',
            'edit_custom_fee' => 'Edit custom fee',
            'manage_attendance' => 'Manage attendance',
            'manage_members' => 'Manage members',
            'manage_trainers' => 'Manage trainers',
            'send_announcements' => 'Send announcements',
            'view_reports' => 'View reports',
            'manage_staff' => 'Manage staff',
        ];
    }
}
