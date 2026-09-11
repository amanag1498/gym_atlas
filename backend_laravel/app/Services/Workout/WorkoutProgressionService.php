<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\WorkoutPlanExercise;
use App\Models\WorkoutProgressionRecommendation;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutProgressionService
{
    public const ALGORITHM_VERSION = 1;

    public const ESTIMATED_ONE_REP_MAX_MAX_REPS = 12;

    /** @return array<int, WorkoutProgressionRecommendation> */
    public function generateForCompletedSession(WorkoutSession $session): array
    {
        $session->loadMissing('plan', 'exercises.sets');
        $recommendations = [];

        foreach ($session->exercises as $sessionExercise) {
            $policy = $sessionExercise->progression_policy ?? 'off';
            if ($policy === 'off' || $sessionExercise->tracking_mode !== 'reps' || ! $sessionExercise->workout_plan_exercise_id) {
                continue;
            }

            $recommendations[] = $this->recommend($session, $sessionExercise, $policy);
        }

        return $recommendations;
    }

    public function review(
        User $trainer,
        WorkoutProgressionRecommendation $recommendation,
        string $decision,
        ?array $overridePrescription = null,
        ?string $notes = null,
    ): WorkoutProgressionRecommendation {
        return DB::transaction(function () use ($trainer, $recommendation, $decision, $overridePrescription, $notes) {
            $locked = WorkoutProgressionRecommendation::query()->whereKey($recommendation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['recommendation' => ['This recommendation has already been reviewed.']]);
            }

            if ($decision === 'reject') {
                $locked->update([
                    'status' => 'rejected',
                    'reviewed_by_user_id' => $trainer->id,
                    'reviewed_at' => now(),
                    'review_notes' => $notes,
                ]);

                return $locked->fresh(['member', 'exercise', 'plan']);
            }

            $prescription = $decision === 'override'
                ? array_merge($locked->recommended_prescription, $overridePrescription ?? [])
                : $locked->recommended_prescription;
            $planExercise = $locked->planExercise()->lockForUpdate()->firstOrFail();
            if ($decision === 'approve' && ! $this->prescriptionMatches($planExercise, $locked->current_prescription)) {
                throw ValidationException::withMessages([
                    'recommendation' => ['The plan changed after this recommendation was created. Review and override it with current targets instead.'],
                ]);
            }
            $this->applyPrescription($planExercise, $prescription);
            $locked->update([
                'status' => $decision === 'override' ? 'overridden' : 'approved',
                'recommended_prescription' => $prescription,
                'reviewed_by_user_id' => $trainer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $locked->fresh(['member', 'exercise', 'plan']);
        });
    }

    private function recommend(WorkoutSession $session, WorkoutSessionExercise $exercise, string $policy): WorkoutProgressionRecommendation
    {
        $completedSets = $exercise->sets->where('is_completed', true);
        $plannedSets = (int) ($exercise->planned_sets ?? 0);
        $config = $exercise->progression_config ?? [];
        $current = [
            'sets' => $plannedSets,
            'reps' => $exercise->planned_reps,
            'target_weight' => $exercise->target_weight !== null ? (float) $exercise->target_weight : null,
        ];
        $recommended = $current;
        $minimumTarget = $this->minimumTargetReps($exercise->planned_reps);
        $requiredReps = $policy === 'double_progression'
            ? (int) ($config['max_reps'] ?? $minimumTarget)
            : $minimumTarget;
        $allSetsComplete = $plannedSets > 0 && $completedSets->count() >= $plannedSets;
        $allLoadsMet = (float) ($exercise->target_weight ?? 0) <= 0
            || $completedSets->every(fn ($set): bool => (float) $set->weight >= (float) $exercise->target_weight);
        $allTargetsMet = $allSetsComplete
            && $requiredReps > 0
            && $completedSets->every(fn ($set): bool => (int) $set->reps >= $requiredReps)
            && $allLoadsMet;
        $increment = (float) ($config['load_increment_kg'] ?? 2.5);
        $hasPendingRecommendation = WorkoutProgressionRecommendation::query()
            ->where('workout_plan_exercise_id', $exercise->workout_plan_exercise_id)
            ->where('status', 'pending')
            ->exists();
        $canIncrease = $allTargetsMet
            && ! $hasPendingRecommendation
            && (float) ($exercise->target_weight ?? 0) > 0;
        $action = $canIncrease ? 'increase' : 'hold';

        if ($canIncrease) {
            $recommended['target_weight'] = round((float) $exercise->target_weight + $increment, 2);
            if ($policy === 'double_progression') {
                $minReps = (int) ($config['min_reps'] ?? $minimumTarget);
                $maxReps = (int) ($config['max_reps'] ?? $requiredReps);
                $recommended['reps'] = $minReps.'-'.$maxReps;
            }
        }

        $explanation = $hasPendingRecommendation
            ? 'Keep the current prescription until the trainer reviews the earlier progression recommendation.'
            : ($canIncrease
            ? sprintf('All %d planned sets reached at least %d reps. Increase load by %.2f kg.', $plannedSets, $requiredReps, $increment)
            : sprintf('Keep the current prescription because %d of %d planned sets reached at least %d reps at the planned load.', $completedSets->filter(fn ($set) => (int) $set->reps >= $requiredReps && (float) $set->weight >= (float) ($exercise->target_weight ?? 0))->count(), $plannedSets, $requiredReps));
        $requiresReview = $canIncrease && $session->trainer_id !== null;

        $recommendation = WorkoutProgressionRecommendation::query()->updateOrCreate([
            'workout_plan_exercise_id' => $exercise->workout_plan_exercise_id,
            'source_workout_session_id' => $session->id,
        ], [
            'member_id' => $session->member_id,
            'trainer_id' => $session->trainer_id,
            'workout_plan_id' => $session->workout_plan_id,
            'exercise_id' => $exercise->exercise_id,
            'policy' => $policy,
            'algorithm_version' => self::ALGORITHM_VERSION,
            'action' => $action,
            'status' => $requiresReview ? 'pending' : 'applied',
            'current_prescription' => $current,
            'recommended_prescription' => $recommended,
            'decision_inputs' => [
                'planned_sets' => $plannedSets,
                'completed_sets' => $completedSets->count(),
                'required_reps_per_set' => $requiredReps,
                'completed_reps' => $completedSets->pluck('reps')->map(fn ($reps) => (int) $reps)->values()->all(),
                'source' => 'completed_workout_sets',
            ],
            'explanation' => $explanation,
        ]);

        if ($canIncrease && ! $requiresReview) {
            $this->applyPrescription($recommendation->planExercise, $recommended);
        }

        return $recommendation;
    }

    /** @param array<string, mixed> $prescription */
    private function applyPrescription(WorkoutPlanExercise $exercise, array $prescription): void
    {
        $exercise->update([
            'sets' => $prescription['sets'] ?? $exercise->sets,
            'reps' => $prescription['reps'] ?? $exercise->reps,
            'target_weight' => array_key_exists('target_weight', $prescription)
                ? $prescription['target_weight']
                : $exercise->target_weight,
        ]);
    }

    private function minimumTargetReps(?string $reps): int
    {
        if ($reps === null || preg_match('/\d+/', $reps, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[0];
    }

    /** @param array<string, mixed> $prescription */
    private function prescriptionMatches(WorkoutPlanExercise $exercise, array $prescription): bool
    {
        return (int) $exercise->sets === (int) ($prescription['sets'] ?? $exercise->sets)
            && (string) $exercise->reps === (string) ($prescription['reps'] ?? $exercise->reps)
            && round((float) ($exercise->target_weight ?? 0), 2) === round((float) ($prescription['target_weight'] ?? 0), 2);
    }
}
