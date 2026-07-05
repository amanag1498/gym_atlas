<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialRequestManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_member_trial_requests_appear_in_gym_list(): void
    {
        [$owner, $gym, $branch, $trainer] = $this->makeGymScope();

        $this->postJson('/api/public/trial-requests', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Public Lead',
            'phone' => '9999999999',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '18:00',
        ])->assertCreated();

        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/trial-requests', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'preferred_date' => now()->addDays(2)->toDateString(),
                'preferred_time' => '19:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.member_id', $member->id);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/trial-requests', ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_accept_reject_and_assign_trainer_work(): void
    {
        [$owner, $gym, $branch, $trainer] = $this->makeGymScope();
        $trial = $this->makeTrial($gym, $branch);

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/trial-requests/{$trial->id}/assign-trainer", [
                'assigned_trainer_id' => $trainer->id,
                'notes' => 'Assigned for evening follow-up',
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.assigned_trainer_id', $trainer->id);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/trial-requests/{$trial->id}/accept", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/trial-requests/{$trial->id}/reject", ['notes' => 'Lead unreachable'], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_convert_creates_member_safely_and_marks_trial_converted(): void
    {
        [$owner, $gym, $branch] = $this->makeGymScope();
        $trial = $this->makeTrial($gym, $branch, [
            'name' => 'Convert Lead',
            'email' => 'convert@example.com',
            'phone' => '8888888888',
            'status' => 'accepted',
        ]);

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/trial-requests/{$trial->id}/convert", [
                'password' => 'Convert@123',
            ], $headers)
            ->assertOk()
            ->assertJsonPath('data.trial_request.status', 'converted');

        $trial->refresh();

        $this->assertNotNull($trial->member_id);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $trial->member_id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_web_trial_detail_loads_and_convert_redirects_to_member(): void
    {
        [$owner, $gym, $branch] = $this->makeGymScope();
        $trial = $this->makeTrial($gym, $branch, ['status' => 'accepted']);

        $this->actingAs($owner)
            ->get(route('web.gym.trial-requests.show', ['gym' => $gym->id, 'branch' => $branch->id, 'trial' => $trial->id]))
            ->assertOk()
            ->assertSee($trial->name);

        $this->actingAs($owner)
            ->post(route('web.gym.trial-requests.convert', ['gym' => $gym->id, 'branch' => $branch->id, 'trial' => $trial->id]), [
                'password' => 'Convert@123',
            ])
            ->assertRedirectContains('/gym/members/');
    }

    /**
     * @return array{0: User, 1: Gym, 2: Branch, 3: User}
     */
    private function makeGymScope(): array
    {
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trial Ops Gym',
            'slug' => 'trial-ops-gym-'.str()->random(6),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => true],
            $trainer->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => true],
            $trainer->id => ['is_primary' => false],
        ]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$owner, $gym, $branch, $trainer];
    }

    private function makeTrial(Gym $gym, Branch $branch, array $overrides = []): TrialRequest
    {
        return TrialRequest::query()->create(array_merge([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Trial Lead',
            'phone' => '7777777777',
            'email' => 'trial@example.com',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '18:00:00',
            'status' => 'pending',
        ], $overrides));
    }
}
