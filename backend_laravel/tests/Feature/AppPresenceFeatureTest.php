<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\UserAppPresence;
use App\Models\UserFcmToken;
use App\Services\Users\AppPresenceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppPresenceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_member_app_presence_is_recorded_and_visible_to_gym_and_platform_admins(): void
    {
        $owner = $this->user(RoleName::GymOwner->value, 'presence-owner@example.com');
        $admin = $this->user(RoleName::PlatformAdmin->value, 'presence-admin@example.com');
        $member = $this->user(RoleName::Member->value, 'presence-member@example.com', 'Presence Member');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Presence Gym',
            'slug' => 'presence-gym',
            'approval_status' => 'approved',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Presence Branch',
            'slug' => 'presence-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->attach($owner, $gym, $branch);
        $this->attach($member, $gym, $branch);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/public/fcm-tokens', [
                'token' => 'presence-fcm-token',
                'platform' => 'android',
                'app_role' => 'member',
                'device_name' => 'Gym Atlas Android',
                'device_id' => 'install-device-1',
                'app_version' => '1.2.3',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_app_presences', [
            'user_id' => $member->id,
            'app_role' => 'member',
            'device_key' => 'install-device-1',
            'platform' => 'android',
            'app_version' => '1.2.3',
        ]);

        $this->actingAs($owner)
            ->get(route('web.gym.members.show', ['member' => $member->id, 'gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Gym Atlas app status')
            ->assertSee('App active')
            ->assertSee('ANDROID')
            ->assertSee('Presence Member');

        $this->actingAs($admin)
            ->get(route('web.admin.users.show', $member))
            ->assertOk()
            ->assertSee('Gym Atlas app status')
            ->assertSee('App active')
            ->assertSee('1.2.3')
            ->assertSee('Presence Member');
    }

    public function test_member_app_presence_can_be_recorded_without_a_push_token(): void
    {
        $member = $this->user(RoleName::Member->value, 'presence-no-push@example.com');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/public/app-presence', [
                'platform' => 'ios',
                'app_role' => 'member',
                'device_name' => 'Gym Atlas iPhone',
                'device_id' => 'presence-only-device',
                'app_version' => '1.2.4',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app_role', 'member')
            ->assertJsonPath('data.platform', 'ios');

        $this->assertDatabaseHas('user_app_presences', [
            'user_id' => $member->id,
            'app_role' => 'member',
            'device_key' => 'presence-only-device',
            'platform' => 'ios',
            'app_version' => '1.2.4',
        ]);
        $this->assertDatabaseMissing('user_fcm_tokens', [
            'user_id' => $member->id,
            'device_key' => 'presence-only-device',
        ]);
    }

    public function test_registering_a_refreshed_token_revokes_the_previous_token_for_that_installation(): void
    {
        $member = $this->user(RoleName::Member->value, 'presence-token-refresh@example.com');
        UserFcmToken::query()->create([
            'user_id' => $member->id,
            'token' => 'old-fcm-token',
            'platform' => 'android',
            'app_role' => 'member',
            'device_key' => 'same-installation',
            'last_seen_at' => now(),
        ]);
        UserFcmToken::query()->create([
            'user_id' => $member->id,
            'token' => 'legacy-fcm-token-without-device-id',
            'platform' => 'android',
            'app_role' => 'member',
            'device_name' => 'Gym Atlas Android',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/public/fcm-tokens', [
                'token' => 'new-fcm-token',
                'platform' => 'android',
                'app_role' => 'member',
                'device_name' => 'Gym Atlas Android',
                'device_id' => 'same-installation',
            ])
            ->assertOk();

        $this->assertNotNull(UserFcmToken::query()->where('token', 'old-fcm-token')->value('revoked_at'));
        $this->assertNotNull(UserFcmToken::query()->where('token', 'legacy-fcm-token-without-device-id')->value('revoked_at'));
        $this->assertNull(UserFcmToken::query()->where('token', 'new-fcm-token')->value('revoked_at'));
        $this->assertSame(1, UserFcmToken::query()->deliverable()->where('user_id', $member->id)->count());
    }

    public function test_recent_signed_out_device_does_not_make_an_old_remaining_install_active(): void
    {
        $member = $this->user(RoleName::Member->value, 'presence-mixed-devices@example.com');

        UserAppPresence::query()->create([
            'user_id' => $member->id,
            'app_role' => 'member',
            'device_key' => 'old-active-device',
            'platform' => 'android',
            'first_seen_at' => now()->subDays(45),
            'last_seen_at' => now()->subDays(45),
        ]);
        UserAppPresence::query()->create([
            'user_id' => $member->id,
            'app_role' => 'member',
            'device_key' => 'recent-signed-out-device',
            'platform' => 'ios',
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);

        $summary = app(AppPresenceService::class)->summary($member->fresh(), 'member');

        $this->assertSame('inactive', $summary['status']);
        $this->assertSame('App inactive', $summary['label']);
        $this->assertSame(1, $summary['device_count']);
        $this->assertSame('old-active-device', $summary['latest']->device_key);
    }

    public function test_admin_and_gym_pages_fall_back_when_presence_table_has_not_been_migrated(): void
    {
        $owner = $this->user(RoleName::GymOwner->value, 'presence-fallback-owner@example.com');
        $admin = $this->user(RoleName::PlatformAdmin->value, 'presence-fallback-admin@example.com');
        $member = $this->user(RoleName::Member->value, 'presence-fallback-member@example.com', 'Presence Fallback Member');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Presence Fallback Gym',
            'slug' => 'presence-fallback-gym',
            'approval_status' => 'approved',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Presence Fallback Branch',
            'slug' => 'presence-fallback-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->attach($owner, $gym, $branch);
        $this->attach($member, $gym, $branch);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        Schema::dropIfExists('user_app_presences');

        $this->actingAs($admin)
            ->get(route('web.admin.users.show', $member))
            ->assertOk()
            ->assertSee('Gym Atlas app status')
            ->assertSee('Not using app yet')
            ->assertSee('Presence Fallback Member');

        $this->actingAs($owner)
            ->get(route('web.gym.members.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Presence Fallback Member')
            ->assertSee('Not using app yet');
    }

    public function test_presence_endpoint_falls_back_when_presence_table_has_not_been_migrated(): void
    {
        $member = $this->user(RoleName::Member->value, 'presence-api-fallback@example.com');

        Schema::dropIfExists('user_app_presences');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/public/app-presence', [
                'platform' => 'android',
                'app_role' => 'member',
                'device_id' => 'pending-migration-device',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.presence_id', null)
            ->assertJsonPath('data.app_role', 'member')
            ->assertJsonPath('data.platform', 'android');
    }

    public function test_uninstall_suspected_status_is_preserved_after_invalid_push_token(): void
    {
        $member = $this->user(RoleName::Member->value, 'presence-invalid@example.com');

        $presence = UserAppPresence::query()->create([
            'user_id' => $member->id,
            'app_role' => 'member',
            'device_key' => 'install-device-2',
            'platform' => 'ios',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        $presence->forceFill(['uninstall_suspected_at' => now()])->save();

        $summary = app(AppPresenceService::class)->summary($member->fresh(), 'member');

        $this->assertSame('uninstall_suspected', $summary['status']);
        $this->assertSame('Uninstall suspected', $summary['label']);
    }

    private function user(string $role, string $email, ?string $name = null): User
    {
        $user = User::factory()->create([
            'name' => $name ?? str($role)->replace('_', ' ')->title()->toString(),
            'email' => $email,
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function attach(User $user, Gym $gym, Branch $branch): void
    {
        $user->gyms()->syncWithoutDetaching([$gym->id => [
            'role_name' => $user->getRoleNames()->first(),
            'status' => 'active',
            'is_primary' => true,
        ]]);
        $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
    }
}
