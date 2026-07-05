<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_unauthenticated_access_to_private_route_groups_with_api_json_format(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->getJson('/api/platform-admin/context')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_blocks_platform_admin_route_when_active_role_is_not_platform_admin(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::PlatformAdmin->value);
        $user->assignRole(RoleName::Member->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/platform-admin/context')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This endpoint is not available for the active role.');
    }

    public function test_member_cannot_access_trainer_or_gym_admin_api_routes(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);

        $headers = [
            'X-Gym-Id' => '1',
            'X-Branch-Id' => '1',
        ];

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/trainer/assigned-members', $headers)
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have the required role.');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/gym/dashboard', $headers)
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have the required role.');
    }
}
