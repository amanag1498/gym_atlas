<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformListingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_manage_public_featured_and_promoted_listing_tabs(): void
    {
        $admin = User::factory()->create([
            'email' => 'listing-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $publicGym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Discovery Gym',
            'slug' => 'discovery-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'show_pricing' => true,
            'contact_visible' => true,
            'is_verified' => true,
        ]);

        $inactiveGym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Inactive Public Gym',
            'slug' => 'inactive-public-gym',
            'city' => 'Mumbai',
            'status' => 'inactive',
            'approval_status' => 'approved',
            'is_active' => false,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);

        $this->post('/admin/login', [
            'email' => 'listing-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.listings.index'))
            ->assertOk()
            ->assertSee('Public Listings')
            ->assertSee('Discovery Gym');

        $this->get(route('web.admin.featured-gyms.index'))->assertOk()->assertSee('Featured Gyms');
        $this->get(route('web.admin.promoted-gyms.index'))->assertOk()->assertSee('Promoted Gyms');

        $this->getJson('/api/public/discovery/gyms')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'discovery-gym'])
            ->assertJsonMissing(['slug' => 'inactive-public-gym']);

        $this->post(route('web.admin.gyms.hide-listing', $publicGym))
            ->assertRedirect();

        $publicGym->refresh();
        $this->assertFalse((bool) $publicGym->public_listing_enabled);

        $this->getJson('/api/public/discovery/gyms')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'discovery-gym']);

        $this->post(route('web.admin.gyms.show-listing', $publicGym))
            ->assertRedirect();

        $publicGym->refresh();
        $this->assertTrue((bool) $publicGym->public_listing_enabled);

        $this->getJson('/api/public/discovery/gyms')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'discovery-gym']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/gyms/'.$publicGym->id.'/feature')
            ->assertOk()
            ->assertJsonPath('data.is_featured', true);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/gyms/'.$publicGym->id.'/promote')
            ->assertOk()
            ->assertJsonPath('data.is_promoted', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/listings?city=Mumbai&verified=1')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/featured-gyms?city=Mumbai')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'discovery-gym');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/promoted-gyms?city=Mumbai')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'discovery-gym');

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'platform.gym.listing_flags.updated',
            'gym_id' => $publicGym->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'web.platform.gym.public_listing.visibility_updated',
            'gym_id' => $publicGym->id,
        ]);
    }
}
