<?php

namespace App\Services\Workout;

use App\Models\Exercise;
use App\Models\ExerciseImportBatch;
use App\Models\ExerciseSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExerciseCatalogImporter
{
    public const IMPORTER_VERSION = '3';

    private const MEDIA_FIELDS = ['image', 'image_url', 'gif', 'gif_url', 'video', 'video_url', 'media_id', 'attribution'];

    public function importFile(string $path, array $metadata, bool $apply = false, bool $publish = false): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Exercise dataset is not readable: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Exercise dataset could not be read: {$path}");
        }

        $records = json_decode($contents, true);
        if (! is_array($records) || ! array_is_list($records)) {
            throw new RuntimeException('Exercise dataset must be a JSON array.');
        }

        $metadata = [
            'source_key' => trim((string) ($metadata['source_key'] ?? 'hasaneyldrm_exercises_dataset')),
            'source_url' => trim((string) ($metadata['source_url'] ?? 'https://github.com/hasaneyldrm/exercises-dataset')),
            'source_commit' => trim((string) ($metadata['source_commit'] ?? '')) ?: null,
            'license_code' => trim((string) ($metadata['license_code'] ?? 'MIT')),
            'source_checksum' => hash('sha256', $contents),
        ];

        if ($metadata['source_key'] === '' || $metadata['license_code'] === '') {
            throw new RuntimeException('A source key and license code are required.');
        }

        $prepared = $this->prepare($records);
        $report = $prepared['report'];
        $report['source_checksum'] = $metadata['source_checksum'];
        $report['source_key'] = $metadata['source_key'];
        $report['importer_version'] = self::IMPORTER_VERSION;
        $report['applied'] = $apply;
        $report['published'] = $apply && $publish;

        $existingSources = ExerciseSource::query()
            ->with('exercise')
            ->where('source_key', $metadata['source_key'])
            ->get()
            ->keyBy('source_external_id');

        $existingExercisesByName = Exercise::query()
            ->where('is_global', true)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Exercise $exercise) => $this->normalizeName($exercise->name));
        $resolvedExerciseIds = [];

        foreach ($prepared['records'] as $record) {
            $source = $existingSources->get($record['external_id']);
            $nameCandidates = $existingExercisesByName->get($record['normalized_name'], collect());
            $canMatchByName = ($prepared['name_counts'][$record['normalized_name']] ?? 0) === 1;

            if (! $source && $canMatchByName && $nameCandidates->isNotEmpty()) {
                $resolvedExerciseIds[$record['external_id']] = $nameCandidates->first()->id;
                $report['matched_existing_by_name']++;
                $report['updated_exercises']++;

                if ($nameCandidates->count() > 1) {
                    $report['ambiguous_existing_name_matches'][] = [
                        'name' => $record['normalized_name'],
                        'selected_exercise_id' => $nameCandidates->first()->id,
                        'candidate_exercise_ids' => $nameCandidates->pluck('id')->values()->all(),
                    ];
                }
            } elseif (! $source) {
                $report['new_exercises']++;
            } elseif (hash_equals($source->content_checksum, $record['checksum'])
                && (! $publish || ($source->exercise?->is_active && $source->exercise?->review_status === 'approved'))) {
                $report['unchanged_exercises']++;
            } else {
                $report['updated_exercises']++;
            }
        }

        if (! $apply) {
            return $report;
        }

        $batch = ExerciseImportBatch::query()->create([
            ...$metadata,
            'status' => 'processing',
            'counts' => $report,
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($prepared, $metadata, $publish, $batch, $resolvedExerciseIds, &$report): void {
                foreach ($prepared['records'] as $record) {
                    $source = ExerciseSource::query()
                        ->with('exercise')
                        ->where('source_key', $metadata['source_key'])
                        ->where('source_external_id', $record['external_id'])
                        ->lockForUpdate()
                        ->first();

                    $exercise = $source?->exercise;
                    if (! $exercise && isset($resolvedExerciseIds[$record['external_id']])) {
                        $exercise = Exercise::query()
                            ->whereKey($resolvedExerciseIds[$record['external_id']])
                            ->where('is_global', true)
                            ->lockForUpdate()
                            ->first();
                    }
                    $attributes = $this->exerciseAttributes($record, $publish, $exercise);

                    if ($exercise) {
                        if (! $source
                            || ! hash_equals($source->content_checksum, $record['checksum'])
                            || ($publish && (! $exercise->is_active || $exercise->review_status !== 'approved'))) {
                            $exercise->update($attributes);
                        }
                    } else {
                        $exercise = Exercise::query()->create($attributes);
                    }

                    foreach ($record['translations'] as $locale => $translation) {
                        $translationAttributes = [
                            'name' => null,
                            'instructions' => $translation['instructions'],
                            'instruction_steps' => $translation['steps'],
                            'source' => $metadata['source_key'],
                        ];
                        if ($publish) {
                            $translationAttributes['review_status'] = 'approved';
                        }

                        $exercise->translations()->updateOrCreate(
                            ['locale' => $locale],
                            $translationAttributes,
                        );
                        $report['translations_upserted']++;
                    }

                    ExerciseSource::query()->updateOrCreate(
                        [
                            'source_key' => $metadata['source_key'],
                            'source_external_id' => $record['external_id'],
                        ],
                        [
                            'exercise_id' => $exercise->id,
                            'exercise_import_batch_id' => $batch->id,
                            'source_url' => $metadata['source_url'],
                            'source_commit' => $metadata['source_commit'],
                            'license_code' => $metadata['license_code'],
                            'content_checksum' => $record['checksum'],
                            'imported_at' => $source?->imported_at ?? now(),
                            'last_synced_at' => now(),
                        ],
                    );
                }
            });

            $batch->update([
                'status' => 'completed',
                'counts' => $report,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'counts' => $report,
                'error_message' => Str::limit($exception->getMessage(), 5000, ''),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $report['import_batch_id'] = $batch->id;

        return $report;
    }

    private function prepare(array $records): array
    {
        $report = [
            'source_rows' => count($records),
            'accepted_rows' => 0,
            'rejected_rows' => 0,
            'new_exercises' => 0,
            'updated_exercises' => 0,
            'unchanged_exercises' => 0,
            'translations_upserted' => 0,
            'matched_existing_by_name' => 0,
            'ambiguous_existing_name_matches' => [],
            'duplicate_source_ids' => [],
            'duplicate_normalized_names' => [],
            'missing_required_fields' => [],
            'media_references_ignored' => 0,
        ];
        $prepared = [];
        $ids = [];
        $names = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $report['rejected_rows']++;
                $report['missing_required_fields'][] = ['row' => $index + 1, 'fields' => ['record']];

                continue;
            }

            $externalId = trim((string) ($record['id'] ?? ''));
            $name = trim((string) ($record['name'] ?? ''));
            $bodyPart = trim((string) ($record['body_part'] ?? $record['category'] ?? ''));
            $target = trim((string) ($record['target'] ?? ''));
            $equipment = trim((string) ($record['equipment'] ?? ''));
            $translations = $this->translations($record);
            $missing = [];

            foreach (['id' => $externalId, 'name' => $name, 'body_part' => $bodyPart, 'target' => $target, 'equipment' => $equipment] as $field => $value) {
                if ($value === '') {
                    $missing[] = $field;
                }
            }
            if (! isset($translations['en']) || $translations['en']['instructions'] === '') {
                $missing[] = 'instructions.en';
            }

            if ($externalId !== '' && isset($ids[$externalId])) {
                $report['rejected_rows']++;
                $report['duplicate_source_ids'][] = $externalId;

                continue;
            }

            if ($missing !== []) {
                $report['rejected_rows']++;
                $report['missing_required_fields'][] = ['row' => $index + 1, 'id' => $externalId ?: null, 'fields' => $missing];

                continue;
            }

            $ids[$externalId] = true;
            $normalizedName = $this->normalizeName($name);
            if (isset($names[$normalizedName])) {
                $report['duplicate_normalized_names'][] = [
                    'name' => $normalizedName,
                    'source_ids' => [$names[$normalizedName], $externalId],
                ];
            } else {
                $names[$normalizedName] = $externalId;
            }

            foreach (self::MEDIA_FIELDS as $field) {
                if (array_key_exists($field, $record) && $record[$field] !== null && $record[$field] !== '') {
                    $report['media_references_ignored']++;
                }
            }

            $content = Arr::except($record, self::MEDIA_FIELDS);
            $this->sortRecursively($content);
            $canonicalBodyPart = $this->canonicalBodyPart($bodyPart, $target);
            $prepared[] = [
                'external_id' => $externalId,
                'normalized_name' => $normalizedName,
                'name' => $name,
                'body_part' => $canonicalBodyPart,
                'target' => Str::lower($target),
                // Upstream muscle_group describes a supporting muscle. Gym Atlas
                // uses muscle_group as the broad primary catalog group.
                'muscle_group' => $canonicalBodyPart,
                'secondary_muscles' => array_values(array_filter(array_map(
                    fn ($muscle) => Str::lower(trim((string) $muscle)),
                    is_array($record['secondary_muscles'] ?? null) ? $record['secondary_muscles'] : [],
                ))),
                'equipment' => Str::lower($equipment),
                'translations' => $translations,
                'checksum' => hash('sha256', self::IMPORTER_VERSION."\n".json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ];
            $report['accepted_rows']++;
        }

        $nameCounts = array_count_values(array_column($prepared, 'normalized_name'));

        return ['records' => $prepared, 'report' => $report, 'name_counts' => $nameCounts];
    }

    private function translations(array $record): array
    {
        $instructions = is_array($record['instructions'] ?? null) ? $record['instructions'] : [];
        $steps = is_array($record['instruction_steps'] ?? null) ? $record['instruction_steps'] : [];
        $locales = array_unique([...array_keys($instructions), ...array_keys($steps)]);
        $translations = [];

        foreach ($locales as $locale) {
            $locale = strtolower(str_replace('_', '-', trim((string) $locale)));
            if (! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale)) {
                continue;
            }
            $localeSteps = is_array($steps[$locale] ?? null)
                ? array_values(array_filter(array_map(fn ($step) => trim((string) $step), $steps[$locale])))
                : [];
            $text = trim((string) ($instructions[$locale] ?? ''));
            if ($text === '' && $localeSteps !== []) {
                $text = implode("\n", $localeSteps);
            }
            if ($text === '' && $localeSteps === []) {
                continue;
            }

            $translations[$locale] = ['instructions' => $text, 'steps' => $localeSteps];
        }

        return $translations;
    }

    private function exerciseAttributes(array $record, bool $publish, ?Exercise $existing): array
    {
        $isBodyweight = in_array($record['equipment'], ['body weight', 'bodyweight'], true);
        $isCardio = $record['body_part'] === 'conditioning';

        return [
            'gym_id' => null,
            'branch_id' => null,
            'created_by_user_id' => $existing?->created_by_user_id,
            'name' => $record['name'],
            'body_part' => $record['body_part'],
            'muscle_group' => $record['muscle_group'],
            'target_muscle' => $record['target'],
            'secondary_muscles' => $record['secondary_muscles'],
            'equipment' => $record['equipment'],
            'difficulty' => $existing?->difficulty,
            'movement_pattern' => $existing?->movement_pattern,
            'default_tracking_mode' => $isCardio ? 'cardio' : ($existing?->default_tracking_mode ?? 'reps'),
            'is_bodyweight' => $isBodyweight,
            'supports_external_load' => $existing?->supports_external_load ?? true,
            'is_per_side' => $existing?->is_per_side ?? false,
            'instructions' => $record['translations']['en']['instructions'],
            'image_url' => $existing?->image_url,
            'video_url' => $existing?->video_url,
            'is_global' => true,
            'status' => $publish ? 'approved' : ($existing?->status ?? 'pending'),
            'review_status' => $publish ? 'approved' : ($existing?->review_status ?? 'imported'),
            'reviewed_by_user_id' => $existing?->reviewed_by_user_id,
            'reviewed_at' => $publish ? ($existing?->reviewed_at ?? now()) : $existing?->reviewed_at,
            'is_active' => $publish ? true : ($existing?->is_active ?? false),
        ];
    }

    private function canonicalBodyPart(string $bodyPart, string $target): string
    {
        $bodyPart = Str::lower(trim($bodyPart));
        $target = Str::lower(trim($target));

        return match ($bodyPart) {
            'waist' => 'core',
            'upper arms', 'lower arms' => 'arms',
            'lower legs' => 'calves',
            'cardio' => 'conditioning',
            'upper legs' => match (true) {
                str_contains($target, 'glute') => 'glutes',
                str_contains($target, 'hamstring') => 'hamstrings',
                str_contains($target, 'calf') => 'calves',
                default => 'quads',
            },
            'chest', 'back', 'shoulders' => $bodyPart,
            default => 'other',
        };
    }

    private function sortRecursively(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
        unset($item);

        if (! array_is_list($value)) {
            ksort($value);
        }
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->lower()->squish()->toString();
    }
}
