<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_create_trainer_and_assign_members_via_web(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-trainer@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer Web Gym',
            'slug' => 'trainer-web-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'trainer-main-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $member = $this->makeUser(RoleName::Member->value, 'member-trainer@example.com');
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.trainers.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Coach Arjun',
            'email' => 'coach-arjun@example.com',
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'experience_years' => 4,
            'status' => 'active',
            'bio' => 'Strength coach',
        ])->assertRedirect();

        $trainer = User::query()->where('email', 'coach-arjun@example.com')->firstOrFail();
        $this->assertTrue($trainer->hasRole(RoleName::Trainer->value));
        $this->assertNull($trainer->password);
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'status' => 'active',
        ]);

        $this->post(route('web.gym.trainers.assign-members', ['gym' => $gym->id, 'branch' => $branch->id, 'trainer' => $trainer->id]), [
            'member_ids' => [$member->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'assigned_trainer_user_id' => $trainer->id,
        ]);
    }

    public function test_existing_user_can_be_assigned_as_trainer(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-existing-trainer@example.com');
        $existingUser = User::factory()->create([
            'email' => 'existing-trainer@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $existingUser->assignRole(RoleName::Trainer->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Existing Trainer Gym',
            'slug' => 'existing-trainer-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'existing-trainer-branch-a',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.trainers.store', ['gym' => $gym->id]), [
            'existing_user_id' => $existingUser->id,
            'branch_id' => $branch->id,
            'specialization' => 'Mobility',
            'status' => 'active',
        ])->assertRedirect();

        $existingUser->refresh();
        $this->assertTrue($existingUser->hasRole(RoleName::Trainer->value));
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Mobility',
        ]);
    }

    public function test_trainer_create_page_only_lists_existing_trainer_users(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-trainer-list@example.com');
        $existingTrainer = $this->makeUser(RoleName::Trainer->value, 'listed-trainer@example.com');
        $existingMember = $this->makeUser(RoleName::Member->value, 'hidden-member@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer List Gym',
            'slug' => 'trainer-list-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Trainer List Branch',
            'slug' => 'trainer-list-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        TrainerProfile::query()->create([
            'user_id' => $existingTrainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Already attached',
            'specializations' => ['Already attached'],
            'status' => 'active',
            'is_active' => true,
        ]);
        $freeTrainer = $this->makeUser(RoleName::Trainer->value, 'free-trainer@example.com');

        $this->attachToGymAndBranches($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->get(route('web.gym.trainers.create', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('free-trainer@example.com')
            ->assertDontSee('listed-trainer@example.com')
            ->assertDontSee('hidden-member@example.com')
            ->assertDontSee('name="password"', false);

        $this->assertTrue($existingMember->hasRole(RoleName::Member->value));
    }

    public function test_branch_manager_scope_applies_to_trainer_views_web_and_api(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-scope-trainer@example.com');
        $manager = $this->makeUser(RoleName::BranchManager->value, 'manager-scope-trainer@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer Scope Gym',
            'slug' => 'trainer-scope-gym-web',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Visible Branch',
            'slug' => 'visible-trainer-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Hidden Branch',
            'slug' => 'hidden-trainer-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainerA = $this->makeUser(RoleName::Trainer->value, 'trainer-a@example.com');
        $trainerB = $this->makeUser(RoleName::Trainer->value, 'trainer-b@example.com');

        TrainerProfile::query()->create([
            'user_id' => $trainerA->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'specialization' => 'Strength',
            'specializations' => ['Strength'],
            'status' => 'active',
            'is_active' => true,
        ]);
        TrainerProfile::query()->create([
            'user_id' => $trainerB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'specialization' => 'Yoga',
            'specializations' => ['Yoga'],
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->attachToGymAndBranches($manager, $gym, [$branchA]);
        $this->attachToGymAndBranches($trainerA, $gym, [$branchA]);
        $this->attachToGymAndBranches($trainerB, $gym, [$branchB]);

        $this->loginGymUser($manager);

        $this->get(route('web.gym.trainers.show', ['gym' => $gym->id, 'branch' => $branchA->id, 'trainer' => $trainerA->id]))
            ->assertOk();
        $this->get(route('web.gym.trainers.show', ['gym' => $gym->id, 'branch' => $branchA->id, 'trainer' => $trainerB->id]))
            ->assertForbidden();

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branchA->id,
        ];

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/trainers/'.$trainerA->id, $headers)
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/trainers/'.$trainerB->id, $headers)
            ->assertForbidden();
    }

    public function test_api_trainer_activation_and_member_assignment_work(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-api-trainer@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer API Gym',
            'slug' => 'trainer-api-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Trainer API Branch',
            'slug' => 'trainer-api-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $member = $this->makeUser(RoleName::Member->value, 'member-api-trainer@example.com');
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch]);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $createResponse = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers', [
                'name' => 'API Trainer',
                'email' => 'api-trainer@example.com',
                'branch_id' => $branch->id,
                'specialization' => 'Fat Loss',
                'status' => 'active',
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $trainerId = (int) $createResponse->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers/'.$trainerId.'/assign-members', [
                'member_ids' => [$member->id],
            ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'assigned_trainer_user_id' => $trainerId,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers/'.$trainerId.'/deactivate', [], $headers)
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
