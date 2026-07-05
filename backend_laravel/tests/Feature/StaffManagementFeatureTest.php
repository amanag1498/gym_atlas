<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_create_staff_with_permissions_and_access_is_enforced(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Staff Gym',
            'slug' => 'staff-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch], []);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.staff.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Cash Desk Staff',
            'email' => 'cashdesk@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => RoleName::GymStaff->value,
            'branch_ids' => [$branch->id],
            'custom_permissions' => ['collect_payment', 'view_billing'],
        ])->assertRedirect();

        $staff = User::query()->where('email', 'cashdesk@example.com')->firstOrFail();
        $this->assertTrue($staff->hasRole(RoleName::GymStaff->value));

        $pivot = $staff->gyms()->where('gyms.id', $gym->id)->firstOrFail()->pivot;
        $permissions = is_array($pivot->custom_permissions)
            ? $pivot->custom_permissions
            : (json_decode((string) $pivot->custom_permissions, true) ?: []);

        $this->assertContains('collect_payment', $permissions);
        $this->assertContains('view_billing', $permissions);

        $this->post('/logout')->assertRedirect('/admin/login');

        $this->post('/gym/login', [
            'email' => 'cashdesk@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->get(route('web.gym.payments.create', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk();

        $this->get(route('web.gym.profile.edit', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertForbidden();
    }

    public function test_existing_user_can_be_assigned_as_branch_manager(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner2@example.com');
        $existingUser = User::factory()->create([
            'email' => 'existing-staff@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Existing User Gym',
            'slug' => 'existing-user-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch], []);
        $this->loginGymUser($owner);

        $this->post(route('web.gym.staff.store', ['gym' => $gym->id]), [
            'existing_user_id' => $existingUser->id,
            'role' => RoleName::BranchManager->value,
            'branch_ids' => [$branch->id],
            'custom_permissions' => ['manage_members', 'manage_attendance'],
        ])->assertRedirect();

        $existingUser->refresh();
        $this->assertTrue($existingUser->hasRole(RoleName::BranchManager->value));
        $this->assertTrue($existingUser->gyms()->where('gyms.id', $gym->id)->exists());
        $this->assertTrue($existingUser->branches()->where('branches.id', $branch->id)->exists());
    }

    public function test_staff_cannot_grant_permission_they_do_not_have_and_deactivate_blocks_access(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner3@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Restricted Staff Gym',
            'slug' => 'restricted-staff-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Restricted Branch',
            'slug' => 'restricted-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $manager = $this->makeUser(RoleName::GymStaff->value, 'managerstaff@example.com');
        $this->attachToGymAndBranches($owner, $gym, [$branch], []);
        $this->attachToGymAndBranches($manager, $gym, [$branch], ['manage_staff']);
        $this->loginGymUser($manager);

        $this->post(route('web.gym.staff.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Over Grant',
            'email' => 'overgrant@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => RoleName::GymStaff->value,
            'branch_ids' => [$branch->id],
            'custom_permissions' => ['manage_staff', 'edit_custom_fee'],
        ])->assertForbidden();

        $this->post(route('web.gym.staff.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'name' => 'Scoped Staff',
            'email' => 'scopedstaff@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => RoleName::GymStaff->value,
            'branch_ids' => [$branch->id],
            'custom_permissions' => ['manage_staff'],
        ])->assertRedirect();

        $staff = User::query()->where('email', 'scopedstaff@example.com')->firstOrFail();

        $this->post(route('web.gym.staff.deactivate', ['gym' => $gym->id, 'branch' => $branch->id, 'staff' => $staff->id]))
            ->assertRedirect();

        $this->assertFalse((bool) $staff->fresh()->is_active);

        $this->post('/logout')->assertRedirect('/admin/login');

        $this->post('/gym/login', [
            'email' => 'scopedstaff@example.com',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');
    }

    public function test_gym_owner_can_manage_staff_via_api(): void
    {
        $owner = $this->makeUser(RoleName::GymOwner->value, 'owner-api@example.com');

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Staff Gym',
            'slug' => 'api-staff-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'API Branch',
            'slug' => 'api-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch], []);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $createResponse = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/staff', [
                'name' => 'API Staff User',
                'email' => 'api-staff@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'role' => RoleName::GymStaff->value,
                'branch_ids' => [$branch->id],
                'custom_permissions' => ['manage_attendance'],
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'api-staff@example.com');

        $staffId = (int) $createResponse->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/staff/'.$staffId, $headers)
            ->assertOk()
            ->assertJsonPath('data.roles.0', RoleName::GymStaff->value);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/staff/'.$staffId.'/deactivate', [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
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
     * @param  list<string>  $customPermissions
     */
    private function attachToGymAndBranches(User $user, Gym $gym, array $branches, array $customPermissions): void
    {
        $encoded = json_encode($customPermissions);

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, [
                'custom_permissions' => $encoded,
                'permissions' => $encoded,
                'role_name' => $roleName = $user->getRoleNames()->first(),
                'status' => 'active',
                'is_primary' => true,
            ]);
        } else {
            $user->gyms()->attach($gym->id, [
                'custom_permissions' => $encoded,
                'permissions' => $encoded,
                'role_name' => $user->getRoleNames()->first(),
                'status' => 'active',
                'is_primary' => true,
            ]);
        }

        foreach ($branches as $branch) {
            if ($user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, [
                    'custom_permissions' => $encoded,
                    'is_primary' => false,
                ]);
            } else {
                $user->branches()->attach($branch->id, [
                    'custom_permissions' => $encoded,
                    'is_primary' => false,
                ]);
            }
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
