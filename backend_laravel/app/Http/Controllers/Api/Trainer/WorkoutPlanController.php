<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\StoreWorkoutPlanRequest;
use App\Http\Requests\Workout\UpdateWorkoutPlanRequest;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutPlanService;
use Illuminate\Http\Request;

class WorkoutPlanController extends Controller
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

        $paginator = WorkoutPlan::query()
            ->with(['member', 'trainer', 'template', 'days.exercises.exercise'])
            ->where('trainer_id', $trainer->id)
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutPlanResource::collection($paginator->getCollection()), 'Workout plans fetched successfully.');
    }

    public function store(StoreWorkoutPlanRequest $request)
    {
        foreach ($request->validated('member_ids') as $memberId) {
            $member = User::query()->findOrFail($memberId);
            $this->workoutAccessService->assertTrainerCanAccessMember($request->user(), $member);
        }

        $plans = $this->workoutPlanService->createPlans($request->user(), $request->validated());

        foreach ($plans as $plan) {
            $this->auditLogService->log(
                event: 'workout_plan.created',
                action: 'create',
                request: $request,
                subject: $plan,
                gym: $plan->gym,
                branch: $plan->branch,
                newValues: $plan->toArray(),
            );
        }

        return $this->success(WorkoutPlanResource::collection($plans), 'Workout plan assigned successfully.', 201);
    }

    public function show(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);

        return $this->success(WorkoutPlanResource::make($workoutPlan->load(['member', 'trainer', 'template.days.exercises.exercise', 'days.exercises.exercise'])));
    }

    public function update(UpdateWorkoutPlanRequest $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);
        $oldValues = $workoutPlan->load('days.exercises')->toArray();
        $plan = $this->workoutPlanService->updatePlan($workoutPlan, $request->validated());

        $this->auditLogService->log(
            event: 'workout_plan.updated',
            action: 'update',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            oldValues: $oldValues,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan->load(['member', 'trainer', 'template', 'days.exercises.exercise'])), 'Workout plan updated successfully.');
    }

    public function destroy(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);
        $oldValues = $workoutPlan->load('days.exercises')->toArray();

        $this->auditLogService->log(
            event: 'workout_plan.deleted',
            action: 'delete',
            request: $request,
            subject: $workoutPlan,
            gym: $workoutPlan->gym,
            branch: $workoutPlan->branch,
            oldValues: $oldValues,
        );

        $workoutPlan->delete();

        return $this->success(null, 'Workout plan deleted successfully.');
    }
}
