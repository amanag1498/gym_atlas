<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\ReminderService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceNotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_same_day_qr_check_in_is_blocked_when_gym_setting_enabled(): void
    {
        [$staff, $member, $gym, $branch] = $this->makeScopedUsers(RoleName::GymStaff->value);

        $qrResponse = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/qr-code', [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ]);

        $qrPayload = $qrResponse->json('data.qr_payload');

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/gym/attendance/scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'qr_payload' => $qrPayload,
            ], $headers)
            ->assertCreated();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/gym/attendance/scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'qr_payload' => $qrPayload,
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_id.0', 'This member has already checked in today.');
    }

    public function test_member_can_only_access_own_notifications(): void
    {
        [, $member, $gym, $branch] = $this->makeScopedUsers(RoleName::GymStaff->value);
        $otherMember = User::factory()->create();
        $otherMember->forceFill(['active_role' => RoleName::Member->value])->save();
        $otherMember->assignRole(RoleName::Member->value);

        Notification::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'type' => NotificationType::GymAnnouncement->value,
            'title' => 'Own Notification',
            'body' => 'Visible',
        ]);

        $otherNotification = Notification::query()->create([
            'user_id' => $otherMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'type' => NotificationType::GymAnnouncement->value,
            'title' => 'Other Notification',
            'body' => 'Hidden',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/public/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/public/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_trainer_can_notify_only_assigned_members(): void
    {
        [$trainer, $assignedMember, $gym, $branch] = $this->makeScopedUsers(RoleName::Trainer->value);
        $unassignedMember = User::factory()->create();
        $unassignedMember->forceFill(['active_role' => RoleName::Member->value])->save();
        $unassignedMember->assignRole(RoleName::Member->value);

        MemberProfile::query()->create([
            'user_id' => $unassignedMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $gym->users()->syncWithoutDetaching([$unassignedMember->id => ['is_primary' => false]]);
        $branch->users()->syncWithoutDetaching([$unassignedMember->id => ['is_primary' => false]]);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/announcements', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'audience_type' => 'selected_members',
                'title' => 'Trainer ping',
                'message' => 'Check in for tomorrow.',
                'member_ids' => [$assignedMember->id, $unassignedMember->id],
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_ids.0', 'Trainers can notify only assigned members.');
    }

    public function test_due_reminder_engine_creates_due_notification(): void
    {
        [$manager, $member, $gym, $branch] = $this->makeScopedUsers(RoleName::BranchManager->value);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $manager->id,
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'expiry_date' => now()->addDays(25)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2000,
            'default_joining_fee' => 500,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 2000,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 500,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2500,
            'amount_paid' => 0,
            'due_amount' => 2500,
            'due_date' => now()->toDateString(),
            'payment_status' => 'unpaid',
            'approved_by_admin_id' => $manager->id,
        ]);

        app(ReminderService::class)->syncMembershipReminders($membership->fresh('membershipPlan'));

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/gym/scheduled-reminders/run-due', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertOk();

        $this->assertTrue(Notification::query()
            ->where('user_id', $member->id)
            ->where('type', NotificationType::PaymentDue->value)
            ->exists());
    }

    private function makeScopedUsers(string $activeRole): array
    {
        $this->seed(PermissionSeeder::class);

        $actor = User::factory()->create();
        $actor->forceFill(['active_role' => $activeRole])->save();
        $actor->assignRole($activeRole);

        $member = User::factory()->create();
        $member->forceFill(['active_role' => RoleName::Member->value])->save();
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $activeRole === RoleName::GymOwner->value ? $actor->id : null,
            'name' => 'Scoped Gym',
            'slug' => 'scoped-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'prevent_duplicate_same_day_checkins' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Scoped Branch',
            'slug' => 'scoped-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $gym->users()->syncWithoutDetaching([$actor->id => ['is_primary' => true], $member->id => ['is_primary' => false]]);
        if ($activeRole === RoleName::GymStaff->value) {
            $gym->users()->syncWithoutDetaching([$actor->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])]]);
            $branch->users()->syncWithoutDetaching([$actor->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])], $member->id => ['is_primary' => false]]);
        } else {
            $branch->users()->syncWithoutDetaching([$actor->id => ['is_primary' => true], $member->id => ['is_primary' => false]]);
        }

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $activeRole === RoleName::Trainer->value ? $actor->id : null,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        if ($activeRole === RoleName::Trainer->value) {
            \App\Models\TrainerProfile::query()->create([
                'user_id' => $actor->id,
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);
        }

        return [$actor, $member, $gym, $branch];
    }
}
