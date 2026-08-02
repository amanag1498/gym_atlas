<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Mail\IndependentTrainerMemberInvitationMail;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\IndependentTrainerMemberInvitation;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndependentCoachingRelationshipFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_verified_independent_trainer_and_existing_member_complete_consent_without_changing_gym_assignment(): void
    {
        $trainer = $this->makeTrainer('verified-independent@example.com', verified: true);
        $member = $this->makeMember('member-with-gym@example.com');
        [$gym, $branch, $gymTrainer] = $this->makeGymScope();
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

        $response = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => $member->name,
                'email' => $member->email,
                'message' => 'I would like to coach you.',
                'sharing_permissions' => ['profile', 'workouts', 'diets'],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.source', 'independent')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.approval_channel', 'app');

        $invitationId = (int) $response->json('data.id');
        $relationshipId = (int) $response->json('data.relationship_id');
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/independent-trainer-invitations')
            ->assertOk()
            ->assertJsonPath('data.0.relationship_id', $relationshipId)
            ->assertJsonPath('data.0.trainer.verification_status', 'verified');

        $otherMember = $this->makeMember('other-member@example.com');
        $this->actingAs($otherMember, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertNotFound();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.relationship_id', $relationshipId)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.source', 'independent');

        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $relationshipId,
            'trainer_user_id' => $trainer->id,
            'member_user_id' => $member->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => $gymTrainer->id,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.is_independent', true)
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.relationships.0.relationship_id', $relationshipId);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainers/'.$relationshipId.'/revoke', ['reason' => 'Coaching completed.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'assigned_trainer_user_id' => $gymTrainer->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'independent_trainer_member.accepted',
            'subject_id' => $relationshipId,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'independent_trainer_member.revoked',
            'subject_id' => $relationshipId,
        ]);
    }

    public function test_pending_trainer_is_blocked_but_verified_gym_trainer_can_invite_personal_members(): void
    {
        $member = $this->makeMember('blocked-member@example.com');
        $pendingTrainer = $this->makeTrainer('pending-trainer@example.com', verified: false);

        $this->actingAs($pendingTrainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', ['name' => $member->name, 'email' => $member->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trainer');

        [$gym, $branch] = $this->makeGymScope();
        $gymTrainer = $this->makeTrainer('gym-trainer-blocked@example.com', verified: true, gym: $gym, branch: $branch);
        $this->actingAs($gymTrainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', ['name' => $member->name, 'email' => $member->email])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('independent_trainer_member_invitations', 1);
        $this->actingAs($gymTrainer, 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.is_independent', false)
            ->assertJsonPath('data.has_gym_assignment', true)
            ->assertJsonPath('data.can_invite_personal_members', true);
    }

    public function test_member_can_decline_and_invitation_cannot_be_replayed(): void
    {
        $trainer = $this->makeTrainer('decline-independent@example.com', verified: true);
        $member = $this->makeMember('declining-member@example.com');
        $invitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', ['name' => $member->name, 'email' => $member->email])
            ->assertStatus(202)
            ->json('data.id');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertUnprocessable();
    }

    public function test_invitation_cannot_be_accepted_after_trainer_loses_independent_verification(): void
    {
        $trainer = $this->makeTrainer('suspended-after-invite@example.com', verified: true);
        $member = $this->makeMember('member-after-suspension@example.com');
        $invitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', ['name' => $member->name, 'email' => $member->email])
            ->assertStatus(202)
            ->json('data.id');

        $trainer->managedTrainerProfile()->update(['verification_status' => 'suspended']);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trainer');
        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonCount(1, 'data.relationships')
            ->assertJsonPath('data.relationships.0.access_active', false)
            ->assertJsonCount(1, 'data.invitations');
        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-members')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trainer');
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'trainer_user_id' => $trainer->id,
            'member_user_id' => $member->id,
            'status' => 'pending',
        ]);
    }

    public function test_email_invitation_creates_independent_member_only_after_signed_consent(): void
    {
        Mail::fake();
        $trainer = $this->makeTrainer('email-invite-trainer@example.com', verified: true);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => 'Future Member',
                'email' => 'future-member@example.com',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.approval_channel', 'email');

        $invitation = IndependentTrainerMemberInvitation::query()->firstOrFail();
        $this->assertDatabaseMissing('users', ['email' => 'future-member@example.com']);

        $reviewUrl = null;
        Mail::assertSent(IndependentTrainerMemberInvitationMail::class, function ($mail) use (&$reviewUrl): bool {
            $reviewUrl = $mail->reviewUrl;

            return $mail->hasTo('future-member@example.com');
        });
        $this->assertNotNull($reviewUrl);
        $this->get($reviewUrl)->assertOk()->assertSee('separate from all gym memberships');
        $this->post($reviewUrl, ['decision' => 'accept'])->assertRedirect()->assertSessionHas('status');

        $member = User::query()->where('email', 'future-member@example.com')->firstOrFail();
        $this->assertTrue($member->hasRole(RoleName::Member->value));
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $invitation->relationship_id,
            'trainer_user_id' => $trainer->id,
            'member_user_id' => $member->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('member_profiles', ['user_id' => $member->id]);
        $this->assertDatabaseMissing('gym_user', ['user_id' => $member->id]);
    }

    public function test_reinvite_after_revocation_preserves_the_previous_relationship_history(): void
    {
        $trainer = $this->makeTrainer('reinvite-trainer@example.com', verified: true);
        $member = $this->makeMember('reinvited-member@example.com');

        $firstInvitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->assertStatus(202)
            ->json('data.id');
        $firstRelationshipId = IndependentTrainerMemberInvitation::query()->findOrFail($firstInvitationId)->relationship_id;

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$firstInvitationId.'/accept')
            ->assertOk();
        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-members/'.$firstRelationshipId.'/revoke', ['reason' => 'First engagement ended.'])
            ->assertOk();

        $secondInvitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->assertStatus(202)
            ->json('data.id');
        $secondRelationshipId = IndependentTrainerMemberInvitation::query()->findOrFail($secondInvitationId)->relationship_id;

        $this->assertNotSame($firstRelationshipId, $secondRelationshipId);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $firstRelationshipId,
            'status' => 'revoked',
            'is_current' => null,
            'revocation_reason' => 'First engagement ended.',
        ]);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $secondRelationshipId,
            'status' => 'pending',
            'is_current' => true,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$secondInvitationId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.relationship_id', $secondRelationshipId)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_suspended_trainer_can_see_and_revoke_relationship_but_cannot_access_coaching_data(): void
    {
        $trainer = $this->makeTrainer('suspended-management@example.com', verified: true);
        $member = $this->makeMember('suspended-management-member@example.com');
        $invitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->assertStatus(202)
            ->json('data.id');
        $relationshipId = IndependentTrainerMemberInvitation::query()->findOrFail($invitationId)->relationship_id;
        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertOk();

        $trainer->managedTrainerProfile()->update(['verification_status' => 'suspended']);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.relationships.0.relationship_id', $relationshipId)
            ->assertJsonPath('data.relationships.0.access_active', false);
        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/independent-members/'.$relationshipId)
            ->assertUnprocessable();
        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-members/'.$relationshipId.'/revoke', [
                'reason' => 'Verification paused.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_trainer_can_cancel_only_their_pending_invitation_and_member_cannot_accept_it_afterwards(): void
    {
        $trainer = $this->makeTrainer('cancel-invite-trainer@example.com', verified: true);
        $otherTrainer = $this->makeTrainer('cancel-invite-other@example.com', verified: true);
        $member = $this->makeMember('cancel-invite-member@example.com');

        $invitationId = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations', [
                'name' => $member->name,
                'email' => $member->email,
            ])
            ->assertStatus(202)
            ->json('data.id');
        $invitation = IndependentTrainerMemberInvitation::query()->findOrFail($invitationId);

        $this->actingAs($otherTrainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations/'.$invitationId.'/cancel')
            ->assertNotFound();

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/independent-member-invitations/'.$invitationId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.actionable', false);

        $this->assertDatabaseHas('independent_trainer_member_invitations', [
            'id' => $invitationId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('independent_trainer_member_relationships', [
            'id' => $invitation->relationship_id,
            'status' => 'cancelled',
            'is_current' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'independent_trainer_member.invitation_cancelled',
            'actor_user_id' => $trainer->id,
            'subject_id' => $invitation->relationship_id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => 'independent_coaching_revoked',
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/independent-trainer-invitations/'.$invitationId.'/accept')
            ->assertUnprocessable();
    }

    private function makeTrainer(string $email, bool $verified, ?Gym $gym = null, ?Branch $branch = null): User
    {
        $trainer = User::factory()->create([
            'email' => $email,
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym?->id,
            'branch_id' => $branch?->id,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => $verified ? 'verified' : 'pending',
        ]);

        return $trainer;
    }

    private function makeMember(string $email): User
    {
        $member = User::factory()->create([
            'email' => $email,
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);

        return $member;
    }

    /** @return array{Gym, Branch, User} */
    private function makeGymScope(): array
    {
        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value, 'is_active' => true]);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Existing Gym',
            'slug' => 'existing-gym-'.Str::lower(Str::random(8)),
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
        ]);
        $trainer = $this->makeTrainer('assigned-gym-trainer-'.Str::lower(Str::random(8)).'@example.com', true, $gym, $branch);

        return [$gym, $branch, $trainer];
    }
}
