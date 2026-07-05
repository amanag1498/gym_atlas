<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFacilityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_manage_facilities_from_web_panel(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-facilities@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'platform-facilities@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.facilities.index'))->assertOk()->assertSee('Facilities Master');
        $this->get(route('web.admin.facilities.create'))->assertOk()->assertSee('Create Facility');

        $response = $this->post(route('web.admin.facilities.store'), [
            'name' => 'Cryotherapy',
            'icon' => 'snowflake',
            'status' => 'active',
        ]);

        $facility = Facility::query()->where('name', 'Cryotherapy')->firstOrFail();

        $response
            ->assertRedirect(route('web.admin.facilities.edit', $facility))
            ->assertSessionHas('status');

        $this->assertSame('cryotherapy', $facility->slug);
        $this->assertTrue((bool) $facility->is_active);

        $this->get(route('web.admin.facilities.edit', $facility))
            ->assertOk()
            ->assertSee('Cryotherapy');

        $this->put(route('web.admin.facilities.update', $facility), [
            'name' => 'Cryo Recovery',
            'icon' => 'snowflake',
            'status' => 'inactive',
        ])->assertRedirect(route('web.admin.facilities.edit', $facility));

        $facility->refresh();
        $this->assertSame('Cryo Recovery', $facility->name);
        $this->assertSame('inactive', $facility->status);
        $this->assertFalse((bool) $facility->is_active);

        $this->post(route('web.admin.facilities.toggle-status', $facility))
            ->assertRedirect();

        $facility->refresh();
        $this->assertTrue((bool) $facility->is_active);
        $this->assertSame('active', $facility->status);

        $this->get(route('web.admin.gyms.create'))
            ->assertOk()
            ->assertSee('Cryo Recovery');
    }

    public function test_used_facility_cannot_be_deleted_unsafely_and_api_crud_works(): void
    {
        $admin = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $facility = Facility::query()->create([
            'name' => 'Zumba Studio',
            'slug' => 'zumba-studio',
            'icon' => 'music',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Pulse Arena',
            'slug' => 'pulse-arena',
            'city' => 'Delhi',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'pulse-arena-main',
            'city' => 'Delhi',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->facilities()->sync([$facility->id]);
        $branch->facilities()->sync([$facility->id]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->delete(route('web.admin.facilities.destroy', $facility))
            ->assertSessionHasErrors('facility');

        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);

        $apiCreate = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/facilities', [
            'name' => 'Ice Bath',
            'icon' => 'droplet',
            'status' => 'active',
        ]);

        $apiCreate
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ice Bath');

        $apiFacilityId = (int) $apiCreate->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/facilities?search=Ice&status=active')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/facilities/'.$apiFacilityId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Ice Bath');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/platform-admin/facilities/'.$apiFacilityId, [
                'name' => 'Ice Bath Pro',
                'icon' => 'droplet',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/facilities/'.$apiFacilityId.'/toggle-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/platform-admin/facilities/'.$facility->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/platform-admin/facilities/'.$apiFacilityId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('facilities', ['id' => $apiFacilityId]);
    }
}
