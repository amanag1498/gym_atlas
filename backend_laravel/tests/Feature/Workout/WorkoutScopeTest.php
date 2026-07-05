<?php

namespace Tests\Feature\Workout;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WorkoutSession;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutScopeTest extends TestCase
{
    use RefreshDatabase;

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
