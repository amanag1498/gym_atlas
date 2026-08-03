<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\AddWorkoutExerciseRequest;
use App\Http\Requests\Workout\AdoptWorkoutBookPlanRequest;
use App\Http\Requests\Workout\CompleteWorkoutSessionRequest;
use App\Http\Requests\Workout\DuplicateMemberWorkoutPlanRequest;
use App\Http\Requests\Workout\StartWorkoutSessionRequest;
use App\Http\Requests\Workout\StoreMemberWorkoutPlanRequest;
use App\Http\Requests\Workout\UpdateMemberWorkoutPlanRequest;
use App\Http\Requests\Workout\WorkoutSessionActionRequest;
use App\Http\Resources\Workout\ExerciseResource;
use App\Http\Resources\Workout\PersonalRecordResource;
use App\Http\Resources\Workout\WorkoutBookResource;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Http\Resources\Workout\WorkoutSessionResource;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\WorkoutBook;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Services\Audit\AuditLogService;
use App\Services\Member\MemberAppService;
use App\Services\Trainer\IndependentCoachingAccessService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutPlanService;
use App\Services\Workout\WorkoutSessionService;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkoutController extends Controller
{
    public function __construct(
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly WorkoutPlanService $workoutPlanService,
        private readonly WorkoutSessionService $workoutSessionService,
        private readonly AuditLogService $auditLogService,
        private readonly MemberAppService $memberAppService,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
    ) {}

    public function books(Request $request)
    {
        $query = WorkoutBook::query()
            ->with(['templates.days.exercises.exercise'])
            ->withCount('templates')
            ->where('status', 'active')
            ->whereHas('templates', fn ($builder) => $builder->where('status', 'active')->where('is_public_catalog', true))
            ->latest('is_featured')
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('goal', 'like', $search)
                    ->orWhere('difficulty', 'like', $search);
            });
        }

        if ($request->filled('goal')) {
            $query->where('goal', 'like', '%'.$request->string('goal')->trim().'%');
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty')->toString());
        }

        if ($request->filled('program_type')) {
            $query->where('program_type', $request->string('program_type')->toString());
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', filter_var($request->input('featured'), FILTER_VALIDATE_BOOL));
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 12));

        return $this->paginated($paginator, WorkoutBookResource::collection($paginator->getCollection()), 'Workout book catalog fetched successfully.');
    }

    public function recommendedBooks(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $profile?->loadMissing('fitnessGoals');
        $goalNames = $profile?->fitnessGoals?->pluck('name')->filter()->values() ?? collect();
        $experience = str($profile?->experience_level ?? 'beginner')->lower()->toString();

        $books = WorkoutBook::query()
            ->with(['templates.days.exercises.exercise'])
            ->withCount('templates')
            ->where('status', 'active')
            ->whereHas('templates', fn ($builder) => $builder->where('status', 'active')->where('is_public_catalog', true))
            ->get()
            ->sortByDesc(function (WorkoutBook $book) use ($goalNames, $experience): int {
                $score = $book->is_featured ? 3 : 0;
                foreach ($goalNames as $goalName) {
                    if (str($book->goal)->lower()->contains(str($goalName)->lower())) {
                        $score += 4;
                    }
                }

                if ($book->difficulty && str($book->difficulty)->lower()->toString() === $experience) {
                    $score += 3;
                }

                if ($experience === 'beginner' && $book->difficulty === 'beginner') {
                    $score += 2;
                }

                return $score;
            })
            ->values()
            ->take((int) $request->integer('limit', 4));

        return $this->success(WorkoutBookResource::collection($books), 'Recommended workout books fetched successfully.');
    }

    public function exercises(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $query = Exercise::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($profile): void {
                $builder->where('is_global', true)
                    ->orWhere(function ($scoped) use ($profile): void {
                        $gymId = $profile?->gym_id;
                        $branchId = $profile?->branch_id;

                        if ($gymId) {
                            $scoped->where('gym_id', $gymId);
                            if ($branchId) {
                                $scoped->where(function ($branchScoped) use ($branchId): void {
                                    $branchScoped->whereNull('branch_id')
                                        ->orWhere('branch_id', $branchId);
                                });
                            }
                        } else {
                            $scoped->whereRaw('1 = 0');
                        }
                    });
            })
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('muscle_group', 'like', $search)
                    ->orWhere('equipment', 'like', $search);
            });
        }

        if ($request->filled('body_part')) {
            ExerciseBookCatalog::applyBodyPartFilter($query, $request->string('body_part')->toString());
        }

        ExerciseBookCatalog::applyBodyPartOrder($query);
        $exercises = $query->paginate((int) $request->integer('per_page', 25));

        if ($request->boolean('grouped')) {
            return $this->paginated($exercises, [
                'groups' => ExerciseBookCatalog::grouped($exercises->getCollection()),
            ], 'Exercise book fetched successfully.');
        }

        return $this->paginated(
            $exercises,
            ExerciseResource::collection($exercises->getCollection()),
            'Workout exercises fetched successfully.'
        );
    }

    public function plans(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $relationshipIds = $this->independentCoachingAccessService
            ->activeRelationshipIdsForMember($request->user(), 'workouts');
        $requestedRelationshipId = $request->filled('independent_trainer_member_relationship_id')
            ? (int) $request->integer('independent_trainer_member_relationship_id')
            : null;
        if ($requestedRelationshipId !== null) {
            $this->independentCoachingAccessService->resolveForMember(
                $request->user(),
                $requestedRelationshipId,
                'workouts',
            );
        }
        $paginator = $request->user()->workoutPlansAsMember()
            ->with(['trainer', 'creator', 'template.workoutBook', 'sourceWorkoutBook', 'days.exercises.exercise'])
            ->where('status', 'active')
            ->where(function ($query) use ($request, $profile, $relationshipIds): void {
                $query->where(function ($personalQuery) use ($request): void {
                    $personalQuery->where('created_by_user_id', $request->user()->id)
                        ->whereNull('trainer_id')
                        ->where('is_member_editable', true);
                });

                if ($profile?->gym_id) {
                    $query->orWhere(function ($gymQuery) use ($profile): void {
                        $gymQuery->where('gym_id', $profile->gym_id)
                            ->whereNull('independent_trainer_member_relationship_id')
                            ->whereNotNull('trainer_id')
                            ->when($profile->branch_id, fn ($branchQuery) => $branchQuery->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $profile->branch_id)));
                    });
                }

                if ($relationshipIds->isNotEmpty()) {
                    $query->orWhereIn('independent_trainer_member_relationship_id', $relationshipIds);
                }
            })
            ->when($requestedRelationshipId !== null, fn ($query) => $query->where('independent_trainer_member_relationship_id', $requestedRelationshipId))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutPlanResource::collection($paginator->getCollection()), 'Workout plans fetched successfully.');
    }

    public function showPlan(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);

        return $this->success(WorkoutPlanResource::make($workoutPlan->load(['trainer', 'creator', 'template.workoutBook', 'sourceWorkoutBook', 'days.exercises.exercise'])));
    }

    public function storePlan(StoreMemberWorkoutPlanRequest $request)
    {
        $plan = $this->workoutPlanService->createMemberPlan($request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'member.workout_plan.created',
            action: 'create',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan), 'Workout plan created successfully.', 201);
    }

    public function adoptPlan(AdoptWorkoutBookPlanRequest $request, WorkoutTemplate $workoutTemplate)
    {
        if (! $workoutTemplate->is_public_catalog || $workoutTemplate->status !== 'active') {
            throw ValidationException::withMessages([
                'workout_template_id' => ['Only active workout-book plans can be chosen.'],
            ]);
        }

        $plan = $this->workoutPlanService->adoptTemplateForMember($request->user(), $workoutTemplate, $request->validated());

        $this->auditLogService->log(
            event: 'member.workout_plan.adopted',
            action: 'create',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan), 'Workout plan added to your library successfully.', 201);
    }

    public function updatePlan(UpdateMemberWorkoutPlanRequest $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);

        if ((int) $workoutPlan->created_by_user_id !== (int) $request->user()->id || ! $workoutPlan->is_member_editable) {
            throw ValidationException::withMessages([
                'workout_plan_id' => ['Only your personal workout plans can be edited.'],
            ]);
        }

        $oldValues = $workoutPlan->load('days.exercises')->toArray();
        $plan = $this->workoutPlanService->updatePlan($workoutPlan, $request->validated());

        $this->auditLogService->log(
            event: 'member.workout_plan.updated',
            action: 'update',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            oldValues: $oldValues,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan->load(['trainer', 'creator', 'template.workoutBook', 'sourceWorkoutBook', 'days.exercises.exercise'])), 'Workout plan updated successfully.');
    }

    public function duplicatePlan(DuplicateMemberWorkoutPlanRequest $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);

        $plan = $this->workoutPlanService->duplicatePlanForMember(
            $request->user(),
            $workoutPlan,
            $request->validated('name'),
        );

        $this->auditLogService->log(
            event: 'member.workout_plan.duplicated',
            action: 'create',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan), 'Workout plan duplicated successfully.', 201);
    }

    public function destroyPlan(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);

        if ((int) $workoutPlan->created_by_user_id !== (int) $request->user()->id || ! $workoutPlan->is_member_editable) {
            throw ValidationException::withMessages([
                'workout_plan_id' => ['Only your personal workout plans can be deleted.'],
            ]);
        }

        $oldValues = $workoutPlan->load('days.exercises')->toArray();
        $workoutPlan->days()->delete();
        $workoutPlan->delete();

        $this->auditLogService->log(
            event: 'member.workout_plan.deleted',
            action: 'delete',
            request: $request,
            subject: $workoutPlan,
            gym: $workoutPlan->gym,
            branch: $workoutPlan->branch,
            oldValues: $oldValues,
            newValues: null,
        );

        return $this->success(null, 'Workout plan deleted successfully.');
    }

    public function start(StartWorkoutSessionRequest $request)
    {
        if ($request->filled('workout_plan_id')) {
            $this->workoutAccessService->assertPlanAccess(
                $request->user(),
                WorkoutPlan::query()->findOrFail($request->integer('workout_plan_id')),
            );
        }
        $session = $this->workoutSessionService->startSession($request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'workout_session.started',
            action: 'create',
            request: $request,
            subject: $session,
            gym: $session->gym,
            branch: $session->branch,
            newValues: $session->toArray(),
        );

        return $this->success(WorkoutSessionResource::make($session), 'Workout session started successfully.', 201);
    }

    public function addExercise(AddWorkoutExerciseRequest $request, WorkoutSession $workoutSession)
    {
        $this->workoutAccessService->assertSessionAccess($request->user(), $workoutSession);
        $sessionExercise = $this->workoutSessionService->addExercise($workoutSession, $request->validated());

        $this->auditLogService->log(
            event: 'workout_session.exercise_added',
            action: 'create',
            request: $request,
            subject: $workoutSession,
            gym: $workoutSession->gym,
            branch: $workoutSession->branch,
            newValues: $sessionExercise->toArray(),
        );

        return $this->success(WorkoutSessionResource::make($workoutSession->fresh('exercises.exercise', 'exercises.sets')), 'Workout exercise added successfully.');
    }

    public function showSession(Request $request, WorkoutSession $workoutSession)
    {
        $this->workoutAccessService->assertSessionReadAccess($request->user(), $workoutSession);

        return $this->success(WorkoutSessionResource::make($workoutSession->load('exercises.exercise', 'exercises.sets')));
    }

    public function activeSession(Request $request)
    {
        $session = WorkoutSession::query()
            ->with('exercises.exercise', 'exercises.sets')
            ->where('member_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->latest('id')
            ->first();

        return $this->success(
            $session === null ? null : WorkoutSessionResource::make($session),
            $session === null ? 'No active workout session.' : 'Active workout session fetched successfully.',
        );
    }

    public function action(WorkoutSessionActionRequest $request, WorkoutSession $workoutSession)
    {
        $this->workoutAccessService->assertSessionAccess($request->user(), $workoutSession);
        $result = $this->workoutSessionService->applyAction($request->user(), $workoutSession, $request->validated());

        if (! $result['replayed']) {
            $this->auditLogService->log(
                event: 'workout_session.action_applied',
                action: 'update',
                request: $request,
                subject: $result['session'],
                gym: $result['session']->gym,
                branch: $result['session']->branch,
                newValues: [
                    'action' => $request->validated('action'),
                    'state_revision' => $result['session']->state_revision,
                ],
            );
        }

        return $this->success(
            WorkoutSessionResource::make($result['session']),
            $result['replayed'] ? 'Workout action already applied.' : 'Workout action applied successfully.',
        );
    }

    public function complete(CompleteWorkoutSessionRequest $request, WorkoutSession $workoutSession)
    {
        $this->workoutAccessService->assertSessionAccess($request->user(), $workoutSession);
        $session = $this->workoutSessionService->completeSession($workoutSession, $request->validated());

        $this->auditLogService->log(
            event: 'workout_session.completed',
            action: 'update',
            request: $request,
            subject: $session,
            gym: $session->gym,
            branch: $session->branch,
            newValues: $session->toArray(),
        );

        return $this->success(WorkoutSessionResource::make($session), 'Workout session completed successfully.');
    }

    public function history(Request $request)
    {
        $paginator = WorkoutSession::query()
            ->with('exercises.exercise', 'exercises.sets')
            ->where('member_id', $request->user()->id)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutSessionResource::collection($paginator->getCollection()), 'Workout history fetched successfully.');
    }

    public function exerciseHistory(Request $request, int $exerciseId)
    {
        $sessions = WorkoutSession::query()
            ->with(['exercises' => fn ($query) => $query->where('exercise_id', $exerciseId)->with('exercise', 'sets')])
            ->where('member_id', $request->user()->id)
            ->whereHas('exercises', fn ($query) => $query->where('exercise_id', $exerciseId))
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        $record = PersonalRecord::query()
            ->with('exercise')
            ->where('member_id', $request->user()->id)
            ->where('exercise_id', $exerciseId)
            ->first();

        return $this->successWithMeta([
            'personal_record' => $record ? PersonalRecordResource::make($record) : null,
            'history' => WorkoutSessionResource::collection($sessions->getCollection()),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ], [
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'from' => $sessions->firstItem(),
                'last_page' => $sessions->lastPage(),
                'path' => $sessions->path(),
                'per_page' => $sessions->perPage(),
                'to' => $sessions->lastItem(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function logbookSummary(Request $request)
    {
        $memberId = $request->user()->id;
        $recentWorkouts = WorkoutSession::query()->where('member_id', $memberId)->count();
        $totalVolume = (float) WorkoutSession::query()->where('member_id', $memberId)->sum('total_volume');

        $records = PersonalRecord::query()
            ->with('exercise')
            ->where('member_id', $memberId)
            ->orderByDesc('best_volume')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return $this->successWithMeta([
            'recent_workouts_count' => $recentWorkouts,
            'total_volume' => $totalVolume,
            'personal_records' => PersonalRecordResource::collection($records->getCollection()),
        ], [
            'pagination' => [
                'current_page' => $records->currentPage(),
                'from' => $records->firstItem(),
                'last_page' => $records->lastPage(),
                'path' => $records->path(),
                'per_page' => $records->perPage(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
            ],
        ]);
    }
}
