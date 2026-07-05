<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\FitnessGoal;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFitnessGoalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_create_and_update_fitness_goal_from_web_panel(): void
    {
        $admin = User::factory()->create([
            'email' => 'fitness-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'fitness-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.fitness-goals.create'))
            ->assertOk()
            ->assertSee('Create Fitness Goal');

        $response = $this->post(route('web.admin.fitness-goals.store'), [
            'name' => 'Hybrid Athlete',
            'description' => 'Blend strength, power, and conditioning.',
            'icon' => 'sports_gymnastics',
            'sort_order' => 7,
            'status' => 'active',
        ]);

        $goal = FitnessGoal::query()->where('name', 'Hybrid Athlete')->firstOrFail();

        $response->assertRedirect(route('web.admin.fitness-goals.edit', $goal));
        $this->assertSame('hybrid-athlete', $goal->slug);
        $this->assertTrue((bool) $goal->is_active);

        $this->put(route('web.admin.fitness-goals.update', $goal), [
            'name' => 'Hybrid Athlete Pro',
            'description' => 'Updated goal description.',
            'icon' => 'sports_martial_arts',
            'sort_order' => 9,
            'status' => 'inactive',
        ])->assertRedirect(route('web.admin.fitness-goals.edit', $goal));

        $goal->refresh();

        $this->assertSame('Hybrid Athlete Pro', $goal->name);
        $this->assertSame('hybrid-athlete-pro', $goal->slug);
        $this->assertFalse((bool) $goal->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'web.platform.fitness_goal.updated',
        ]);
    }
}
