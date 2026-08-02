<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\IndependentTrainerMemberRelationship;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Users\ManagedUserService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndependentTrainerVerificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_filter_and_open_independent_trainer_verification_submissions(): void
    {
        $admin = $this->platformAdmin();
        $pending = $this->trainerProfile('Pending Coach', null, 'pending');
        $verified = $this->trainerProfile('Verified Coach', null, 'verified');
        $gymTrainer = $this->trainerProfile('Gym Coach', $this->gym('Filter Gym')->id, 'pending');

        $this->actingAs($admin)
            ->get(route('web.admin.trainer-verifications.index', ['status' => 'pending', 'search' => 'Pending Coach']))
            ->assertOk()
            ->assertSee('Pending Coach')
            ->assertDontSee('Verified Coach')
            ->assertDontSee('Gym Coach');

        $this->actingAs($admin)
            ->get(route('web.admin.trainer-verifications.show', $pending))
            ->assertOk()
            ->assertSee('Independent trainer')
            ->assertSee('Certified Personal Trainer');

        $this->actingAs($admin)
            ->get(route('web.admin.trainer-verifications.show', $gymTrainer))
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/trainer-verifications?status=verified')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $verified->id)
            ->assertJsonPath('data.0.independent', true);
    }

    public function test_platform_admin_can_approve_reject_and_suspend_with_review_audit(): void
    {
        $admin = $this->platformAdmin();
        $profile = $this->trainerProfile('Review Coach');

        $this->actingAs($admin)
            ->patch(route('web.admin.trainer-verifications.update', $profile), [
                'verification_status' => 'verified',
                'notes' => 'Identity and certification checked.',
            ])
            ->assertRedirect(route('web.admin.trainer-verifications.show', $profile));

        $profile->refresh();
        $this->assertSame('verified', $profile->verification_status);
        $this->assertSame($admin->id, $profile->verification_reviewed_by_user_id);
        $this->assertNotNull($profile->verification_reviewed_at);
        $this->assertNotNull($profile->verification_verified_at);
        $this->assertSame('Identity and certification checked.', $profile->verification_review_notes);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $profile->user_id,
            'type' => 'independent_trainer_verification',
            'created_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'platform.independent_trainer.verification_verified',
            'actor_user_id' => $admin->id,
            'subject_type' => $profile->getMorphClass(),
            'subject_id' => $profile->id,
        ]);

        $verifiedAt = $profile->verification_verified_at;

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'suspended',
                'reason' => 'Certification expired.',
                'notes' => 'May reapply with renewed evidence.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification.status', 'suspended')
            ->assertJsonPath('data.verification.rejection_reason', 'Certification expired.');

        $profile->refresh();
        $this->assertSame('suspended', $profile->verification_status);
        $this->assertSame('Certification expired.', $profile->verification_rejection_reason);
        $this->assertTrue($profile->verification_verified_at->equalTo($verifiedAt));
        $this->actingAs($profile->user, 'sanctum')
            ->getJson('/api/trainer/profile')
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.verification_status', 'suspended')
            ->assertJsonPath('data.trainer_profile.verification_rejection_reason', 'Certification expired.');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'rejected',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'unverified',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_status');

        $this->assertSame(2, ActivityLog::query()
            ->where('subject_type', $profile->getMorphClass())
            ->where('subject_id', $profile->id)
            ->where('event', 'like', 'platform.independent_trainer.verification_%')
            ->count());
    }

    public function test_non_platform_admin_cannot_review_and_gym_trainer_cannot_use_independent_endpoint(): void
    {
        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);
        $profile = $this->trainerProfile('Independent Coach');
        $gymProfile = $this->trainerProfile('Gym Coach', $this->gym('Protected Gym')->id);

        $this->actingAs($member)
            ->get(route('web.admin.trainer-verifications.index'))
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'verified',
            ])
            ->assertForbidden();

        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$gymProfile->id, [
                'verification_status' => 'verified',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trainer');
    }

    public function test_review_queue_excludes_branch_linked_profiles_and_enforces_decision_state_machine(): void
    {
        $admin = $this->platformAdmin();
        $gym = $this->gym('Branch Source Gym');
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch One',
            'slug' => 'branch-one',
            'city' => 'Mumbai',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchLinked = $this->trainerProfile('Branch Linked Coach');
        $branchLinked->forceFill(['branch_id' => $branch->id])->save();
        $pending = $this->trainerProfile('State Coach');

        $this->actingAs($admin)
            ->get(route('web.admin.trainer-verifications.index'))
            ->assertOk()
            ->assertDontSee('Branch Linked Coach')
            ->assertSee('State Coach');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$pending->id, [
                'verification_status' => 'suspended',
                'reason' => 'Invalid direct suspension.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_status');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$branchLinked->id, [
                'verification_status' => 'verified',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trainer');

        $pending->user->forceFill(['is_active' => false])->save();
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$pending->id, [
                'verification_status' => 'verified',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_status');
    }

    public function test_gym_assignment_is_blocked_until_active_independent_relationships_are_ended(): void
    {
        $profile = $this->trainerProfile('Independent To Gym Coach', null, 'verified');
        $member = User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);
        $relationship = IndependentTrainerMemberRelationship::query()->create([
            'trainer_user_id' => $profile->user_id,
            'member_user_id' => $member->id,
            'invited_email' => strtolower($member->email),
            'status' => 'active',
            'sharing_permissions' => ['profile'],
            'accepted_at' => now(),
        ]);
        $gym = $this->gym('Assignment Guard Gym');
        $service = app(ManagedUserService::class);
        $payload = [
            'name' => $profile->user->name,
            'email' => $profile->user->email,
            'is_active' => true,
        ];

        try {
            $service->upsertTrainer($profile->user, $gym, $payload);
            $this->fail('Expected active independent coaching to block gym assignment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertNull($profile->fresh()->gym_id);
        $relationship->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

        $updated = $service->upsertTrainer($profile->user, $gym, $payload);
        $this->assertSame($gym->id, $updated->managedTrainerProfile->gym_id);
    }

    public function test_verified_independent_trainer_material_profile_changes_require_re_review(): void
    {
        $profile = $this->trainerProfile('Credential Change Coach', null, 'verified');
        $profile->forceFill([
            'verification_reviewed_at' => now(),
            'verification_verified_at' => now(),
        ])->save();
        $trainer = $profile->user;

        $this->actingAs($trainer, 'sanctum')
            ->putJson('/api/trainer/profile', [
                'profile_photo_url' => 'https://example.com/new-photo.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.verification_status', 'verified');

        $this->actingAs($trainer, 'sanctum')
            ->putJson('/api/trainer/profile', [
                'bio' => 'Updated professional coaching background.',
                'specializations' => ['Mobility'],
                'experience_years' => 7,
                'certifications' => [['name' => 'Updated CPT']],
            ])
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.verification_status', 'pending');

        $profile->refresh();
        $this->assertNull($profile->verification_reviewed_at);
        $this->assertNull($profile->verification_verified_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'trainer.independent_verification.review_required',
            'actor_user_id' => $trainer->id,
            'subject_id' => $profile->id,
        ]);
    }

    public function test_rejected_independent_trainer_can_resubmit_by_updating_review_fields(): void
    {
        $profile = $this->trainerProfile('Rejected Coach', null, 'rejected');
        $profile->forceFill([
            'verification_reviewed_at' => now(),
            'verification_rejection_reason' => 'Add current certification evidence.',
        ])->save();

        $this->actingAs($profile->user, 'sanctum')
            ->putJson('/api/trainer/profile', [
                'certifications' => [[
                    'name' => 'Current CPT',
                    'issuer' => 'Atlas Academy',
                    'issued_year' => 2026,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.verification_status', 'pending')
            ->assertJsonPath('data.trainer_profile.verification_rejection_reason', null);

        $profile->refresh();
        $this->assertNull($profile->verification_reviewed_at);
        $this->assertNull($profile->verification_rejection_reason);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'trainer.independent_verification.review_required',
            'actor_user_id' => $profile->user_id,
            'subject_id' => $profile->id,
        ]);
    }

    public function test_suspension_immediately_hides_relationship_access_and_reverification_restores_it(): void
    {
        $admin = $this->platformAdmin();
        $profile = $this->trainerProfile('Suspend Access Coach', null, 'verified');
        $member = User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);
        $relationship = IndependentTrainerMemberRelationship::query()->create([
            'trainer_user_id' => $profile->user_id,
            'member_user_id' => $member->id,
            'invited_email' => strtolower($member->email),
            'status' => 'active',
            'sharing_permissions' => ['profile', 'chat'],
            'accepted_at' => now(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/independent-trainers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'suspended',
                'reason' => 'Evidence expired.',
            ])
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/independent-trainers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_active', false);
        $this->actingAs($profile->user, 'sanctum')
            ->getJson('/api/trainer/independent-context')
            ->assertOk()
            ->assertJsonPath('data.eligible', false);
        $this->assertSame('active', $relationship->fresh()->status);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/trainer-verifications/'.$profile->id, [
                'verification_status' => 'verified',
                'notes' => 'Renewed evidence accepted.',
            ])
            ->assertOk();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/independent-trainers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_active', true);
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        return $admin;
    }

    private function trainerProfile(string $name, ?int $gymId = null, string $verificationStatus = 'pending'): TrainerProfile
    {
        $trainer = User::factory()->create([
            'name' => $name,
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);

        return TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gymId,
            'branch_id' => null,
            'specialization' => 'Strength coaching',
            'experience_years' => 6,
            'certifications' => ['Certified Personal Trainer'],
            'status' => 'active',
            'is_active' => true,
            'verification_status' => $verificationStatus,
        ]);
    }

    private function gym(string $name): Gym
    {
        return Gym::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }
}
