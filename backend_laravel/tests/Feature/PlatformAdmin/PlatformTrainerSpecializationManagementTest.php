<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\TrainerProfile;
use App\Models\TrainerSpecialization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTrainerSpecializationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_manage_trainer_specializations_from_web_panel(): void
    {
        $admin = User::factory()->create([
            'email' => 'trainer-specializations-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'trainer-specializations-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.trainer-specializations.index'))
            ->assertOk()
            ->assertSee('Trainer Specialization Master');

        $this->get(route('web.admin.trainer-specializations.create'))
            ->assertOk()
            ->assertSee('Create Trainer Specialization');

        $response = $this->post(route('web.admin.trainer-specializations.store'), [
            'name' => 'Corrective Exercise',
            'description' => 'Movement assessment and corrective programming.',
            'icon' => 'accessibility_new',
            'sort_order' => 8,
            'status' => 'active',
        ]);

        $specialization = TrainerSpecialization::query()->where('name', 'Corrective Exercise')->firstOrFail();

        $response->assertRedirect(route('web.admin.trainer-specializations.edit', $specialization));
        $this->assertSame('corrective-exercise', $specialization->slug);
        $this->assertTrue((bool) $specialization->is_active);

        $this->put(route('web.admin.trainer-specializations.update', $specialization), [
            'name' => 'Corrective Exercise Pro',
            'description' => 'Updated specialization description.',
            'icon' => 'accessibility_new',
            'sort_order' => 9,
            'status' => 'inactive',
        ])->assertRedirect(route('web.admin.trainer-specializations.edit', $specialization));

        $specialization->refresh();

        $this->assertSame('Corrective Exercise Pro', $specialization->name);
        $this->assertSame('corrective-exercise-pro', $specialization->slug);
        $this->assertFalse((bool) $specialization->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'web.platform.trainer_specialization.updated',
        ]);
    }

    public function test_api_crud_and_used_specialization_delete_guard_work(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/platform-admin/trainer-specializations', [
            'name' => 'Athletic Performance',
            'description' => 'Speed, agility, and power coaching.',
            'icon' => 'directions_run',
            'sort_order' => 10,
            'status' => 'active',
        ]);

        $createResponse->assertCreated()->assertJsonPath('data.name', 'Athletic Performance');
        $specialization = TrainerSpecialization::query()->where('name', 'Athletic Performance')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/trainer-specializations?search=Athletic&status=active')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Athletic Performance');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/platform-admin/trainer-specializations/'.$specialization->id, [
                'name' => 'Athletic Performance',
                'description' => 'Updated API description.',
                'icon' => 'directions_run',
                'sort_order' => 11,
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 11);

        $trainer = User::factory()->create(['is_active' => true]);
        $gym = Gym::query()->create([
            'name' => 'Specialization Test Gym',
            'slug' => 'specialization-test-gym',
            'status' => 'active',
        ]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'specialization' => 'Athletic Performance',
            'specializations' => ['Athletic Performance'],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/platform-admin/trainer-specializations/'.$specialization->id)
            ->assertStatus(422);
    }
}
