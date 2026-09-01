<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\ExerciseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreExerciseRequest;
use App\Http\Resources\Workout\ExerciseResource;
use App\Models\Exercise;
use App\Models\WorkoutPlanExercise;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Workout\ExerciseCatalogExperienceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly AuditLogService $auditLogService,
        private readonly ExerciseCatalogExperienceService $exerciseCatalogExperienceService,
    ) {}

    public function index(Request $request)
    {
        $gymIds = $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id');
        $branchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id');

        $query = Exercise::query()
            ->with(['translations', 'previewMedia'])
            ->where(function ($query) use ($gymIds, $branchIds): void {
                $query->where('is_global', true)
                    ->orWhere(function ($builder) use ($gymIds, $branchIds): void {
                        $builder->whereIn('gym_id', $gymIds)
                            ->where(function ($scope) use ($branchIds): void {
                                $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                            });
                    });
            })
            ->where('is_active', true)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
                fn ($query) => $query->where(fn ($status) => $status
                    ->where('status', ExerciseStatus::Approved->value)
                    ->orWhere('created_by_user_id', $request->user()->id)),
            )
            ->searchCatalog($request->string('search')->toString())
            ->applyCatalogFilters(array_filter([
                'equipment' => $request->filled('equipment') ? $request->string('equipment')->trim()->toString() : null,
                'target_muscle' => $request->filled('target_muscle') ? $request->string('target_muscle')->trim()->toString() : null,
                'secondary_muscle' => $request->filled('secondary_muscle') ? $request->string('secondary_muscle')->trim()->toString() : null,
                'difficulty' => $request->filled('difficulty') ? $request->string('difficulty')->trim()->toString() : null,
                'movement_pattern' => $request->filled('movement_pattern') ? $request->string('movement_pattern')->trim()->toString() : null,
                'tracking_mode' => $request->filled('tracking_mode') ? $request->string('tracking_mode')->trim()->toString() : null,
                'is_bodyweight' => $request->has('is_bodyweight') ? $request->boolean('is_bodyweight') : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->orderBy('name');

        $this->exerciseCatalogExperienceService->applyTrainerRecent($query, $request, $request->user());
        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, ExerciseResource::collection($paginator->getCollection()), 'Exercises fetched successfully.');
    }

    public function show(Request $request, Exercise $exercise)
    {
        $gymIds = $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id');
        $branchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id');
        $exercise = Exercise::query()->with(['translations', 'previewMedia'])
            ->where('is_active', true)
            ->where(fn ($status) => $status->where('status', ExerciseStatus::Approved->value)
                ->orWhere('created_by_user_id', $request->user()->id))
            ->where(function ($query) use ($gymIds, $branchIds): void {
                $query->where('is_global', true)->orWhere(function ($builder) use ($gymIds, $branchIds): void {
                    $builder->whereIn('gym_id', $gymIds)->where(fn ($scope) => $scope
                        ->whereNull('branch_id')->orWhereIn('branch_id', $branchIds));
                });
            })->findOrFail($exercise->id);

        $notes = WorkoutPlanExercise::query()
            ->where('exercise_id', $exercise->id)
            ->whereNotNull('notes')
            ->whereHas('day.plan', fn ($query) => $query->where('trainer_id', $request->user()->id))
            ->with('day.plan:id,member_id,name')
            ->latest()->limit(5)->get()->map(fn ($item) => [
                'notes' => $item->notes,
                'plan_id' => $item->day->plan->id,
                'plan_name' => $item->day->plan->name,
                'member_id' => $item->day->plan->member_id,
            ]);
        $substitutions = $this->exerciseCatalogExperienceService
            ->curatedSubstitutions($exercise, $request->has('available_equipment')
                ? collect((array) $request->input('available_equipment'))->flatMap(fn ($value) => explode(',', $value))->all()
                : null, fn (Builder $query) => $query->where(function ($scope) use ($gymIds, $branchIds): void {
                    $scope->where('is_global', true)->orWhere(function ($gymScope) use ($gymIds, $branchIds): void {
                        $gymScope->whereIn('gym_id', $gymIds)->where(fn ($branchScope) => $branchScope
                            ->whereNull('branch_id')->orWhereIn('branch_id', $branchIds));
                    });
                }));

        return $this->success([
            'exercise' => ExerciseResource::make($exercise),
            'recent_coaching_notes' => $notes,
            'substitutions' => $substitutions->map(fn ($mapping) => [
                'exercise' => ExerciseResource::make($mapping->substitute),
                'reason' => $mapping->reason,
                'priority' => $mapping->priority,
                'requires_trainer_approval' => $mapping->requires_trainer_approval,
                'selection_basis' => 'curated',
            ])->values(),
            'substitution_notice' => 'Substitutions are curated catalog options, not medical advice. Review them in the member coaching context.',
        ], 'Exercise details fetched successfully.');
    }

    public function store(StoreExerciseRequest $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $branch = $this->scopeResolver->resolveBranch($request, false);

        $exercise = Exercise::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
            'is_global' => false,
            'status' => $request->validated('status', ExerciseStatus::Pending->value),
            'is_active' => true,
        ]);

        $this->auditLogService->log(
            event: 'exercise.gym.created',
            action: 'create',
            request: $request,
            subject: $exercise,
            gym: $gym,
            branch: $branch,
            newValues: $exercise->toArray(),
        );

        return $this->success(ExerciseResource::make($exercise), 'Gym exercise created successfully.', 201);
    }
}
