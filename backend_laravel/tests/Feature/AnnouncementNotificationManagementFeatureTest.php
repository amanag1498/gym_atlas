<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\NotificationPreference;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementNotificationManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_announcement_creates_member_notifications_and_show_delete_work(): void
    {
        [$owner, $gym, $branch, $member] = $this->makeGymScope();

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $announcementId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/announcements', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'audience_type' => 'branch_specific',
                'title' => 'Branch Alert',
                'message' => 'Today closes at 9 PM.',
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Branch Alert')
            ->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => NotificationType::GymAnnouncement->value,
            'announcement_id' => $announcementId,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/gym/announcements/{$announcementId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $announcementId);

        $this->actingAs($owner)
            ->get(route('web.gym.announcements.show', ['gym' => $gym->id, 'branch' => $branch->id, 'announcement' => $announcementId]))
            ->assertOk()
            ->assertSee('Branch Alert');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/gym/announcements/{$announcementId}", [], $headers)
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $announcementId]);
    }

    public function test_notification_alias_routes_support_read_read_all_and_preferences(): void
    {
        [$owner, $gym, $branch, $member] = $this->makeGymScope();

        $announcement = Announcement::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'audience_type' => 'branch_specific',
            'title' => 'Reminder',
            'message' => 'Bring water.',
            'status' => 'sent',
            'send_at' => now(),
        ]);

        \App\Models\Notification::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'announcement_id' => $announcement->id,
            'type' => NotificationType::GymAnnouncement->value,
            'title' => 'Reminder',
            'message' => 'Bring water.',
            'body' => 'Bring water.',
        ]);

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $notificationId = $this->actingAs($member, 'sanctum')
            ->getJson('/api/notifications', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0.id');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/notifications/{$notificationId}/read", [], $headers)
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/notifications/read-all', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.marked_count', 0);

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/notification-preferences', [
                'preferences' => [[
                    'notification_type' => NotificationType::GymAnnouncement->value,
                    'is_enabled' => false,
                ]],
            ], $headers)
            ->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $member->id,
            'notification_type' => NotificationType::GymAnnouncement->value,
            'is_enabled' => false,
        ]);
    }

    public function test_branch_manager_is_branch_scoped_for_announcements(): void
    {
        [$owner, $gym, $branchA, $memberA] = $this->makeGymScope();
        $manager = User::factory()->create(['active_role' => RoleName::BranchManager->value, 'is_active' => true]);
        $manager->assignRole(RoleName::BranchManager->value);

        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $memberB = User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $memberB->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $memberB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([$manager->id => ['is_primary' => false]]);
        $branchA->users()->syncWithoutDetaching([$manager->id => ['is_primary' => true]]);

        Announcement::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'created_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'audience_type' => 'branch_specific',
            'title' => 'A Only',
            'message' => 'A',
            'status' => 'sent',
            'send_at' => now(),
        ]);
        Announcement::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'created_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'audience_type' => 'branch_specific',
            'title' => 'B Only',
            'message' => 'B',
            'status' => 'sent',
            'send_at' => now(),
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/announcements', ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branchA->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'A Only');
    }

    public function test_gym_staff_needs_send_announcements_custom_permission(): void
    {
        [$owner, $gym, $branch, $member] = $this->makeGymScope();

        $staff = User::factory()->create(['active_role' => RoleName::GymStaff->value, 'is_active' => true]);
        $staff->assignRole(RoleName::GymStaff->value);
        $gym->users()->syncWithoutDetaching([$staff->id => ['is_primary' => false, 'custom_permissions' => json_encode([])]]);
        $branch->users()->syncWithoutDetaching([$staff->id => ['is_primary' => true, 'custom_permissions' => json_encode([])]]);

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/gym/announcements', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'audience_type' => 'branch_specific',
                'title' => 'Blocked',
                'message' => 'Should fail',
            ], $headers)
            ->assertForbidden();

        $gym->users()->updateExistingPivot($staff->id, ['custom_permissions' => json_encode(['send_announcements'])]);
        $branch->users()->updateExistingPivot($staff->id, ['custom_permissions' => json_encode(['send_announcements'])]);
        $staff->unsetRelation('gyms');
        $staff->unsetRelation('branches');
        $staff->refresh();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/gym/announcements', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'audience_type' => 'branch_specific',
                'title' => 'Allowed',
                'message' => 'Now allowed',
            ], $headers)
            ->assertCreated();
    }

    /**
     * @return array{0: User, 1: Gym, 2: Branch, 3: User}
     */
    private function makeGymScope(): array
    {
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value, 'is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $member = User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Notify Gym',
            'slug' => 'notify-gym-'.str()->random(6),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Notify Branch',
            'slug' => 'notify-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => true],
            $member->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => true],
            $member->id => ['is_primary' => false],
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return [$owner, $gym, $branch, $member];
    }
}
