<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\MemberGymInvitation;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_create_member_with_new_user(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-member@example.com');
        $trainer = $this->makeUser(RoleName::Trainer->value, 'trainer-member@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Member Gym',
            'slug' => 'member-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'member-main-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->attachToGymAndBranches($trainer, $gym, [$branch]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'specializations' => ['Strength'],
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Starter Monthly',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $this->loginGymUser($owner);

        $this->post(route('web.gym.members.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Riya Member',
            'email' => 'riya-member@example.com',
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Fat Loss',
            'status' => 'active',
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_paid' => 1000,
            'custom_fee_enabled' => 1,
            'custom_fee_amount' => 2200,
            'custom_joining_fee' => 300,
            'custom_fee_reason' => 'Launch offer',
        ])->assertRedirect();

        $member = User::query()->where('email', 'riya-member@example.com')->firstOrFail();
        $this->assertTrue($member->hasRole(RoleName::Member->value));
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Fat Loss',
        ]);
        $this->assertDatabaseHas('member_memberships', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_plan_id' => $plan->id,
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 2200,
            'amount_paid' => 1000,
            'custom_fee_reason' => 'Launch offer',
        ]);
    }

    public function test_existing_user_can_become_member(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-existing-member@example.com');
        $trainer = $this->makeUser(RoleName::Trainer->value, 'trainer-existing-member@example.com');
        $existingUser = User::factory()->create([
            'email' => 'existing-member@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $existingUser->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Existing Member Gym',
            'slug' => 'existing-member-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Existing Branch',
            'slug' => 'existing-member-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $sourceGym = Gym::query()->create([
            'name' => 'Source Profile Gym',
            'slug' => 'source-profile-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $sourceBranch = Branch::query()->create([
            'gym_id' => $sourceGym->id,
            'name' => 'Source Branch',
            'slug' => 'source-profile-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $existingUser->id,
            'gym_id' => $sourceGym->id,
            'branch_id' => $sourceBranch->id,
            'fitness_goal' => 'Build Muscle',
            'experience_level' => 'Intermediate',
            'height_cm' => 178,
            'weight_kg' => 76,
            'medical_notes' => 'No restrictions',
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Accepted Monthly',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->attachToGymAndBranches($trainer, $gym, [$branch]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'specializations' => ['Strength'],
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.members.store', ['gym' => $gym->id]), [
            'existing_user_id' => $existingUser->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'status' => 'active',
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_paid' => 2000,
        ])->assertRedirect()
            ->assertSessionHas('status', 'Membership invitation sent to existing-member@example.com. The member must accept before they are added to this gym.');

        $existingUser->refresh();
        $this->assertDatabaseMissing('member_profiles', [
            'user_id' => $existingUser->id,
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseHas('member_gym_invitations', [
            'invited_user_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'pending',
        ]);

        $invitation = MemberGymInvitation::query()
            ->where('invited_user_id', $existingUser->id)
            ->where('gym_id', $gym->id)
            ->firstOrFail();

        $this->actingAs($existingUser, 'sanctum')
            ->postJson('/api/member/gym-invitations/'.$invitation->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Build Muscle',
            'experience_level' => 'Intermediate',
            'height_cm' => 178,
            'weight_kg' => 76,
            'medical_notes' => 'No restrictions',
        ]);
        $this->assertDatabaseHas('member_memberships', [
            'member_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_plan_id' => $plan->id,
            'amount_paid' => 2000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);
        $membership = MemberMembership::query()
            ->where('member_id', $existingUser->id)
            ->where('gym_id', $gym->id)
            ->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'member_membership_id' => $membership->id,
            'member_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'amount' => 2000,
            'payment_mode' => 'cash',
            'status' => 'recorded',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($existingUser, 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.user_state', 'gym_member_with_trainer')
            ->assertJsonPath('data.member_profile.current_branch.id', $branch->id)
            ->assertJsonPath('data.member_profile.assigned_trainer.id', $trainer->id)
            ->assertJsonPath('data.trainer_connection.assigned_trainer.id', $trainer->id);

        $this->actingAs($existingUser, 'sanctum')
            ->getJson('/api/member/membership')
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 2000)
            ->assertJsonPath('data.due_amount', 0)
            ->assertJsonPath('data.assigned_trainer.id', $trainer->id);
    }

    public function test_non_member_existing_user_cannot_be_invited_as_member(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-block-existing@example.com');
        $trainer = $this->makeUser(RoleName::Trainer->value, 'trainer-block-existing@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Block Existing Gym',
            'slug' => 'block-existing-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Block Branch',
            'slug' => 'block-existing-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.members.store', ['gym' => $gym->id]), [
            'existing_user_id' => $trainer->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ])->assertSessionHasErrors('existing_user_id');

        $this->assertDatabaseMissing('member_gym_invitations', [
            'invited_user_id' => $trainer->id,
            'gym_id' => $gym->id,
        ]);
    }

    public function test_assign_trainer_and_filters_work(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-filter-member@example.com');
        $trainer = $this->makeUser(RoleName::Trainer->value, 'trainer-filter-member@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Filter Member Gym',
            'slug' => 'filter-member-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Filter Branch',
            'slug' => 'filter-member-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->attachToGymAndBranches($trainer, $gym, [$branch]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Fat Loss',
            'specializations' => ['Fat Loss'],
            'status' => 'active',
            'is_active' => true,
        ]);

        $memberA = $this->makeUser(RoleName::Member->value, 'member-a-filter@example.com');
        $memberB = $this->makeUser(RoleName::Member->value, 'member-b-filter@example.com');

        MemberProfile::query()->create([
            'user_id' => $memberA->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $memberB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'expired',
            'membership_expires_on' => now()->subDay()->toDateString(),
            'is_active' => false,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Elite Plan',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $memberA->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDays(2)->toDateString(),
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
            'amount_paid' => 1000,
            'due_amount' => 1500,
            'due_date' => now()->subDay()->toDateString(),
            'payment_status' => 'overdue',
        ]);

        $this->loginGymUser($owner);

        $this->post(route('web.gym.members.assign-trainer', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $memberA->id]), [
            'assigned_trainer_user_id' => $trainer->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $memberA->id,
            'assigned_trainer_user_id' => $trainer->id,
        ]);

        $this->get(route('web.gym.members.index', ['gym' => $gym->id, 'status' => 'overdue']))
            ->assertOk()
            ->assertSee('member-a-filter@example.com');

        $this->get(route('web.gym.members.index', ['gym' => $gym->id, 'trainer_id' => $trainer->id]))
            ->assertOk()
            ->assertSee('member-a-filter@example.com');
    }

    public function test_member_detail_page_loads_and_api_member_actions_work(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-api-member@example.com');
        $trainer = $this->makeUser(RoleName::Trainer->value, 'trainer-api-member@example.com');
        $member = $this->makeUser(RoleName::Member->value, 'member-api-member@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Member Gym',
            'slug' => 'api-member-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'API Member Branch',
            'slug' => 'api-member-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->attachToGymAndBranches($trainer, $gym, [$branch]);
        $this->attachToGymAndBranches($member, $gym, [$branch]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'General Fitness',
            'specializations' => ['General Fitness'],
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

        $this->loginGymUser($owner);
        $this->get(route('web.gym.members.show', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]))
            ->assertOk();

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/members/'.$member->id.'/assign-trainer', [
                'assigned_trainer_user_id' => $trainer->id,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/members/'.$member->id.'/deactivate', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function attachToGymAndBranches(User $user, Gym $gym, array $branches): void
    {
        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, [
                'role_name' => $user->getRoleNames()->first(),
                'status' => 'active',
                'is_primary' => true,
            ]);
        } else {
            $user->gyms()->attach($gym->id, [
                'role_name' => $user->getRoleNames()->first(),
                'status' => 'active',
                'is_primary' => true,
            ]);
        }

        foreach ($branches as $branch) {
            $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => false]]);
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
