<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_owner_can_record_manual_attendance_and_view_today_screen(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);

        $this->actingAs($owner)
            ->post(route('web.gym.attendance.manual.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'source_device' => 'feature-test',
            ])
            ->assertRedirect(route('web.gym.attendance.index'));

        $this->assertDatabaseHas('attendance_logs', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'checked_in_by' => $owner->id,
            'check_in_method' => 'manual',
        ]);

        $this->actingAs($owner)
            ->get(route('web.gym.attendance.today', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee($member->name);
    }

    public function test_qr_scan_alias_records_attendance_and_blocks_duplicate_same_day(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);

        $qrPayload = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/qr-code')
            ->assertOk()
            ->json('data.qr_payload');

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/qr-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'qr_payload' => $qrPayload,
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.check_in_method', 'qr');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/qr-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'qr_payload' => $qrPayload,
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_id.0', 'This member has already checked in today.');
    }

    public function test_member_qr_uses_current_gym_profile_when_independent_profile_exists(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'name' => 'Scoped QR Gym',
            'slug' => 'scoped-qr-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Scoped QR Branch',
            'slug' => 'scoped-qr-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Scoped QR Plan',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 0,
            'status' => 'active',
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2500,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2500,
            'amount_paid' => 2500,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/qr-code')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.check_in_status.enabled', true)
            ->assertJsonPath('message', 'Member QR code payload generated successfully.');
    }

    public function test_qr_scan_rejects_member_without_active_membership(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value, membershipStatus: 'expired');
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Expired Plan',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(60)->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(),
            'status' => 'expired',
            'default_plan_price' => 2000,
            'default_joining_fee' => 0,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 2000,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2000,
            'amount_paid' => 2000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $payload = app(AttendanceService::class)->buildQrPayload($member, $gym, $branch)['qr_payload'];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/qr-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'qr_payload' => $payload,
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_id.0', 'Attendance is unavailable because the member does not have an active membership.');
    }

    public function test_branch_manager_cannot_view_other_branch_member_attendance(): void
    {
        [$manager, $member, $gym, $branch] = $this->makeGymScope(RoleName::BranchManager->value);
        $otherBranch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);
        $otherMember = User::factory()->create();
        $otherMember->forceFill(['active_role' => RoleName::Member->value])->save();
        $otherMember->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $otherMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $otherBranch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('web.gym.members.attendance', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'member' => $otherMember->id,
            ]))
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/gym/members/{$otherMember->id}/attendance?gym_id={$gym->id}&branch_id={$branch->id}", [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertNotFound();
    }

    public function test_gym_staff_needs_manage_attendance_custom_permission(): void
    {
        [$staff, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymStaff->value, customPermissions: []);

        $this->actingAs($staff)
            ->get(route('web.gym.attendance.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/attendance', [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertForbidden();

        $gym->users()->syncWithoutDetaching([
            $staff->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
        ]);
        $branch->users()->syncWithoutDetaching([
            $staff->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
        ]);

        $this->actingAs($staff)
            ->get(route('web.gym.attendance.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: User, 2: Gym, 3: Branch}
     */
    private function makeGymScope(string $role, string $membershipStatus = 'active', array $customPermissions = ['manage_attendance']): array
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
            'name' => 'Attendance Gym',
            'slug' => 'attendance-gym-'.str()->random(6),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'prevent_duplicate_same_day_checkins' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Attendance Branch',
            'slug' => 'attendance-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode($customPermissions)],
            $member->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode($customPermissions)],
            $member->id => ['is_primary' => false],
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => $membershipStatus,
            'is_active' => true,
        ]);

        return [$actor, $member, $gym, $branch];
    }
}
