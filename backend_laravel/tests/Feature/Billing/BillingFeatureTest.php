<?php

namespace Tests\Feature\Billing;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_update_custom_fee_when_permitted(): void
    {
        [$manager, $membership] = $this->makeScopedMembership(RoleName::BranchManager->value);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/gym/member-memberships/{$membership->id}/custom-fee", [
                'custom_fee_enabled' => true,
                'custom_fee_amount' => 1800,
                'discount_type' => 'fixed',
                'discount_amount' => 100,
                'custom_joining_fee' => 200,
                'joining_fee_waived' => false,
                'partial_month_fee' => 0,
                'pt_custom_fee' => 300,
                'due_date' => now()->addDays(2)->toDateString(),
                'custom_fee_reason' => 'Retention offer',
            ], [
                'X-Gym-Id' => (string) $membership->gym_id,
                'X-Branch-Id' => (string) $membership->branch_id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.custom_fee_enabled', true)
            ->assertJsonPath('data.custom_fee_reason', 'Retention offer');

        $membership->refresh();

        $this->assertSame('Retention offer', $membership->custom_fee_reason);
        $this->assertGreaterThan(0, $membership->customFeeAuditLogs()->count());
    }

    public function test_gym_staff_cannot_edit_custom_fee_without_permission_path(): void
    {
        [$staff, $membership] = $this->makeScopedMembership(RoleName::GymStaff->value);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/gym/member-memberships/{$membership->id}/custom-fee", [
                'custom_fee_enabled' => true,
                'custom_fee_amount' => 1700,
                'custom_fee_reason' => 'Unauthorized edit attempt',
            ], [
                'X-Gym-Id' => (string) $membership->gym_id,
                'X-Branch-Id' => (string) $membership->branch_id,
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_partial_payment_updates_membership_balance(): void
    {
        [$manager, $membership] = $this->makeScopedMembership(RoleName::BranchManager->value);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/gym/member-memberships/{$membership->id}/payments", [
                'amount' => 1000,
                'payment_mode' => 'upi',
                'notes' => 'Advance',
            ], [
                'X-Gym-Id' => (string) $membership->gym_id,
                'X-Branch-Id' => (string) $membership->branch_id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 1000);

        $membership->refresh();

        $this->assertSame('partial', $membership->payment_status);
        $this->assertEquals(1000.0, (float) $membership->amount_paid);
        $this->assertEquals(1500.0, (float) $membership->due_amount);
    }

    public function test_membership_assignment_with_initial_payment_creates_payment_record(): void
    {
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Enrollment Gym',
            'slug' => 'enrollment-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Enrollment Branch',
            'slug' => 'enrollment-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $gym->users()->syncWithoutDetaching([$owner->id => ['is_primary' => true], $member->id => ['is_primary' => false]]);
        $branch->users()->syncWithoutDetaching([$owner->id => ['is_primary' => true], $member->id => ['is_primary' => false]]);

        $member->memberProfile()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Quarterly Plan',
            'duration_days' => 90,
            'plan_price' => 3000,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/member-memberships', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'amount_paid' => 500,
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_paid', 500)
            ->assertJsonPath('data.due_amount', 2700);

        $membership = MemberMembership::query()->latest('id')->firstOrFail();

        $this->assertSame(500.0, (float) $membership->amount_paid);
        $this->assertSame(2700.0, (float) $membership->due_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_is_blocked_for_cancelled_membership(): void
    {
        [$manager, $membership] = $this->makeScopedMembership(RoleName::BranchManager->value, [
            'status' => 'cancelled',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/gym/member-memberships/{$membership->id}/payments", [
                'amount' => 500,
                'payment_mode' => 'cash',
            ], [
                'X-Gym-Id' => (string) $membership->gym_id,
                'X-Branch-Id' => (string) $membership->branch_id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.membership.0', 'Payments cannot be recorded for a cancelled membership.');
    }

    public function test_cross_gym_billing_access_is_blocked(): void
    {
        [$manager, $membership] = $this->makeScopedMembership(RoleName::GymOwner->value);
        $otherGym = Gym::query()->create([
            'name' => 'Other Gym',
            'slug' => 'other-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/gym/membership-plans', [
                'gym_id' => $otherGym->id,
                'name' => 'Unauthorized Plan',
                'duration_days' => 30,
                'plan_price' => 1000,
                'joining_fee' => 0,
                'pt_included' => false,
                'status' => 'active',
            ], [
                'X-Gym-Id' => (string) $membership->gym_id,
                'X-Branch-Id' => (string) $membership->branch_id,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Requested gym scope does not match the authenticated gym scope.');
    }

    private function makeScopedMembership(string $activeRole, array $membershipOverrides = []): array
    {
        $this->seed(PermissionSeeder::class);

        $manager = User::factory()->create();
        $manager->forceFill(['active_role' => $activeRole])->save();
        $manager->assignRole($activeRole);

        $member = User::factory()->create();
        $member->forceFill(['active_role' => RoleName::Member->value])->save();
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $manager->id,
            'name' => 'Scoped Gym',
            'slug' => 'scoped-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Scoped Branch',
            'slug' => 'scoped-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $managerPermissions = match ($activeRole) {
            RoleName::BranchManager->value => ['view_billing', 'collect_payment', 'edit_custom_fee'],
            RoleName::GymStaff->value => [],
            default => [],
        };

        $gym->users()->syncWithoutDetaching([
            $manager->id => ['is_primary' => true, 'custom_permissions' => json_encode($managerPermissions)],
            $member->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $manager->id => ['is_primary' => true, 'custom_permissions' => json_encode($managerPermissions)],
            $member->id => ['is_primary' => false],
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly Plan',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $manager->id,
        ]);

        $membership = MemberMembership::query()->create(array_merge([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
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
            'due_date' => now()->addDays(5)->toDateString(),
            'payment_status' => 'unpaid',
            'approved_by_admin_id' => $manager->id,
        ], $membershipOverrides));

        return [$manager, $membership];
    }
}
