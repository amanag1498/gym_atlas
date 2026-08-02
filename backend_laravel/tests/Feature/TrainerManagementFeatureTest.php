<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerEmailInvitation;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Members\TrainerEmailInvitationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('public');

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
            'phone' => '+91 99887 76655',
            'profile_photo' => UploadedFile::fake()->image('coach-arjun.jpg', 200, 200),
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'experience_years' => 4,
            'status' => 'active',
            'bio' => 'Strength coach',
        ])->assertRedirect();

        $invitation = TrainerEmailInvitation::query()
            ->where('invited_email', 'coach-arjun@example.com')
            ->firstOrFail();
        $this->assertSame('pending', $invitation->status);
        app(TrainerEmailInvitationService::class)->respond($invitation, true);

        $trainer = User::query()->where('email', 'coach-arjun@example.com')->firstOrFail();
        $this->assertTrue($trainer->hasRole(RoleName::Trainer->value));
        $this->assertNull($trainer->password);
        $this->assertSame('+91 99887 76655', $trainer->phone);
        $this->assertStringContainsString('/storage/trainer-profile-photos/', $trainer->avatar);
        $this->assertCount(1, Storage::disk('public')->files('trainer-profile-photos'));
        $originalAvatar = $trainer->avatar;

        $this->put(route('web.gym.trainers.update', [
            'gym' => $gym->id,
            'branch' => $branch->id,
            'trainer' => $trainer->id,
        ]), [
            'name' => 'Coach Arjun',
            'email' => 'coach-arjun@example.com',
            'phone' => '+91 99887 70000',
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'experience_years' => 4,
            'status' => 'active',
            'profile_photo' => UploadedFile::fake()->image('coach-arjun-updated.jpg', 200, 200),
        ])->assertRedirect();

        $trainer->refresh();
        $this->assertSame('+91 99887 70000', $trainer->phone);
        $this->assertNotSame($originalAvatar, $trainer->avatar);
        $this->assertCount(2, Storage::disk('public')->files('trainer-profile-photos'));
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

        $this->assertDatabaseMissing('trainer_profiles', [
            'user_id' => $existingUser->id,
            'gym_id' => $gym->id,
        ]);
        $invitation = TrainerEmailInvitation::query()
            ->where('invited_user_id', $existingUser->id)
            ->firstOrFail();
        app(TrainerEmailInvitationService::class)->respondForUser(
            $existingUser,
            $invitation,
            true,
        );

        $existingUser->refresh();
        $this->assertTrue($existingUser->hasRole(RoleName::Trainer->value));
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $existingUser->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Mobility',
        ]);
    }

    public function test_trainer_create_page_lists_free_trainers_and_member_role_users(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-trainer-list@example.com');
        $existingTrainer = $this->makeUser(RoleName::Trainer->value, 'listed-trainer@example.com');
        $existingMember = $this->makeUser(RoleName::Member->value, 'visible-member@example.com');
        $outsideMember = $this->makeUser(RoleName::Member->value, 'outside-member@example.com');

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
            ->assertSee('Search users by name, email, or phone')
            ->assertDontSee('name="password"', false);

        $this->getJson(route('web.gym.trainers.search.eligible-users', [
            'gym' => $gym->id,
            'q' => 'free-trainer',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $freeTrainer->id);

        $this->getJson(route('web.gym.trainers.search.eligible-users', [
            'gym' => $gym->id,
            'q' => 'visible-member',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $existingMember->id);

        $this->getJson(route('web.gym.trainers.search.eligible-users', [
            'gym' => $gym->id,
            'q' => 'listed-trainer',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertTrue($existingMember->hasRole(RoleName::Member->value));
        $this->assertTrue($outsideMember->hasRole(RoleName::Member->value));
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
        $trainer = $this->makeUser(RoleName::Trainer->value, 'api-trainer@example.com');
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'pending',
        ]);
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
                'existing_user_id' => $trainer->id,
                'branch_id' => $branch->id,
                'specialization' => 'Fat Loss',
                'status' => 'active',
            ], $headers)
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.approval_channel', 'in_app');

        $invitationId = (int) $createResponse->json('data.invitation_id');
        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer-invitations/'.$invitationId.'/respond', [
                'decision' => 'accept',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers/'.$trainer->id.'/assign-members', [
                'member_ids' => [$member->id],
            ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'assigned_trainer_user_id' => $trainer->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers/'.$trainer->id.'/deactivate', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.trainer_profile.gym_id', null)
            ->assertJsonPath('data.trainer_profile.is_active', true);

        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => null,
            'assigned_trainer_id' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'type' => 'trainer_assignment_removed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'type' => 'trainer_gym_assignment_removed',
        ]);

        $this->actingAs($trainer->refresh(), 'sanctum')
            ->getJson('/api/trainer/context')
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.gym_id', null)
            ->assertJsonPath('data.trainer_profile.verification_status', 'pending');

        $this->actingAs($trainer->refresh(), 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.is_independent', true)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.verification_status', 'pending');

        $this->actingAs($trainer->refresh(), 'sanctum')
            ->getJson('/api/trainer/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'trainer_gym_assignment_removed');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/trainer', ['X-Gym-Id' => (string) $gym->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_trainer', null)
            ->assertJsonPath('data.enabled', false);
    }

    public function test_legacy_gym_deactivation_is_backfilled_without_overriding_platform_suspension(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-legacy-trainer@example.com');
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Legacy Trainer Gym',
            'slug' => 'legacy-trainer-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Legacy Branch',
            'slug' => 'legacy-trainer-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $gymDeactivated = $this->makeUser(RoleName::Trainer->value, 'gym-deactivated@example.com');
        $platformDeactivated = $this->makeUser(RoleName::Trainer->value, 'platform-deactivated@example.com');

        foreach ([$gymDeactivated, $platformDeactivated] as $trainer) {
            $trainer->forceFill(['is_active' => false])->save();
            $trainer->gyms()->attach($gym->id, ['role_name' => RoleName::Trainer->value, 'status' => 'inactive']);
            $trainer->branches()->attach($branch->id);
            TrainerProfile::query()->create([
                'user_id' => $trainer->id,
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'status' => 'inactive',
                'is_active' => false,
                'verification_status' => 'pending',
            ]);
        }

        ActivityLog::query()->create([
            'actor_user_id' => $owner->id,
            'gym_id' => $gym->id,
            'event' => 'gym.trainer.status.updated',
            'action' => 'update',
            'subject_type' => $gymDeactivated->getMorphClass(),
            'subject_id' => $gymDeactivated->id,
            'new_values' => ['is_active' => false, 'status' => 'inactive'],
            'occurred_at' => now()->subMinute(),
        ]);
        ActivityLog::query()->create([
            'actor_user_id' => $owner->id,
            'event' => 'web.platform.user.deactivated',
            'action' => 'update',
            'subject_type' => $platformDeactivated->getMorphClass(),
            'subject_id' => $platformDeactivated->id,
            'new_values' => ['is_active' => false],
            'occurred_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_02_120000_convert_gym_deactivated_trainers_to_independent.php');
        $migration->up();

        foreach ([$gymDeactivated, $platformDeactivated] as $trainer) {
            $this->assertDatabaseHas('trainer_profiles', [
                'user_id' => $trainer->id,
                'gym_id' => null,
                'branch_id' => null,
                'status' => 'active',
                'is_active' => true,
            ]);
            $this->assertDatabaseMissing('gym_user', ['user_id' => $trainer->id, 'gym_id' => $gym->id]);
            $this->assertDatabaseMissing('branch_user', ['user_id' => $trainer->id, 'branch_id' => $branch->id]);
        }

        $this->assertTrue((bool) $gymDeactivated->fresh()->is_active);
        $this->assertFalse((bool) $platformDeactivated->fresh()->is_active);
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
