<?php

namespace App\Services\Workout;

use App\Enums\WorkoutSessionStatus;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionAction;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutSet;
use App\Services\Member\MemberAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutSessionService
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {}

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

            $session->update([
                'current_workout_session_exercise_id' => $session->exercises()->value('id'),
                'current_set_number' => 1,
            ]);

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
                    'entry_source' => 'legacy',
                ]);
            }

            $session->update([
                'current_workout_session_exercise_id' => $session->current_workout_session_exercise_id ?? $sessionExercise->id,
                'current_set_number' => $session->current_set_number ?? 1,
                'state_revision' => ((int) $session->state_revision) + 1,
            ]);

            return $sessionExercise->load('exercise', 'sets');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function completeSession(WorkoutSession $session, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($session, $payload) {
            $session = WorkoutSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Only an active workout session can be completed.'],
                ]);
            }

            $this->assertExpectedRevision($session, $payload);

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

                $incomingSetNumbers = collect($exercisePayload['sets'] ?? [])->pluck('set_number');
                $sessionExercise->sets()
                    ->where('entry_source', '!=', 'atomic_action')
                    ->whereNotIn('set_number', $incomingSetNumbers)
                    ->delete();

                foreach ($exercisePayload['sets'] ?? [] as $setPayload) {
                    $existingSet = $sessionExercise->sets()
                        ->where('set_number', $setPayload['set_number'])
                        ->first();

                    // A lock-screen/background write is already durable. A legacy
                    // completion payload may have been assembled before that write,
                    // so it must never replace the persisted values.
                    if ($existingSet?->entry_source === 'atomic_action') {
                        continue;
                    }

                    $setValues = [
                        'set_number' => $setPayload['set_number'],
                        'reps' => $setPayload['reps'],
                        'weight' => $setPayload['weight'] ?? 0,
                        'rest_seconds' => $setPayload['rest_seconds'] ?? null,
                        'notes' => $setPayload['notes'] ?? null,
                        'is_completed' => $setPayload['is_completed'] ?? true,
                        'entry_source' => 'legacy',
                    ];

                    $existingSet
                        ? $existingSet->update($setValues)
                        : $sessionExercise->sets()->create($setValues);
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
                'rest_ends_at' => null,
                'state_revision' => ((int) $session->state_revision) + 1,
            ]);

            foreach ($session->exercises as $exercise) {
                $bestWeight = (float) $exercise->sets->max('weight');
                $bestReps = (int) $exercise->sets->max('reps');
                $bestVolume = (float) $exercise->sets->sum(fn ($set) => ((float) $set->weight) * (int) $set->reps);

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

    /**
     * Apply one small, durable workout action. The session row lock serializes
     * notification, widget, and foreground-app writes for the same workout.
     *
     * @param  array<string, mixed>  $payload
     * @return array{session: WorkoutSession, replayed: bool}
     */
    public function applyAction(User $actor, WorkoutSession $session, array $payload): array
    {
        return DB::transaction(function () use ($actor, $session, $payload): array {
            $session = WorkoutSession::query()->lockForUpdate()->findOrFail($session->id);

            $existingAction = WorkoutSessionAction::query()
                ->where('workout_session_id', $session->id)
                ->where('idempotency_key', $payload['idempotency_key'])
                ->first();

            if ($existingAction !== null) {
                if ($existingAction->action !== $payload['action']) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['This idempotency key was already used for a different workout action.'],
                    ]);
                }

                return [
                    'session' => $this->loadSession($session),
                    'replayed' => true,
                ];
            }

            if ($session->status !== WorkoutSessionStatus::Active->value) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['Actions can be applied only to an active workout session.'],
                ]);
            }

            $this->assertExpectedRevision($session, $payload);

            match ($payload['action']) {
                'complete_set' => $this->upsertSet($session, $payload, true),
                'update_set' => $this->upsertSet($session, $payload, false),
                'delete_set' => $this->deleteSet($session, $payload),
                'next_exercise' => $this->moveExercise($session, 1),
                'previous_exercise' => $this->moveExercise($session, -1),
                'start_rest' => $this->startRest($session, $payload),
                'skip_rest' => $session->rest_ends_at = null,
            };

            $session->state_revision = ((int) $session->state_revision) + 1;
            $session->save();

            WorkoutSessionAction::query()->create([
                'workout_session_id' => $session->id,
                'user_id' => $actor->id,
                'idempotency_key' => $payload['idempotency_key'],
                'action' => $payload['action'],
                'resulting_revision' => $session->state_revision,
            ]);

            return [
                'session' => $this->loadSession($session),
                'replayed' => false,
            ];
        });
    }

    /** @param array<string, mixed> $payload */
    private function upsertSet(WorkoutSession $session, array $payload, bool $complete): void
    {
        $exercise = $this->sessionExercise($session, (int) $payload['workout_session_exercise_id']);
        $set = $this->resolveSet($exercise, $payload);
        $setNumber = isset($payload['set_number'])
            ? (int) $payload['set_number']
            : (int) $set?->set_number;

        $values = [
            'set_number' => $setNumber,
            'reps' => (int) $payload['reps'],
            'weight' => $payload['weight'] ?? 0,
            'rest_seconds' => $payload['rest_seconds'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_completed' => $complete ? true : ($set?->is_completed ?? false),
            'entry_source' => 'atomic_action',
        ];

        $set ? $set->update($values) : $exercise->sets()->create($values);

        $session->current_workout_session_exercise_id = $exercise->id;
        $session->current_set_number = $complete ? $setNumber + 1 : $setNumber;
    }

    /** @param array<string, mixed> $payload */
    private function deleteSet(WorkoutSession $session, array $payload): void
    {
        $exercise = $this->sessionExercise($session, (int) $payload['workout_session_exercise_id']);
        $set = $this->resolveSet($exercise, $payload);

        if ($set === null) {
            throw ValidationException::withMessages([
                'set_number' => ['The requested workout set does not exist.'],
            ]);
        }

        $deletedNumber = (int) $set->set_number;
        $set->delete();
        $session->current_workout_session_exercise_id = $exercise->id;
        $session->current_set_number = max(1, $deletedNumber);
    }

    private function moveExercise(WorkoutSession $session, int $direction): void
    {
        $exerciseIds = $session->exercises()->orderBy('sort_order')->orderBy('id')->pluck('id')->values();

        if ($exerciseIds->isEmpty()) {
            throw ValidationException::withMessages([
                'workout_session_id' => ['This workout session has no exercises.'],
            ]);
        }

        $currentIndex = $exerciseIds->search((int) $session->current_workout_session_exercise_id);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;
        $nextIndex = min(max($currentIndex + $direction, 0), $exerciseIds->count() - 1);

        $session->current_workout_session_exercise_id = $exerciseIds[$nextIndex];
        $session->current_set_number = 1;
        $session->rest_ends_at = null;
    }

    /** @param array<string, mixed> $payload */
    private function startRest(WorkoutSession $session, array $payload): void
    {
        $exercise = $this->sessionExercise($session, (int) $payload['workout_session_exercise_id']);
        $seconds = (int) ($payload['rest_seconds'] ?? $exercise->rest_timer_seconds ?? 60);
        $session->rest_ends_at = now()->addSeconds($seconds);
    }

    private function sessionExercise(WorkoutSession $session, int $exerciseId): WorkoutSessionExercise
    {
        $exercise = $session->exercises()->whereKey($exerciseId)->first();

        if ($exercise === null) {
            throw ValidationException::withMessages([
                'workout_session_exercise_id' => ['The exercise does not belong to this workout session.'],
            ]);
        }

        return $exercise;
    }

    /** @param array<string, mixed> $payload */
    private function resolveSet(WorkoutSessionExercise $exercise, array $payload): ?WorkoutSet
    {
        if (isset($payload['set_id'])) {
            $set = $exercise->sets()->whereKey($payload['set_id'])->first();

            if ($set === null) {
                throw ValidationException::withMessages([
                    'set_id' => ['The workout set does not belong to the selected session exercise.'],
                ]);
            }

            return $set;
        }

        return $exercise->sets()->where('set_number', $payload['set_number'])->first();
    }

    /** @param array<string, mixed> $payload */
    private function assertExpectedRevision(WorkoutSession $session, array $payload): void
    {
        if (isset($payload['expected_revision']) && (int) $payload['expected_revision'] !== (int) $session->state_revision) {
            throw ValidationException::withMessages([
                'expected_revision' => [sprintf(
                    'The workout session has changed. Refresh it and retry with revision %d.',
                    $session->state_revision,
                )],
            ]);
        }
    }

    private function loadSession(WorkoutSession $session): WorkoutSession
    {
        return $session->fresh('exercises.exercise', 'exercises.sets', 'plan', 'member');
    }
}
