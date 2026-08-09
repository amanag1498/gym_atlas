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
use App\Models\WorkoutSession;
use App\Services\Workout\WorkoutPlanService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
