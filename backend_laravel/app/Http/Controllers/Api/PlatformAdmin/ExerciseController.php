<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Enums\ExerciseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\StoreExerciseRequest;
use App\Http\Requests\PlatformAdmin\UpdateExerciseRequest;
use App\Http\Resources\Workout\ExerciseResource;
use App\Models\Exercise;
use App\Services\Audit\AuditLogService;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $query = Exercise::query()
            ->where('is_global', true)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));

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
            'Exercises fetched successfully.'
        );
    }

    public function store(StoreExerciseRequest $request)
    {
        $exercise = Exercise::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
            'is_global' => true,
            'status' => $request->validated('status', ExerciseStatus::Approved->value),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditLogService->log(
            event: 'exercise.global.created',
            action: 'create',
            request: $request,
            subject: $exercise,
            newValues: $exercise->toArray(),
        );

        return $this->success(ExerciseResource::make($exercise), 'Global exercise created successfully.', 201);
    }

    public function update(UpdateExerciseRequest $request, Exercise $exercise)
    {
        $oldValues = $exercise->toArray();
        $exercise->update($request->validated());

        $this->auditLogService->log(
            event: 'exercise.global.updated',
            action: 'update',
            request: $request,
            subject: $exercise,
            oldValues: $oldValues,
            newValues: $exercise->fresh()->toArray(),
        );

        return $this->success(ExerciseResource::make($exercise->fresh()), 'Global exercise updated successfully.');
    }
}
