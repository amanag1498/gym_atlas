<?php

namespace App\Services\Workout;

use App\Enums\WorkoutSessionStatus;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Services\Member\MemberAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutSessionService
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startSession(User $member, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($member, $payload) {
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
                ? WorkoutPlan::query()->with('days.exercises')->findOrFail($payload['workout_plan_id'])
                : null;

            if ($plan && (int) $plan->member_id !== (int) $member->id) {
                throw ValidationException::withMessages([
                    'workout_plan_id' => ['You do not have access to this workout plan.'],
                ]);
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
                'started_by_user_id' => $member->id,
                'session_date' => $payload['session_date'],
                'status' => WorkoutSessionStatus::Active->value,
                'started_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            if ($plan) {
                foreach ($plan->days as $day) {
                    foreach ($day->exercises as $planExercise) {
                        $session->exercises()->create([
                            'workout_plan_exercise_id' => $planExercise->id,
                            'exercise_id' => $planExercise->exercise_id,
                            'sort_order' => $planExercise->sort_order,
                            'planned_sets' => $planExercise->sets,
                            'planned_reps' => $planExercise->reps,
                            'target_weight' => $planExercise->target_weight,
                            'rest_timer_seconds' => $planExercise->rest_seconds,
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
            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Exercises can be added only to an active workout session.'],
                ]);
            }

            $sessionExercise = $session->exercises()->create([
                'exercise_id' => $payload['exercise_id'],
                'sort_order' => $payload['sort_order'] ?? ($session->exercises()->max('sort_order') + 1),
                'planned_sets' => $payload['planned_sets'] ?? null,
                'planned_reps' => $payload['planned_reps'] ?? null,
                'target_weight' => $payload['target_weight'] ?? null,
                'rest_timer_seconds' => $payload['rest_timer_seconds'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($payload['sets'] ?? [] as $setPayload) {
                $sessionExercise->sets()->create([
                    'set_number' => $setPayload['set_number'],
                    'reps' => $setPayload['reps'],
                    'weight' => $setPayload['weight'] ?? 0,
                    'rest_seconds' => $setPayload['rest_seconds'] ?? null,
                    'notes' => $setPayload['notes'] ?? null,
                    'is_completed' => $setPayload['is_completed'] ?? true,
                ]);
            }

            return $sessionExercise->load('exercise', 'sets');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function completeSession(WorkoutSession $session, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($session, $payload) {
            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Only an active workout session can be completed.'],
                ]);
            }

            if (isset($payload['notes'])) {
                $session->notes = $payload['notes'];
            }

            foreach ($payload['exercises'] ?? [] as $exercisePayload) {
                $sessionExercise = isset($exercisePayload['id'])
                    ? $session->exercises()->findOrFail($exercisePayload['id'])
                    : $session->exercises()->create([
                        'exercise_id' => $exercisePayload['exercise_id'],
                        'sort_order' => $exercisePayload['sort_order'] ?? ($session->exercises()->max('sort_order') + 1),
                        'planned_sets' => $exercisePayload['planned_sets'] ?? null,
                        'planned_reps' => $exercisePayload['planned_reps'] ?? null,
                        'target_weight' => $exercisePayload['target_weight'] ?? null,
                        'rest_timer_seconds' => $exercisePayload['rest_timer_seconds'] ?? null,
                        'notes' => $exercisePayload['notes'] ?? null,
                    ]);

                $sessionExercise->sets()->delete();

                foreach ($exercisePayload['sets'] ?? [] as $setPayload) {
                    $sessionExercise->sets()->create([
                        'set_number' => $setPayload['set_number'],
                        'reps' => $setPayload['reps'],
                        'weight' => $setPayload['weight'] ?? 0,
                        'rest_seconds' => $setPayload['rest_seconds'] ?? null,
                        'notes' => $setPayload['notes'] ?? null,
                        'is_completed' => $setPayload['is_completed'] ?? true,
                    ]);
                }
            }

            $session->load('exercises.exercise', 'exercises.sets');

            $volume = $session->exercises->sum(
                fn ($exercise) => $exercise->sets->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps)
            );

            $session->update([
                'status' => WorkoutSessionStatus::Completed->value,
                'completed_at' => now(),
                'total_volume' => $volume,
                'notes' => $session->notes,
            ]);

            foreach ($session->exercises as $exercise) {
                $bestWeight = (float) $exercise->sets->max('weight');
                $bestReps = (int) $exercise->sets->max('reps');
                $bestVolume = (float) $exercise->sets->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps);

                $record = PersonalRecord::query()->firstOrNew([
                    'member_id' => $session->member_id,
                    'exercise_id' => $exercise->exercise_id,
                ]);

                $record->fill([
                    'gym_id' => $session->gym_id,
                    'branch_id' => $session->branch_id,
                    'workout_session_id' => $session->id,
                    'best_weight' => max((float) $record->best_weight, $bestWeight),
                    'best_reps' => max((int) $record->best_reps, $bestReps),
                    'best_volume' => max((float) $record->best_volume, $bestVolume),
                    'achieved_at' => now(),
                ]);
                $record->save();
            }

            return $session->fresh('exercises.exercise', 'exercises.sets', 'plan', 'member');
        });
    }
}
