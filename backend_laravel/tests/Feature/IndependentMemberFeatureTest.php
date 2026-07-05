<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\FitnessGoal;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutSession;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndependentMemberFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_member_context_and_gym_only_features_degrade_gracefully(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.user_state', 'independent_user')
            ->assertJsonPath('data.current_membership', null)
            ->assertJsonPath('data.attendance_status.enabled', false)
            ->assertJsonPath('data.steps.today', 0)
            ->assertJsonPath('data.steps.goal', 10000)
            ->assertJsonPath('data.steps.progressPercent', 0)
            ->assertJsonPath('data.steps.distanceKm', 0)
            ->assertJsonPath('data.steps.calories', 0)
            ->assertJsonPath('data.steps.streakDays', 0)
            ->assertJsonPath('data.steps.lastSyncedAt', null);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/qr-code')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/trainer')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/history')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/progress/summary')
            ->assertOk()
            ->assertJsonPath('data.latest_weight_log', null)
            ->assertJsonPath('data.latest_body_measurement', null)
            ->assertJsonCount(0, 'data.recent_progress_photos');
    }

    public function test_independent_member_can_start_workout_and_log_weight(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-sessions/start', [
                'session_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.gym_id', null)
            ->assertJsonPath('data.branch_id', null);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/progress/weight-logs', [
                'log_date' => now()->toDateString(),
                'weight_kg' => 75.2,
            ])
            ->assertCreated();

        $this->assertDatabaseHas(WorkoutSession::class, [
            'member_id' => $member->id,
            'gym_id' => null,
            'branch_id' => null,
        ]);

        $this->assertDatabaseHas(WeightLog::class, [
            'member_id' => $member->id,
            'gym_id' => null,
            'branch_id' => null,
        ]);
    }

    public function test_independent_member_can_advance_onboarding_without_creating_gym_bound_profile(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'member_onboarding_step' => 1,
            'member_onboarding_completed' => false,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/member/profile', [
                'member_onboarding_step' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.member_onboarding_step', 2)
            ->assertJsonPath('data.current_gym', null);

        $this->assertDatabaseMissing('member_profiles', [
            'user_id' => $member->id,
        ]);
    }

    public function test_independent_member_can_save_multiple_profile_goals_without_gym_assignment(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'member_onboarding_step' => 2,
            'member_onboarding_completed' => false,
        ]);
        $member->assignRole(RoleName::Member->value);
        $goals = FitnessGoal::query()->ordered()->take(2)->get();

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/member/profile', [
                'fitness_goal_ids' => $goals->pluck('id')->all(),
                'member_onboarding_step' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.member_onboarding_step', 3)
            ->assertJsonPath('data.fitness_goal', $goals->pluck('name')->implode(', '))
            ->assertJsonCount(2, 'data.fitness_goals')
            ->assertJsonPath('data.current_gym', null);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => null,
            'fitness_goal' => $goals->pluck('name')->implode(', '),
        ]);
        $this->assertDatabaseCount('fitness_goal_member_profile', 2);
    }

    public function test_member_profile_update_targets_current_gym_profile_when_user_has_independent_profile(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'name' => 'Current Gym',
            'slug' => 'current-gym',
            'status' => 'active',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'current-gym-main',
            'status' => 'active',
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 0,
            'status' => 'active',
        ]);

        $independentProfile = $member->memberProfiles()->create([
            'gym_id' => null,
            'fitness_goal' => 'Lose Fat',
            'weight_kg' => 68,
            'status' => 'active',
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);
        $gymProfile = $member->memberProfiles()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'fitness_goal' => 'Lose Fat',
            'weight_kg' => 68,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2500,
            'final_payable_amount' => 2500,
            'amount_paid' => 2500,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $goals = FitnessGoal::query()->ordered()->take(2)->get();

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/member/profile', [
                'weight_kg' => 69,
                'fitness_goal_ids' => $goals->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 69)
            ->assertJsonPath('data.current_gym.id', $gym->id)
            ->assertJsonCount(2, 'data.fitness_goals');

        $this->assertDatabaseHas('member_profiles', [
            'id' => $gymProfile->id,
            'weight_kg' => 69,
            'fitness_goal' => $goals->pluck('name')->implode(', '),
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'id' => $independentProfile->id,
            'weight_kg' => 68,
            'fitness_goal' => 'Lose Fat',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/profile')
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 69)
            ->assertJsonPath('data.current_gym.id', $gym->id)
            ->assertJsonCount(2, 'data.fitness_goals');
    }

    public function test_independent_member_can_create_trial_request_and_save_public_gym(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        [$gym, $branch] = $this->makePublicGym();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/public/trial-requests', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'preferred_date' => now()->addDay()->toDateString(),
                'preferred_time' => '18:00',
            ])
            ->assertCreated();

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/favorite-gyms/{$gym->id}")
            ->assertCreated()
            ->assertJsonPath('data.saved', true);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.user_state', 'trial_user')
            ->assertJsonPath('data.steps.today', 0)
            ->assertJsonPath('data.steps.goal', 10000);
    }

    public function test_independent_member_trial_request_defaults_to_first_active_branch_when_not_provided(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Branch Default Trial Gym',
            'slug' => 'branch-default-trial-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Alpha Branch',
            'slug' => 'alpha-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Zulu Branch',
            'slug' => 'zulu-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/trial-requests', [
                'gym_id' => $gym->id,
                'preferred_date' => now()->addDay()->toDateString(),
                'preferred_time' => '18:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branchA->id);
    }

    /**
     * @return array{0: Gym, 1: Branch}
     */
    private function makePublicGym(): array
    {
        $owner = User::factory()->create();

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Independent Trial Gym',
            'slug' => 'independent-trial-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branch];
    }
}
