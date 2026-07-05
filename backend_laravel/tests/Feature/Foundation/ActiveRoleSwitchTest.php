<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveRoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_switches_active_role_only_to_a_backend_assigned_role(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);
        $user->assignRole(RoleName::Trainer->value);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/public/auth/active-role', [
                'active_role' => RoleName::Trainer->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Trainer->value);
    }

    public function test_it_refuses_switching_to_a_role_that_the_user_does_not_have(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/public/auth/active-role', [
                'active_role' => RoleName::Trainer->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.active_role.0', 'The requested role is not assigned to this user.');
    }
}
