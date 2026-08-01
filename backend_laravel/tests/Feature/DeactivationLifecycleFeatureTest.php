<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivationLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_deactivated_gym_is_removed_from_live_owner_and_member_scopes_without_deleting_history(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin, 'lifecycle-admin@example.com');
        $owner = $this->user(RoleName::GymOwner, 'lifecycle-owner@example.com');
        $member = $this->user(RoleName::Member, 'lifecycle-member@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Lifecycle Gym',
            'slug' => 'lifecycle-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Lifecycle Branch',
            'slug' => 'lifecycle-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $owner->gyms()->attach($gym->id, ['status' => 'active', 'role_name' => RoleName::GymOwner->value]);
        $owner->branches()->attach($branch->id);
        $member->gyms()->attach($gym->id, ['status' => 'active', 'role_name' => RoleName::Member->value]);
        $member->branches()->attach($branch->id);
        $profile = MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/gyms/'.$gym->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/context')
            ->assertOk()
            ->assertJsonCount(0, 'data.gyms');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonCount(0, 'data.gym_relationships')
            ->assertJsonPath('data.selected_gym_id', null);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/profile', ['X-Gym-Id' => (string) $gym->id])
            ->assertForbidden();

        $this->assertDatabaseHas('member_profiles', [
            'id' => $profile->id,
            'gym_id' => $gym->id,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_platform_user_deactivation_revokes_trainer_assignments_and_existing_api_access(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin, 'user-lifecycle-admin@example.com');
        $trainer = $this->user(RoleName::Trainer, 'user-lifecycle-trainer@example.com');
        $member = $this->user(RoleName::Member, 'user-lifecycle-member@example.com');
        $gym = Gym::query()->create([
            'name' => 'User Lifecycle Gym',
            'slug' => 'user-lifecycle-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'User Lifecycle Branch',
            'slug' => 'user-lifecycle-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'assigned_trainer_id' => $trainer->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/users/'.$trainer->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'assigned_trainer_user_id' => null,
            'assigned_trainer_id' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => 'trainer_assignment_removed',
        ]);

        $this->actingAs($trainer->refresh(), 'sanctum')
            ->getJson('/api/public/me')
            ->assertForbidden();
    }

    private function user(RoleName $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'active_role' => $role->value,
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
