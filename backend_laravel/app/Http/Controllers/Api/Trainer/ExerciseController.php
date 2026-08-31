<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\ExerciseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreExerciseRequest;
use App\Http\Resources\Workout\ExerciseResource;
use App\Models\Exercise;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $gymIds = $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id');
        $branchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id');

        $paginator = Exercise::query()
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
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->searchCatalog($request->string('search')->toString())
            ->applyCatalogFilters(array_filter([
                'equipment' => $request->filled('equipment') ? $request->string('equipment')->trim()->toString() : null,
                'target_muscle' => $request->filled('target_muscle') ? $request->string('target_muscle')->trim()->toString() : null,
                'difficulty' => $request->filled('difficulty') ? $request->string('difficulty')->trim()->toString() : null,
                'tracking_mode' => $request->filled('tracking_mode') ? $request->string('tracking_mode')->trim()->toString() : null,
                'is_bodyweight' => $request->has('is_bodyweight') ? $request->boolean('is_bodyweight') : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, ExerciseResource::collection($paginator->getCollection()), 'Exercises fetched successfully.');
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
