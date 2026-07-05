<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberDailyStep;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MemberStepTrackingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_a_new_step_row(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member, $gym] = $this->makeGymMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 8245,
                'goalSteps' => 12000,
                'distanceMeters' => 5400,
                'caloriesEstimated' => 320,
                'source' => 'health_connect',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 8245)
            ->assertJsonPath('data.goalSteps', 12000)
            ->assertJsonPath('data.distanceMeters', 5400)
            ->assertJsonPath('data.caloriesEstimated', 320)
            ->assertJsonPath('data.source', 'health_connect');

        $this->assertDatabaseHas('member_daily_steps', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->startOfDay()->toDateTimeString(),
            'steps' => 8245,
            'goal_steps' => 12000,
        ]);
    }

    public function test_member_can_update_same_day_with_higher_steps(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member, $gym] = $this->makeGymMember();

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->toDateString(),
            'steps' => 6200,
            'goal_steps' => 10000,
            'distance_meters' => 3000,
            'calories_estimated' => 200,
            'source' => 'health_connect',
            'synced_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 9100,
                'goalSteps' => 11000,
                'distanceMeters' => 6200,
                'caloriesEstimated' => 380,
                'source' => 'healthkit',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 9100)
            ->assertJsonPath('data.goalSteps', 11000);

        $this->assertDatabaseHas('member_daily_steps', [
            'user_id' => $member->id,
            'step_date' => now()->startOfDay()->toDateTimeString(),
            'steps' => 9100,
            'goal_steps' => 11000,
            'distance_meters' => 6200,
            'calories_estimated' => 380,
            'source' => 'healthkit',
        ]);
    }

    public function test_same_day_sync_does_not_reduce_steps_with_stale_lower_value(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member, $gym] = $this->makeGymMember();

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->toDateString(),
            'steps' => 12000,
            'goal_steps' => 10000,
            'distance_meters' => 8100,
            'calories_estimated' => 450,
            'source' => 'health_connect',
            'synced_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 7400,
                'goalSteps' => 9500,
                'distanceMeters' => 5000,
                'caloriesEstimated' => 290,
                'source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 12000)
            ->assertJsonPath('data.goalSteps', 9500)
            ->assertJsonPath('data.distanceMeters', 5000)
            ->assertJsonPath('data.caloriesEstimated', 290)
            ->assertJsonPath('data.source', 'manual');

        $this->assertDatabaseHas('member_daily_steps', [
            'user_id' => $member->id,
            'step_date' => now()->startOfDay()->toDateTimeString(),
            'steps' => 12000,
            'goal_steps' => 9500,
            'distance_meters' => 5000,
            'calories_estimated' => 290,
            'source' => 'manual',
        ]);
    }

    public function test_future_date_is_rejected(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member] = $this->makeGymMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->addDay()->toDateString(),
                'steps' => 5000,
                'goalSteps' => 10000,
                'distanceMeters' => 3000,
                'caloriesEstimated' => 200,
                'source' => 'manual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_unrealistic_steps_above_limit_are_rejected(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member] = $this->makeGymMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 100001,
                'goalSteps' => 10000,
                'distanceMeters' => 3000,
                'caloriesEstimated' => 200,
                'source' => 'manual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['steps']);
    }

    public function test_android_sensor_source_is_accepted_for_independent_members(): void
    {
        $this->seed(PermissionSeeder::class);
        $member = $this->makeIndependentMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 4321,
                'goalSteps' => 9000,
                'distanceMeters' => 3370,
                'caloriesEstimated' => 173,
                'source' => 'android_sensor',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 4321)
            ->assertJsonPath('data.source', 'android_sensor');

        $this->assertDatabaseHas('member_daily_steps', [
            'user_id' => $member->id,
            'gym_id' => null,
            'steps' => 4321,
            'source' => 'android_sensor',
        ]);
    }

    public function test_member_context_includes_dashboard_steps_object(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member, $gym] = $this->makeGymMember();

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->toDateString(),
            'steps' => 8400,
            'goal_steps' => 10000,
            'distance_meters' => 6100,
            'calories_estimated' => 345,
            'source' => 'health_connect',
            'synced_at' => now()->subMinutes(3),
        ]);

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->subDay()->toDateString(),
            'steps' => 9200,
            'goal_steps' => 10000,
            'distance_meters' => 6500,
            'calories_estimated' => 360,
            'source' => 'health_connect',
            'synced_at' => now()->subDay(),
        ]);

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->subDays(2)->toDateString(),
            'steps' => 10100,
            'goal_steps' => 10000,
            'distance_meters' => 7000,
            'calories_estimated' => 410,
            'source' => 'health_connect',
            'synced_at' => now()->subDays(2),
        ]);

        Cache::flush();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.steps.today', 8400)
            ->assertJsonPath('data.steps.goal', 10000)
            ->assertJsonPath('data.steps.progressPercent', 84)
            ->assertJsonPath('data.steps.distanceKm', 6.1)
            ->assertJsonPath('data.steps.calories', 345)
            ->assertJsonPath('data.steps.streakDays', 3);
    }

    public function test_today_endpoint_returns_default_payload_when_missing(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member] = $this->makeGymMember();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/steps/today')
            ->assertOk()
            ->assertJsonPath('data.steps', 0)
            ->assertJsonPath('data.goalSteps', 10000)
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.lastSyncedAt', null);
    }

    public function test_summary_endpoint_returns_requested_daily_rows(): void
    {
        $this->seed(PermissionSeeder::class);
        [$member, $gym] = $this->makeGymMember();

        MemberDailyStep::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'step_date' => now()->toDateString(),
            'steps' => 7000,
            'goal_steps' => 10000,
            'distance_meters' => 5000,
            'calories_estimated' => 280,
            'source' => 'health_connect',
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/steps/summary?range=7d')
            ->assertOk();

        $this->assertCount(7, $response->json('data'));
        $this->assertSame(7000, data_get($response->json('data'), '6.steps'));
    }

    public function test_independent_member_can_sync_steps_without_gym_assignment(): void
    {
        $this->seed(PermissionSeeder::class);
        $member = $this->makeIndependentMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/steps/sync', [
                'date' => now()->toDateString(),
                'steps' => 5600,
                'goalSteps' => 10000,
                'distanceMeters' => 4100,
                'caloriesEstimated' => 220,
                'source' => 'health_connect',
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 5600)
            ->assertJsonPath('data.goalSteps', 10000);

        $this->assertDatabaseHas('member_daily_steps', [
            'user_id' => $member->id,
            'gym_id' => null,
            'step_date' => now()->startOfDay()->toDateTimeString(),
            'steps' => 5600,
            'goal_steps' => 10000,
        ]);
    }

    /**
     * @return array{0: User, 1: Gym, 2: Branch}
     */
    private function makeGymMember(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Step Sync Gym',
            'slug' => 'step-sync-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Step Sync Branch',
            'slug' => 'step-sync-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

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
            'fitness_goal' => 'General fitness',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return [$member, $gym, $branch];
    }

    private function makeIndependentMember(): User
    {
        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => null,
            'branch_id' => null,
            'fitness_goal' => 'General fitness',
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);

        return $member;
    }
}
