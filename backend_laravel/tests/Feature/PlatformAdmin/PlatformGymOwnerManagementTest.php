<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformGymOwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_create_gym_owner_from_web_and_owner_appears_in_add_gym_dropdown(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-gym-owner-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'platform-gym-owner-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $response = $this->post(route('web.admin.gym-owners.store'), [
            'name' => 'Owner One',
            'email' => 'owner.one@example.com',
        ]);

        $owner = User::query()->where('email', 'owner.one@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('web.admin.gym-owners.show', $owner))
            ->assertSessionHas('status');

        $this->assertTrue($owner->hasRole(RoleName::GymOwner->value));
        $this->assertSame(RoleName::GymOwner->value, $owner->active_role);
        $this->assertTrue((bool) $owner->is_active);

        $this->get(route('web.admin.gyms.create'))
            ->assertOk()
            ->assertSee('owner.one@example.com');
    }

    public function test_platform_admin_can_manage_gym_owner_activation_safely(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create([
            'email' => 'owner.safe@example.com',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Safe Active Gym',
            'slug' => 'safe-active-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->post(route('web.admin.gym-owners.deactivate', $owner))
            ->assertSessionHasErrors('owner');

        $owner->refresh();
        $this->assertTrue((bool) $owner->is_active);

        $this->post(route('web.admin.gym-owners.deactivate', $owner), [
            'confirm_orphan_active_gyms' => '1',
        ])->assertRedirect(route('web.admin.gym-owners.show', $owner));

        $owner->refresh();
        $this->assertFalse((bool) $owner->is_active);

        $this->post(route('web.admin.gym-owners.activate', $owner))
            ->assertRedirect(route('web.admin.gym-owners.show', $owner));

        $owner->refresh();
        $this->assertTrue((bool) $owner->is_active);
    }

    public function test_platform_admin_can_open_full_gym_owner_dashboard_from_web(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create([
            'email' => 'owner.dashboard@example.com',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Preview Gym',
            'slug' => 'preview-gym',
            'city' => 'Bengaluru',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gym-owners.dashboard', $owner))
            ->assertRedirect(route('web.gym.dashboard', ['gym' => $gym->id]))
            ->assertSessionHas('web_panel.gym_id', $gym->id)
            ->assertSessionHas('web_panel.platform_admin_impersonator_id', $admin->id)
            ->assertSessionMissing('web_panel.branch_id');

        $this->get(route('web.gym.dashboard', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Gym Dashboard')
            ->assertSee('Viewing as gym owner')
            ->assertSee('Back to Platform Admin');

        $this->post(route('web.admin.impersonation.stop'))
            ->assertRedirect(route('web.admin.gym-owners.show', $owner))
            ->assertSessionMissing('web_panel.platform_admin_impersonator_id');

        $this->get(route('web.admin.dashboard'))->assertOk();
    }

    public function test_platform_admin_is_blocked_from_previewing_unowned_gym_dashboard(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $otherOwner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
        ]);
        $otherOwner->assignRole(RoleName::GymOwner->value);

        $otherGym = Gym::query()->create([
            'owner_user_id' => $otherOwner->id,
            'name' => 'Other Gym',
            'slug' => 'other-gym',
            'city' => 'Delhi',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gym-owners.gyms.dashboard', ['user' => $owner, 'gym' => $otherGym]))
            ->assertNotFound();
    }

    public function test_platform_admin_api_can_list_create_update_and_toggle_gym_owners(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/gym-owners', [
            'name' => 'Api Owner',
            'email' => 'api.owner@example.com',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Gym Owner created successfully.')
            ->assertJsonPath('data.owner.email', 'api.owner@example.com');

        $ownerId = (int) $createResponse->json('data.owner.id');
        $owner = User::query()->findOrFail($ownerId);
        $this->assertTrue($owner->hasRole(RoleName::GymOwner->value));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/gym-owners?search=api.owner@example.com')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.email', 'api.owner@example.com');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/platform-admin/gym-owners/'.$owner->id, [
                'name' => 'Api Owner Updated',
                'email' => 'api.owner@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Api Owner Updated');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/gym-owners/'.$owner->id.'/deactivate', [
                'confirm_orphan_active_gyms' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/gym-owners/'.$owner->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }
}
