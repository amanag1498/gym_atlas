<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RunReminderEngineRequest;
use App\Models\ScheduledReminder;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\ReminderService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ReminderService $reminderService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym, false);
        $this->gymWebPanelService->assertPermission($request, PermissionName::NotificationsManage->value, $gym, $branch?->id);

        $query = ScheduledReminder::query()
            ->with(['user', 'branch', 'membership.membershipPlan'])
            ->where('gym_id', $gym->id)
            ->when($branch, fn ($builder) => $builder->where('branch_id', $branch->id))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($builder) => $builder->where('type', $request->string('type')))
            ->latest('scheduled_for');

        return view('web.gym.reminders.index', [
            'pageTitle' => 'Scheduled Reminders',
            'breadcrumbs' => ['Gym', 'Scheduled Reminders'],
            'gym' => $gym,
            'branch' => $branch,
            'reminders' => $query->paginate(15)->withQueryString(),
            'pendingCount' => ScheduledReminder::query()->where('gym_id', $gym->id)->where('status', 'pending')->count(),
            'sentCount' => ScheduledReminder::query()->where('gym_id', $gym->id)->where('status', 'sent')->count(),
            'typeOptions' => collect(ReminderType::cases())
                ->mapWithKeys(fn (ReminderType $type) => [$type->value => str($type->value)->replace('_', ' ')->title()->toString()])
                ->all(),
        ]);
    }

    public function runDue(RunReminderEngineRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym, false);
        $this->gymWebPanelService->assertPermission($request, PermissionName::NotificationsManage->value, $gym, $branch?->id);

        $processed = $this->reminderService->runDueReminders(
            type: $request->validated('type'),
            gymId: $gym->id,
            branchId: $branch?->id,
        );

        $this->auditLogService->log(
            event: 'web.gym.reminders.run_due',
            action: 'update',
            request: $request,
            subject: $gym,
            gym: $gym,
            branch: $branch,
            newValues: [
                'type' => $request->validated('type'),
                'processed_count' => $processed->count(),
            ],
        );

        return back()->with('status', $processed->count().' due reminder(s) processed.');
    }
}
