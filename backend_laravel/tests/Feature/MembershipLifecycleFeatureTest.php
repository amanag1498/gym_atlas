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

class MembershipLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_assign_membership_and_expiry_is_calculated_correctly(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'member-lifecycle@example.com');
        [$gym, $branch] = $this->makeGymWithBranch($owner, 'membership-lifecycle-gym', 'Main Branch');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->attachToGym($member, $gym, [$branch]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Quarterly',
            'duration_days' => 90,
            'plan_price' => 3000,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $this->loginGymUser($owner);

        $startDate = now()->toDateString();

        $this->post(route('web.gym.members.assign-membership.store', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startDate,
            'amount_paid' => 500,
            'due_date' => $startDate,
        ])->assertRedirect(route('web.gym.members.custom-fee', ['member' => $member->id]));

        $membership = MemberMembership::query()->latest('id')->firstOrFail();

        $this->assertSame($member->id, $membership->member_id);
        $this->assertSame(now()->addDays(90)->toDateString(), $membership->expiry_date?->toDateString());
        $this->assertSame(3000.0, (float) $membership->default_plan_price);
        $this->assertSame(200.0, (float) $membership->default_joining_fee);
        $this->assertSame(3200.0, (float) $membership->final_payable_amount);
        $this->assertSame(2700.0, (float) $membership->due_amount);

        $this->get(route('web.gym.memberships.active', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Active Memberships')
            ->assertSee('Quarterly');
    }

    public function test_membership_renew_freeze_extend_and_cancel_work_without_corrupting_old_snapshot(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'renew-member@example.com');
        [$gym, $branch] = $this->makeGymWithBranch($owner, 'renew-membership-gym', 'Renew Branch');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->attachToGym($member, $gym, [$branch]);

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
            'start_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
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
            'amount_paid' => 0,
            'due_amount' => 2100,
            'payment_status' => 'unpaid',
            'due_date' => now()->toDateString(),
            'approved_by_admin_id' => $owner->id,
        ]);

        $this->loginGymUser($owner);

        $this->post(route('web.gym.memberships.freeze', ['gym' => $gym->id, 'branch' => $branch->id, 'membership' => $membership->id]))
            ->assertRedirect();
        $this->assertSame('frozen', $membership->fresh()->status);

        $originalExpiry = $membership->expiry_date?->toDateString();

        $this->post(route('web.gym.memberships.extend', ['gym' => $gym->id, 'branch' => $branch->id, 'membership' => $membership->id]), [
            'extra_days' => 7,
        ])->assertRedirect();

        $membership->refresh();
        $this->assertSame(now()->parse($originalExpiry)->addDays(7)->toDateString(), $membership->expiry_date?->toDateString());

        $plan->update([
            'plan_price' => 2600,
            'joining_fee' => 150,
        ]);

        $renewStart = now()->parse($membership->expiry_date)->addDay()->toDateString();

        $this->post(route('web.gym.memberships.renew', ['gym' => $gym->id, 'branch' => $branch->id, 'membership' => $membership->id]), [
            'start_date' => $renewStart,
            'due_date' => $renewStart,
            'amount_paid' => 0,
        ])->assertRedirect();

        $renewed = MemberMembership::query()->latest('id')->firstOrFail();

        $this->assertNotSame($membership->id, $renewed->id);
        $this->assertSame(2000.0, (float) $membership->fresh()->default_plan_price);
        $this->assertSame(2600.0, (float) $renewed->default_plan_price);
        $this->assertSame(150.0, (float) $renewed->default_joining_fee);

        $this->post(route('web.gym.memberships.cancel', ['gym' => $gym->id, 'branch' => $branch->id, 'membership' => $renewed->id]))
            ->assertRedirect();

        $this->assertSame('cancelled', $renewed->fresh()->status);
    }

    public function test_api_membership_alias_routes_work(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'api-member@example.com');
        [$gym, $branch] = $this->makeGymWithBranch($owner, 'api-membership-gym', 'Api Branch');

        $this->attachToGym($owner, $gym, [$branch]);
        $this->attachToGym($member, $gym, [$branch]);

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
            'name' => 'API Plan',
            'duration_days' => 30,
            'plan_price' => 1500,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/members/'.$member->id.'/assign-membership', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $membership = MemberMembership::query()->latest('id')->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/memberships/'.$membership->id.'/freeze', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/memberships/'.$membership->id.'/extend', [
                'extra_days' => 5,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/memberships/'.$membership->id.'/cancel', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
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
