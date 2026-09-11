<?php

namespace App\Services\Workout;

use App\Enums\WorkoutSessionStatus;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanDay;
use App\Models\WorkoutProgressionRecommendation;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Services\Member\MemberAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutSessionService
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly WorkoutProgressionService $workoutProgressionService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startSession(User $member, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($member, $payload) {
            User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $membership = $this->memberAppService->currentMembershipFor($member);
            $memberProfile = $this->memberAppService->memberProfileFor($member);
            $gymId = $membership?->gym_id ?? $memberProfile?->gym_id;
            $branchId = $membership?->branch_id ?? $memberProfile?->branch_id;

            if (! ($payload['allow_duplicate_active_session'] ?? false)) {
                $hasActiveSession = WorkoutSession::query()
                    ->where('member_id', $member->id)
                    ->where('status', WorkoutSessionStatus::Active->value)
                    ->exists();

                if ($hasActiveSession) {
                    throw ValidationException::withMessages([
                        'session' => ['An active workout session already exists for this member.'],
                    ]);
                }
            }

            $plan = isset($payload['workout_plan_id'])
                ? WorkoutPlan::query()->with('days.exercises.exercise')->findOrFail($payload['workout_plan_id'])
                : null;

            if ($plan === null && isset($payload['workout_plan_day_id'])) {
                throw ValidationException::withMessages([
                    'workout_plan_day_id' => ['Select a workout plan before selecting a workout day.'],
                ]);
            }

            if ($plan !== null) {
                $this->workoutAccessService->assertPlanAccess($member, $plan);
            }

            $selectedDay = null;
            if ($plan !== null && isset($payload['workout_plan_day_id'])) {
                $selectedDay = WorkoutPlanDay::query()
                    ->with('exercises')
                    ->where('workout_plan_id', $plan->id)
                    ->whereKey((int) $payload['workout_plan_day_id'])
                    ->lockForUpdate()
                    ->first();

                if ($selectedDay === null) {
                    throw ValidationException::withMessages([
                        'workout_plan_day_id' => ['The selected workout day does not belong to this workout plan.'],
                    ]);
                }
            }

            if ($plan !== null && $plan->gym_id === null) {
                $gymId = null;
                $branchId = null;
            }

            if ($plan && $plan->gym_id !== null && $plan->branch_id !== null) {
                if (
                    $gymId === null
                    || $branchId === null
                    || ! $this->memberAppService->hasActiveMembership($membership, $memberProfile)
                    || (int) $plan->gym_id !== (int) $gymId
                    || (int) $plan->branch_id !== (int) $branchId
                ) {
                    throw ValidationException::withMessages([
                        'workout_plan_id' => ['The selected workout plan does not belong to the member branch scope.'],
                    ]);
                }
            }

            if ($plan === null && $gymId !== null && $branchId !== null && ! $this->memberAppService->hasActiveMembership($membership, $memberProfile)) {
                throw ValidationException::withMessages([
                    'session' => ['Workout tracking unlocks after an active gym membership is assigned.'],
                ]);
            }

            $session = WorkoutSession::query()->create([
                'gym_id' => $gymId,
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'trainer_id' => $plan?->trainer_id,
                'workout_plan_id' => $plan?->id,
                'workout_plan_day_id' => $selectedDay?->id,
                'plan_day_number' => $selectedDay?->day_number,
                'plan_day_label' => $selectedDay?->label,
                'day_selection_mode' => $plan === null
                    ? 'custom_session'
                    : ($selectedDay === null
                        ? 'legacy_all_days'
                        : ($plan->days->count() === 1 ? 'single_day_default' : 'member_selected')),
                'started_by_user_id' => $member->id,
                'session_date' => $payload['session_date'],
                'status' => WorkoutSessionStatus::Active->value,
                'started_at' => now(),
                'notes' => $payload['notes'] ?? null,
                'pre_workout_weight_kg' => $payload['pre_workout_weight_kg'] ?? null,
                'last_activity_at' => now(),
            ]);

            if (isset($payload['pre_workout_weight_kg']) && ($payload['save_pre_workout_weight'] ?? false)) {
                WeightLog::query()->create([
                    'gym_id' => $gymId,
                    'branch_id' => $branchId,
                    'member_id' => $member->id,
                    'logged_by_user_id' => $member->id,
                    'log_date' => $payload['session_date'],
                    'weight_kg' => $payload['pre_workout_weight_kg'],
                    'notes' => 'Optional pre-workout check-in.',
                ]);
            }

            if ($plan) {
                $sessionDays = $selectedDay !== null ? collect([$selectedDay]) : $plan->days;
                foreach ($sessionDays as $day) {
                    foreach ($day->exercises as $planExercise) {
                        $session->exercises()->create([
                            'workout_plan_exercise_id' => $planExercise->id,
                            'exercise_id' => $planExercise->exercise_id,
                            'tracking_mode' => $planExercise->tracking_mode ?: ($planExercise->exercise?->default_tracking_mode ?? 'reps'),
                            'sort_order' => $planExercise->sort_order,
                            'planned_sets' => $planExercise->sets,
                            'planned_reps' => $planExercise->reps,
                            'planned_duration_seconds' => $planExercise->planned_duration_seconds,
                            'planned_distance_meters' => $planExercise->planned_distance_meters,
                            'planned_speed_kph' => $planExercise->planned_speed_kph,
                            'planned_pace_seconds_per_km' => $planExercise->planned_pace_seconds_per_km,
                            'target_weight' => $planExercise->target_weight,
                            'target_resistance' => $planExercise->target_resistance,
                            'target_machine_level' => $planExercise->target_machine_level,
                            'is_per_side' => $planExercise->is_per_side,
                            'is_bodyweight' => $planExercise->is_bodyweight,
                            'rest_timer_seconds' => $planExercise->rest_seconds,
                            'group_key' => $planExercise->group_key,
                            'group_type' => $planExercise->group_type,
                            'group_order' => $planExercise->group_order,
                            'group_rounds' => $planExercise->group_rounds,
                            'transition_seconds' => $planExercise->transition_seconds,
                            'rest_after' => $planExercise->rest_after,
                            'progression_policy' => $planExercise->progression_policy,
                            'progression_config' => $planExercise->progression_config,
                            'progression_version' => $planExercise->progression_version,
                            'notes' => $planExercise->notes,
                        ]);
                    }
                }
            }

            return $session->load('exercises.exercise', 'exercises.sets');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addExercise(WorkoutSession $session, array $payload): WorkoutSessionExercise
    {
        return DB::transaction(function () use ($session, $payload) {
            $session = WorkoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Exercises can be added only to an active workout session.'],
                ]);
            }

            $sessionExercise = $session->exercises()->create([
                'exercise_id' => $payload['exercise_id'],
                ...$this->sessionExerciseModePayload($payload),
                'sort_order' => $payload['sort_order'] ?? ($session->exercises()->max('sort_order') + 1),
                'planned_sets' => $payload['planned_sets'] ?? null,
                'planned_reps' => $payload['planned_reps'] ?? null,
                'target_weight' => $payload['target_weight'] ?? null,
                'rest_timer_seconds' => $payload['rest_timer_seconds'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($payload['sets'] ?? [] as $setPayload) {
                $sessionExercise->sets()->create($this->setPayload($setPayload));
            }

            $session->update(['last_activity_at' => now()]);

            return $sessionExercise->load('exercise', 'sets');
        });
    }

    /** @param array<string, mixed> $payload */
    public function saveProgress(WorkoutSession $session, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($session, $payload) {
            $lockedSession = WorkoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($lockedSession->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Progress can be saved only for an active workout session.'],
                ]);
            }

            foreach ($payload['exercises'] as $exercisePayload) {
                $sessionExercise = $lockedSession->exercises()->findOrFail($exercisePayload['id']);
                $sessionExercise->update([
                    'notes' => $exercisePayload['notes'] ?? $sessionExercise->notes,
                    'performed_status' => $exercisePayload['performed_status'] ?? $sessionExercise->performed_status,
                ]);
                $sessionExercise->sets()->delete();
                foreach ($exercisePayload['sets'] as $setPayload) {
                    $sessionExercise->sets()->create($this->setPayload($setPayload));
                }
            }

            $lockedSession->update([
                'runtime_state' => $payload['runtime_state'] ?? $lockedSession->runtime_state,
                'last_activity_at' => now(),
            ]);

            return $lockedSession->fresh('exercises.exercise', 'exercises.sets');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function completeSession(WorkoutSession $session, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($session, $payload) {
            $session = WorkoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Only an active workout session can be completed.'],
                ]);
            }

            if (isset($payload['notes'])) {
                $session->notes = $payload['notes'];
            }

            foreach ($payload['exercises'] ?? [] as $exercisePayload) {
                $substitutedForId = $exercisePayload['substituted_for_session_exercise_id'] ?? null;
                if (
                    $substitutedForId !== null
                    && ! $session->exercises()->whereKey($substitutedForId)->exists()
                ) {
                    throw ValidationException::withMessages([
                        'substituted_for_session_exercise_id' => ['A substituted exercise must reference an exercise in the same workout session.'],
                    ]);
                }
                $sessionExercise = isset($exercisePayload['id'])
                    ? $session->exercises()->findOrFail($exercisePayload['id'])
                    : $session->exercises()->create([
                        'exercise_id' => $exercisePayload['exercise_id'],
                        ...$this->sessionExerciseModePayload($exercisePayload),
                        'sort_order' => $exercisePayload['sort_order'] ?? ($session->exercises()->max('sort_order') + 1),
                        'planned_sets' => $exercisePayload['planned_sets'] ?? null,
                        'planned_reps' => $exercisePayload['planned_reps'] ?? null,
                        'target_weight' => $exercisePayload['target_weight'] ?? null,
                        'rest_timer_seconds' => $exercisePayload['rest_timer_seconds'] ?? null,
                        'notes' => $exercisePayload['notes'] ?? null,
                    ]);

                $sessionExercise->update([
                    'performed_status' => $exercisePayload['performed_status'] ?? 'completed',
                    'substituted_for_session_exercise_id' => $exercisePayload['substituted_for_session_exercise_id'] ?? null,
                    'notes' => $exercisePayload['notes'] ?? $sessionExercise->notes,
                ]);

                $sessionExercise->sets()->delete();

                foreach ($exercisePayload['sets'] ?? [] as $setPayload) {
                    $sessionExercise->sets()->create($this->setPayload($setPayload));
                }
            }

            $session->load('plan', 'exercises.exercise', 'exercises.sets');

            $volume = $session->exercises
                ->where('tracking_mode', 'reps')
                ->sum(
                    fn ($exercise) => $exercise->sets->where('is_completed', true)
                        ->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps)
                );

            $session->update([
                'status' => WorkoutSessionStatus::Completed->value,
                'completed_at' => now(),
                'total_volume' => $volume,
                'last_activity_at' => now(),
                'notes' => $session->notes,
            ]);

            $newPersonalRecordExerciseIds = [];
            foreach ($session->exercises as $exercise) {
                if ($exercise->tracking_mode !== 'reps' || $exercise->performed_status === 'skipped') {
                    continue;
                }
                $completedSets = $exercise->sets->where('is_completed', true);
                if ($completedSets->isEmpty()) {
                    continue;
                }
                $bestWeight = (float) $completedSets->max('weight');
                $bestReps = (int) $completedSets->max('reps');
                $bestVolume = (float) $completedSets->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps);
                $eligibleEstimatedSets = $exercise->is_bodyweight
                    ? collect()
                    : $completedSets->filter(fn ($set): bool => (float) $set->weight > 0 && (int) $set->reps >= 1 && (int) $set->reps <= WorkoutProgressionService::ESTIMATED_ONE_REP_MAX_MAX_REPS);
                $bestEstimatedSet = $eligibleEstimatedSets
                    ->sortByDesc(fn ($set): float => $this->estimatedOneRepMax((float) $set->weight, (int) $set->reps))
                    ->first();
                $bestEstimatedOneRepMax = $bestEstimatedSet !== null
                    ? $this->estimatedOneRepMax((float) $bestEstimatedSet->weight, (int) $bestEstimatedSet->reps)
                    : null;

                $record = PersonalRecord::query()->firstOrNew([
                    'member_id' => $session->member_id,
                    'exercise_id' => $exercise->exercise_id,
                    'coaching_scope_key' => PersonalRecord::coachingScopeKey(
                        $session->gym_id !== null ? (int) $session->gym_id : null,
                        $session->branch_id !== null ? (int) $session->branch_id : null,
                        $session->plan?->independent_trainer_member_relationship_id !== null
                            ? (int) $session->plan->independent_trainer_member_relationship_id
                            : null,
                    ),
                ]);

                $isNewBest = ! $record->exists
                    || $bestWeight > (float) $record->best_weight
                    || $bestReps > (int) $record->best_reps
                    || $bestVolume > (float) $record->best_volume
                    || ($bestEstimatedOneRepMax !== null && $bestEstimatedOneRepMax > (float) ($record->best_estimated_one_rep_max ?? 0));
                $isNewEstimatedBest = $bestEstimatedOneRepMax !== null
                    && $bestEstimatedOneRepMax > (float) ($record->best_estimated_one_rep_max ?? 0);
                $record->fill([
                    'gym_id' => $session->gym_id,
                    'branch_id' => $session->branch_id,
                    'workout_session_id' => $session->id,
                    'best_weight' => max((float) $record->best_weight, $bestWeight),
                    'best_reps' => max((int) $record->best_reps, $bestReps),
                    'best_volume' => max((float) $record->best_volume, $bestVolume),
                    'best_estimated_one_rep_max' => $isNewEstimatedBest ? $bestEstimatedOneRepMax : $record->best_estimated_one_rep_max,
                    'estimated_one_rep_max_weight' => $isNewEstimatedBest ? (float) $bestEstimatedSet->weight : $record->estimated_one_rep_max_weight,
                    'estimated_one_rep_max_reps' => $isNewEstimatedBest ? (int) $bestEstimatedSet->reps : $record->estimated_one_rep_max_reps,
                    'estimated_one_rep_max_formula' => $isNewEstimatedBest ? 'epley_v1' : $record->estimated_one_rep_max_formula,
                    'estimated_one_rep_max_achieved_at' => $isNewEstimatedBest ? now() : $record->estimated_one_rep_max_achieved_at,
                    'achieved_at' => $isNewBest ? now() : $record->achieved_at,
                ]);
                $record->save();
                if ($isNewBest) {
                    $newPersonalRecordExerciseIds[] = $exercise->exercise_id;
                }
            }

            $progressionRecommendations = $this->workoutProgressionService->generateForCompletedSession($session);

            $session->update([
                'completion_summary' => $this->completionSummary(
                    $session,
                    $newPersonalRecordExerciseIds,
                    $progressionRecommendations,
                ),
                'runtime_state' => null,
            ]);

            return $session->fresh('exercises.exercise', 'exercises.sets', 'plan', 'member');
        });
    }

    /** @param array<string, mixed> $payload */
    private function sessionExerciseModePayload(array $payload): array
    {
        return [
            'tracking_mode' => $payload['tracking_mode'] ?? 'reps',
            'planned_duration_seconds' => $payload['planned_duration_seconds'] ?? null,
            'planned_distance_meters' => $payload['planned_distance_meters'] ?? null,
            'planned_speed_kph' => $payload['planned_speed_kph'] ?? null,
            'planned_pace_seconds_per_km' => $payload['planned_pace_seconds_per_km'] ?? null,
            'target_resistance' => $payload['target_resistance'] ?? null,
            'target_machine_level' => $payload['target_machine_level'] ?? null,
            'is_per_side' => (bool) ($payload['is_per_side'] ?? false),
            'is_bodyweight' => (bool) ($payload['is_bodyweight'] ?? false),
            'group_key' => $payload['group_key'] ?? null,
            'group_type' => $payload['group_type'] ?? null,
            'group_order' => $payload['group_order'] ?? null,
            'group_rounds' => $payload['group_rounds'] ?? null,
            'transition_seconds' => $payload['transition_seconds'] ?? null,
            'rest_after' => $payload['rest_after'] ?? 'exercise',
            'progression_policy' => $payload['progression_policy'] ?? 'off',
            'progression_config' => $payload['progression_config'] ?? null,
            'progression_version' => (int) ($payload['progression_version'] ?? 1),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function setPayload(array $payload): array
    {
        $completed = (bool) ($payload['is_completed'] ?? true);

        return [
            'set_number' => $payload['set_number'],
            'reps' => $payload['reps'] ?? 0,
            'duration_seconds' => $payload['duration_seconds'] ?? null,
            'distance_meters' => $payload['distance_meters'] ?? null,
            'speed_kph' => $payload['speed_kph'] ?? null,
            'pace_seconds_per_km' => $payload['pace_seconds_per_km'] ?? null,
            'weight' => $payload['weight'] ?? 0,
            'rest_seconds' => $payload['rest_seconds'] ?? null,
            'effort_scale' => $payload['effort_scale'] ?? null,
            'effort_value' => $payload['effort_value'] ?? null,
            'side' => $payload['side'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_completed' => $completed,
            'completed_at' => $payload['completed_at'] ?? ($completed ? now() : null),
        ];
    }

    /**
     * @param  array<int, int>  $newPersonalRecordExerciseIds
     * @param  array<int, WorkoutProgressionRecommendation>  $progressionRecommendations
     */
    private function completionSummary(WorkoutSession $session, array $newPersonalRecordExerciseIds, array $progressionRecommendations = []): array
    {
        $exerciseSummaries = $session->exercises->map(function (WorkoutSessionExercise $exercise): array {
            $completedSets = $exercise->sets->where('is_completed', true);

            return [
                'session_exercise_id' => $exercise->id,
                'exercise_id' => $exercise->exercise_id,
                'exercise_name' => $exercise->exercise?->name,
                'tracking_mode' => $exercise->tracking_mode,
                'performed_status' => $exercise->performed_status,
                'planned_sets' => $exercise->planned_sets,
                'completed_sets' => $completedSets->count(),
                'planned' => [
                    'reps' => $exercise->planned_reps,
                    'duration_seconds' => $exercise->planned_duration_seconds,
                    'distance_meters' => $exercise->planned_distance_meters !== null ? (float) $exercise->planned_distance_meters : null,
                    'speed_kph' => $exercise->planned_speed_kph !== null ? (float) $exercise->planned_speed_kph : null,
                    'pace_seconds_per_km' => $exercise->planned_pace_seconds_per_km,
                    'load' => $exercise->target_weight !== null ? (float) $exercise->target_weight : null,
                ],
                'performed' => [
                    'reps' => $completedSets->sum('reps'),
                    'duration_seconds' => $completedSets->sum('duration_seconds'),
                    'distance_meters' => round((float) $completedSets->sum('distance_meters'), 2),
                    'max_speed_kph' => $completedSets->max('speed_kph') !== null ? (float) $completedSets->max('speed_kph') : null,
                    'best_pace_seconds_per_km' => $completedSets->min('pace_seconds_per_km'),
                    'max_load' => (float) $completedSets->max('weight'),
                    'volume' => $exercise->tracking_mode === 'reps'
                        ? round((float) $completedSets->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps), 2)
                        : null,
                    'best_estimated_one_rep_max' => $exercise->tracking_mode === 'reps' && ! $exercise->is_bodyweight
                        ? $completedSets
                            ->filter(fn ($set): bool => (float) $set->weight > 0 && (int) $set->reps >= 1 && (int) $set->reps <= WorkoutProgressionService::ESTIMATED_ONE_REP_MAX_MAX_REPS)
                            ->map(fn ($set): float => $this->estimatedOneRepMax((float) $set->weight, (int) $set->reps))
                            ->max()
                        : null,
                ],
            ];
        })->values();

        return [
            'session_duration_seconds' => max(0, $session->started_at?->diffInSeconds($session->completed_at) ?? 0),
            'planned_exercises' => $session->exercises->count(),
            'completed_exercises' => $session->exercises->where('performed_status', 'completed')->count(),
            'skipped_exercises' => $session->exercises->where('performed_status', 'skipped')->count(),
            'substituted_exercises' => $session->exercises->where('performed_status', 'substituted')->count(),
            'total_compatible_volume' => (float) $session->total_volume,
            'new_personal_record_exercise_ids' => array_values(array_unique($newPersonalRecordExerciseIds)),
            'progression_recommendation_ids' => collect($progressionRecommendations)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'progression_recommendations' => collect($progressionRecommendations)->map(fn ($recommendation): array => [
                'id' => $recommendation->id,
                'exercise_id' => $recommendation->exercise_id,
                'action' => $recommendation->action,
                'status' => $recommendation->status,
                'policy' => $recommendation->policy,
                'algorithm_version' => $recommendation->algorithm_version,
                'recommended_prescription' => $recommendation->recommended_prescription,
                'explanation' => $recommendation->explanation,
            ])->values()->all(),
            'exercises' => $exerciseSummaries->all(),
        ];
    }

    private function estimatedOneRepMax(float $weight, int $reps): float
    {
        return round($weight * (1 + ($reps / 30)), 2);
    }
}
