<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_create_and_edit_branch_via_web(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Branch Gym',
            'slug' => 'branch-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $facility = Facility::query()->create([
            'name' => 'Parking',
            'slug' => 'parking',
            'is_active' => true,
        ]);

        $this->attachToGym($owner, $gym);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.branches.store', ['gym' => $gym->id]), [
            'name' => 'North Branch',
            'city' => 'Delhi',
            'address' => 'North Road',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'facility_ids' => [$facility->id],
            'is_active' => '1',
        ])->assertRedirect();

        $branch = Branch::query()->where('gym_id', $gym->id)->where('name', 'North Branch')->firstOrFail();
        $this->assertSame('active', $branch->status);
        $this->assertSame([$facility->id], $branch->facilities()->pluck('facilities.id')->all());

        $this->put(route('web.gym.branches.update', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'North Branch Prime',
            'city' => 'Noida',
            'address' => 'Updated Road',
            'is_active' => '0',
        ])->assertRedirect(route('web.gym.branches.show', ['gym' => $gym->id, 'branch' => $branch->id]));

        $branch->refresh();
        $this->assertSame('North Branch Prime', $branch->name);
        $this->assertSame('Noida', $branch->city);
        $this->assertSame('Updated Road', $branch->address);
        $this->assertFalse((bool) $branch->is_active);
        $this->assertSame('inactive', $branch->status);
    }

    public function test_branch_manager_scope_applies_to_web_and_api_branch_views(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::BranchManager->value,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Scoped Branch Gym',
            'slug' => 'scoped-branch-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Visible Branch',
            'slug' => 'visible-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Hidden Branch',
            'slug' => 'hidden-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGym($manager, $gym, [$branchA]);
        $this->loginGymUser($manager);

        $this->get(route('web.gym.branches.show', ['gym' => $gym->id, 'branch' => $branchA->id]))->assertOk();
        $this->get(route('web.gym.branches.show', ['gym' => $gym->id, 'branch' => $branchB->id]))->assertForbidden();

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branchA->id,
        ];

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/branches/'.$branchA->id, $headers)
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/branches/'.$branchB->id, $headers)
            ->assertForbidden();
    }

    public function test_branch_with_active_members_cannot_be_deleted_and_can_toggle_status(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value, 'secret123');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Delete Safe Gym',
            'slug' => 'delete-safe-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Protected Branch',
            'slug' => 'protected-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $member = $this->makeRoleUser(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGym($owner, $gym, [$branch]);
        $this->loginGymUser($owner);

        $this->delete(route('web.gym.branches.destroy', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertSessionHasErrors('branch');

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);

        $this->post(route('web.gym.branches.toggle-status', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'inactive',
            'is_active' => false,
        ]);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/gym/branches/'.$branch->id, [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function attachToGym(User $user, Gym $gym, array $branches = []): void
    {
        $user->gyms()->syncWithoutDetaching([$gym->id => ['is_primary' => true]]);

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

    private function makeRoleUser(string $role, string $password = 'secret123'): User
    {
        $user = User::factory()->create([
            'password' => $password,
            'is_active' => true,
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
