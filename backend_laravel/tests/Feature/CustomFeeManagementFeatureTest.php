<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFeeManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_view_custom_fee_tabs_and_update_custom_fee_without_mutating_plan_price(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'custom-fee-member@example.com');
        [$gym, $branch, $plan, $membership] = $this->makeScopedMembership($owner, $member);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);
        $this->loginGymUser($owner);

        $this->get(route('web.gym.custom-fees.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Custom Fees')
            ->assertSee('No custom fee memberships');

        $this->get(route('web.gym.custom-fees.audit-logs', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Custom Fee Audit Logs');

        $this->post(route('web.gym.members.custom-fee.update', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]), [
            'member_membership_id' => $membership->id,
            'custom_fee_enabled' => 1,
            'custom_fee_amount' => 1800,
            'discount_type' => 'fixed',
            'discount_amount' => 100,
            'custom_joining_fee' => 50,
            'joining_fee_waived' => 0,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'custom_fee_reason' => 'Retention pricing',
        ])->assertRedirect(route('web.gym.members.custom-fee', [
            'member' => $member->id,
            'member_membership_id' => $membership->id,
        ]));

        $membership->refresh();
        $plan->refresh();

        $this->get(route('web.gym.custom-fees.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('Retention pricing');

        $this->assertTrue((bool) $membership->custom_fee_enabled);
        $this->assertSame('Retention pricing', $membership->custom_fee_reason);
        $this->assertSame(2000.0, (float) $plan->plan_price);
        $this->assertSame(1850.0, (float) $membership->final_payable_amount);
        $this->assertSame(1650.0, (float) $membership->due_amount);
        $this->assertDatabaseHas('custom_fee_audit_logs', [
            'member_membership_id' => $membership->id,
            'reason' => 'Retention pricing',
        ]);
    }

    public function test_staff_permission_is_enforced_for_custom_fee_alias_routes(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'staff-fee-member@example.com');
        [$gym, $branch, , $membership] = $this->makeScopedMembership($owner, $member);

        $staff = $this->makeRoleUser(RoleName::GymStaff->value);
        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);
        $this->attachToGym($staff, $gym, [$branch], []);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/gym/members/'.$member->id.'/custom-fee', [
                'member_membership_id' => $membership->id,
                'custom_fee_enabled' => true,
                'custom_fee_amount' => 1500,
                'custom_fee_reason' => 'Unauthorized change',
            ], $headers)
            ->assertForbidden();

        $this->attachToGym($staff, $gym, [$branch], ['edit_custom_fee']);

        $this->actingAs($staff->fresh(), 'sanctum')
            ->postJson('/api/gym/members/'.$member->id.'/custom-fee', [
                'member_membership_id' => $membership->id,
                'custom_fee_enabled' => true,
                'custom_fee_amount' => 1500,
                'discount_type' => 'none',
                'discount_amount' => 0,
                'custom_joining_fee' => 100,
                'joining_fee_waived' => false,
                'partial_month_fee' => 0,
                'pt_custom_fee' => 0,
                'due_date' => now()->addDay()->toDateString(),
                'custom_fee_reason' => 'Allowed change',
            ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.custom_fee_reason', 'Allowed change');
    }

    public function test_api_custom_fee_list_and_member_detail_endpoints_work(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'api-custom-fee-member@example.com');
        [$gym, $branch, , $membership] = $this->makeScopedMembership($owner, $member);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);

        $membership->update([
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 1700,
            'custom_fee_reason' => 'API preload',
        ]);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/custom-fees?gym_id='.$gym->id.'&branch_id='.$branch->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/members/'.$member->id.'/custom-fee?gym_id='.$gym->id.'&branch_id='.$branch->id.'&member_membership_id='.$membership->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.memberships.0.id', $membership->id);
    }

    private function makeScopedMembership(User $owner, User $member): array
    {
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Custom Fee Gym',
            'slug' => 'custom-fee-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'custom-fee-main-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

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
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 100,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2000,
            'default_joining_fee' => 100,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 100,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2100,
            'amount_paid' => 200,
            'due_amount' => 1900,
            'due_date' => now()->addDays(3)->toDateString(),
            'payment_status' => 'partial',
            'approved_by_admin_id' => $owner->id,
        ]);

        return [$gym, $branch, $plan, $membership];
    }

    private function attachToGym(User $user, Gym $gym, array $branches = [], array $customPermissions = []): void
    {
        $payload = [
            'custom_permissions' => json_encode($customPermissions),
            'is_primary' => true,
        ];

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, $payload);
        } else {
            $user->gyms()->attach($gym->id, $payload);
        }

        foreach ($branches as $branch) {
            $branchPayload = [
                'custom_permissions' => json_encode($customPermissions),
                'is_primary' => false,
            ];

            if ($user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, $branchPayload);
            } else {
                $user->branches()->attach($branch->id, $branchPayload);
            }
        }
    }

    private function loginGymUser(User $user, string $password = 'secret123'): void
    {
        $this->post('/gym/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('web.gym.dashboard'));
    }

    private function makeRoleUser(string $role, string $password = 'secret123', ?string $email = null): User
    {
        $user = User::factory()->create([
            'password' => $password,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'is_active' => true,
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
