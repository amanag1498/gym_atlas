<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\CustomFeeAuditLog;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymReportManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_reports_load_filters_work_and_exports_are_scoped(): void
    {
        [$owner, $gym, $branchA, $branchB, $trainerA, $memberA, $memberB, $planA] = $this->seedReportFixture();

        $this->loginGymUser($owner);

        $this->get(route('web.gym.reports.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Gym Reports Overview');

        $this->get(route('web.gym.reports.revenue', ['gym' => $gym->id, 'branch_id' => $branchA->id]))
            ->assertOk()
            ->assertSee('Revenue Report')
            ->assertSee('Member Alpha')
            ->assertDontSee('Member Beta');

        $this->get(route('web.gym.reports.dues', ['gym' => $gym->id, 'branch_id' => $branchA->id, 'status' => 'overdue']))
            ->assertOk()
            ->assertSee('Pending / Overdue Dues Report')
            ->assertSee('Member Alpha')
            ->assertDontSee('Member Beta');

        $this->get(route('web.gym.reports.memberships', [
            'gym' => $gym->id,
            'branch_id' => $branchA->id,
            'plan_id' => $planA->id,
            'status' => 'expiring-soon',
            'end_date' => now()->addDays(7)->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Membership Lifecycle Report')
            ->assertSee('Member Alpha')
            ->assertDontSee('Member Beta');

        $this->get(route('web.gym.reports.attendance', [
            'gym' => $gym->id,
            'trainer_id' => $trainerA->id,
        ]))
            ->assertOk()
            ->assertSee('Attendance Report')
            ->assertSee('Member Alpha')
            ->assertDontSee('Member Beta');

        $export = $this->get(route('web.gym.reports.export', [
            'type' => 'dues',
            'gym' => $gym->id,
            'branch_id' => $branchA->id,
        ]));

        $export->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Member Alpha', $export->streamedContent());
        $this->assertStringNotContainsString('Member Beta', $export->streamedContent());
    }

    public function test_branch_manager_reports_are_branch_scoped_on_web_and_api(): void
    {
        [$owner, $gym, $branchA, $branchB] = $this->seedReportFixture();

        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::BranchManager->value,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);

        $this->loginGymUser($manager);

        $this->get(route('web.gym.reports.revenue', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Member Alpha')
            ->assertDontSee('Member Beta')
            ->assertDontSee($branchB->name);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/reports/revenue?gym_id='.$gym->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_key', 'revenue')
            ->assertJsonMissing(['Member Beta']);
    }

    public function test_gym_staff_reports_require_view_reports_permission(): void
    {
        [, $gym, $branchA] = $this->seedReportFixture();

        $staff = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymStaff->value,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $this->attachToGymAndBranches($staff, $gym, [$branchA], []);

        $this->loginGymUser($staff);

        $this->get(route('web.gym.reports.index', ['gym' => $gym->id, 'branch' => $branchA->id]))
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/reports?gym_id='.$gym->id.'&branch_id='.$branchA->id)
            ->assertForbidden();

        $this->attachToGymAndBranches($staff, $gym, [$branchA], ['view_reports']);
        $staff = $staff->fresh();
        $this->actingAs($staff);

        $this->get(route('web.gym.reports.index', ['gym' => $gym->id, 'branch' => $branchA->id]))
            ->assertOk()
            ->assertSee('Gym Reports Overview');

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/reports?gym_id='.$gym->id.'&branch_id='.$branchA->id)
            ->assertOk()
            ->assertJsonPath('data.report_key', 'overview');
    }

    /**
     * @return array{0: User, 1: Gym, 2: Branch, 3: Branch, 4: User, 5: User, 6: User, 7: MembershipPlan}
     */
    private function seedReportFixture(): array
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Report Gym',
            'slug' => 'report-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Alpha Branch',
            'slug' => 'alpha-branch',
            'city' => 'Mumbai',
            'status' => 'active',
            'is_active' => true,
        ]);

        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Beta Branch',
            'slug' => 'beta-branch',
            'city' => 'Pune',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainerA = User::factory()->create([
            'name' => 'Trainer Alpha',
            'is_active' => true,
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainerA->assignRole(RoleName::Trainer->value);
        $this->attachToGymAndBranches($trainerA, $gym, [$branchA], []);
        TrainerProfile::query()->create([
            'user_id' => $trainerA->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'specialization' => 'Strength',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainerB = User::factory()->create([
            'name' => 'Trainer Beta',
            'is_active' => true,
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainerB->assignRole(RoleName::Trainer->value);
        $this->attachToGymAndBranches($trainerB, $gym, [$branchB], []);
        TrainerProfile::query()->create([
            'user_id' => $trainerB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'specialization' => 'Cardio',
            'status' => 'active',
            'is_active' => true,
        ]);

        $memberA = $this->createMember($gym, $branchA, 'Member Alpha', 'member-a@example.com', $trainerA->id);
        $memberB = $this->createMember($gym, $branchB, 'Member Beta', 'member-b@example.com', $trainerB->id);

        $planA = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'name' => 'Monthly Alpha',
            'duration_days' => 30,
            'plan_price' => 3000,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $planB = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'name' => 'Monthly Beta',
            'duration_days' => 30,
            'plan_price' => 3500,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $membershipA = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'member_id' => $memberA->id,
            'membership_plan_id' => $planA->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'expiry_date' => now()->toDateString(),
            'status' => 'active',
            'default_plan_price' => 3000,
            'default_joining_fee' => 500,
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 2600,
            'discount_type' => 'fixed',
            'discount_amount' => 400,
            'custom_joining_fee' => 500,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 3100,
            'amount_paid' => 1000,
            'due_amount' => 2100,
            'due_date' => now()->subDay()->toDateString(),
            'payment_status' => 'overdue',
            'custom_fee_reason' => 'Loyalty discount',
            'approved_by_admin_id' => $owner->id,
        ]);

        $membershipB = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'member_id' => $memberB->id,
            'membership_plan_id' => $planB->id,
            'start_date' => now()->subDays(40)->toDateString(),
            'expiry_date' => now()->subDays(2)->toDateString(),
            'status' => 'expired',
            'default_plan_price' => 3500,
            'default_joining_fee' => 500,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 500,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 4000,
            'amount_paid' => 4000,
            'due_amount' => 0,
            'due_date' => now()->subDays(10)->toDateString(),
            'payment_status' => 'paid',
            'approved_by_admin_id' => $owner->id,
        ]);

        Payment::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'member_membership_id' => $membershipA->id,
            'member_id' => $memberA->id,
            'amount' => 1000,
            'payment_mode' => 'cash',
            'status' => 'recorded',
            'payment_status' => 'paid',
            'paid_at' => now()->subDay(),
            'payment_date' => now()->subDay(),
            'receipt_number' => 'RCT-A',
        ]);

        Payment::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'member_membership_id' => $membershipB->id,
            'member_id' => $memberB->id,
            'amount' => 4000,
            'payment_mode' => 'upi',
            'status' => 'recorded',
            'payment_status' => 'paid',
            'paid_at' => now()->subDays(3),
            'payment_date' => now()->subDays(3),
            'receipt_number' => 'RCT-B',
        ]);

        AttendanceLog::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'member_id' => $memberA->id,
            'checked_in_by' => $trainerA->id,
            'check_in_method' => 'manual',
            'checked_in_at' => now(),
        ]);

        AttendanceLog::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'member_id' => $memberB->id,
            'checked_in_by' => $trainerB->id,
            'check_in_method' => 'qr',
            'checked_in_at' => now()->subDay(),
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'name' => 'Lead Alpha',
            'phone' => '9999999991',
            'email' => 'lead-a@example.com',
            'preferred_date' => now()->toDateString(),
            'assigned_trainer_id' => $trainerA->id,
            'status' => 'pending',
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'name' => 'Lead Beta',
            'phone' => '9999999992',
            'email' => 'lead-b@example.com',
            'preferred_date' => now()->subDay()->toDateString(),
            'assigned_trainer_id' => $trainerB->id,
            'status' => 'converted',
        ]);

        CustomFeeAuditLog::query()->create([
            'gym_id' => $gym->id,
            'member_id' => $memberA->id,
            'member_membership_id' => $membershipA->id,
            'old_values' => ['final_payable_amount' => 3500],
            'new_values' => ['final_payable_amount' => 3100],
            'changed_by' => $owner->id,
            'reason' => 'Loyalty discount',
            'changed_at' => now()->subDay(),
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branchA, $branchB], []);

        return [$owner, $gym, $branchA, $branchB, $trainerA, $memberA, $memberB, $planA];
    }

    private function createMember(Gym $gym, Branch $branch, string $name, string $email, int $trainerId): User
    {
        $member = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainerId,
            'membership_status' => 'active',
            'membership_expires_on' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($member, $gym, [$branch], []);

        return $member;
    }

    /**
     * @param  list<Branch>  $branches
     * @param  list<string>  $customPermissions
     */
    private function attachToGymAndBranches(User $user, Gym $gym, array $branches, array $customPermissions): void
    {
        $encoded = json_encode($customPermissions);
        $gymPayload = [
            'custom_permissions' => $encoded,
            'is_primary' => true,
        ];

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, $gymPayload);
        } else {
            $user->gyms()->attach($gym->id, $gymPayload);
        }

        foreach ($branches as $branch) {
            $branchPayload = [
                'custom_permissions' => $encoded,
                'is_primary' => false,
            ];

            if ($user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, $branchPayload);
            } else {
                $user->branches()->attach($branch->id, $branchPayload);
            }
        }
    }

    private function loginGymUser(User $user): void
    {
        $this->post('/gym/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));
    }
}
