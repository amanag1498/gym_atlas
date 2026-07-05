<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformGymManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_platform_admin_can_create_gym_from_web_panel_with_new_owner_and_default_branch(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $facility = Facility::query()->create([
            'name' => 'Steam Room',
            'slug' => 'steam-room',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'platform-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gyms.create'))->assertOk()->assertSee('Create Gym');

        $response = $this->post(route('web.admin.gyms.store'), [
            'owner_mode' => 'new',
            'owner_name' => 'New Gym Owner',
            'owner_email' => 'new.owner@gmail.com',
            'name' => 'Atlas Fitness',
            'description' => 'Premium gym network.',
            'address' => '42 Park Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'weekly_off_text' => 'sunday',
            'facility_ids' => [$facility->id],
            'public_listing_enabled' => '1',
            'show_pricing' => '1',
            'trial_available' => '1',
            'contact_visible' => '1',
            'status' => 'active',
            'create_default_branch' => '1',
            'branch_same_as_gym' => '1',
            'branch_name' => 'Main Branch',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover_image' => UploadedFile::fake()->image('cover.png'),
        ]);

        $gym = Gym::query()->where('slug', 'atlas-fitness')->firstOrFail();
        $owner = User::query()->where('email', 'new.owner@gmail.com')->firstOrFail();

        $response
            ->assertRedirect(route('web.admin.gyms.show', $gym))
            ->assertSessionHas('status');

        $this->assertSame($owner->id, $gym->owner_user_id);
        $this->assertTrue($owner->hasRole(RoleName::GymOwner->value));
        $this->assertSame('approved', $gym->approval_status);
        $this->assertTrue((bool) $gym->is_active);
        $this->assertNotNull($gym->approved_by_user_id);
        $this->assertNotNull($gym->approved_at);
        $this->assertDatabaseHas('branches', [
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
        ]);
        $this->assertTrue($gym->facilities()->whereKey($facility->id)->exists());
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'platform_admin_created_gym',
            'gym_id' => $gym->id,
        ]);
    }

    public function test_platform_admin_can_create_gym_from_web_panel_with_existing_owner(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-admin-existing@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create([
            'email' => 'existing.owner@example.com',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'platform-admin-existing@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $response = $this->post(route('web.admin.gyms.store'), [
            'owner_mode' => 'existing',
            'owner_user_id' => $owner->id,
            'name' => 'Existing Owner Gym',
            'city' => 'Jaipur',
            'status' => 'pending',
            'create_default_branch' => '1',
            'branch_name' => 'Main Branch',
            'branch_same_as_gym' => '1',
            'show_pricing' => '1',
            'contact_visible' => '1',
        ]);

        $gym = Gym::query()->where('slug', 'existing-owner-gym')->firstOrFail();
        $owner->refresh();

        $response->assertRedirect(route('web.admin.gyms.show', $gym));
        $this->assertSame($owner->id, $gym->owner_user_id);
        $this->assertTrue($owner->hasRole(RoleName::GymOwner->value));
        $this->assertDatabaseHas('branches', [
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
        ]);
    }

    public function test_platform_admin_can_update_gym_from_web_panel_with_existing_owner(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-admin-2@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $ownerA = User::factory()->create(['is_active' => true]);
        $ownerA->assignRole(RoleName::GymOwner->value);
        $ownerB = User::factory()->create(['is_active' => true]);

        $facility = Facility::query()->create([
            'name' => 'Cafe',
            'slug' => 'cafe',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym = Gym::query()->create([
            'owner_user_id' => $ownerA->id,
            'name' => 'Iron House',
            'slug' => 'iron-house',
            'city' => 'Pune',
            'status' => 'pending',
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Existing Branch',
            'slug' => 'existing-branch',
            'city' => 'Pune',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'platform-admin-2@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gyms.edit', $gym))->assertOk()->assertSee('Edit Gym');

        $this->put(route('web.admin.gyms.update', $gym), [
            'owner_user_id' => $ownerB->id,
            'name' => 'Iron House Elite',
            'description' => 'Updated description',
            'address' => '11 Main Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'opening_time' => '05:30',
            'closing_time' => '23:00',
            'weekly_off_text' => 'sunday',
            'facility_ids' => [$facility->id],
            'public_listing_enabled' => '1',
            'show_pricing' => '1',
            'trial_available' => '0',
            'contact_visible' => '1',
            'status' => 'active',
        ])->assertRedirect(route('web.admin.gyms.show', $gym));

        $gym->refresh();
        $ownerB->refresh();

        $this->assertSame($ownerB->id, $gym->owner_user_id);
        $this->assertSame('iron-house-elite', $gym->slug);
        $this->assertTrue($ownerB->hasRole(RoleName::GymOwner->value));
        $this->assertSame('approved', $gym->approval_status);
        $this->assertTrue((bool) $gym->is_active);
        $this->assertTrue($gym->facilities()->whereKey($facility->id)->exists());
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'platform_admin_updated_gym',
            'gym_id' => $gym->id,
        ]);
    }

    public function test_platform_admin_api_can_create_and_update_gym(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $facility = Facility::query()->create([
            'name' => 'Crossfit Zone',
            'slug' => 'crossfit-zone',
            'status' => 'active',
            'is_active' => true,
        ]);

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/gyms', [
            'owner_name' => 'API Owner',
            'owner_email' => 'api.owner@gmail.com',
            'name' => 'Velocity Club',
            'city' => 'Delhi',
            'status' => 'active',
            'facility_ids' => [$facility->id],
            'create_default_branch' => true,
            'branch_name' => 'Main Branch',
            'branch_same_as_gym' => true,
            'show_pricing' => true,
            'contact_visible' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Gym created successfully.');

        $gymId = (int) $createResponse->json('data.id');
        $gym = Gym::query()->findOrFail($gymId);

        $owner = User::query()->where('email', 'api.owner@gmail.com')->firstOrFail();
        $this->assertTrue($owner->hasRole(RoleName::GymOwner->value));
        $this->assertDatabaseHas('branches', ['gym_id' => $gym->id, 'name' => 'Main Branch']);

        $existingOwner = User::factory()->create(['is_active' => true]);

        $updateResponse = $this->actingAs($admin, 'sanctum')->putJson('/api/platform-admin/gyms/'.$gym->id, [
            'owner_user_id' => $existingOwner->id,
            'name' => 'Velocity Club Prime',
            'city' => 'Delhi',
            'status' => 'active',
            'show_pricing' => true,
            'contact_visible' => true,
            'facility_ids' => [$facility->id],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Gym updated successfully.');

        $gym->refresh();
        $existingOwner->refresh();
        $this->assertSame($existingOwner->id, $gym->owner_user_id);
        $this->assertTrue($existingOwner->hasRole(RoleName::GymOwner->value));
    }

    public function test_platform_admin_gym_list_filters_and_detail_pages_work(): void
    {
        $admin = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $ownerA = User::factory()->create([
            'name' => 'Aman Owner',
            'email' => 'aman.owner@example.com',
            'is_active' => true,
        ]);
        $ownerA->assignRole(RoleName::GymOwner->value);
        $ownerB = User::factory()->create([
            'name' => 'Beta Owner',
            'email' => 'beta.owner@example.com',
            'is_active' => true,
        ]);
        $ownerB->assignRole(RoleName::GymOwner->value);

        $gymA = Gym::query()->create([
            'owner_user_id' => $ownerA->id,
            'name' => 'Aman Fitness',
            'slug' => 'aman-fitness',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'is_verified' => true,
            'is_featured' => true,
        ]);
        $gymB = Gym::query()->create([
            'owner_user_id' => $ownerB->id,
            'name' => 'Beta Barbell',
            'slug' => 'beta-barbell',
            'city' => 'Delhi',
            'status' => 'inactive',
            'approval_status' => 'pending',
            'is_active' => false,
            'is_promoted' => true,
        ]);

        Branch::query()->create([
            'gym_id' => $gymA->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-a',
            'city' => 'Mumbai',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gyms.index', ['owner' => 'aman.owner@example.com', 'city' => 'Mumbai', 'featured' => 1]))
            ->assertOk()
            ->assertSee('Aman Fitness')
            ->assertDontSee('Beta Barbell');

        $this->get(route('web.admin.gyms.show', $gymA))
            ->assertOk()
            ->assertSee('Owner Details')
            ->assertSee('Public Listing Settings')
            ->assertSee('Branches')
            ->assertSee('Aman Owner');
    }

    public function test_platform_admin_web_and_api_status_actions_work(): void
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Status Gym',
            'slug' => 'status-gym',
            'city' => 'Pune',
            'status' => 'pending',
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->post(route('web.admin.gyms.approve', $gym))->assertRedirect();
        $gym->refresh();
        $this->assertSame('approved', $gym->approval_status);
        $this->assertNotNull($gym->approved_at);

        $this->post(route('web.admin.gyms.activate', $gym))->assertRedirect();
        $gym->refresh();
        $this->assertTrue((bool) $gym->is_active);
        $this->assertSame('active', $gym->status);

        $apiVerify = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/gyms/'.$gym->id.'/verify');
        $apiVerify->assertOk()->assertJsonPath('success', true);
        $gym->refresh();
        $this->assertTrue((bool) $gym->is_verified);

        $apiFeature = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/gyms/'.$gym->id.'/feature');
        $apiFeature->assertOk()->assertJsonPath('success', true);
        $gym->refresh();
        $this->assertTrue((bool) $gym->is_featured);

        $apiPromote = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/gyms/'.$gym->id.'/promote');
        $apiPromote->assertOk()->assertJsonPath('success', true);
        $gym->refresh();
        $this->assertTrue((bool) $gym->is_promoted);
    }

    public function test_non_platform_admin_cannot_access_platform_admin_gym_create_routes(): void
    {
        $gymOwner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $gymOwner->assignRole(RoleName::GymOwner->value);

        $this->actingAs($gymOwner)
            ->get('/admin/gyms/create')
            ->assertForbidden();

        $this->actingAs($gymOwner, 'sanctum')
            ->postJson('/api/platform-admin/gyms', [
                'owner_name' => 'Blocked Owner',
                'owner_email' => 'blocked.owner@gmail.com',
                'name' => 'Blocked Gym',
                'city' => 'Noida',
                'status' => 'pending',
            ])
            ->assertForbidden();
    }

    public function test_platform_admin_gyms_index_shows_add_button_and_owner_search_results(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-admin-4@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create([
            'name' => 'Arena Owner',
            'email' => 'arena.owner@example.com',
            'is_active' => true,
        ]);

        Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Arena Prime',
            'slug' => 'arena-prime',
            'city' => 'Hyderabad',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'platform-admin-4@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.gyms.index', ['search' => 'Arena Owner']))
            ->assertOk()
            ->assertSee('Add Gym')
            ->assertSee('Arena Prime')
            ->assertSee('arena.owner@example.com');
    }
}
