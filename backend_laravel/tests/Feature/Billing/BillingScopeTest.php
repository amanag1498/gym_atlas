<?php

namespace Tests\Feature\Billing;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\MembershipPricingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_only_sees_memberships_for_the_assigned_branch(): void
    {
        $this->seed(PermissionSeeder::class);

        [$gym, $branchA, $branchB] = $this->makeGymWithBranches();

        $manager = User::factory()->create([
            'active_role' => RoleName::BranchManager->value,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $manager->gyms()->attach($gym->id);
        $manager->branches()->attach($branchA->id);

        $memberA = User::factory()->create();
        $memberA->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $memberA->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $memberB = User::factory()->create();
        $memberB->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $memberB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $planA = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'name' => 'Branch A Plan',
            'duration_days' => 30,
            'plan_price' => 1200,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $planB = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'name' => 'Branch B Plan',
            'duration_days' => 30,
            'plan_price' => 1400,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $this->createMembership($gym, $branchA, $memberA, $planA);
        $otherMembership = $this->createMembership($gym, $branchB, $memberB, $planB);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/member-memberships');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $branchA->id);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($otherMembership->id, $returnedIds);
    }

    public function test_gym_staff_cannot_edit_custom_fee_without_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        [$gym, $branch] = $this->makeGymWithBranches(singleBranch: true);

        $staff = User::factory()->create([
            'active_role' => RoleName::GymStaff->value,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $staff->gyms()->attach($gym->id);
        $staff->branches()->attach($branch->id);

        $member = User::factory()->create();
        $member->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Standard',
            'duration_days' => 30,
            'plan_price' => 1500,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $membership = $this->createMembership($gym, $branch, $member, $plan);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/gym/member-memberships/{$membership->id}/custom-fee", [
                'custom_fee_enabled' => true,
                'custom_fee_amount' => 1000,
                'custom_fee_reason' => 'Unauthorized discount attempt',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You do not have the required permission.');
    }

    /**
     * @return array{0: Gym, 1: Branch, 2?: Branch}
     */
    private function makeGymWithBranches(bool $singleBranch = false): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Billing Gym',
            'slug' => 'billing-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        if ($singleBranch) {
            return [$gym, $branchA];
        }

        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branchA, $branchB];
    }

    private function createMembership(Gym $gym, Branch $branch, User $member, MembershipPlan $plan): MemberMembership
    {
        $pricing = app(MembershipPricingService::class)->buildMembershipPayload($plan, []);

        return MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'due_date' => now()->toDateString(),
            'approved_by_admin_id' => $member->id,
            ...$pricing,
        ]);
    }
}
