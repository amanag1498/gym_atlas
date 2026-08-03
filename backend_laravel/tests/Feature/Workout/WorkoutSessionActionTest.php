<?php

namespace Tests\Feature\Workout;

use App\Enums\RoleName;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSessionAction;
use App\Models\WorkoutSet;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutSessionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_fetch_only_their_active_session(): void
    {
        [$member, $sessionId, $sessionExerciseId] = $this->activeWorkout();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.current_workout_session_exercise_id', $sessionExerciseId)
            ->assertJsonPath('data.current_set_number', 1)
            ->assertJsonPath('data.state_revision', 1);

        $otherMember = $this->member();

        $this->actingAs($otherMember, 'sanctum')
            ->getJson('/api/member/workout-sessions/active')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($otherMember, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/actions", [
                'action' => 'complete_set',
                'idempotency_key' => 'other-member-action',
                'workout_session_exercise_id' => $sessionExerciseId,
                'set_number' => 1,
                'reps' => 12,
            ])
            ->assertUnprocessable();
    }

    public function test_set_action_is_atomic_idempotent_and_revision_guarded(): void
    {
        [$member, $sessionId, $sessionExerciseId] = $this->activeWorkout();
        $payload = [
            'action' => 'complete_set',
            'idempotency_key' => 'lock-screen-set-1',
            'expected_revision' => 1,
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 1,
            'reps' => 10,
            'weight' => 42.5,
            'rest_seconds' => 60,
        ];

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/actions", $payload)
            ->assertOk()
            ->assertJsonPath('data.state_revision', 2)
            ->assertJsonPath('data.current_set_number', 2)
            ->assertJsonPath('data.exercises.0.sets.0.reps', 10)
            ->assertJsonPath('data.exercises.0.sets.0.weight', 42.5)
            ->assertJsonPath('data.exercises.0.sets.0.entry_source', 'atomic_action');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/actions", $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Workout action already applied.')
            ->assertJsonPath('data.state_revision', 2);

        $this->assertDatabaseCount(WorkoutSet::class, 1);
        $this->assertDatabaseCount(WorkoutSessionAction::class, 1);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/actions", [
                ...$payload,
                'idempotency_key' => 'stale-write',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.expected_revision.0',
                'The workout session has changed. Refresh it and retry with revision 2.',
            );
    }

    public function test_update_delete_navigation_and_rest_actions_persist_current_state(): void
    {
        [$member, $sessionId, $sessionExerciseId, $secondExerciseId] = $this->activeWorkout(twoExercises: true);

        $response = $this->action($member, $sessionId, [
            'action' => 'update_set',
            'idempotency_key' => 'draft-set',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 2,
            'reps' => 8,
            'weight' => 50,
        ])->assertJsonPath('data.exercises.0.sets.0.is_completed', false);

        $setId = $response->json('data.exercises.0.sets.0.id');

        $this->action($member, $sessionId, [
            'action' => 'start_rest',
            'idempotency_key' => 'rest-start',
            'workout_session_exercise_id' => $sessionExerciseId,
            'rest_seconds' => 90,
        ])->assertJsonPath('data.state_revision', 4)
            ->assertJsonPath('data.current_workout_session_exercise_id', $sessionExerciseId)
            ->assertJson(fn ($json) => $json->whereType('data.rest_ends_at', 'string')->etc());

        $this->action($member, $sessionId, [
            'action' => 'next_exercise',
            'idempotency_key' => 'next-exercise',
        ])->assertJsonPath('data.current_workout_session_exercise_id', $secondExerciseId)
            ->assertJsonPath('data.current_set_number', 1)
            ->assertJsonPath('data.rest_ends_at', null);

        $this->action($member, $sessionId, [
            'action' => 'start_rest',
            'idempotency_key' => 'rest-after-navigation',
            'workout_session_exercise_id' => $sessionExerciseId,
            'rest_seconds' => 45,
        ])->assertJsonPath('data.current_workout_session_exercise_id', $secondExerciseId)
            ->assertJson(fn ($json) => $json->whereType('data.rest_ends_at', 'string')->etc());

        $this->action($member, $sessionId, [
            'action' => 'previous_exercise',
            'idempotency_key' => 'previous-exercise',
        ])->assertJsonPath('data.current_workout_session_exercise_id', $sessionExerciseId);

        $this->action($member, $sessionId, [
            'action' => 'delete_set',
            'idempotency_key' => 'delete-draft',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_id' => $setId,
        ])->assertJsonCount(0, 'data.exercises.0.sets');

        $this->assertDatabaseMissing('workout_sets', ['id' => $setId]);
    }

    public function test_legacy_completion_cannot_overwrite_an_atomic_set(): void
    {
        [$member, $sessionId, $sessionExerciseId, , $exerciseId] = $this->activeWorkout();

        $this->action($member, $sessionId, [
            'action' => 'complete_set',
            'idempotency_key' => 'native-complete-set',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 1,
            'reps' => 12,
            'weight' => 60,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/complete", [
                'exercises' => [[
                    'id' => $sessionExerciseId,
                    'exercise_id' => $exerciseId,
                    'sets' => [
                        ['set_number' => 1, 'reps' => 5, 'weight' => 10, 'is_completed' => true],
                        ['set_number' => 2, 'reps' => 8, 'weight' => 40, 'is_completed' => true],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.exercises.0.sets.0.reps', 12)
            ->assertJsonPath('data.exercises.0.sets.0.weight', 60)
            ->assertJsonPath('data.exercises.0.sets.1.reps', 8)
            ->assertJsonPath('data.total_volume', 1040);

        $this->assertDatabaseHas('workout_sets', [
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 1,
            'reps' => 12,
            'weight' => 60,
            'entry_source' => 'atomic_action',
        ]);
    }

    public function test_update_action_can_renumber_a_persisted_set_after_deletion(): void
    {
        [$member, $sessionId, $sessionExerciseId] = $this->activeWorkout();

        $firstSetId = $this->action($member, $sessionId, [
            'action' => 'update_set',
            'idempotency_key' => 'renumber-first',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 1,
            'reps' => 8,
        ])->json('data.exercises.0.sets.0.id');

        $secondSetId = $this->action($member, $sessionId, [
            'action' => 'update_set',
            'idempotency_key' => 'renumber-second',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_number' => 2,
            'reps' => 10,
        ])->json('data.exercises.0.sets.1.id');

        $this->action($member, $sessionId, [
            'action' => 'delete_set',
            'idempotency_key' => 'renumber-delete-first',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_id' => $firstSetId,
        ]);

        $this->action($member, $sessionId, [
            'action' => 'update_set',
            'idempotency_key' => 'renumber-survivor',
            'workout_session_exercise_id' => $sessionExerciseId,
            'set_id' => $secondSetId,
            'set_number' => 1,
            'reps' => 10,
        ])->assertJsonPath('data.exercises.0.sets.0.set_number', 1);

        $this->assertDatabaseHas('workout_sets', [
            'id' => $secondSetId,
            'set_number' => 1,
        ]);
    }

    private function action(User $member, int $sessionId, array $payload)
    {
        return $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/actions", $payload)
            ->assertOk();
    }

    /** @return array{User, int, int, int|null, int} */
    private function activeWorkout(bool $twoExercises = false): array
    {
        $this->seed(PermissionSeeder::class);
        $member = $this->member();
        $exercise = $this->exercise('Lock-screen press');

        $sessionId = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', ['session_date' => today()->toDateString()])
            ->assertCreated()
            ->json('data.id');

        $sessionExerciseId = $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/exercises", [
                'exercise_id' => $exercise->id,
                'sort_order' => 1,
                'planned_sets' => 3,
                'rest_timer_seconds' => 60,
            ])
            ->assertOk()
            ->json('data.exercises.0.id');

        $secondExerciseId = null;
        if ($twoExercises) {
            $secondExercise = $this->exercise('Lock-screen row');
            $secondExerciseId = $this->actingAs($member, 'sanctum')
                ->postJson("/api/member/workout-sessions/{$sessionId}/exercises", [
                    'exercise_id' => $secondExercise->id,
                    'sort_order' => 2,
                    'planned_sets' => 3,
                ])
                ->assertOk()
                ->json('data.exercises.1.id');
        }

        return [$member, $sessionId, $sessionExerciseId, $secondExerciseId, $exercise->id];
    }

    private function member(): User
    {
        $member = User::factory()->create(['active_role' => RoleName::Member->value]);
        $member->assignRole(RoleName::Member->value);

        return $member;
    }

    private function exercise(string $name): Exercise
    {
        return Exercise::query()->create([
            'name' => $name,
            'muscle_group' => 'chest',
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }
}
