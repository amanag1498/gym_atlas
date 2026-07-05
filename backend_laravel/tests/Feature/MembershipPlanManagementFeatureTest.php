<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_create_edit_and_deactivate_membership_plan_via_web(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        [$gym, $branch] = $this->makeGymWithBranch($owner, 'membership-plans-gym', 'Main Branch');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->get(route('web.gym.membership-plans.create', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Create Membership Plan');

        $this->post(route('web.gym.membership-plans.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Quarterly',
            'duration_days' => 90,
            'plan_price' => 3000,
            'joining_fee' => 200,
            'pt_included' => false,
            'description' => 'Quarterly transformation plan',
            'status' => 'active',
        ])->assertRedirect();

        $plan = MembershipPlan::query()->where('gym_id', $gym->id)->where('name', 'Quarterly')->firstOrFail();

        $this->get(route('web.gym.membership-plans.show', ['gym' => $gym->id, 'plan' => $plan->id]))
            ->assertOk()
            ->assertSee('Quarterly');

        $this->put(route('web.gym.membership-plans.update', ['gym' => $gym->id, 'plan' => $plan->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Quarterly Prime',
            'duration_days' => 120,
            'plan_price' => 3600,
            'joining_fee' => 250,
            'pt_included' => true,
            'description' => 'Updated quarterly plan',
            'status' => 'active',
        ])->assertRedirect(route('web.gym.membership-plans.show', ['gym' => $gym->id, 'plan' => $plan->id]));

        $plan->refresh();
        $this->assertSame('Quarterly Prime', $plan->name);
        $this->assertSame('active', $plan->status);
        $this->assertTrue((bool) $plan->pt_included);

        $this->post(route('web.gym.membership-plans.deactivate', ['gym' => $gym->id, 'plan' => $plan->id]))
            ->assertRedirect();

        $this->assertSame('inactive', $plan->fresh()->status);
    }

    public function test_membership_plan_update_does_not_corrupt_existing_membership_snapshot_and_plan_appears_in_assign_form(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'member-plan@example.com');
        [$gym, $branch] = $this->makeGymWithBranch($owner, 'snapshot-membership-gym', 'Snapshot Branch');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->attachToGym($member, $gym, [$branch]);
        $this->loginGymUser($owner);

        $member->memberProfile()->create([
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
            'description' => 'Monthly plan',
            'status' => 'active',
            'created_by_user_id' => $owner->id,
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
            'default_joining_fee' => 100,
            'custom_fee_enabled' => false,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2100,
            'amount_paid' => 500,
            'due_amount' => 1600,
            'payment_status' => 'partial',
        ]);

        $this->put(route('web.gym.membership-plans.update', ['gym' => $gym->id, 'plan' => $plan->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly Updated',
            'duration_days' => 30,
            'plan_price' => 2600,
            'joining_fee' => 150,
            'pt_included' => false,
            'description' => 'Updated monthly plan',
            'status' => 'active',
        ])->assertRedirect();

        $membership->refresh();
        $this->assertSame(2000.0, (float) $membership->default_plan_price);
        $this->assertSame(100.0, (float) $membership->default_joining_fee);
        $this->assertSame(2100.0, (float) $membership->final_payable_amount);

        $this->get(route('web.gym.members.assign-membership', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]))
            ->assertOk()
            ->assertSee('Monthly Updated');
    }

    public function test_branch_manager_can_manage_branch_specific_plan_only_when_permitted(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $manager = $this->makeRoleUser(RoleName::BranchManager->value, 'manager-secret', 'branch-manager-plan@example.com');
        $manager->givePermissionTo(PermissionName::MembershipPlansManage->value);

        [$gym, $branch] = $this->makeGymWithBranch($owner, 'branch-manager-plan-gym', 'Branch One');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->attachToGym($manager, $gym, [$branch]);
        $this->loginGymUser($manager, 'manager-secret');

        $gymWidePlan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Gym Wide',
            'duration_days' => 30,
            'plan_price' => 1500,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $branchPlan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Branch Plan',
            'duration_days' => 45,
            'plan_price' => 2200,
            'joining_fee' => 50,
            'pt_included' => true,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $this->post(route('web.gym.membership-plans.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'gym_id' => $gym->id,
            'branch_id' => null,
            'name' => 'Forbidden Gym Plan',
            'duration_days' => 60,
            'plan_price' => 2800,
            'joining_fee' => 100,
            'pt_included' => false,
            'status' => 'active',
        ])->assertForbidden();

        $this->get(route('web.gym.membership-plans.edit', ['gym' => $gym->id, 'branch' => $branch->id, 'plan' => $gymWidePlan->id]))
            ->assertForbidden();

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/gym/membership-plans/'.$branchPlan->id.'/deactivate', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/gym/membership-plans/'.$branchPlan->id.'/activate', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/gym/membership-plans/'.$gymWidePlan->id.'/deactivate', [], $headers)
            ->assertForbidden();
    }

    private function makeGymWithBranch(User $owner, string $gymSlug, string $branchName): array
    {
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => ucwords(str_replace('-', ' ', $gymSlug)),
            'slug' => $gymSlug,
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => $branchName,
            'slug' => strtolower(str_replace(' ', '-', $branchName)).'-'.$gym->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branch];
    }

    private function attachToGym(User $user, Gym $gym, array $branches = []): void
    {
        $user->gyms()->syncWithoutDetaching([$gym->id => ['is_primary' => true]]);

        foreach ($branches as $branch) {
            $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => false]]);
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
