<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\DietPlan;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\IndependentTrainerMemberRelationship;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\PersonalRecord;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndependentCoachingPlanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_and_independent_coaching_coexist_without_changing_gym_assignment(): void
    {
        [$member, $gymTrainer, $independentTrainer, $gym, $branch, $relationship] = $this->coexistingPair();

        $gymWorkout = WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $gymTrainer->id,
            'created_by_user_id' => $gymTrainer->id,
            'name' => 'Gym strength plan',
        ]));
        $independentWorkout = WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => null,
            'branch_id' => null,
            'member_id' => $member->id,
            'trainer_id' => $independentTrainer->id,
            'created_by_user_id' => $independentTrainer->id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            'name' => 'Independent mobility plan',
        ]));

        $gymDiet = DietPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $gymTrainer->id,
            'created_by_user_id' => $gymTrainer->id,
            'name' => 'Gym diet',
            'status' => 'active',
        ]);
        $independentDiet = DietPlan::query()->create([
            'gym_id' => null,
            'branch_id' => null,
            'member_id' => $member->id,
            'trainer_id' => $independentTrainer->id,
            'created_by_user_id' => $independentTrainer->id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            'name' => 'Independent diet',
            'status' => 'active',
        ]);
        MemberProfile::query()
            ->where('user_id', $member->id)
            ->where('gym_id', $gym->id)
            ->update([
                'fitness_goal' => 'Build strength',
                'height_cm' => 178,
                'weight_kg' => 76,
                'experience_level' => 'intermediate',
                'injury_notes' => 'Protect left shoulder',
            ]);

        $workouts = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans')
            ->assertOk()
            ->json('data');
        $this->assertEqualsCanonicalizing(
            [$gymWorkout->id, $independentWorkout->id],
            collect($workouts)->pluck('id')->all(),
        );

        $diets = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/diet-plans')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 15)
            ->json('data');
        $this->assertEqualsCanonicalizing(
            [$gymDiet->id, $independentDiet->id],
            collect($diets)->pluck('id')->all(),
        );

        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$relationship->id)
            ->assertOk()
            ->assertJsonPath('data.member_profile.fitness_goal', 'Build strength')
            ->assertJsonPath('data.member_profile.height_cm', 178)
            ->assertJsonPath('data.member_profile.weight_kg', 76)
            ->assertJsonPath('data.member_profile.injury_notes', 'Protect left shoulder')
            ->assertJsonMissingPath('data.member_profile.gym_id')
            ->assertJsonMissingPath('data.membership_summary')
            ->assertJsonMissingPath('data.attendance_summary');
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/diet-plans?member_id='.$member->id.'&independent_trainer_member_relationship_id='.$relationship->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $independentDiet->id);

        $relationship->update(['sharing_permissions' => ['workouts', 'chat']]);
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$relationship->id)
            ->assertOk()
            ->assertJsonMissingPath('data.member_profile');
        $relationship->update(['sharing_permissions' => ['profile', 'workouts', 'diets', 'progress', 'chat']]);

        $conversations = $this->actingAs($member, 'sanctum')
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->json('data');
        $this->assertEqualsCanonicalizing(
            [$gymTrainer->id, $independentTrainer->id],
            collect($conversations)->pluck('trainer_id')->all(),
        );

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => $gymTrainer->id,
        ]);
    }

    public function test_revocation_hides_independent_scope_but_preserves_gym_scope(): void
    {
        [$member, $gymTrainer, $independentTrainer, $gym, $branch, $relationship] = $this->coexistingPair();

        $gymWorkout = WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $gymTrainer->id,
            'created_by_user_id' => $gymTrainer->id,
            'name' => 'Gym plan remains',
        ]));
        WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => null,
            'branch_id' => null,
            'member_id' => $member->id,
            'trainer_id' => $independentTrainer->id,
            'created_by_user_id' => $independentTrainer->id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            'name' => 'Revoked independent plan',
        ]));

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainers/'.$relationship->id.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $plans = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans')
            ->assertOk()
            ->json('data');
        $this->assertSame([$gymWorkout->id], collect($plans)->pluck('id')->all());

        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/chat/messages?recipient_id='.$member->id)
            ->assertUnprocessable();

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => $gymTrainer->id,
        ]);
    }

    public function test_verified_independent_trainer_assigns_workout_and_diet_through_existing_plan_apis(): void
    {
        [$member, , $independentTrainer, , , $relationship] = $this->coexistingPair();
        $exercise = Exercise::query()->create([
            'created_by_user_id' => $independentTrainer->id,
            'name' => 'Independent Bodyweight Squat',
            'muscle_group' => 'legs',
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->actingAs($independentTrainer, 'sanctum')
            ->postJson('/api/trainer/workout-plans', [
                'independent_trainer_member_relationship_id' => $relationship->id,
                'member_ids' => [$member->id],
                'name' => 'Independent API workout',
                'duration_weeks' => 4,
                'days' => [[
                    'day_number' => 1,
                    'exercises' => [[
                        'exercise_id' => $exercise->id,
                        'sets' => 3,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.coaching_scope', 'independent')
            ->assertJsonPath('data.0.independent_trainer_member_relationship_id', $relationship->id);

        $this->actingAs($independentTrainer, 'sanctum')
            ->postJson('/api/trainer/diet-plans', [
                'independent_trainer_member_relationship_id' => $relationship->id,
                'member_ids' => [$member->id],
                'name' => 'Independent API diet',
                'meals' => [[
                    'name' => 'Breakfast',
                    'items' => [['name' => 'Oats', 'quantity' => '1 bowl']],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.coaching_scope', 'independent')
            ->assertJsonPath('data.0.independent_trainer_member_relationship_id', $relationship->id);

        $this->assertDatabaseHas('workout_plans', [
            'member_id' => $member->id,
            'gym_id' => null,
            'independent_trainer_member_relationship_id' => $relationship->id,
        ]);
        $this->assertDatabaseHas('diet_plans', [
            'member_id' => $member->id,
            'gym_id' => null,
            'independent_trainer_member_relationship_id' => $relationship->id,
        ]);
    }

    public function test_same_verified_gym_trainer_manages_gym_and_personal_members_in_parallel(): void
    {
        [, $trainer, , $gym] = $this->coexistingPair();
        $personalMember = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $personalMember->assignRole(RoleName::Member->value);
        $relationship = IndependentTrainerMemberRelationship::query()->create([
            'trainer_user_id' => $trainer->id,
            'member_user_id' => $personalMember->id,
            'invited_email' => $personalMember->email,
            'status' => 'active',
            'sharing_permissions' => ['profile', 'workouts', 'diets', 'chat'],
            'accepted_at' => now(),
        ]);
        $exercise = Exercise::query()->create([
            'name' => 'Parallel coaching squat',
            'muscle_group' => 'legs',
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-members')
            ->assertOk()
            ->assertJsonPath('data.0.member.id', $personalMember->id);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/workout-plans', [
                'independent_trainer_member_relationship_id' => $relationship->id,
                'member_ids' => [$personalMember->id],
                'name' => 'Personal plan while gym assigned',
                'duration_weeks' => 4,
                'days' => [[
                    'day_number' => 1,
                    'exercises' => [['exercise_id' => $exercise->id, 'sets' => 3]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.gym_id', null)
            ->assertJsonPath('data.0.independent_trainer_member_relationship_id', $relationship->id);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/diet-plans', [
                'independent_trainer_member_relationship_id' => $relationship->id,
                'member_ids' => [$personalMember->id],
                'name' => 'Personal diet while gym assigned',
                'meals' => [['name' => 'Breakfast', 'items' => [['name' => 'Oats']]]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.gym_id', null)
            ->assertJsonPath('data.0.independent_trainer_member_relationship_id', $relationship->id);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonFragment(['member_id' => $personalMember->id]);

        $this->assertSame($gym->id, $trainer->managedTrainerProfile->fresh()->gym_id);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $relationship->id,
            'status' => 'active',
        ]);
    }

    public function test_capabilities_and_revocation_are_enforced_across_discovery_chat_progress_and_notes(): void
    {
        [$member, $gymTrainer, $independentTrainer, , , $relationship] = $this->coexistingPair();
        $relationship->update(['sharing_permissions' => ['profile']]);

        $workout = WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => null,
            'branch_id' => null,
            'member_id' => $member->id,
            'trainer_id' => $independentTrainer->id,
            'created_by_user_id' => $independentTrainer->id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            'name' => 'Restricted workout',
        ]));
        $diet = DietPlan::query()->create([
            'gym_id' => null,
            'branch_id' => null,
            'member_id' => $member->id,
            'trainer_id' => $independentTrainer->id,
            'created_by_user_id' => $independentTrainer->id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            'name' => 'Restricted diet',
            'status' => 'active',
        ]);

        $memberWorkouts = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans')
            ->assertOk()
            ->json('data');
        $this->assertNotContains($workout->id, collect($memberWorkouts)->pluck('id')->all());
        $memberDiets = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/diet-plans')
            ->assertOk()
            ->json('data');
        $this->assertNotContains($diet->id, collect($memberDiets)->pluck('id')->all());

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-plans?independent_trainer_member_relationship_id='.$relationship->id)
            ->assertUnprocessable();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/chat/messages?recipient_id='.$independentTrainer->id)
            ->assertUnprocessable();
        $conversationTrainerIds = collect($this->actingAs($member, 'sanctum')
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->json('data'))
            ->pluck('trainer_id')
            ->all();
        $this->assertContains($gymTrainer->id, $conversationTrainerIds);
        $this->assertNotContains($independentTrainer->id, $conversationTrainerIds);
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$relationship->id.'/progress')
            ->assertUnprocessable();

        $noteId = $this->actingAs($independentTrainer, 'sanctum')
            ->postJson('/api/trainer/independent-members/'.$relationship->id.'/notes', [
                'note' => 'Private independent note',
                'visibility' => 'gym_admin_visible',
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'private_to_trainer')
            ->json('data.id');
        $this->actingAs($independentTrainer, 'sanctum')
            ->putJson('/api/trainer/notes/'.$noteId, [
                'note' => 'Updated independent note',
                'visibility' => 'gym_admin_visible',
            ])
            ->assertOk()
            ->assertJsonPath('data.note', 'Updated independent note')
            ->assertJsonPath('data.visibility', 'private_to_trainer');
        $this->actingAs($independentTrainer, 'sanctum')
            ->postJson('/api/trainer/notes/'.$noteId.'/complete')
            ->assertOk()
            ->assertJson(fn ($json) => $json->whereType('data.completed_at', 'string')->etc());
        $pendingNoteId = $this->actingAs($independentTrainer, 'sanctum')
            ->postJson('/api/trainer/independent-members/'.$relationship->id.'/notes', [
                'note' => 'Follow up before revocation',
                'follow_up_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->json('data.id');
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/pending-follow-ups')
            ->assertOk()
            ->assertJsonFragment(['id' => $pendingNoteId]);

        $relationship->update(['sharing_permissions' => ['profile', 'workouts', 'diets', 'progress', 'chat']]);
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainers/'.$relationship->id.'/revoke')
            ->assertOk();

        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/workout-plans')
            ->assertOk()
            ->assertJsonMissing(['id' => $workout->id]);
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/diet-plans')
            ->assertOk()
            ->assertJsonMissing(['id' => $diet->id]);
        $this->actingAs($independentTrainer, 'sanctum')
            ->putJson('/api/trainer/notes/'.$noteId, ['note' => 'Must fail after revoke'])
            ->assertUnprocessable();
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/pending-follow-ups')
            ->assertOk()
            ->assertJsonMissing(['id' => $pendingNoteId]);
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/tasks')
            ->assertOk()
            ->assertJsonPath('data.pending_follow_ups_count', 0);
    }

    public function test_independent_private_workout_templates_are_isolated_between_trainers(): void
    {
        [, , $trainer] = $this->coexistingPair();
        $otherTrainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $otherTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $otherTrainer->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);
        $privateTemplate = WorkoutTemplate::query()->create([
            'created_by_user_id' => $otherTrainer->id,
            'name' => 'Other trainer private template',
            'duration_weeks' => 4,
            'status' => 'active',
            'is_public_catalog' => false,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/workout-templates')
            ->assertOk()
            ->assertJsonMissing(['id' => $privateTemplate->id]);
        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/workout-templates/'.$privateTemplate->id)
            ->assertUnprocessable();
    }

    public function test_personal_records_are_isolated_between_independent_trainer_relationships(): void
    {
        [$member, , $firstTrainer, , , $firstRelationship] = $this->coexistingPair();
        $secondTrainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $secondTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $secondTrainer->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);
        $secondRelationship = IndependentTrainerMemberRelationship::query()->create([
            'trainer_user_id' => $secondTrainer->id,
            'member_user_id' => $member->id,
            'invited_email' => $member->email,
            'status' => 'active',
            'sharing_permissions' => ['profile', 'progress'],
            'accepted_at' => now(),
        ]);
        $exercise = Exercise::query()->create([
            'name' => 'Relationship scoped press',
            'muscle_group' => 'chest',
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);

        PersonalRecord::query()->create([
            'member_id' => $member->id,
            'exercise_id' => $exercise->id,
            'coaching_scope_key' => PersonalRecord::coachingScopeKey(null, null, $firstRelationship->id),
            'best_weight' => 45,
        ]);
        PersonalRecord::query()->create([
            'member_id' => $member->id,
            'exercise_id' => $exercise->id,
            'coaching_scope_key' => PersonalRecord::coachingScopeKey(null, null, $secondRelationship->id),
            'best_weight' => 65,
        ]);

        $this->actingAs($firstTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$firstRelationship->id.'/progress')
            ->assertOk()
            ->assertJsonCount(1, 'data.personal_records')
            ->assertJsonPath('data.personal_records.0.best_weight', 45)
            ->assertJsonPath('meta.personal_records_pagination.current_page', 1);
        $this->actingAs($secondTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$secondRelationship->id.'/progress')
            ->assertOk()
            ->assertJsonCount(1, 'data.personal_records')
            ->assertJsonPath('data.personal_records.0.best_weight', 65)
            ->assertJsonPath('meta.personal_records_pagination.current_page', 1);
    }

    public function test_member_removes_only_the_gym_trainer_assignment(): void
    {
        [$member, $gymTrainer, $independentTrainer, $gym, , $relationship] = $this->coexistingPair();

        $this->actingAs($member, 'sanctum')
            ->deleteJson('/api/member/trainer-assignment')
            ->assertOk()
            ->assertJsonPath('data.removed', true)
            ->assertJsonPath('data.previous_trainer_user_id', $gymTrainer->id)
            ->assertJsonPath('data.assigned_trainer', null)
            ->assertJsonPath('data.independent_relationships_unchanged', true);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => null,
            'assigned_trainer_id' => null,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('member_memberships', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $relationship->id,
            'trainer_user_id' => $independentTrainer->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'member.gym_trainer.removed',
            'actor_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $gymTrainer->id,
            'type' => 'trainer_assignment_removed',
        ]);

        $this->actingAs($gymTrainer, 'sanctum')
            ->getJson('/api/chat/messages?recipient_id='.$member->id)
            ->assertUnprocessable();
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/chat/messages?recipient_id='.$member->id)
            ->assertOk();
    }

    public function test_cancellation_revokes_gym_access_and_same_gym_reenrollment_starts_clean(): void
    {
        [$member, $oldGymTrainer, $independentTrainer, $gym, $branch, $relationship] = $this->coexistingPair();
        $owner = User::query()->findOrFail($gym->owner_user_id);
        $membership = MemberMembership::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gym->id)
            ->where('status', 'active')
            ->firstOrFail();
        $oldWorkout = WorkoutPlan::query()->create($this->workoutPayload([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $oldGymTrainer->id,
            'created_by_user_id' => $oldGymTrainer->id,
            'name' => 'Previous membership workout',
        ]));
        $oldDiet = DietPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $oldGymTrainer->id,
            'created_by_user_id' => $oldGymTrainer->id,
            'name' => 'Previous membership diet',
            'status' => 'active',
            'assigned_at' => now(),
        ]);
        $oldSession = WorkoutSession::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $oldGymTrainer->id,
            'workout_plan_id' => $oldWorkout->id,
            'started_by_user_id' => $member->id,
            'session_date' => today(),
            'status' => 'active',
            'started_at' => now(),
        ]);
        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/memberships/'.$membership->id.'/cancel', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => null,
            'membership_status' => 'cancelled',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('workout_plans', ['id' => $oldWorkout->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('diet_plans', ['id' => $oldDiet->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('workout_sessions', ['id' => $oldSession->id, 'status' => 'cancelled']);
        $this->assertDatabaseMissing('gym_user', ['gym_id' => $gym->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('branch_user', ['branch_id' => $branch->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $relationship->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => 'membership_cancelled',
            'member_membership_id' => $membership->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $oldGymTrainer->id,
            'type' => 'trainer_assignment_removed',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/members/'.$member->id, $headers)
            ->assertNotFound();
        $this->actingAs($oldGymTrainer, 'sanctum')
            ->getJson('/api/trainer/assigned-members/'.$member->id)
            ->assertUnprocessable();
        $this->actingAs($oldGymTrainer, 'sanctum')
            ->putJson('/api/trainer/workout-plans/'.$oldWorkout->id, ['name' => 'Must not mutate'])
            ->assertUnprocessable();
        $this->actingAs($independentTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$relationship->id)
            ->assertOk();

        $newGymTrainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $newGymTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $newGymTrainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);
        $owner->gyms()->syncWithoutDetaching([$gym->id => ['role_name' => RoleName::GymOwner->value, 'status' => 'active', 'is_primary' => true]]);
        $owner->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
        $newGymTrainer->gyms()->syncWithoutDetaching([$gym->id => ['role_name' => RoleName::Trainer->value, 'status' => 'active', 'is_primary' => true]]);
        $newGymTrainer->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);

        $invitationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/members', [
                'existing_user_id' => $member->id,
                'branch_id' => $branch->id,
                'assigned_trainer_user_id' => $newGymTrainer->id,
                'membership_plan_id' => $membership->membership_plan_id,
                'start_date' => today()->toDateString(),
                'amount_paid' => 0,
            ], $headers)
            ->assertStatus(202)
            ->json('data.invitation_id');
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/gym-invitations/'.$invitationId.'/accept')
            ->assertOk();

        $this->assertDatabaseHas('member_memberships', ['id' => $membership->id, 'status' => 'cancelled']);
        $this->assertSame(1, MemberMembership::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gym->id)
            ->where('status', 'active')
            ->count());
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $newGymTrainer->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('independent_trainer_member_relationships', ['id' => $relationship->id, 'status' => 'active']);
        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.member_profile.current_gym.id', $gym->id)
            ->assertJsonPath('data.trainer_connection.assigned_trainer.id', $newGymTrainer->id);
        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/member/workout-plans')
            ->assertOk()
            ->assertJsonMissing(['id' => $oldWorkout->id]);
        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/member/diet-plans')
            ->assertOk()
            ->assertJsonMissing(['id' => $oldDiet->id]);
        $this->actingAs($oldGymTrainer, 'sanctum')
            ->getJson('/api/trainer/workout-plans/'.$oldWorkout->id)
            ->assertUnprocessable();
    }

    /** @return array{User, User, User, Gym, Branch, IndependentTrainerMemberRelationship} */
    private function coexistingPair(): array
    {
        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Coexist Gym',
            'slug' => 'coexist-gym-'.Str::lower(Str::random(8)),
            'approval_status' => 'approved',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main',
            'slug' => 'main-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
        ]);
        $owner->gyms()->syncWithoutDetaching([$gym->id => ['role_name' => RoleName::GymOwner->value, 'status' => 'active', 'is_primary' => true]]);
        $owner->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);

        $gymTrainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $gymTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $gymTrainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);
        $gymTrainer->gyms()->syncWithoutDetaching([$gym->id => ['role_name' => RoleName::Trainer->value, 'status' => 'active', 'is_primary' => true]]);
        $gymTrainer->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);

        $independentTrainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $independentTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $independentTrainer->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'verified',
        ]);

        $member = User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $gymTrainer->id,
            'assigned_trainer_id' => $gymTrainer->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $member->gyms()->syncWithoutDetaching([$gym->id => ['role_name' => RoleName::Member->value, 'status' => 'active', 'is_primary' => true]]);
        $member->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
        $membershipPlan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Coexist Membership',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 0,
            'status' => 'active',
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $membershipPlan->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'status' => 'active',
            'default_plan_price' => 2000,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2000,
            'amount_paid' => 0,
            'due_amount' => 2000,
            'payment_status' => 'unpaid',
        ]);

        $relationship = IndependentTrainerMemberRelationship::query()->create([
            'trainer_user_id' => $independentTrainer->id,
            'member_user_id' => $member->id,
            'invited_email' => $member->email,
            'status' => 'active',
            'sharing_permissions' => ['profile', 'workouts', 'diets', 'progress', 'chat'],
            'accepted_at' => now(),
        ]);

        return [$member, $gymTrainer, $independentTrainer, $gym, $branch, $relationship];
    }

    /** @param array<string, mixed> $overrides */
    private function workoutPayload(array $overrides): array
    {
        return [
            'duration_weeks' => 4,
            'weekly_schedule' => [],
            'status' => 'active',
            'plan_origin' => 'trainer_assigned',
            'is_member_editable' => false,
            'assigned_at' => now(),
            ...$overrides,
        ];
    }
}
