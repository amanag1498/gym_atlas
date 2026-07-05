<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_web_dashboard_loads_with_empty_database(): void
    {
        $admin = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->actingAs($admin)
            ->get(route('web.admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Dashboard')
            ->assertSee('No pending gym approvals')
            ->assertSee('No gyms added yet');
    }

    public function test_platform_admin_dashboard_stats_and_sections_are_correct(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $ownerA = User::factory()->create(['is_active' => true, 'name' => 'Owner Alpha']);
        $ownerA->assignRole(RoleName::GymOwner->value);
        $ownerB = User::factory()->create(['is_active' => true, 'name' => 'Owner Beta']);
        $ownerB->assignRole(RoleName::GymOwner->value);

        $gymPending = Gym::query()->create([
            'owner_user_id' => $ownerA->id,
            'name' => 'Pending Fitness',
            'slug' => 'pending-fitness',
            'city' => 'Mumbai',
            'status' => 'pending',
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        $gymActive = Gym::query()->create([
            'owner_user_id' => $ownerB->id,
            'name' => 'Active Arena',
            'slug' => 'active-arena',
            'city' => 'Pune',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'is_verified' => true,
            'is_featured' => true,
            'is_promoted' => true,
        ]);

        $pendingBranch = Branch::query()->create([
            'gym_id' => $gymPending->id,
            'name' => 'Pending Branch',
            'slug' => 'pending-branch',
            'city' => 'Mumbai',
            'status' => 'active',
            'is_active' => true,
        ]);
        $activeBranch = Branch::query()->create([
            'gym_id' => $gymActive->id,
            'name' => 'Active Branch',
            'slug' => 'active-branch',
            'city' => 'Pune',
            'status' => 'active',
            'is_active' => true,
        ]);

        $memberUser = User::factory()->create(['is_active' => true]);
        $memberUser->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $memberUser->id,
            'gym_id' => $gymActive->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainerUser = User::factory()->create(['is_active' => true]);
        $trainerUser->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $trainerUser->id,
            'gym_id' => $gymActive->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gymActive->id,
            'branch_id' => $activeBranch->id,
            'name' => 'Rahul',
            'preferred_date' => now()->toDateString(),
            'preferred_time' => '08:00',
            'status' => 'pending',
        ]);

        $facility = Facility::query()->create([
            'name' => 'Steam',
            'slug' => 'steam',
            'status' => 'active',
            'is_active' => true,
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'gym_id' => $gymActive->id,
            'event' => 'platform_admin_created_gym',
            'action' => 'create',
            'subject_type' => Gym::class,
            'subject_id' => $gymActive->id,
            'new_values' => ['name' => $gymActive->name],
            'occurred_at' => now()->subMinutes(4),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'gym_id' => $gymActive->id,
            'event' => 'web.platform.gym.approval.updated',
            'action' => 'update',
            'subject_type' => Gym::class,
            'subject_id' => $gymActive->id,
            'new_values' => ['approval_status' => 'approved'],
            'occurred_at' => now()->subMinutes(3),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'gym_id' => $gymActive->id,
            'event' => 'web.platform.gym.featured.updated',
            'action' => 'update',
            'subject_type' => Gym::class,
            'subject_id' => $gymActive->id,
            'new_values' => ['is_featured' => true],
            'occurred_at' => now()->subMinutes(2),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'event' => 'web.platform.facility.updated',
            'action' => 'update',
            'subject_type' => Facility::class,
            'subject_id' => $facility->id,
            'new_values' => ['name' => $facility->name],
            'occurred_at' => now()->subMinute(),
        ]);

        $webResponse = $this->actingAs($admin)->get(route('web.admin.dashboard'));

        $webResponse
            ->assertOk()
            ->assertSee('Pending Fitness')
            ->assertSee('Active Arena')
            ->assertSee('Gym approval updated')
            ->assertSee('Facility updated');

        $apiResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/platform-admin/dashboard');

        $apiResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Platform dashboard loaded successfully.')
            ->assertJsonPath('data.stats.total_gyms', 2)
            ->assertJsonPath('data.stats.active_gyms', 1)
            ->assertJsonPath('data.stats.pending_gym_approvals', 1)
            ->assertJsonPath('data.stats.inactive_gyms', 1)
            ->assertJsonPath('data.stats.total_members', 1)
            ->assertJsonPath('data.stats.total_trainers', 1)
            ->assertJsonPath('data.stats.total_branches', 2)
            ->assertJsonPath('data.stats.total_trial_requests', 1)
            ->assertJsonPath('data.stats.featured_gyms', 1)
            ->assertJsonPath('data.stats.promoted_gyms', 1)
            ->assertJsonPath('data.pending_gym_approvals.0.name', 'Pending Fitness')
            ->assertJsonPath('data.pending_gym_approvals.0.owner_name', 'Owner Alpha')
            ->assertJsonPath('data.recently_added_gyms.0.name', 'Active Arena');
    }

    public function test_non_platform_admin_cannot_access_platform_dashboard_routes(): void
    {
        $gymOwner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $gymOwner->assignRole(RoleName::GymOwner->value);

        $this->actingAs($gymOwner)
            ->get(route('web.admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($gymOwner, 'sanctum')
            ->getJson('/api/platform-admin/dashboard')
            ->assertForbidden();
    }
}
