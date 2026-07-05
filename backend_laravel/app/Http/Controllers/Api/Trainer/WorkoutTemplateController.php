<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\AssignWorkoutTemplateRequest;
use App\Http\Requests\Workout\StoreWorkoutTemplateRequest;
use App\Http\Requests\Workout\UpdateWorkoutTemplateRequest;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Http\Resources\Workout\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutPlanService;
use Illuminate\Http\Request;

class WorkoutTemplateController extends Controller
{
    public function __construct(
        private readonly WorkoutPlanService $workoutPlanService,
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $trainer = $request->user();

        $paginator = WorkoutTemplate::query()
            ->with('days.exercises.exercise')
            ->where(function ($query) use ($trainer): void {
                $query->where('is_public_catalog', true)
                    ->where('status', 'active')
                    ->orWhere('created_by_user_id', $trainer->id)
                    ->orWhere(function ($builder) use ($trainer): void {
                        $builder->where('gym_id', optional($trainer->managedTrainerProfile)->gym_id)
                            ->where(function ($scope) use ($trainer): void {
                                $scope->whereNull('branch_id')
                                    ->orWhere('branch_id', optional($trainer->managedTrainerProfile)->branch_id);
                            });
                    });
            })
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutTemplateResource::collection($paginator->getCollection()), 'Workout templates fetched successfully.');
    }

    public function store(StoreWorkoutTemplateRequest $request)
    {
        $template = $this->workoutPlanService->createTemplateFromPayload($request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'workout_template.created',
            action: 'create',
            request: $request,
            subject: $template,
            gym: $template->gym,
            branch: $template->branch,
            newValues: $template->toArray(),
        );

        return $this->success(WorkoutTemplateResource::make($template), 'Workout template created successfully.', 201);
    }

    public function show(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->workoutAccessService->assertTemplateAccess($request->user(), $workoutTemplate);

        return $this->success(WorkoutTemplateResource::make($workoutTemplate->load('days.exercises.exercise')));
    }

    public function update(UpdateWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate)
    {
        $this->workoutAccessService->assertTemplateAccess($request->user(), $workoutTemplate);
        $oldValues = $workoutTemplate->load('days.exercises')->toArray();
        $template = $this->workoutPlanService->updateTemplate($workoutTemplate, $request->validated());

        $this->auditLogService->log(
            event: 'workout_template.updated',
            action: 'update',
            request: $request,
            subject: $template,
            gym: $template->gym,
            branch: $template->branch,
            oldValues: $oldValues,
            newValues: $template->toArray(),
        );

        return $this->success(WorkoutTemplateResource::make($template), 'Workout template updated successfully.');
    }

    public function assign(AssignWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate)
    {
        $this->workoutAccessService->assertTemplateAccess($request->user(), $workoutTemplate);
        foreach ($request->validated('member_ids') as $memberId) {
            $member = \App\Models\User::query()->findOrFail($memberId);
            $this->workoutAccessService->assertTrainerCanAccessMember($request->user(), $member);
        }

        $plans = $this->workoutPlanService->assignTemplateToMembers(
            $request->user(),
            $workoutTemplate->load('days.exercises'),
            $request->validated(),
        );

        foreach ($plans as $plan) {
            $this->auditLogService->log(
                event: 'workout_plan.assigned_from_template',
                action: 'create',
                request: $request,
                subject: $plan,
                gym: $plan->gym,
                branch: $plan->branch,
                newValues: $plan->toArray(),
                context: ['template_id' => $workoutTemplate->id],
            );
        }

        return $this->success(WorkoutPlanResource::collection($plans), 'Workout template assigned successfully.');
    }
}
