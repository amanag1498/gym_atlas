<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\FitnessGoal;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLeaveGymFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_leave_current_gym_without_deleting_history(): void
    {
        $this->seed(PermissionSeeder::class);
        [, $member, $gym, $branch, $membership] = $this->makeActiveGymMember();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/membership/leave')
            ->assertOk()
            ->assertJsonPath('data.status', 'independent_user')
            ->assertJsonPath('data.left_gym_id', $gym->id)
            ->assertJsonPath('data.left_branch_id', $branch->id)
            ->assertJsonPath('data.membership_id', $membership->id);

        $this->assertDatabaseHas('member_memberships', [
            'id' => $membership->id,
            'status' => 'cancelled',
            'amount_paid' => 2500,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'cancelled',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => null,
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseMissing('branch_user', [
            'user_id' => $member->id,
            'branch_id' => $branch->id,
        ]);
        $independentProfile = MemberProfile::query()
            ->where('user_id', $member->id)
            ->whereNull('gym_id')
            ->firstOrFail();
        $this->assertSame(['Strength'], $independentProfile->fitnessGoals()->pluck('name')->all());
        $this->assertTrue($member->fresh()->hasRole(RoleName::Member->value));

        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.user_state', 'independent_user')
            ->assertJsonPath('data.current_membership', null)
            ->assertJsonPath('data.member_profile.current_gym', null);
    }

    public function test_gym_admin_can_remove_member_from_gym_without_deleting_history(): void
    {
        $this->seed(PermissionSeeder::class);
        [$owner, $member, $gym, $branch, $membership] = $this->makeActiveGymMember();

        $this->actingAs($owner)
            ->post(route('web.gym.members.remove-from-gym', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'member' => $member->id,
            ]))
            ->assertRedirect(route('web.gym.members.index', [
                'gym' => $gym->id,
                'branch' => $branch->id,
            ]));

        $this->assertDatabaseHas('member_memberships', [
            'id' => $membership->id,
            'status' => 'cancelled',
            'amount_paid' => 2500,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'cancelled',
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseMissing('branch_user', [
            'user_id' => $member->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertTrue($member->fresh()->hasRole(RoleName::Member->value));
    }

    public function test_gym_api_delete_member_uses_safe_remove_flow(): void
    {
        $this->seed(PermissionSeeder::class);
        [$owner, $member, $gym, $branch, $membership] = $this->makeActiveGymMember();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/gym/members/'.$member->id, [], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'independent_user')
            ->assertJsonPath('data.membership_id', $membership->id);

        $this->assertDatabaseHas('member_memberships', [
            'id' => $membership->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'cancelled',
            'is_active' => false,
        ]);
        $this->assertTrue($member->fresh()->hasRole(RoleName::Member->value));
    }

    /**
     * @return array{0: User, 1: User, 2: Gym, 3: Branch, 4: MemberMembership}
     */
    private function makeActiveGymMember(): array
    {
        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Leave Flow Gym',
            'slug' => 'leave-flow-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Leave Flow Branch',
            'slug' => 'leave-flow-branch-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Leave Flow Plan',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 0,
            'status' => 'active',
        ]);
        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);
        $owner->gyms()->attach($gym->id, [
            'role_name' => RoleName::GymOwner->value,
            'status' => 'active',
            'is_primary' => true,
        ]);
        $owner->branches()->attach($branch->id, [
            'is_primary' => true,
        ]);
        $member->gyms()->attach($gym->id);
        $member->branches()->attach($branch->id);

        $goal = FitnessGoal::query()->create([
            'name' => 'Strength',
            'slug' => 'leave-flow-strength',
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        $profile = MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'fitness_goal' => 'General fitness',
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $profile->fitnessGoals()->attach($goal->id);
        $membership = MemberMembership::query()->create([
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

        return [$owner, $member, $gym, $branch, $membership];
    }
}
