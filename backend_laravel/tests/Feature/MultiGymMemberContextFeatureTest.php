<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\PersonalRecord;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutPlan;
use App\Services\Members\MemberGymInvitationService;
use App\Services\Users\ManagedUserService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiGymMemberContextFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_switch_between_two_gym_relationships_with_separate_trainers(): void
    {
        config()->set('services.realtime.internal_api_key', 'multi-gym-internal-test-key');
        $this->seed(PermissionSeeder::class);
        $member = $this->user(RoleName::Member->value, 'multi-member@example.com');
        $ownerA = $this->user(RoleName::GymOwner->value, 'owner-a@example.com');
        $ownerB = $this->user(RoleName::GymOwner->value, 'owner-b@example.com');
        $trainerA = $this->user(RoleName::Trainer->value, 'trainer-a@example.com');
        $trainerB = $this->user(RoleName::Trainer->value, 'trainer-b@example.com');
        [$gymA, $branchA] = $this->gymScope($ownerA, 'North Club');
        [$gymB, $branchB] = $this->gymScope($ownerB, 'South Club');
        $this->trainerProfile($trainerA, $gymA, $branchA);
        $this->trainerProfile($trainerB, $gymB, $branchB);

        $invitationA = app(MemberGymInvitationService::class)->invite($ownerA, $member, $gymA, [
            'branch_id' => $branchA->id,
            'assigned_trainer_user_id' => $trainerA->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        app(MemberGymInvitationService::class)->accept($member, $invitationA);
        $invitationB = app(MemberGymInvitationService::class)->invite($ownerB, $member, $gymB, [
            'branch_id' => $branchB->id,
            'assigned_trainer_user_id' => $trainerB->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        app(MemberGymInvitationService::class)->accept($member, $invitationB);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymA->id,
            'assigned_trainer_user_id' => $trainerA->id,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymB->id,
            'assigned_trainer_user_id' => $trainerB->id,
        ]);

        $gymAHeaders = [
            'X-Gym-Id' => (string) $gymA->id,
            'X-Branch-Id' => (string) $branchA->id,
        ];
        $this->actingAs($ownerA, 'sanctum')
            ->getJson('/api/gym/members', $gymAHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.member_profile.gym_id', $gymA->id)
            ->assertJsonPath('data.0.member_profile.assigned_trainer_user_id', $trainerA->id);
        $this->actingAs($ownerA, 'sanctum')
            ->getJson('/api/gym/members?trainer_id='.$trainerB->id, $gymAHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context', ['X-Gym-Id' => (string) $gymA->id])
            ->assertOk()
            ->assertJsonPath('data.selected_gym_id', $gymA->id)
            ->assertJsonPath('data.has_multiple_gyms', true)
            ->assertJsonCount(2, 'data.gym_relationships')
            ->assertJsonPath('data.member_profile.current_gym.id', $gymA->id)
            ->assertJsonPath('data.trainer_connection.assigned_trainer.id', $trainerA->id)
            ->assertJsonPath('data.gym_relationships.0.is_selected', true);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context', ['X-Gym-Id' => (string) $gymB->id])
            ->assertOk()
            ->assertJsonPath('data.selected_gym_id', $gymB->id)
            ->assertJsonPath('data.member_profile.current_gym.id', $gymB->id)
            ->assertJsonPath('data.trainer_connection.assigned_trainer.id', $trainerB->id);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context', ['X-Gym-Id' => '999999'])
            ->assertOk()
            ->assertJsonPath('data.selected_gym_id', $gymB->id);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/trainer', ['X-Gym-Id' => (string) $gymA->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_trainer.id', $trainerA->id);
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/trainer', ['X-Gym-Id' => (string) $gymB->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_trainer.id', $trainerB->id);

        $realtimeContext = $this->actingAs($member, 'sanctum')
            ->getJson('/api/public/realtime/context')
            ->assertOk()
            ->json('data');
        $this->assertEqualsCanonicalizing(
            [$trainerA->id, $trainerB->id],
            $realtimeContext['assigned_trainer_ids'],
        );

        $member->forceFill(['accepted_chat_terms_at' => now()])->save();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/chat/messages?recipient_id='.$trainerA->id, ['X-Gym-Id' => (string) $gymB->id])
            ->assertOk();
        $this->postJson('/api/internal/chat/messages', [
            'room' => "trainer:{$trainerA->id}:member:{$member->id}",
            'trainer_id' => $trainerA->id,
            'member_id' => $member->id,
            'sender_id' => $member->id,
            'recipient_id' => $trainerA->id,
            'message' => 'North gym update',
            'client_message_id' => 'multi-gym-north-1',
            'recipient_active_in_chat' => true,
        ], [
            'X-Internal-Api-Key' => config('services.realtime.internal_api_key'),
        ])->assertCreated();

        $planA = WorkoutPlan::query()->create($this->workoutPayload($gymA, $branchA, $member, $trainerA, 'North plan'));
        $planB = WorkoutPlan::query()->create($this->workoutPayload($gymB, $branchB, $member, $trainerB, 'South plan'));

        WeightLog::query()->create([
            'gym_id' => $gymA->id,
            'branch_id' => $branchA->id,
            'member_id' => $member->id,
            'logged_by_user_id' => $trainerA->id,
            'log_date' => today(),
            'weight_kg' => 71,
        ]);
        WeightLog::query()->create([
            'gym_id' => $gymB->id,
            'branch_id' => $branchB->id,
            'member_id' => $member->id,
            'logged_by_user_id' => $trainerB->id,
            'log_date' => today(),
            'weight_kg' => 82,
        ]);

        $this->actingAs($trainerA, 'sanctum')
            ->getJson('/api/trainer/assigned-members/'.$member->id, [
                'X-Gym-Id' => (string) $gymA->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.gym_name', $gymA->name)
            ->assertJsonPath('data.branch_name', $branchA->name)
            ->assertJsonPath('data.gym.id', $gymA->id)
            ->assertJsonPath('data.branch.id', $branchA->id);

        $this->actingAs($trainerA, 'sanctum')
            ->getJson('/api/trainer/assigned-members/'.$member->id.'/progress', [
                'X-Gym-Id' => (string) $gymA->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.weight_logs')
            ->assertJsonPath('data.weight_logs.0.weight_kg', 71);

        $trainerPlans = $this->actingAs($trainerA, 'sanctum')
            ->getJson('/api/trainer/assigned-members/'.$member->id.'/workout-plans', [
                'X-Gym-Id' => (string) $gymA->id,
                'X-Branch-Id' => (string) $branchA->id,
            ])
            ->assertOk()
            ->json('data');
        $this->assertSame([$planA->id], collect($trainerPlans)->pluck('id')->all());

        $exercise = Exercise::query()->create([
            'name' => 'Shared bench press',
            'muscle_group' => 'chest',
            'is_global' => true,
            'status' => 'active',
            'is_active' => true,
        ]);
        PersonalRecord::query()->create([
            'gym_id' => $gymA->id,
            'branch_id' => $branchA->id,
            'member_id' => $member->id,
            'exercise_id' => $exercise->id,
            'best_weight' => 60,
            'best_reps' => 8,
            'best_volume' => 480,
        ]);
        PersonalRecord::query()->create([
            'gym_id' => $gymB->id,
            'branch_id' => $branchB->id,
            'member_id' => $member->id,
            'exercise_id' => $exercise->id,
            'best_weight' => 70,
            'best_reps' => 6,
            'best_volume' => 420,
        ]);
        $this->assertSame(2, PersonalRecord::query()
            ->where('member_id', $member->id)
            ->where('exercise_id', $exercise->id)
            ->count());

        $northPlans = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans', ['X-Gym-Id' => (string) $gymA->id])
            ->assertOk()
            ->json('data');
        $this->assertSame([$planA->id], collect($northPlans)->pluck('id')->all());
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans/'.$planB->id, ['X-Gym-Id' => (string) $gymA->id])
            ->assertUnprocessable();

        $replacementTrainerA = $this->user(RoleName::Trainer->value, 'trainer-a2@example.com');
        $this->trainerProfile($replacementTrainerA, $gymA, $branchA);
        app(ManagedUserService::class)->upsertMember($member, $gymA, [
            'name' => $member->name,
            'email' => $member->email,
            'branch_id' => $branchA->id,
            'assigned_trainer_user_id' => $replacementTrainerA->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->assertSame($replacementTrainerA->id, MemberProfile::query()
            ->where('user_id', $member->id)->where('gym_id', $gymA->id)
            ->value('assigned_trainer_user_id'));
        $this->assertSame($trainerB->id, MemberProfile::query()
            ->where('user_id', $member->id)->where('gym_id', $gymB->id)
            ->value('assigned_trainer_user_id'));

        app(ManagedUserService::class)->setMemberActive($member->fresh(), $gymA, false);
        $this->assertTrue((bool) $member->fresh()->is_active);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymA->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymB->id,
            'is_active' => true,
        ]);
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/profile', ['X-Gym-Id' => (string) $gymA->id])
            ->assertForbidden();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/context', ['X-Gym-Id' => (string) $gymA->id])
            ->assertOk()
            ->assertJsonPath('data.selected_gym_id', $gymB->id);
        app(ManagedUserService::class)->setMemberActive($member->fresh(), $gymA, true);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/membership/leave', [], ['X-Gym-Id' => '999999'])
            ->assertForbidden();
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymA->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymB->id,
            'is_active' => true,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/membership/leave', [], ['X-Gym-Id' => (string) $gymB->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'gym_member')
            ->assertJsonPath('data.remaining_gym_count', 1);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymA->id,
            'is_active' => true,
            'assigned_trainer_user_id' => $replacementTrainerA->id,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gymB->id,
            'is_active' => false,
            'membership_status' => 'cancelled',
            'assigned_trainer_user_id' => null,
        ]);
    }

    private function user(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'active_role' => $role,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{Gym, Branch} */
    private function gymScope(User $owner, string $name): array
    {
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(5),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => $name.' Main',
            'slug' => str($name)->slug().'-main-'.str()->random(5),
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branch];
    }

    private function trainerProfile(User $trainer, Gym $gym, Branch $branch): void
    {
        $trainer->gyms()->syncWithoutDetaching([$gym->id => [
            'branch_id' => $branch->id,
            'role_name' => RoleName::Trainer->value,
            'status' => 'active',
            'is_primary' => true,
        ]]);
        $trainer->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);
    }

    /** @return array<string, mixed> */
    private function workoutPayload(Gym $gym, Branch $branch, User $member, User $trainer, string $name): array
    {
        return [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id,
            'name' => $name,
            'duration_weeks' => 4,
            'weekly_schedule' => [],
            'status' => 'active',
            'plan_origin' => 'trainer_assigned',
            'is_member_editable' => false,
            'assigned_at' => now(),
        ];
    }
}
