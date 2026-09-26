<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\SmartAttendanceHub;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartAttendanceHubFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_admin_can_create_list_update_toggle_and_rotate_smart_attendance_hub(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope();
        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Reception Android Hub',
                'platform' => 'android',
                'firmware_version' => '1.0.0',
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hub.name', 'Reception Android Hub')
            ->assertJsonPath('data.hub.platform', 'android')
            ->assertJsonPath('data.hub.branch_id', $branch->id)
            ->assertJsonStructure(['data' => ['hub' => ['uuid', 'public_id'], 'device_secret', 'activation_endpoint', 'heartbeat_endpoint', 'config_endpoint']]);

        $hubId = $create->json('data.hub.id');
        $secret = $create->json('data.device_secret');
        $this->assertNotEmpty($secret);
        $this->assertDatabaseHas('smart_attendance_hubs', [
            'id' => $hubId,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Reception Android Hub',
            'platform' => 'android',
        ]);
        $this->assertNotSame($secret, SmartAttendanceHub::query()->findOrFail($hubId)->device_secret_hash);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/smart-attendance-hubs', $headers)
            ->assertOk()
            ->assertJsonPath('data.hubs.0.id', $hubId)
            ->assertJsonPath('data.hubs.0.public_id', SmartAttendanceHub::query()->findOrFail($hubId)->public_id)
            ->assertJsonMissingPath('data.hubs.0.device_secret')
            ->assertJsonMissingPath('data.hubs.0.device_secret_hash');

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/gym/smart-attendance-hubs/{$hubId}", [
                'branch_id' => $branch->id,
                'name' => 'Reception ESP Hub',
                'platform' => 'esp32',
                'firmware_version' => '1.0.1',
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Reception ESP Hub')
            ->assertJsonPath('data.platform', 'esp32');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/smart-attendance-hubs/{$hubId}/toggle", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'disabled');

        $rotate = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/smart-attendance-hubs/{$hubId}/rotate-secret", [], $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => ['hub', 'device_secret']]);
        $this->assertNotSame($secret, $rotate->json('data.device_secret'));
    }

    public function test_gym_admin_cannot_manage_another_gym_hub(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope();
        [$otherOwner, , $otherGym, $otherBranch] = $this->makeGymScope(slugSuffix: 'other');
        $hub = SmartAttendanceHub::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_id' => 'SAHOTHER1234567',
            'gym_id' => $otherGym->id,
            'branch_id' => $otherBranch->id,
            'created_by_user_id' => $otherOwner->id,
            'name' => 'Other Gym Hub',
            'platform' => 'android',
            'device_secret_hash' => hash('sha256', 'secret'),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/gym/smart-attendance-hubs/{$hub->id}", [
                'branch_id' => $branch->id,
                'name' => 'Bad Update',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $otherBranch->id,
                'name' => 'Unauthorized Branch Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertForbidden();
    }

    public function test_device_activation_heartbeat_and_config_use_per_hub_secret(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope();
        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Door Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertCreated();

        $uuid = $create->json('data.hub.uuid');
        $publicId = $create->json('data.hub.public_id');
        $secret = $create->json('data.device_secret');

        $this->postJson("/api/smart-attendance/hubs/{$uuid}/heartbeat", [
            'firmware_version' => 'hub-1.2.0',
        ], ['X-GymAtlas-Device-Token' => 'wrong-secret'])->assertUnauthorized();

        $this->postJson("/api/smart-attendance/hubs/{$uuid}/activate", [
            'firmware_version' => 'hub-1.2.0',
            'battery_percent' => 87,
            'ble_advertising' => true,
        ], ['X-GymAtlas-Device-Token' => $secret])
            ->assertOk()
            ->assertJsonPath('data.hub.public_id', $publicId)
            ->assertJsonPath('data.hub.status', 'online')
            ->assertJsonPath('data.ble.protocol_version', 1)
            ->assertJsonMissing(['device_secret' => $secret]);

        $this->assertDatabaseHas('smart_attendance_hubs', [
            'uuid' => $uuid,
            'status' => 'online',
            'firmware_version' => 'hub-1.2.0',
        ]);
        $this->assertNotNull(SmartAttendanceHub::query()->where('uuid', $uuid)->value('last_seen_at'));

        $this->postJson("/api/smart-attendance/hubs/{$uuid}/heartbeat", [
            'firmware_version' => 'hub-1.2.1',
        ], ['X-GymAtlas-Device-Token' => $secret])
            ->assertOk()
            ->assertJsonPath('data.hub.firmware_version', 'hub-1.2.1');

        $this->getJson("/api/smart-attendance/hubs/{$uuid}/config", ['X-GymAtlas-Device-Token' => $secret])
            ->assertOk()
            ->assertJsonPath('data.hub.public_id', $publicId)
            ->assertJsonPath('data.gym.id', $gym->id)
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonMissing(['device_secret_hash' => SmartAttendanceHub::query()->where('uuid', $uuid)->value('device_secret_hash')]);
    }

    public function test_rotated_and_disabled_hub_credentials_are_enforced(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope();
        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];
        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Credential Test Hub',
                'platform' => 'android',
            ], $headers)
            ->assertCreated();

        $hubId = (int) $create->json('data.hub.id');
        $uuid = $create->json('data.hub.uuid');
        $oldSecret = $create->json('data.device_secret');
        $rotate = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/smart-attendance-hubs/{$hubId}/rotate-secret", [], $headers)
            ->assertOk();
        $newSecret = $rotate->json('data.device_secret');

        $this->postJson("/api/smart-attendance/hubs/{$uuid}/activate", [], [
            'X-GymAtlas-Device-Token' => $oldSecret,
        ])->assertUnauthorized();
        $this->postJson("/api/smart-attendance/hubs/{$uuid}/activate", [], [
            'X-GymAtlas-Device-Token' => $newSecret,
        ])->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/smart-attendance-hubs/{$hubId}/toggle", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');
        $this->postJson("/api/smart-attendance/hubs/{$uuid}/heartbeat", [], [
            'X-GymAtlas-Device-Token' => $newSecret,
        ])->assertUnauthorized();
    }

    public function test_member_can_check_in_with_active_smart_attendance_hub_detection(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope();
        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Entrance Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertCreated();
        $this->markHubOnline((int) $create->json('data.hub.id'));

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/attendance/smart-check-in', [
                'hub_public_id' => strtolower($create->json('data.hub.public_id')),
                'protocol_version' => 1,
                'rssi' => -58,
                'detected_at' => now()->toIso8601String(),
                'source' => 'android_foreground_ble',
                'metadata' => ['presence_window_ms' => 2400],
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertCreated()
            ->assertJsonPath('data.attendance.check_in_method', 'smart_attendance')
            ->assertJsonPath('data.attendance.smart_attendance_hub_id', $create->json('data.hub.id'))
            ->assertJsonPath('data.attendance.smart_attendance_detection.protocol_version', 1)
            ->assertJsonPath('data.attendance.smart_attendance_detection.rssi', -58)
            ->assertJsonPath('data.check_in_status.checked_in_today', true)
            ->assertJsonPath('data.check_in_status.check_in_method', 'smart_attendance')
            ->assertJsonStructure(['data' => ['attendance_date', 'duplicate_suppression_until']]);

        $this->assertDatabaseHas('attendance_logs', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'check_in_method' => 'smart_attendance',
            'smart_attendance_hub_id' => $create->json('data.hub.id'),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'type' => NotificationType::SmartAttendanceCheckIn->value,
            'title' => 'Welcome to '.$gym->name,
        ]);
    }

    public function test_member_smart_attendance_rejects_hub_from_another_selected_gym(): void
    {
        [, $member, $gym, $branch] = $this->makeGymScope();
        [$otherOwner, , $otherGym, $otherBranch] = $this->makeGymScope(slugSuffix: 'other-smart');
        $otherHub = $this->actingAs($otherOwner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $otherBranch->id,
                'name' => 'Other Entrance Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $otherGym->id, 'X-Branch-Id' => (string) $otherBranch->id])
            ->assertCreated();
        $this->markHubOnline((int) $otherHub->json('data.hub.id'));

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/attendance/smart-check-in', [
                'hub_public_id' => $otherHub->json('data.hub.public_id'),
                'protocol_version' => 1,
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hub_public_id');

        $this->assertDatabaseMissing('attendance_logs', [
            'member_id' => $member->id,
            'check_in_method' => 'smart_attendance',
        ]);
    }

    public function test_member_smart_attendance_uses_existing_duplicate_protection(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope();
        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Duplicate Test Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertCreated();
        $this->markHubOnline((int) $create->json('data.hub.id'));
        $payload = ['hub_public_id' => $create->json('data.hub.public_id'), 'protocol_version' => 1];
        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/smart-check-in', $payload, $headers)->assertCreated();
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/attendance/smart-check-in', $payload, $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_id');

        $this->assertSame(1, SmartAttendanceHub::query()->findOrFail($create->json('data.hub.id'))->attendanceLogs()->count());
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => NotificationType::SmartAttendanceCheckIn->value,
        ]);
    }

    public function test_member_smart_attendance_rejects_hub_before_device_activation(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope();
        $create = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/smart-attendance-hubs', [
                'branch_id' => $branch->id,
                'name' => 'Pending Hub',
                'platform' => 'android',
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertCreated();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/attendance/smart-check-in', [
                'hub_public_id' => $create->json('data.hub.public_id'),
                'protocol_version' => 1,
            ], ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hub_public_id');

        $this->assertDatabaseMissing('attendance_logs', [
            'member_id' => $member->id,
            'check_in_method' => 'smart_attendance',
        ]);
    }

    public function test_web_gym_admin_can_create_and_view_smart_attendance_hub(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope();

        $this->actingAs($owner)
            ->post(route('web.gym.smart-attendance-hubs.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
                'branch_id' => $branch->id,
                'name' => 'Web Smart Hub',
                'platform' => 'android',
            ])
            ->assertRedirect(route('web.gym.smart-attendance-hubs.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertSessionHas('smart_hub_secret')
            ->assertSessionHas('smart_hub_uuid')
            ->assertSessionHas('smart_hub_public_id');

        $this->actingAs($owner)
            ->get(route('web.gym.smart-attendance-hubs.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Smart Attendance Hubs')
            ->assertSee('Web Smart Hub')
            ->assertSee('Setup checklist')
            ->assertSee('Create hub and show secret');
    }

    private function makeGymScope(string $role = 'gym_owner', string $slugSuffix = 'main'): array
    {
        $this->seed(PermissionSeeder::class);

        $actor = User::factory()->create([
            'is_active' => true,
            'active_role' => $role,
        ]);
        $actor->assignRole($role);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $role === RoleName::GymOwner->value ? $actor->id : null,
            'name' => 'Smart Attendance Gym '.$slugSuffix,
            'slug' => 'smart-attendance-gym-'.$slugSuffix.'-'.str()->random(6),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Smart Attendance Branch '.$slugSuffix,
            'slug' => 'smart-attendance-branch-'.$slugSuffix.'-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
            $member->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
            $member->id => ['is_primary' => false],
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'membership_expires_on' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Smart Attendance Plan '.$slugSuffix,
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 0,
            'status' => 'active',
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 1000,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 1000,
            'amount_paid' => 1000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        return [$actor, $member, $gym, $branch];
    }

    private function markHubOnline(int $hubId): void
    {
        SmartAttendanceHub::query()->findOrFail($hubId)->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
        ])->save();
    }
}
