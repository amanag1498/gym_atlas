<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\TrainerEmailInvitation;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndependentTrainerEnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_invites_independent_trainer_and_enrollment_waits_for_app_approval(): void
    {
        [$owner, $gym, $branch] = $this->makeGymOwnerScope();
        $trainer = $this->makeIndependentTrainer('independent-trainer@example.com');
        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers', [
                'existing_user_id' => $trainer->id,
                'branch_id' => $branch->id,
                'specialization' => 'Strength',
                'experience_years' => 5,
                'status' => 'active',
            ], $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.approval_channel', 'in_app');

        $invitationId = (int) $response->json('data.invitation_id');
        $this->assertDatabaseHas('trainer_email_invitations', [
            'id' => $invitationId,
            'invited_user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
        ]);
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
        ]);

        foreach ([
            '/api/trainer/assigned-members',
            '/api/trainer/today-clients',
            '/api/trainer/workout-templates',
            '/api/trainer/workout-plans',
            '/api/trainer/exercises',
            '/api/trainer/trial-requests',
        ] as $endpoint) {
            $this->actingAs($trainer, 'sanctum')
                ->getJson($endpoint)
                ->assertOk();
        }

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'trainer_gym_invitation')
            ->assertJsonPath('data.0.data.invitation_id', $invitationId)
            ->assertJsonPath('data.0.data.status', 'pending');

        $otherTrainer = $this->makeIndependentTrainer('other-trainer@example.com');
        $this->actingAs($otherTrainer, 'sanctum')
            ->postJson('/api/trainer-invitations/'.$invitationId.'/respond', [
                'decision' => 'accept',
            ])
            ->assertNotFound();

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer-invitations/'.$invitationId.'/respond', [
                'decision' => 'accept',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specialization' => 'Strength',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('gym_user', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'role_name' => RoleName::Trainer->value,
            'status' => 'active',
        ]);
        $this->assertSame(
            'accepted',
            Notification::query()
                ->where('user_id', $trainer->id)
                ->where('type', 'trainer_gym_invitation')
                ->firstOrFail()
                ->data['status'],
        );
        $this->assertDatabaseHas('activity_logs', [
            'gym_id' => $gym->id,
            'event' => 'gym.trainer.invitation.created',
            'subject_id' => $invitationId,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'gym_id' => $gym->id,
            'event' => 'gym.trainer.invitation.accepted',
            'subject_id' => $invitationId,
        ]);
    }

    public function test_rejecting_gym_invitation_keeps_trainer_independent(): void
    {
        [$owner, $gym, $branch] = $this->makeGymOwnerScope();
        $trainer = $this->makeIndependentTrainer('rejecting-trainer@example.com');
        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers', [
                'existing_user_id' => $trainer->id,
                'branch_id' => $branch->id,
                'specialization' => 'Mobility',
                'status' => 'active',
            ], $headers)
            ->assertStatus(202);

        $invitation = TrainerEmailInvitation::query()
            ->findOrFail((int) $response->json('data.invitation_id'));

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer-invitations/'.$invitation->id.'/respond', [
                'decision' => 'reject',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
        ]);
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'gym_id' => $gym->id,
            'event' => 'gym.trainer.invitation.rejected',
            'subject_id' => $invitation->id,
        ]);
    }

    public function test_accepted_gym_level_trainer_is_visible_in_owner_trainer_sections(): void
    {
        [$owner, $gym] = $this->makeGymOwnerScope();
        $trainer = $this->makeIndependentTrainer('gym-level-trainer@example.com');

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/trainers', [
                'existing_user_id' => $trainer->id,
                'specialization' => 'Functional Training',
                'status' => 'active',
            ], [
                'X-Gym-Id' => (string) $gym->id,
            ])
            ->assertStatus(202);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer-invitations/'.$response->json('data.invitation_id').'/respond', [
                'decision' => 'accept',
            ])
            ->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/trainers', [
                'X-Gym-Id' => (string) $gym->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $trainer->id);

        $this->actingAs($owner)
            ->get(route('web.gym.trainers.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('gym-level-trainer@example.com');
    }

    /**
     * @return array{User, Gym, Branch}
     */
    private function makeGymOwnerScope(): array
    {
        $owner = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Independent Trainer Gym',
            'slug' => 'independent-trainer-gym-'.Str::lower(Str::random(8)),
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_active' => true,
        ]);

        $owner->gyms()->attach($gym->id, [
            'role_name' => RoleName::GymOwner->value,
            'status' => 'active',
            'is_primary' => true,
        ]);
        $owner->branches()->attach($branch->id, ['is_primary' => true]);

        return [$owner, $gym, $branch];
    }

    private function makeIndependentTrainer(string $email): User
    {
        $trainer = User::factory()->create([
            'email' => $email,
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        return $trainer;
    }
}
