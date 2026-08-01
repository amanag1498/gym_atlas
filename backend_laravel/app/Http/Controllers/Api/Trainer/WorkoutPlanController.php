<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\StoreWorkoutPlanRequest;
use App\Http\Requests\Workout\UpdateWorkoutPlanRequest;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Services\Audit\AuditLogService;
use App\Services\Trainer\IndependentCoachingAccessService;
use App\Services\Trainer\TrainerScopeService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkoutPlanController extends Controller
{
    public function __construct(
        private readonly WorkoutPlanService $workoutPlanService,
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly TrainerScopeService $trainerScopeService,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
    ) {}

    public function index(Request $request)
    {
        $trainer = $request->user();
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $activeRelationshipIds = $profile->gym_id === null
            && $this->independentCoachingAccessService->isVerifiedIndependentTrainer($trainer)
            ? $this->independentCoachingAccessService
                ->activeRelationshipsForTrainer($trainer)
                ->get(['id', 'sharing_permissions'])
                ->filter(fn ($relationship): bool => in_array('workouts', $relationship->sharing_permissions ?? [], true))
                ->pluck('id')
            : collect();

        $paginator = WorkoutPlan::query()
            ->with(['member', 'trainer', 'template', 'days.exercises.exercise'])
            ->where('trainer_id', $trainer->id)
            ->where('gym_id', $profile->gym_id)
            ->where('status', 'active')
            ->when($profile->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))
            ->when(
                $profile->gym_id === null,
                fn ($query) => $query->whereIn('independent_trainer_member_relationship_id', $activeRelationshipIds),
            )
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutPlanResource::collection($paginator->getCollection()), 'Workout plans fetched successfully.');
    }

    public function store(StoreWorkoutPlanRequest $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $data = $request->validated();
        $data['gym_id'] = $profile->gym_id;
        $data['branch_id'] = $profile->branch_id;
        if ($profile->gym_id === null) {
            if (count($data['member_ids']) !== 1) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Assign an independent plan to one member at a time.'],
                ]);
            }

            $member = User::query()->findOrFail((int) $data['member_ids'][0]);
            $relationship = $this->independentCoachingAccessService->resolveActiveRelationship(
                $request->user(),
                $member,
                isset($data['independent_trainer_member_relationship_id'])
                    ? (int) $data['independent_trainer_member_relationship_id']
                    : null,
                'workouts',
            );
            $data['independent_trainer_member_relationship_id'] = $relationship->id;
        } else {
            $data['independent_trainer_member_relationship_id'] = null;
            foreach ($data['member_ids'] as $memberId) {
                $member = User::query()->findOrFail($memberId);
                $this->workoutAccessService->assertTrainerCanAccessMember($request->user(), $member);
            }
        }

        $plans = $this->workoutPlanService->createPlans($request->user(), $data);

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
