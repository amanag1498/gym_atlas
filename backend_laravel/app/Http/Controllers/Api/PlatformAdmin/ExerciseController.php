<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Enums\ExerciseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\StoreExerciseRequest;
use App\Http\Requests\PlatformAdmin\UpdateExerciseRequest;
use App\Http\Resources\Workout\ExerciseResource;
use App\Models\Exercise;
use App\Models\ExerciseSubstitution;
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
            ->with(['translations', 'previewMedia', 'sources'])
            ->withCount(['translations', 'media'])
            ->where('is_global', true)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('review_status'), fn ($query) => $query->where('review_status', $request->string('review_status')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('source_key'), fn ($query) => $query->whereHas('sources', fn ($sources) => $sources->where('source_key', $request->string('source_key'))))
            ->when($request->string('media')->toString() === 'ready', fn ($query) => $query->whereHas('media', fn ($media) => $media->where('status', 'active')))
            ->when($request->string('media')->toString() === 'missing', fn ($query) => $query->whereDoesntHave('media', fn ($media) => $media->where('status', 'active')));

        $query->searchCatalog($request->string('search')->toString())
            ->applyCatalogFilters(array_filter([
                'equipment' => $request->filled('equipment') ? $request->string('equipment')->trim()->toString() : null,
                'target_muscle' => $request->filled('target_muscle') ? $request->string('target_muscle')->trim()->toString() : null,
                'difficulty' => $request->filled('difficulty') ? $request->string('difficulty')->trim()->toString() : null,
                'tracking_mode' => $request->filled('tracking_mode') ? $request->string('tracking_mode')->trim()->toString() : null,
                'is_bodyweight' => $request->has('is_bodyweight') ? $request->boolean('is_bodyweight') : null,
            ], fn ($value) => $value !== null && $value !== ''));

        if ($request->filled('body_part')) {
            ExerciseBookCatalog::applyBodyPartFilter($query, $request->string('body_part')->toString());
        }

        ExerciseBookCatalog::applyBodyPartOrder($query);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $exercises = $query->paginate($perPage);

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
        $attributes = $request->validated();
        if (array_key_exists('review_status', $attributes)) {
            $attributes['reviewed_by_user_id'] = $attributes['review_status'] === 'approved' ? $request->user()->id : null;
            $attributes['reviewed_at'] = $attributes['review_status'] === 'approved' ? now() : null;
        }
        $exercise->update($attributes);

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

    public function substitutions(Exercise $exercise)
    {
        $mappings = $exercise->substitutions()->with(['substitute.translations', 'substitute.previewMedia'])
            ->orderBy('priority')->orderBy('id')->get();

        return $this->success($mappings->map(fn (ExerciseSubstitution $mapping) => [
            'id' => $mapping->id,
            'exercise' => ExerciseResource::make($mapping->substitute),
            'reason' => $mapping->reason,
            'priority' => $mapping->priority,
            'requires_trainer_approval' => $mapping->requires_trainer_approval,
            'is_active' => $mapping->is_active,
        ])->values(), 'Exercise substitutions fetched successfully.');
    }

    public function storeSubstitution(Request $request, Exercise $exercise)
    {
        $data = $request->validate([
            'substitute_exercise_id' => ['required', 'integer', 'different:exercise_id', 'exists:exercises,id'],
            'reason' => ['required', 'string', 'max:500'],
            'priority' => ['sometimes', 'integer', 'between:1,1000'],
            'requires_trainer_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ((int) $data['substitute_exercise_id'] === (int) $exercise->id) {
            return $this->error('An exercise cannot substitute itself.', 422, [
                'substitute_exercise_id' => ['Choose a different exercise.'],
            ]);
        }

        $mapping = ExerciseSubstitution::query()->updateOrCreate([
            'exercise_id' => $exercise->id,
            'substitute_exercise_id' => $data['substitute_exercise_id'],
        ], [
            'reason' => $data['reason'],
            'priority' => $data['priority'] ?? 100,
            'requires_trainer_approval' => $data['requires_trainer_approval'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->auditLogService->log(
            event: 'exercise.substitution.curated', action: 'update', request: $request,
            subject: $mapping, newValues: $mapping->toArray(),
        );

        return $this->success($mapping, 'Exercise substitution saved successfully.', $mapping->wasRecentlyCreated ? 201 : 200);
    }

    public function destroySubstitution(Request $request, ExerciseSubstitution $exerciseSubstitution)
    {
        $oldValues = $exerciseSubstitution->toArray();
        $this->auditLogService->log(
            event: 'exercise.substitution.deleted', action: 'delete', request: $request,
            subject: $exerciseSubstitution, oldValues: $oldValues,
        );
        $exerciseSubstitution->delete();

        return $this->success(null, 'Exercise substitution deleted successfully.');
    }
}
