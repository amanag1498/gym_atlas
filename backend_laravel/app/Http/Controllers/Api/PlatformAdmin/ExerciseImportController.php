<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\ExerciseImportBatch;
use App\Models\ExerciseSource;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\ExerciseCatalogReviewService;
use Illuminate\Http\Request;

class ExerciseImportController extends Controller
{
    public function __construct(
        private readonly ExerciseCatalogReviewService $reviewService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $batches = ExerciseImportBatch::query()
            ->withCount('sources')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            $batches,
            $batches->getCollection()->map(fn (ExerciseImportBatch $batch): array => $this->batchData($batch)),
            'Exercise import batches fetched successfully.',
        );
    }

    public function show(Request $request, ExerciseImportBatch $exerciseImportBatch)
    {
        $sources = $exerciseImportBatch->sources()
            ->with(['exercise.translations' => fn ($query) => $query->where('locale', 'en')])
            ->orderBy('source_external_id')
            ->paginate($request->integer('per_page', 50));
        $assessments = $this->reviewService->assessments($exerciseImportBatch, $sources->getCollection());

        return $this->successWithMeta([
            'batch' => $this->batchData($exerciseImportBatch->loadCount('sources')),
            'summary' => $this->reviewService->summary($exerciseImportBatch),
            'records' => $sources->getCollection()->map(
                fn (ExerciseSource $source): array => [
                    'source_external_id' => $source->source_external_id,
                    'license_code' => $source->license_code,
                    'exercise_id' => $source->exercise_id,
                    'exercise' => $source->exercise ? [
                        'name' => $source->exercise->name,
                        'body_part' => $source->exercise->body_part,
                        'target_muscle' => $source->exercise->target_muscle,
                        'equipment' => $source->exercise->equipment,
                        'default_tracking_mode' => $source->exercise->default_tracking_mode,
                        'review_status' => $source->exercise->review_status,
                        'is_active' => $source->exercise->is_active,
                    ] : null,
                    'assessment' => $assessments[$source->id] ?? ['state' => 'missing', 'issues' => ['Assessment unavailable']],
                ],
            )->values(),
        ], [
            'pagination' => [
                'current_page' => $sources->currentPage(),
                'from' => $sources->firstItem(),
                'to' => $sources->lastItem(),
                'last_page' => $sources->lastPage(),
                'per_page' => $sources->perPage(),
                'total' => $sources->total(),
            ],
        ], 'Exercise import batch fetched successfully.');
    }

    public function publish(Request $request, ExerciseImportBatch $exerciseImportBatch)
    {
        $request->validate(['confirm' => ['accepted']]);
        $result = $this->reviewService->publishEligible($exerciseImportBatch, $request->user());

        $this->auditLogService->log(
            event: 'exercise_import.published',
            action: 'publish',
            request: $request,
            subject: $exerciseImportBatch,
            newValues: $result,
            context: [
                'source_key' => $exerciseImportBatch->source_key,
                'source_commit' => $exerciseImportBatch->source_commit,
            ],
        );

        return $this->success($result, 'Eligible exercise import rows published successfully.');
    }

    /** @return array<string, mixed> */
    private function batchData(ExerciseImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'source_key' => $batch->source_key,
            'source_url' => $batch->source_url,
            'source_commit' => $batch->source_commit,
            'license_code' => $batch->license_code,
            'source_checksum' => $batch->source_checksum,
            'status' => $batch->status,
            'counts' => $batch->counts,
            'sources_count' => (int) ($batch->sources_count ?? 0),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
        ];
    }
}
