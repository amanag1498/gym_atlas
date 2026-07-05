<?php

namespace App\Http\Controllers\Api\Gym\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\RunReminderEngineRequest;
use App\Http\Resources\Notification\ScheduledReminderResource;
use App\Models\ScheduledReminder;
use App\Services\Authorization\ScopeResolver;
use App\Services\Notification\ReminderService;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ReminderService $reminderService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request, true);
        $query = ScheduledReminder::query()
            ->where('gym_id', $gym->id)
            ->when($branch = $this->scopeResolver->resolveBranch($request), fn ($builder) => $builder->where('branch_id', $branch->id))
            ->latest('scheduled_for');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, ScheduledReminderResource::collection($paginator->getCollection()), 'Scheduled reminders fetched successfully.');
    }

    public function runDue(RunReminderEngineRequest $request)
    {
        $gym = $this->scopeResolver->resolveGym($request, false);
        $branch = $this->scopeResolver->resolveBranch($request, false);

        $processed = $this->reminderService->runDueReminders(
            type: $request->validated('type'),
            gymId: $gym?->id ?? $request->validated('gym_id'),
            branchId: $branch?->id ?? $request->validated('branch_id'),
        );

        return $this->success([
            'processed_count' => $processed->count(),
        ], 'Reminder engine processed due reminders successfully.');
    }
}
