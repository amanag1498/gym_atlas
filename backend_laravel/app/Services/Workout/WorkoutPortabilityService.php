<?php

namespace App\Services\Workout;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutHistoryImportBatch;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanShare;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkoutPortabilityService
{
    public function __construct(private readonly WorkoutPlanService $workoutPlanService) {}

    public function createShare(User $actor, WorkoutPlan $plan, array $payload): array
    {
        $token = Str::random(48);
        $share = WorkoutPlanShare::query()->create([
            'workout_plan_id' => $plan->id,
            'shared_by_user_id' => $actor->id,
            'recipient_user_id' => $payload['recipient_user_id'] ?? null,
            'recipient_email' => $payload['recipient_email'] ?? null,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'expires_at' => isset($payload['expires_at']) ? CarbonImmutable::parse($payload['expires_at']) : now()->addDays((int) ($payload['expires_in_days'] ?? 14)),
            'snapshot' => $this->planSnapshot($plan),
        ]);

        return ['share' => $share, 'token' => $token];
    }

    public function resolveShare(string $token, ?User $viewer = null): WorkoutPlanShare
    {
        $share = WorkoutPlanShare::query()
            ->with(['plan', 'sharedBy'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        if ($share->status !== 'active' || $share->revoked_at !== null || ($share->expires_at && $share->expires_at->isPast())) {
            throw ValidationException::withMessages(['share' => ['This workout plan share is no longer available.']]);
        }
        if ($share->recipient_user_id !== null && $viewer !== null && (int) $share->recipient_user_id !== (int) $viewer->id) {
            throw ValidationException::withMessages(['share' => ['This workout plan share was sent to another member.']]);
        }
        if ($share->recipient_email !== null && $viewer !== null && strcasecmp($share->recipient_email, (string) $viewer->email) !== 0) {
            throw ValidationException::withMessages(['share' => ['This workout plan share was sent to another email.']]);
        }
        $share->forceFill(['last_accessed_at' => now()])->save();

        return $share;
    }

    public function resolvePublicShare(string $token): WorkoutPlanShare
    {
        $share = WorkoutPlanShare::query()
            ->with('sharedBy')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_if(
            $share->status !== 'active'
                || $share->revoked_at !== null
                || ($share->expires_at && $share->expires_at->isPast()),
            410,
            'This workout plan share is no longer available.',
        );
        abort_if(
            $share->recipient_user_id !== null || $share->recipient_email !== null,
            404,
        );

        $share->forceFill(['last_accessed_at' => now()])->save();

        return $share;
    }

    public function adoptShare(User $member, string $token, ?string $name = null): WorkoutPlan
    {
        $share = $this->resolveShare($token, $member);
        $snapshot = $share->snapshot;

        return $this->workoutPlanService->createMemberPlan($member, [
            'source_shared_workout_plan_id' => $share->workout_plan_id,
            'source_shared_by_user_id' => $share->shared_by_user_id,
            'shared_adopted_at' => now(),
            'plan_origin' => 'shared_adopted',
            'name' => $name ?: ($snapshot['name'] ?? 'Shared workout plan'),
            'goal' => $snapshot['goal'] ?? null,
            'difficulty' => $snapshot['difficulty'] ?? null,
            'duration_weeks' => $snapshot['duration_weeks'] ?? 1,
            'estimated_session_minutes' => $snapshot['estimated_session_minutes'] ?? null,
            'equipment_profile' => $snapshot['equipment_profile'] ?? null,
            'progression_policy' => $snapshot['progression_policy'] ?? 'off',
            'progression_config' => $snapshot['progression_config'] ?? null,
            'progression_version' => $snapshot['progression_version'] ?? 1,
            'weekly_schedule' => $snapshot['weekly_schedule'] ?? null,
            'notes' => $snapshot['notes'] ?? null,
            'status' => 'active',
            'days' => $snapshot['days'] ?? [],
        ]);
    }

    public function previewHistoryImport(User $member, User $actor, array $payload): WorkoutHistoryImportBatch
    {
        $timezone = $payload['timezone'] ?? $member->workoutPreference?->timezone ?? 'Asia/Kolkata';
        $sourceFormat = $payload['source_format'] ?? 'generic_csv';
        $rows = $this->parseRows($payload);
        $sourceHash = hash('sha256', json_encode([$sourceFormat, $rows], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($member, $actor, $payload, $timezone, $sourceFormat, $rows, $sourceHash): WorkoutHistoryImportBatch {
            $batch = WorkoutHistoryImportBatch::query()->firstOrCreate(
                [
                    'member_id' => $member->id,
                    'source_format' => $sourceFormat,
                    'source_file_hash' => $sourceHash,
                ],
                [
                    'created_by_user_id' => $actor->id,
                    'source_filename' => $payload['source_filename'] ?? null,
                    'status' => 'previewed',
                    'timezone' => $timezone,
                    'summary' => [],
                ],
            );
            if ($batch->status === 'confirmed') {
                return $batch->load('rows.matchedExercise');
            }
            $batch->rows()->delete();

            $summary = ['matched' => 0, 'body_weight' => 0, 'unmatched' => 0, 'invalid' => 0, 'skipped' => 0];
            foreach ($rows as $index => $row) {
                $rowHash = hash('sha256', ($index + 1).'|'.json_encode($row, JSON_THROW_ON_ERROR));
                $normalized = $this->normalizeImportRow($row, $timezone, $index + 1);
                $status = $normalized['status'];
                $exercise = $normalized['exercise_name'] ? $this->matchExercise($normalized['exercise_name']) : null;
                if ($status === 'matched' && $exercise === null) {
                    $status = 'unmatched';
                    $normalized['message'] = 'No conservative exercise match found.';
                }
                $summary[$status] = ($summary[$status] ?? 0) + 1;
                $batch->rows()->create([
                    'row_number' => $index + 1,
                    'row_hash' => $rowHash,
                    'status' => $status,
                    'matched_exercise_id' => $exercise?->id,
                    'raw_payload' => $row,
                    'normalized_payload' => $normalized,
                    'message' => $normalized['message'] ?? null,
                ]);
            }

            $batch->update(['summary' => $summary]);

            return $batch->fresh('rows.matchedExercise');
        });
    }

    public function confirmHistoryImport(User $member, WorkoutHistoryImportBatch $batch): WorkoutHistoryImportBatch
    {
        if ((int) $batch->member_id !== (int) $member->id) {
            throw ValidationException::withMessages(['import' => ['This import does not belong to your account.']]);
        }
        if ($batch->status === 'confirmed') {
            return $batch->load('rows.matchedExercise', 'rows.session');
        }

        return DB::transaction(function () use ($member, $batch): WorkoutHistoryImportBatch {
            $created = 0;
            $skipped = 0;
            $batch->load('rows');
            foreach ($batch->rows as $row) {
                $payload = $row->normalized_payload ?? [];
                if ($row->status === 'body_weight' && ! empty($payload['session_date'])) {
                    WeightLog::query()->firstOrCreate(
                        [
                            'member_id' => $member->id,
                            'log_date' => $payload['session_date'],
                            'notes' => 'Imported body weight from '.$batch->source_format,
                        ],
                        [
                            'gym_id' => null,
                            'branch_id' => null,
                            'logged_by_user_id' => $member->id,
                            'weight_kg' => (float) ($payload['weight'] ?? 0),
                        ],
                    );
                    $skipped++;

                    continue;
                }
                if ($row->status !== 'matched' || ! $row->matched_exercise_id || empty($payload['session_date'])) {
                    $skipped++;

                    continue;
                }
                $externalId = $payload['external_id'] ?? $row->row_hash;
                $session = WorkoutSession::query()->firstOrCreate(
                    [
                        'member_id' => $member->id,
                        'source_import_batch_id' => $batch->id,
                        'source_external_id' => $externalId,
                    ],
                    [
                        'gym_id' => null,
                        'branch_id' => null,
                        'trainer_id' => null,
                        'workout_plan_id' => null,
                        'workout_plan_day_id' => null,
                        'started_by_user_id' => $member->id,
                        'session_date' => $payload['session_date'],
                        'status' => WorkoutSessionStatus::Completed->value,
                        'started_at' => CarbonImmutable::parse($payload['started_at'] ?? $payload['session_date'].' 12:00:00', $batch->timezone),
                        'completed_at' => CarbonImmutable::parse($payload['completed_at'] ?? $payload['session_date'].' 12:30:00', $batch->timezone),
                        'notes' => 'Imported from '.$batch->source_format,
                        'total_volume' => ((float) ($payload['weight'] ?? 0)) * ((int) ($payload['reps'] ?? 0)),
                    ],
                );
                if ($session->wasRecentlyCreated) {
                    $sessionExercise = $session->exercises()->create([
                        'exercise_id' => $row->matched_exercise_id,
                        'sort_order' => 1,
                        'planned_sets' => 1,
                        'tracking_mode' => 'reps',
                        'performed_status' => 'completed',
                    ]);
                    $sessionExercise->sets()->create([
                        'set_number' => (int) ($payload['set_number'] ?? 1),
                        'reps' => (int) ($payload['reps'] ?? 0),
                        'weight' => (float) ($payload['weight'] ?? 0),
                        'is_completed' => true,
                        'completed_at' => $session->completed_at,
                    ]);
                    $created++;
                } else {
                    $skipped++;
                }
                $row->update(['workout_session_id' => $session->id]);
            }

            $summary = array_merge($batch->summary ?? [], ['created_sessions' => $created, 'skipped_on_confirm' => $skipped]);
            $batch->update(['status' => 'confirmed', 'summary' => $summary, 'confirmed_at' => now()]);

            return $batch->fresh('rows.matchedExercise', 'rows.session');
        });
    }

    public function exportMemberData(User $member): array
    {
        return [
            'schema_version' => 'gym-atlas.workout-export.v1',
            'generated_at' => now()->toIso8601String(),
            'member' => ['id' => $member->id, 'timezone' => $member->workoutPreference?->timezone ?? 'Asia/Kolkata'],
            'plans' => $member->workoutPlansAsMember()->with('days.exercises.exercise.sources')->get()->map(fn ($plan) => $this->planSnapshot($plan))->values(),
            'sessions' => WorkoutSession::query()->with('exercises.exercise.sources', 'exercises.sets')
                ->where('member_id', $member->id)->orderBy('session_date')->get()->map(fn (WorkoutSession $session) => [
                    'id' => $session->id,
                    'workout_plan_id' => $session->workout_plan_id,
                    'workout_plan_day_id' => $session->workout_plan_day_id,
                    'source_import_batch_id' => $session->source_import_batch_id,
                    'source_external_id' => $session->source_external_id,
                    'session_date' => $session->session_date?->toDateString(),
                    'status' => $session->status,
                    'started_at' => $session->started_at?->toIso8601String(),
                    'completed_at' => $session->completed_at?->toIso8601String(),
                    'total_volume' => (float) $session->total_volume,
                    'exercises' => $session->exercises->map(fn ($exercise) => [
                        'exercise_id' => $exercise->exercise_id,
                        'exercise_name' => $exercise->exercise?->name,
                        'exercise_sources' => $exercise->exercise?->sources?->map(fn ($source) => [
                            'source_key' => $source->source_key,
                            'source_exercise_id' => $source->source_exercise_id,
                        ])->values() ?? [],
                        'sets' => $exercise->sets->map(fn ($set) => [
                            'set_number' => $set->set_number,
                            'reps' => $set->reps,
                            'weight' => (float) $set->weight,
                            'duration_seconds' => $set->duration_seconds,
                            'distance_meters' => $set->distance_meters !== null ? (float) $set->distance_meters : null,
                            'effort_scale' => $set->effort_scale,
                            'effort_value' => $set->effort_value !== null ? (float) $set->effort_value : null,
                        ])->values(),
                    ])->values(),
                ])->values(),
            'progress' => [
                'weight_logs' => WeightLog::query()->where('member_id', $member->id)->orderBy('log_date')->get()
                    ->map(fn (WeightLog $log) => [
                        'log_date' => $log->log_date?->toDateString(),
                        'weight_kg' => (float) $log->weight_kg,
                        'notes' => $log->notes,
                    ])->values(),
                'body_measurements' => $member->bodyMeasurements()->orderBy('measured_on')->get()
                    ->map(fn ($measurement) => [
                        'measured_on' => $measurement->measured_on?->toDateString(),
                        'chest_cm' => $measurement->chest_cm !== null ? (float) $measurement->chest_cm : null,
                        'waist_cm' => $measurement->waist_cm !== null ? (float) $measurement->waist_cm : null,
                        'hips_cm' => $measurement->hips_cm !== null ? (float) $measurement->hips_cm : null,
                        'arm_cm' => $measurement->arm_cm !== null ? (float) $measurement->arm_cm : null,
                        'thigh_cm' => $measurement->thigh_cm !== null ? (float) $measurement->thigh_cm : null,
                        'calf_cm' => $measurement->calf_cm !== null ? (float) $measurement->calf_cm : null,
                        'body_fat_percentage' => $measurement->body_fat_percentage !== null ? (float) $measurement->body_fat_percentage : null,
                        'notes' => $measurement->notes,
                    ])->values(),
            ],
        ];
    }

    public function planSnapshot(WorkoutPlan $plan): array
    {
        $plan->loadMissing('days.exercises.exercise.sources');

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'goal' => $plan->goal,
            'difficulty' => $plan->difficulty,
            'duration_weeks' => $plan->duration_weeks,
            'estimated_session_minutes' => $plan->estimated_session_minutes,
            'equipment_profile' => $plan->equipment_profile,
            'progression_policy' => $plan->progression_policy ?? 'off',
            'progression_config' => $plan->progression_config,
            'progression_version' => $plan->progression_version ?? 1,
            'weekly_schedule' => $plan->weekly_schedule ?? [],
            'notes' => $plan->notes,
            'days' => $plan->days->map(fn ($day) => [
                'day_number' => $day->day_number,
                'label' => $day->label,
                'focus' => $day->focus,
                'notes' => $day->notes,
                'exercises' => $day->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->exercise_id,
                    'exercise_name' => $exercise->exercise?->name,
                    'exercise_sources' => $exercise->exercise?->sources?->map(fn ($source) => [
                        'source_key' => $source->source_key,
                        'source_exercise_id' => $source->source_exercise_id,
                    ])->values() ?? [],
                    'sort_order' => $exercise->sort_order,
                    'sets' => $exercise->sets,
                    'tracking_mode' => $exercise->tracking_mode ?? 'reps',
                    'reps' => $exercise->reps,
                    'planned_duration_seconds' => $exercise->planned_duration_seconds,
                    'planned_distance_meters' => $exercise->planned_distance_meters !== null ? (float) $exercise->planned_distance_meters : null,
                    'planned_speed_kph' => $exercise->planned_speed_kph !== null ? (float) $exercise->planned_speed_kph : null,
                    'planned_pace_seconds_per_km' => $exercise->planned_pace_seconds_per_km,
                    'target_weight' => $exercise->target_weight !== null ? (float) $exercise->target_weight : null,
                    'target_resistance' => $exercise->target_resistance !== null ? (float) $exercise->target_resistance : null,
                    'target_machine_level' => $exercise->target_machine_level !== null ? (float) $exercise->target_machine_level : null,
                    'is_per_side' => (bool) $exercise->is_per_side,
                    'is_bodyweight' => (bool) $exercise->is_bodyweight,
                    'rest_seconds' => $exercise->rest_seconds,
                    'group_key' => $exercise->group_key,
                    'group_type' => $exercise->group_type,
                    'group_order' => $exercise->group_order,
                    'group_rounds' => $exercise->group_rounds,
                    'transition_seconds' => $exercise->transition_seconds,
                    'rest_after' => $exercise->rest_after,
                    'progression_policy' => $exercise->progression_policy ?? 'off',
                    'progression_config' => $exercise->progression_config,
                    'progression_version' => $exercise->progression_version ?? 1,
                    'notes' => $exercise->notes,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function parseRows(array $payload): Collection
    {
        if (! empty($payload['rows']) && is_array($payload['rows'])) {
            return collect($payload['rows'])->filter(fn ($row) => is_array($row))->values();
        }
        $csv = trim((string) ($payload['csv_text'] ?? ''));
        if ($csv === '') {
            throw ValidationException::withMessages(['csv_text' => ['Provide CSV text or structured rows to preview.']]);
        }
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $header = null;

        return collect($lines)->filter(fn ($line) => trim($line) !== '')->map(function (string $line) use (&$header) {
            $values = str_getcsv($line);
            if ($header === null) {
                $header = array_map(fn ($value) => str($value)->lower()->trim()->snake()->toString(), $values);

                return null;
            }

            return array_combine($header, array_pad($values, count($header), null)) ?: [];
        })->filter()->values();
    }

    /** @param array<string, mixed> $row */
    private function normalizeImportRow(array $row, string $timezone, int $rowNumber): array
    {
        $name = $this->firstValue($row, ['exercise', 'exercise_name', 'name', 'movement']);
        $date = $this->firstValue($row, ['date', 'session_date', 'start_time', 'started_at']);
        $bodyWeight = $this->firstValue($row, ['body_weight', 'body_weight_kg', 'weight_kg']);
        if (! $name && $date && $bodyWeight !== null) {
            $weight = $this->numeric($bodyWeight);
            if ($this->unitValue($row) === 'lb') {
                $weight = round($weight * 0.45359237, 2);
            }

            return [
                'status' => 'body_weight',
                'exercise_name' => null,
                'session_date' => CarbonImmutable::parse((string) $date, $timezone)->toDateString(),
                'weight' => $weight,
                'external_id' => hash('sha256', strtolower($rowNumber.'|body_weight|'.$date.'|'.json_encode($row))),
            ];
        }
        if (! $name || ! $date) {
            return ['status' => 'invalid', 'exercise_name' => $name, 'message' => 'Exercise and date are required.'];
        }

        $sessionDate = CarbonImmutable::parse((string) $date, $timezone)->toDateString();
        $weight = $this->numeric($this->firstValue($row, ['weight', 'weight_kg', 'kg']));
        if ($this->unitValue($row) === 'lb') {
            $weight = round($weight * 0.45359237, 2);
        }

        return [
            'status' => 'matched',
            'exercise_name' => trim((string) $name),
            'session_date' => $sessionDate,
            'started_at' => CarbonImmutable::parse((string) $date, $timezone)->toDateTimeString(),
            'set_number' => (int) ($this->firstValue($row, ['set', 'set_number']) ?: 1),
            'reps' => (int) ($this->firstValue($row, ['reps', 'repetitions']) ?: 0),
            'weight' => $weight,
            'external_id' => hash('sha256', strtolower($rowNumber.'|'.$sessionDate.'|'.$name.'|'.json_encode($row))),
        ];
    }

    private function matchExercise(string $name): ?Exercise
    {
        $needle = str($name)->lower()->squish()->toString();

        return Exercise::query()
            ->where('is_active', true)
            ->where('status', 'approved')
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(name) = ?', [$needle])
                    ->orWhereHas('aliases', fn ($aliases) => $aliases
                        ->where('review_status', 'approved')
                        ->whereRaw('LOWER(alias) = ?', [$needle]));
            })
            ->orderByDesc('is_global')
            ->first();
    }

    /** @param list<string> $keys */
    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function numeric(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }

    private function unitValue(array $row): ?string
    {
        $unit = strtolower((string) $this->firstValue($row, ['unit', 'weight_unit']));

        return in_array($unit, ['lb', 'lbs', 'pound', 'pounds'], true) ? 'lb' : null;
    }
}
