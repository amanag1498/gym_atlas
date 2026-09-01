<?php

namespace App\Services\Workout;

use App\Models\Exercise;
use App\Models\ExerciseImportBatch;
use App\Models\ExerciseSource;
use App\Models\ExerciseTranslation;
use App\Models\User;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExerciseCatalogReviewService
{
    private const TRACKING_MODES = ['reps', 'timed', 'cardio', 'distance'];

    /** @return array<string, mixed> */
    public function summary(ExerciseImportBatch $batch): array
    {
        $sources = $this->reviewSources($batch);
        $blockers = $this->batchBlockers($batch);
        $assessments = $sources->mapWithKeys(
            fn (ExerciseSource $source): array => [$source->id => $this->assess($source, $blockers)],
        );

        $localeCoverage = ExerciseTranslation::query()
            ->selectRaw('locale, COUNT(*) AS translation_count')
            ->whereHas('exercise.sources', fn ($query) => $query->where('exercise_import_batch_id', $batch->id))
            ->groupBy('locale')
            ->orderByDesc('translation_count')
            ->pluck('translation_count', 'locale')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'source_records' => $sources->count(),
            'eligible' => $assessments->where('state', 'eligible')->count(),
            'published' => $assessments->where('state', 'published')->count(),
            'blocked' => $assessments->where('state', 'blocked')->count(),
            'missing_exercise' => $assessments->where('state', 'missing')->count(),
            'translation_locales' => count($localeCoverage),
            'translation_coverage' => $localeCoverage,
            'blocker_counts' => $assessments
                ->pluck('issues')
                ->flatten()
                ->countBy()
                ->sortDesc()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ExerciseSource>  $sources
     * @return Collection<int, array{state: string, issues: array<int, string>}>
     */
    public function assessments(ExerciseImportBatch $batch, Collection $sources): Collection
    {
        $blockers = $this->batchBlockers($batch);

        return $sources->mapWithKeys(
            fn (ExerciseSource $source): array => [$source->id => $this->assess($source, $blockers)],
        );
    }

    /** @return array<string, int> */
    public function publishEligible(ExerciseImportBatch $batch, User $reviewer): array
    {
        if ($batch->status !== 'completed') {
            return ['published_now' => 0, 'already_published' => 0, 'blocked' => 0, 'missing' => 0];
        }

        $result = DB::transaction(function () use ($batch, $reviewer): array {
            $sources = ExerciseSource::query()
                ->where('exercise_import_batch_id', $batch->id)
                ->lockForUpdate()
                ->get();
            $exerciseIds = $sources->pluck('exercise_id')->filter()->unique()->values();
            $exercises = Exercise::query()
                ->with(['translations' => fn ($query) => $query->where('locale', 'en')])
                ->whereKey($exerciseIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sources->each(fn (ExerciseSource $source) => $source->setRelation('exercise', $exercises->get($source->exercise_id)));
            $blockers = $this->batchBlockers($batch);
            $assessments = $sources->mapWithKeys(
                fn (ExerciseSource $source): array => [$source->id => $this->assess($source, $blockers)],
            );
            $eligibleExerciseIds = $sources
                ->filter(fn (ExerciseSource $source): bool => ($assessments[$source->id]['state'] ?? null) === 'eligible')
                ->pluck('exercise_id')
                ->unique()
                ->values();
            $reviewedAt = now();

            if ($eligibleExerciseIds->isNotEmpty()) {
                Exercise::query()->whereKey($eligibleExerciseIds)->update([
                    'status' => 'approved',
                    'review_status' => 'approved',
                    'reviewed_by_user_id' => $reviewer->id,
                    'reviewed_at' => $reviewedAt,
                    'is_active' => true,
                    'updated_at' => $reviewedAt,
                ]);
                ExerciseTranslation::query()
                    ->whereIn('exercise_id', $eligibleExerciseIds)
                    ->where('locale', 'en')
                    ->update([
                        'review_status' => 'approved',
                        'reviewed_by_user_id' => $reviewer->id,
                        'reviewed_at' => $reviewedAt,
                        'updated_at' => $reviewedAt,
                    ]);
            }

            $result = [
                'published_now' => $eligibleExerciseIds->count(),
                'already_published' => $assessments->where('state', 'published')->count(),
                'blocked' => $assessments->where('state', 'blocked')->count(),
                'missing' => $assessments->where('state', 'missing')->count(),
            ];
            $counts = $batch->counts ?? [];
            $counts['publication'] = [
                ...$result,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => $reviewedAt->toIso8601String(),
            ];
            $batch->update(['counts' => $counts]);

            return $result;
        });

        return $result;
    }

    /** @return Collection<int, ExerciseSource> */
    private function reviewSources(ExerciseImportBatch $batch): Collection
    {
        return $batch->sources()
            ->with(['exercise.translations' => fn ($query) => $query->where('locale', 'en')])
            ->orderBy('source_external_id')
            ->get();
    }

    /**
     * @param  array{external_ids: array<string, true>, exercise_ids: array<int, true>}  $batchBlockers
     * @return array{state: string, issues: array<int, string>}
     */
    private function assess(ExerciseSource $source, array $batchBlockers): array
    {
        $exercise = $source->exercise;
        if (! $exercise) {
            return ['state' => 'missing', 'issues' => ['Missing canonical exercise']];
        }
        if ($exercise->is_active && $exercise->review_status === 'approved') {
            return ['state' => 'published', 'issues' => []];
        }

        $issues = [];
        if (isset($batchBlockers['external_ids'][$source->source_external_id]) || isset($batchBlockers['exercise_ids'][$exercise->id])) {
            $issues[] = 'Duplicate or ambiguous merge requires individual review';
        }
        if (! $exercise->is_global) {
            $issues[] = 'Exercise is not global';
        }
        if (in_array($exercise->review_status, ['rejected', 'archived'], true)) {
            $issues[] = 'Exercise was rejected or archived';
        } elseif ($exercise->review_status === 'approved' && ! $exercise->is_active) {
            $issues[] = 'Approved exercise is manually inactive';
        }
        if (trim((string) $exercise->name) === '') {
            $issues[] = 'Canonical name is missing';
        }
        if (! in_array($exercise->body_part, ExerciseBookCatalog::BODY_PART_ORDER, true) || $exercise->body_part === 'other') {
            $issues[] = 'Body-part taxonomy is unresolved';
        }
        if (trim((string) $exercise->target_muscle) === '') {
            $issues[] = 'Target muscle is missing';
        }
        if (trim((string) $exercise->equipment) === '') {
            $issues[] = 'Equipment is missing';
        }
        if (! in_array($exercise->default_tracking_mode, self::TRACKING_MODES, true)) {
            $issues[] = 'Tracking mode is invalid';
        }
        if (mb_strlen(trim((string) $exercise->instructions)) < 10) {
            $issues[] = 'English instructions are not meaningful';
        }
        if (! $exercise->translations->contains(fn (ExerciseTranslation $translation): bool => $translation->locale === 'en' && mb_strlen(trim((string) $translation->instructions)) >= 10)) {
            $issues[] = 'English source translation is missing';
        }

        return ['state' => $issues === [] ? 'eligible' : 'blocked', 'issues' => array_values(array_unique($issues))];
    }

    /** @return array{external_ids: array<string, true>, exercise_ids: array<int, true>} */
    private function batchBlockers(ExerciseImportBatch $batch): array
    {
        $counts = $batch->counts ?? [];
        $externalIds = [];
        $exerciseIds = [];

        foreach ($counts['duplicate_normalized_names'] ?? [] as $duplicate) {
            foreach ($duplicate['source_ids'] ?? [] as $sourceId) {
                $externalIds[(string) $sourceId] = true;
            }
        }
        foreach ($counts['ambiguous_existing_name_matches'] ?? [] as $ambiguity) {
            foreach ([$ambiguity['selected_exercise_id'] ?? null, ...($ambiguity['candidate_exercise_ids'] ?? [])] as $exerciseId) {
                if ($exerciseId !== null) {
                    $exerciseIds[(int) $exerciseId] = true;
                }
            }
        }

        return ['external_ids' => $externalIds, 'exercise_ids' => $exerciseIds];
    }
}
