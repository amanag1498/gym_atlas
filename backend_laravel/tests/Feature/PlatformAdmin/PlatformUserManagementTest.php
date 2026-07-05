<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymStaff;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_filter_users_and_view_user_detail(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-users@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $gymOwnerPayload = [
            'name' => 'Owner Search',
            'email' => 'owner-search@example.com',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ];
        if (Schema::hasColumn('users', 'phone')) {
            $gymOwnerPayload['phone'] = '9999999999';
        }
        $gymOwner = User::factory()->create($gymOwnerPayload);
        $gymOwner->assignRole(RoleName::GymOwner->value);

        $trainer = User::factory()->create([
            'name' => 'Trainer Search',
            'email' => 'trainer-search@example.com',
            'is_active' => false,
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $gymOwner->id,
            'name' => 'Owner Gym',
            'slug' => 'owner-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'city' => 'Mumbai',
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

        $member = User::factory()->create([
            'name' => 'Member Search',
            'email' => 'member-search@example.com',
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        GymStaff::query()->create([
            'gym_id' => $gym->id,
            'user_id' => $gymOwner->id,
            'branch_id' => $branch->id,
            'role_name' => 'gym_owner',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'platform-users@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.users.index', ['search' => 'owner-search@example.com', 'role' => 'gym_owner', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Owner Search')
            ->assertDontSee('Trainer Search');

        $this->get(route('web.admin.users.show', $gymOwner))
            ->assertOk()
            ->assertSee('Owner Search')
            ->assertSee('Owned Gyms')
            ->assertSee('Owner Gym');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/users?search=trainer-search@example.com&role=trainer&status=inactive')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.email', 'trainer-search@example.com');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/users/'.$gymOwner->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'owner-search@example.com')
            ->assertJsonPath('data.owned_gyms.0.name', 'Owner Gym');
    }

    public function test_platform_admin_can_activate_deactivate_users_but_not_self(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-self@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->post('/admin/login', [
            'email' => 'platform-self@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->post(route('web.admin.users.deactivate', $member))
            ->assertRedirect();

        $member->refresh();
        $this->assertFalse((bool) $member->is_active);

        $this->post(route('web.admin.users.activate', $member))
            ->assertRedirect();

        $member->refresh();
        $this->assertTrue((bool) $member->is_active);

        $this->post(route('web.admin.users.deactivate', $admin))
            ->assertSessionHasErrors('user');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/users/'.$member->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/users/'.$member->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/users/'.$admin->id.'/deactivate')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
