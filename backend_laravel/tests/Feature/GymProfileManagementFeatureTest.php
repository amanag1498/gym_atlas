<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GymProfileManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_update_profile_via_web_with_media_and_facilities(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Profile Gym',
            'slug' => 'profile-gym',
            'city' => 'Delhi',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $facility = Facility::query()->create([
            'name' => 'Steam',
            'slug' => 'steam',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranch($owner, $gym, $branch);
        $this->loginGymUser($owner);

        $this->put(route('web.gym.profile.update', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Profile Gym Plus',
            'city' => 'Delhi',
            'description' => 'Updated profile description',
            'address' => 'Ring Road',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'weekly_off_text' => 'sunday',
            'timings_json' => '{"all_days":{"open":"06:00","close":"22:00"}}',
            'facility_ids' => [$facility->id],
            'show_pricing' => '1',
            'public_listing_enabled' => '1',
            'trial_available' => '1',
            'contact_visible' => '1',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 500),
            'gallery_images' => [UploadedFile::fake()->image('gallery-1.jpg')],
        ])->assertRedirect();

        $gym->refresh();

        $this->assertSame('Profile Gym Plus', $gym->name);
        $this->assertSame('Ring Road', $gym->address);
        $this->assertSame('06:00', $gym->opening_time);
        $this->assertSame('22:00', $gym->closing_time);
        $this->assertTrue((bool) $gym->contact_visible);
        $this->assertNotEmpty($gym->logo_url);
        $this->assertNotEmpty($gym->cover_image_url);
        $this->assertNotEmpty($gym->photo_urls);
        $this->assertSame([$facility->id], $gym->facilities()->pluck('facilities.id')->all());
    }

    public function test_gym_owner_can_update_public_listing_settings_via_api_and_discovery_respects_them(): void
    {
        $owner = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Discovery Gym',
            'slug' => 'discovery-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'show_pricing' => true,
            'pricing_visible' => true,
            'trial_available' => true,
            'contact_visible' => true,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Discovery Branch',
            'slug' => 'discovery-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranch($owner, $gym, $branch);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/gym/public-listing-settings', [
                'public_listing_enabled' => true,
                'show_pricing' => false,
                'trial_available' => true,
                'contact_visible' => false,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.public_listing_enabled', true)
            ->assertJsonPath('data.show_pricing', false)
            ->assertJsonPath('data.contact_visible', false);

        $this->getJson('/api/public/discovery/gyms/discovery-gym')
            ->assertOk()
            ->assertJsonPath('data.show_pricing', false)
            ->assertJsonPath('data.pricing_visible', false)
            ->assertJsonPath('data.contact_visible', false)
            ->assertJsonPath('data.contact_action.enabled', false);
    }

    private function attachToGymAndBranch(User $user, Gym $gym, Branch $branch): void
    {
        $user->gyms()->syncWithoutDetaching([$gym->id => ['is_primary' => true]]);
        $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
    }

    private function loginGymUser(User $user): void
    {
        $this->post('/gym/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));
    }
}
