<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDiscoveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_discovery_shows_only_public_enabled_approved_gyms(): void
    {
        Gym::query()->create([
            'name' => 'Public Gym',
            'slug' => 'public-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);

        Gym::query()->create([
            'name' => 'Private Gym',
            'slug' => 'private-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'pending',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => false,
            'public_listing_approval_status' => 'pending',
        ]);

        $this->getJson('/api/public/discovery/gyms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'public-gym');
    }

    public function test_public_discovery_api_supports_search_and_listing_filters(): void
    {
        Gym::query()->create([
            'name' => 'Verified Feature Gym',
            'slug' => 'verified-feature-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'is_verified' => true,
            'is_featured' => true,
        ]);

        Gym::query()->create([
            'name' => 'Unverified Gym',
            'slug' => 'unverified-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'is_verified' => false,
            'is_featured' => false,
        ]);

        $this->getJson('/api/public/discovery/gyms?search=Verified&verified_only=1&featured_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'verified-feature-gym');
    }

    public function test_member_can_view_only_own_profile_and_membership_data(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create(['name' => 'Primary Member']);
        $member->forceFill(['active_role' => RoleName::Member->value])->save();
        $member->assignRole(RoleName::Member->value);

        $otherMember = User::factory()->create(['name' => 'Other Member']);
        $otherMember->forceFill(['active_role' => RoleName::Member->value])->save();
        $otherMember->assignRole(RoleName::Member->value);

        $trainer = User::factory()->create(['name' => 'Assigned Trainer']);
        $trainer->forceFill(['active_role' => RoleName::Trainer->value])->save();
        $trainer->assignRole(RoleName::Trainer->value);

        $gym = Gym::query()->create([
            'name' => 'Member Gym',
            'slug' => 'member-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $member->id => ['is_primary' => true],
            $otherMember->id => ['is_primary' => false],
            $trainer->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $member->id => ['is_primary' => true],
            $otherMember->id => ['is_primary' => false],
            $trainer->id => ['is_primary' => false],
        ]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Fat loss',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        MemberProfile::query()->create([
            'user_id' => $otherMember->id,
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
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $trainer->id,
        ]);

        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(1)->toDateString(),
            'expiry_date' => now()->addDays(29)->toDateString(),
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
            'amount_paid' => 1000,
            'due_amount' => 1500,
            'due_date' => now()->addDays(5)->toDateString(),
            'payment_status' => 'partial',
            'approved_by_admin_id' => $trainer->id,
        ]);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/profile', $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Primary Member')
            ->assertJsonPath('data.assigned_trainer.name', 'Assigned Trainer');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/membership', $headers)
            ->assertOk()
            ->assertJsonPath('data.current_gym.slug', 'member-gym')
            ->assertJsonPath('data.assigned_trainer.name', 'Assigned Trainer')
            ->assertJsonMissing(['name' => 'Other Member']);
    }

    public function test_trial_request_access_is_scoped_for_owner_manager_and_trainer(): void
    {
        $this->seed(PermissionSeeder::class);

        $owner = $this->makeUserWithRole(RoleName::GymOwner->value);
        $manager = $this->makeUserWithRole(RoleName::BranchManager->value);
        $trainer = $this->makeUserWithRole(RoleName::Trainer->value);
        $otherOwner = $this->makeUserWithRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trial Gym',
            'slug' => 'trial-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $otherGym = Gym::query()->create([
            'owner_user_id' => $otherOwner->id,
            'name' => 'Other Gym',
            'slug' => 'other-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $otherBranch = Branch::query()->create([
            'gym_id' => $otherGym->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => true],
            $manager->id => ['is_primary' => false],
            $trainer->id => ['is_primary' => false],
        ]);
        $branchA->users()->syncWithoutDetaching([
            $manager->id => ['is_primary' => true],
            $trainer->id => ['is_primary' => false],
        ]);
        $branchB->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => false],
        ]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $assignedTrial = TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'name' => 'Assigned Lead',
            'phone' => '1111111111',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '18:00:00',
            'status' => 'pending',
            'assigned_trainer_id' => $trainer->id,
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'name' => 'Unassigned Lead',
            'phone' => '2222222222',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time' => '19:00:00',
            'status' => 'pending',
        ]);

        TrialRequest::query()->create([
            'gym_id' => $otherGym->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Outside Lead',
            'phone' => '3333333333',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_time' => '20:00:00',
            'status' => 'pending',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/trial-requests', ['X-Gym-Id' => (string) $gym->id])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/trial-requests', [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch.slug', 'branch-a');

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/trial-requests', [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedTrial->id);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/trial-requests/'.($assignedTrial->id + 1), [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertNotFound();
    }

    public function test_public_gym_detail_shows_only_active_public_gyms(): void
    {
        $visibleGym = Gym::query()->create([
            'name' => 'Visible Gym',
            'slug' => 'visible-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        Branch::query()->create([
            'gym_id' => $visibleGym->id,
            'name' => 'Visible Branch',
            'slug' => 'visible-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        Gym::query()->create([
            'name' => 'Hidden Gym',
            'slug' => 'hidden-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'inactive',
            'approval_status' => 'approved',
            'is_active' => false,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $this->get('/gyms/visible-gym')
            ->assertOk()
            ->assertSee('Visible Gym');

        $this->get('/gyms/hidden-gym')
            ->assertNotFound();
    }

    public function test_public_gym_trial_request_submits_from_web_detail_page(): void
    {
        $owner = User::factory()->create();

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trial Submit Gym',
            'slug' => 'trial-submit-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->post('/gyms/trial-submit-gym/trial-request', [
            'name' => 'Public Lead',
            'phone' => '9999999999',
            'email' => 'lead@example.com',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '18:30',
            'notes' => 'Interested in evening slot',
        ])
            ->assertRedirect('/gyms/trial-submit-gym#request-trial')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('trial_requests', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Public Lead',
            'phone' => '9999999999',
            'status' => 'pending',
        ]);
    }

    public function test_public_trial_request_api_allows_single_branch_without_explicit_branch_or_date(): void
    {
        $owner = User::factory()->create();

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Trial Gym',
            'slug' => 'api-trial-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Solo Branch',
            'slug' => 'solo-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->postJson('/api/public/trial-requests', [
            'gym_id' => $gym->id,
            'name' => 'API Lead',
            'phone' => '8888888888',
        ])
            ->assertCreated()
            ->assertJsonPath('data.gym.slug', 'api-trial-gym')
            ->assertJsonPath('data.branch.slug', 'solo-branch');

        $this->assertDatabaseHas('trial_requests', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'API Lead',
            'status' => 'pending',
        ]);
    }

    public function test_public_gym_trial_request_validates_required_phone_on_web(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Validation Gym',
            'slug' => 'validation-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Only Branch',
            'slug' => 'only-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->from('/gyms/validation-gym')
            ->post('/gyms/validation-gym/trial-request', [
                'name' => 'Missing Phone',
                'phone' => '',
            ])
            ->assertRedirect('/gyms/validation-gym')
            ->assertSessionHasErrors('phone');
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['active_role' => $role])->save();
        $user->assignRole($role);

        return $user;
    }
}
