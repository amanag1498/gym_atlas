<?php

namespace Tests\Feature\Workout;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutProgressionRecommendation;
use App\Models\WorkoutSession;
use App\Services\Workout\WorkoutPlanService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkoutScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_trainer_assignment_from_current_profile_when_an_older_profile_exists(): void
    {
        $this->seed(PermissionSeeder::class);
        [$oldGym, $oldBranch] = $this->makeGymContext();
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);
        $member->gyms()->attach([$oldGym->id, $gym->id]);
        $member->branches()->attach([$oldBranch->id, $branch->id]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $oldGym->id,
            'branch_id' => $oldBranch->id,
            'membership_status' => 'inactive',
            'is_active' => false,
        ]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $exercise = Exercise::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $trainer->id,
            'name' => 'Current Gym Squat',
            'muscle_group' => 'legs',
            'is_global' => false,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/workout-plans', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_ids' => [$member->id],
                'name' => 'Trainer Strength Plan',
                'duration_weeks' => 4,
                'days' => [[
                    'day_number' => 1,
                    'exercises' => [[
                        'exercise_id' => $exercise->id,
                        'sets' => 3,
                    ]],
                ]],
            ])
            ->assertCreated();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Trainer Strength Plan')
            ->assertJsonPath('data.0.trainer_id', $trainer->id);
    }

    public function test_trainer_cannot_assign_workout_to_unassigned_member(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();

        $trainer = $this->makeTrainer($gym, $branch);
        $otherTrainer = $this->makeTrainer($gym, $branch, 'other-trainer@example.com');
        $member = $this->makeMember($gym, $branch, $otherTrainer->id);

        $exercise = Exercise::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $trainer->id,
            'name' => 'Goblet Squat',
            'muscle_group' => 'legs',
            'is_global' => false,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/workout-plans', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_ids' => [$member->id],
                'name' => 'Restricted Plan',
                'duration_weeks' => 4,
                'days' => [
                    [
                        'day_number' => 1,
                        'exercises' => [
                            [
                                'exercise_id' => $exercise->id,
                                'sets' => 3,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_ids.0', 'You can assign workouts only to your assigned members.');
    }

    public function test_member_cannot_start_duplicate_active_workout_session(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();

        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.session.0', 'An active workout session already exists for this member.');
    }

    public function test_member_workout_session_uses_backend_member_scope_instead_of_client_scope(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        [$otherGym, $otherBranch] = $this->makeGymContext();

        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'gym_id' => $otherGym->id,
                'branch_id' => $otherBranch->id,
                'session_date' => now()->toDateString(),
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Requested gym scope does not match the authenticated gym scope.');

        $this->assertDatabaseCount(WorkoutSession::class, 0);
    }

    public function test_member_can_start_only_the_selected_workout_plan_day(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$pushExercise, $pullExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $plan = $this->makeMemberPlan($member, $pushExercise, $pullExercise);
        $pullDay = $plan->days->firstWhere('day_number', 2);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $pullDay->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.workout_plan_day_id', $pullDay->id)
            ->assertJsonPath('data.plan_day_number', 2)
            ->assertJsonPath('data.plan_day_label', 'Pull')
            ->assertJsonPath('data.day_selection_mode', 'member_selected')
            ->assertJsonCount(1, 'data.exercises')
            ->assertJsonPath('data.exercises.0.exercise_id', $pullExercise->id);

        $this->assertDatabaseHas('workout_sessions', [
            'member_id' => $member->id,
            'workout_plan_id' => $plan->id,
            'workout_plan_day_id' => $pullDay->id,
            'plan_day_number' => 2,
            'plan_day_label' => 'Pull',
            'day_selection_mode' => 'member_selected',
        ]);

        app(WorkoutPlanService::class)->updatePlan($plan, [
            'name' => 'Updated after session start',
            'duration_weeks' => 4,
            'days' => [[
                'day_number' => 1,
                'label' => 'Replacement day',
                'exercises' => [[
                    'exercise_id' => $pushExercise->id,
                    'sets' => 3,
                ]],
            ]],
        ]);

        $session = WorkoutSession::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertNull($session->workout_plan_day_id);
        $this->assertSame(2, $session->plan_day_number);
        $this->assertSame('Pull', $session->plan_day_label);
    }

    public function test_member_cannot_start_a_day_from_another_workout_plan(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$pushExercise, $pullExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $plan = $this->makeMemberPlan($member, $pushExercise, $pullExercise, 'Primary plan');
        $otherPlan = $this->makeMemberPlan($member, $pushExercise, $pullExercise, 'Other plan');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $otherPlan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.workout_plan_day_id.0',
                'The selected workout day does not belong to this workout plan.',
            );

        $this->assertDatabaseCount(WorkoutSession::class, 0);
    }

    public function test_member_cannot_start_an_inactive_historical_workout_plan_by_id(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$pushExercise, $pullExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $plan = $this->makeMemberPlan($member, $pushExercise, $pullExercise);
        $plan->update(['status' => 'inactive']);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.workout_plan_id.0',
                'This gym workout plan is historical and is no longer available as a current assignment.',
            );

        $this->assertDatabaseCount(WorkoutSession::class, 0);
    }

    public function test_legacy_start_request_still_loads_all_plan_days(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$pushExercise, $pullExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $plan = $this->makeMemberPlan($member, $pushExercise, $pullExercise);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.workout_plan_day_id', null)
            ->assertJsonPath('data.day_selection_mode', 'legacy_all_days')
            ->assertJsonCount(2, 'data.exercises');
    }

    public function test_member_cannot_select_a_workout_day_without_its_plan(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$pushExercise, $pullExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $plan = $this->makeMemberPlan($member, $pushExercise, $pullExercise);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.workout_plan_day_id.0',
                'Select a workout plan before selecting a workout day.',
            );

        $this->assertDatabaseCount(WorkoutSession::class, 0);
    }

    public function test_member_context_advertises_workout_day_selection(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.capabilities.workout_day_selection', true);
    }

    public function test_mode_aware_plan_round_trips_through_session_and_completion_summary(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$exercise] = $this->makePlanExercises($gym, $branch, $trainer);

        $plan = app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => 'Timed conditioning',
            'duration_weeks' => 2,
            'days' => [[
                'day_number' => 1,
                'exercises' => [[
                    'exercise_id' => $exercise->id,
                    'sets' => 2,
                    'tracking_mode' => 'timed',
                    'planned_duration_seconds' => 45,
                    'target_weight' => 12.5,
                    'target_resistance' => 4,
                    'is_per_side' => true,
                    'rest_seconds' => 30,
                ]],
            ]],
        ]);

        $started = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
                'pre_workout_weight_kg' => 72.4,
                'save_pre_workout_weight' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.exercises.0.tracking_mode', 'timed')
            ->assertJsonPath('data.exercises.0.planned_duration_seconds', 45)
            ->assertJsonPath('data.exercises.0.is_per_side', true)
            ->assertJsonPath('data.pre_workout_weight_kg', 72.4);

        $sessionId = $started->json('data.id');
        $sessionExerciseId = $started->json('data.exercises.0.id');

        $restEndsAt = now()->addMinute()->toIso8601String();
        $this->actingAs($member, 'sanctum')
            ->putJson("/api/member/workout-sessions/{$sessionId}/progress", [
                'exercises' => [[
                    'id' => $sessionExerciseId,
                    'notes' => 'Autosaved draft',
                    'sets' => [[
                        'set_number' => 1,
                        'duration_seconds' => 31,
                        'weight' => 12.5,
                        'is_completed' => true,
                    ]],
                ]],
                'runtime_state' => [
                    'rest_exercise_index' => 0,
                    'rest_ends_at' => $restEndsAt,
                    'rest_total_seconds' => 60,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.exercises.0.sets.0.duration_seconds', 31);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.exercises.0.tracking_mode', 'timed')
            ->assertJsonPath('data.exercises.0.sets.0.duration_seconds', 31)
            ->assertJsonPath('data.runtime_state.rest_exercise_index', 0);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/complete", [
                'exercises' => [[
                    'id' => $sessionExerciseId,
                    'exercise_id' => $exercise->id,
                    'tracking_mode' => 'timed',
                    'performed_status' => 'completed',
                    'sets' => [[
                        'set_number' => 1,
                        'duration_seconds' => 47,
                        'weight' => 12.5,
                        'effort_scale' => 'rpe',
                        'effort_value' => 8,
                        'side' => 'both',
                        'is_completed' => true,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.exercises.0.sets.0.duration_seconds', 47)
            ->assertJsonPath('data.exercises.0.sets.0.effort_scale', 'rpe')
            ->assertJsonPath('data.completion_summary.completed_exercises', 1)
            ->assertJsonPath('data.completion_summary.exercises.0.performed.duration_seconds', 47);

        $this->assertDatabaseHas('weight_logs', [
            'member_id' => $member->id,
            'weight_kg' => 72.4,
        ]);
    }

    public function test_completed_timed_and_distance_sets_require_mode_specific_actuals(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$exercise] = $this->makePlanExercises($gym, $branch, $trainer);

        $sessionId = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/complete", [
                'exercises' => [[
                    'exercise_id' => $exercise->id,
                    'tracking_mode' => 'timed',
                    'sets' => [['set_number' => 1, 'is_completed' => true]],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exercises.0.sets.0.duration_seconds');
    }

    public function test_cardio_and_distance_actuals_round_trip_without_creating_rep_volume(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$cardioExercise, $distanceExercise] = $this->makePlanExercises($gym, $branch, $trainer);

        $plan = app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => 'Conditioning modes',
            'duration_weeks' => 2,
            'days' => [[
                'day_number' => 1,
                'exercises' => [[
                    'exercise_id' => $cardioExercise->id,
                    'sets' => 1,
                    'tracking_mode' => 'cardio',
                    'planned_duration_seconds' => 600,
                    'planned_speed_kph' => 9.5,
                ], [
                    'exercise_id' => $distanceExercise->id,
                    'sets' => 1,
                    'tracking_mode' => 'distance',
                    'planned_distance_meters' => 1000,
                ]],
            ]],
        ]);

        $started = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.exercises.0.tracking_mode', 'cardio')
            ->assertJsonPath('data.exercises.1.tracking_mode', 'distance');

        $sessionId = $started->json('data.id');
        $sessionExercises = $started->json('data.exercises');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-sessions/{$sessionId}/complete", [
                'exercises' => [[
                    'id' => $sessionExercises[0]['id'],
                    'exercise_id' => $cardioExercise->id,
                    'tracking_mode' => 'cardio',
                    'sets' => [[
                        'set_number' => 1,
                        'duration_seconds' => 615,
                        'distance_meters' => 1500,
                        'speed_kph' => 9.8,
                    ]],
                ], [
                    'id' => $sessionExercises[1]['id'],
                    'exercise_id' => $distanceExercise->id,
                    'tracking_mode' => 'distance',
                    'sets' => [[
                        'set_number' => 1,
                        'duration_seconds' => 280,
                        'distance_meters' => 1000,
                        'pace_seconds_per_km' => 280,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_volume', 0)
            ->assertJsonPath('data.completion_summary.exercises.0.performed.distance_meters', 1500)
            ->assertJsonPath('data.completion_summary.exercises.1.performed.best_pace_seconds_per_km', 280);
    }

    public function test_active_session_endpoint_does_not_expose_another_members_session(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        $otherMember = $this->makeMember($gym, $branch, $trainer->id);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', ['session_date' => now()->toDateString()])
            ->assertCreated();

        $this->actingAs($otherMember, 'sanctum')
            ->getJson('/api/member/workout-sessions/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_grouped_plan_progression_and_estimated_one_rep_max_round_trip_end_to_end(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$benchPress, $latPulldown] = $this->makePlanExercises($gym, $branch, $trainer);

        $plan = app(WorkoutPlanService::class)->createPlans($trainer, [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_ids' => [$member->id],
            'name' => 'Progressive Superset',
            'duration_weeks' => 4,
            'days' => [[
                'day_number' => 1,
                'exercises' => [[
                    'exercise_id' => $benchPress->id,
                    'sort_order' => 1,
                    'sets' => 3,
                    'reps' => '8-10',
                    'target_weight' => 100,
                    'group_key' => 'A',
                    'group_type' => 'superset',
                    'group_order' => 1,
                    'group_rounds' => 3,
                    'transition_seconds' => 15,
                    'rest_after' => 'group',
                    'progression_policy' => 'double_progression',
                    'progression_config' => [
                        'min_reps' => 8,
                        'max_reps' => 10,
                        'load_increment_kg' => 2.5,
                    ],
                ], [
                    'exercise_id' => $latPulldown->id,
                    'sort_order' => 2,
                    'sets' => 3,
                    'reps' => '10',
                    'target_weight' => 60,
                    'group_key' => 'A',
                    'group_type' => 'superset',
                    'group_order' => 2,
                    'group_rounds' => 3,
                    'transition_seconds' => 15,
                    'rest_after' => 'group',
                ]],
            ]],
        ])->firstOrFail();

        $started = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.exercises.0.group_key', 'A')
            ->assertJsonPath('data.exercises.0.group_type', 'superset')
            ->assertJsonPath('data.exercises.1.group_order', 2)
            ->assertJsonPath('data.exercises.0.progression_policy', 'double_progression');

        $sessionExercises = $started->json('data.exercises');
        $this->actingAs($member, 'sanctum')
            ->putJson('/api/member/workout-sessions/'.$started->json('data.id').'/progress', [
                'exercises' => [[
                    'id' => $sessionExercises[0]['id'],
                    'performed_status' => 'skipped',
                    'sets' => [],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.exercises.0.performed_status', 'skipped');
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.exercises.0.performed_status', 'skipped');

        $sets = fn (float $weight): array => collect(range(1, 3))->map(fn (int $setNumber): array => [
            'set_number' => $setNumber,
            'reps' => 10,
            'weight' => $weight,
            'effort_scale' => $setNumber === 1 ? 'rir' : 'rpe',
            'effort_value' => $setNumber === 1 ? 0 : 8,
            'is_completed' => true,
        ])->all();

        $completed = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/'.$started->json('data.id').'/complete', [
                'exercises' => [[
                    'id' => $sessionExercises[0]['id'],
                    'exercise_id' => $benchPress->id,
                    'tracking_mode' => 'reps',
                    'sets' => $sets(100),
                ], [
                    'id' => $sessionExercises[1]['id'],
                    'exercise_id' => $latPulldown->id,
                    'tracking_mode' => 'reps',
                    'sets' => $sets(60),
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.exercises.0.sets.0.effort_scale', 'rir')
            ->assertJsonPath('data.exercises.0.sets.0.effort_value', 0)
            ->assertJsonPath('data.completion_summary.exercises.0.performed.best_estimated_one_rep_max', 133.33);

        $recommendationId = $completed->json('data.completion_summary.progression_recommendation_ids.0');
        $this->assertNotNull($recommendationId);
        $this->assertDatabaseHas('workout_progression_recommendations', [
            'id' => $recommendationId,
            'trainer_id' => $trainer->id,
            'action' => 'increase',
            'status' => 'pending',
            'algorithm_version' => 1,
        ]);
        $this->assertDatabaseHas('personal_records', [
            'member_id' => $member->id,
            'exercise_id' => $benchPress->id,
            'best_estimated_one_rep_max' => 133.33,
            'estimated_one_rep_max_formula' => 'epley_v1',
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/workout-progression-recommendations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recommendationId)
            ->assertJsonPath('data.0.recommended_prescription.target_weight', 102.5);

        $otherTrainer = $this->makeTrainer($gym, $branch, 'progression-other@example.com');
        $this->actingAs($otherTrainer, 'sanctum')
            ->postJson("/api/trainer/workout-progression-recommendations/{$recommendationId}/review", [
                'decision' => 'approve',
            ])
            ->assertUnprocessable();

        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/workout-progression-recommendations/{$recommendationId}/review", [
                'decision' => 'approve',
                'notes' => 'Form remained consistent.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('workout_plan_exercises', [
            'id' => $sessionExercises[0]['workout_plan_exercise_id'] ?? $plan->days->first()->exercises->first()->id,
            'target_weight' => 102.5,
        ]);

        $secondPlanExercise = $plan->days->first()->exercises->last();
        $manualRecommendation = WorkoutProgressionRecommendation::query()->create([
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'workout_plan_id' => $plan->id,
            'workout_plan_exercise_id' => $secondPlanExercise->id,
            'exercise_id' => $latPulldown->id,
            'source_workout_session_id' => $started->json('data.id'),
            'policy' => 'linear_load',
            'algorithm_version' => 1,
            'action' => 'increase',
            'status' => 'pending',
            'current_prescription' => ['sets' => 3, 'reps' => '10', 'target_weight' => 60],
            'recommended_prescription' => ['sets' => 3, 'reps' => '10', 'target_weight' => 62.5],
            'decision_inputs' => ['source' => 'completed_workout_sets'],
            'explanation' => 'Manual review fixture.',
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/workout-progression-recommendations/{$manualRecommendation->id}/review", [
                'decision' => 'override',
                'prescription' => ['sets' => 4, 'reps' => '8', 'target_weight' => 65],
                'notes' => 'Use a larger but reviewed change.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'overridden')
            ->assertJsonPath('data.recommended_prescription.target_weight', 65);
        $this->assertDatabaseHas('workout_plan_exercises', [
            'id' => $secondPlanExercise->id,
            'sets' => 4,
            'reps' => '8',
            'target_weight' => 65,
        ]);
    }

    public function test_personal_progression_applies_automatically_and_high_rep_sets_do_not_create_e1rm(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$exercise] = $this->makePlanExercises($gym, $branch, $trainer);

        $plan = app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => 'Personal progression',
            'duration_weeks' => 2,
            'days' => [[
                'day_number' => 1,
                'exercises' => [[
                    'exercise_id' => $exercise->id,
                    'sets' => 1,
                    'reps' => '15',
                    'target_weight' => 50,
                    'progression_policy' => 'linear_load',
                    'progression_config' => ['load_increment_kg' => 2.5],
                ]],
            ]],
        ]);

        $started = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'workout_plan_id' => $plan->id,
                'workout_plan_day_id' => $plan->days->first()->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $completed = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/'.$started->json('data.id').'/complete', [
                'exercises' => [[
                    'id' => $started->json('data.exercises.0.id'),
                    'exercise_id' => $exercise->id,
                    'tracking_mode' => 'reps',
                    'sets' => [[
                        'set_number' => 1,
                        'reps' => 15,
                        'weight' => 50,
                        'is_completed' => true,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.completion_summary.progression_recommendations.0.status', 'applied')
            ->assertJsonPath('data.completion_summary.progression_recommendations.0.algorithm_version', 1)
            ->assertJsonPath('data.completion_summary.exercises.0.performed.best_estimated_one_rep_max', null);

        $this->assertDatabaseHas('workout_plan_exercises', [
            'id' => $plan->days->first()->exercises->first()->id,
            'target_weight' => 52.5,
        ]);
        $this->assertDatabaseHas('personal_records', [
            'member_id' => $member->id,
            'exercise_id' => $exercise->id,
            'best_estimated_one_rep_max' => null,
        ]);
    }

    public function test_single_exercise_group_is_rejected(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$exercise] = $this->makePlanExercises($gym, $branch, $trainer);

        $this->expectException(ValidationException::class);
        app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => 'Invalid singleton group',
            'duration_weeks' => 1,
            'days' => [[
                'day_number' => 1,
                'exercises' => [[
                    'exercise_id' => $exercise->id,
                    'sets' => 3,
                    'group_key' => 'A',
                    'group_type' => 'superset',
                    'group_order' => 1,
                ]],
            ]],
        ]);
    }

    public function test_group_structure_persists_through_template_assignment_and_plan_duplication(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch] = $this->makeGymContext();
        $trainer = $this->makeTrainer($gym, $branch);
        $member = $this->makeMember($gym, $branch, $trainer->id);
        [$firstExercise, $secondExercise] = $this->makePlanExercises($gym, $branch, $trainer);
        $service = app(WorkoutPlanService::class);
        $template = $service->createTemplateFromPayload($trainer, [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Circuit template',
            'duration_weeks' => 3,
            'progression_policy' => 'linear_load',
            'progression_config' => ['load_increment_kg' => 1.25],
            'days' => [[
                'day_number' => 1,
                'exercises' => collect([$firstExercise, $secondExercise])->values()->map(
                    fn (Exercise $exercise, int $index): array => [
                        'exercise_id' => $exercise->id,
                        'sort_order' => $index + 1,
                        'sets' => 4,
                        'reps' => '12',
                        'group_key' => 'C1',
                        'group_type' => 'circuit',
                        'group_order' => $index + 1,
                        'group_rounds' => 4,
                        'transition_seconds' => 20,
                        'rest_after' => 'group',
                    ],
                )->all(),
            ]],
        ]);

        $assigned = $service->assignTemplateToMembers($trainer, $template, [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_ids' => [$member->id],
        ])->firstOrFail();
        $copy = $service->duplicatePlanForMember($member, $assigned, 'Circuit copy');

        foreach ([$template, $assigned, $copy] as $workout) {
            $exercises = $workout->days->first()->exercises;
            $this->assertSame(['C1', 'C1'], $exercises->pluck('group_key')->all());
            $this->assertSame(['circuit', 'circuit'], $exercises->pluck('group_type')->all());
            $this->assertSame([1, 2], $exercises->pluck('group_order')->all());
            $this->assertSame([4, 4], $exercises->pluck('group_rounds')->all());
            $this->assertSame([20, 20], $exercises->pluck('transition_seconds')->all());
            $this->assertSame(['linear_load', 'linear_load'], $exercises->pluck('progression_policy')->all());
            $this->assertSame([1.25, 1.25], $exercises->map(fn ($exercise) => (float) $exercise->progression_config['load_increment_kg'])->all());
        }
    }

    /**
     * @return array{0: Exercise, 1: Exercise}
     */
    private function makePlanExercises(Gym $gym, Branch $branch, User $trainer): array
    {
        $exercise = fn (string $name, string $muscleGroup): Exercise => Exercise::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $trainer->id,
            'name' => $name,
            'muscle_group' => $muscleGroup,
            'is_global' => false,
            'status' => 'approved',
            'is_active' => true,
        ]);

        return [
            $exercise('Bench Press', 'chest'),
            $exercise('Lat Pulldown', 'back'),
        ];
    }

    private function makeMemberPlan(
        User $member,
        Exercise $pushExercise,
        Exercise $pullExercise,
        string $name = 'Push Pull Plan',
    ): WorkoutPlan {
        return app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => $name,
            'duration_weeks' => 4,
            'days' => [
                [
                    'day_number' => 1,
                    'label' => 'Push',
                    'focus' => 'Chest and shoulders',
                    'exercises' => [[
                        'exercise_id' => $pushExercise->id,
                        'sets' => 3,
                    ]],
                ],
                [
                    'day_number' => 2,
                    'label' => 'Pull',
                    'focus' => 'Back and biceps',
                    'exercises' => [[
                        'exercise_id' => $pullExercise->id,
                        'sets' => 3,
                    ]],
                ],
            ],
        ]);
    }

    private function makeTrainer(Gym $gym, Branch $branch, string $email = 'trainer@example.com'): User
    {
        $trainer = User::factory()->create([
            'email' => $email,
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['strength'],
            'experience_years' => 4,
            'certifications' => ['ACE'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        return $trainer;
    }

    private function makeMember(Gym $gym, Branch $branch, int $assignedTrainerId): User
    {
        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);
        $member->gyms()->attach($gym->id);
        $member->branches()->attach($branch->id);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $assignedTrainerId,
            'fitness_goal' => 'Strength',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return $member;
    }

    /**
     * @return array{0: Gym, 1: Branch}
     */
    private function makeGymContext(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Workout Scope Gym',
            'slug' => 'workout-scope-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Workout Scope Branch',
            'slug' => 'workout-scope-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branch];
    }
}
