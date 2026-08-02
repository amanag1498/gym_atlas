<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginationContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_api_rejects_invalid_pagination_parameters_consistently(): void
    {
        $admin = $this->platformAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/platform-admin/exercises?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/platform-admin/exercises?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');

        $this->getJson('/api/platform-admin/exercises?notifications_page=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notifications_page');
    }

    public function test_empty_and_populated_exercise_lists_use_the_shared_pagination_meta_contract(): void
    {
        $admin = $this->platformAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/platform-admin/exercises?per_page=25')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.from', null)
            ->assertJsonPath('meta.pagination.to', null)
            ->assertJsonPath('meta.pagination.per_page', 25)
            ->assertJsonPath('meta.pagination.total', 0);

        foreach (range(1, 31) as $index) {
            $this->exercise("Chest Press {$index}", 'chest');
        }

        $this->getJson('/api/platform-admin/exercises?per_page=25&page=2')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.from', 26)
            ->assertJsonPath('meta.pagination.to', 31)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.total', 31);
    }

    public function test_grouped_exercise_pages_keep_body_part_order_and_filtered_totals(): void
    {
        $admin = $this->platformAdmin();
        Sanctum::actingAs($admin);

        $this->exercise('Back Row', 'back');
        $this->exercise('Chest Press', 'chest');
        $this->exercise('Glute Leg Drive', 'glutes and legs');
        $this->exercise('Quad Leg Press', 'legs');

        $this->getJson('/api/platform-admin/exercises?grouped=1&per_page=2')
            ->assertOk()
            ->assertJsonPath('data.groups.0.body_part', 'chest')
            ->assertJsonPath('data.groups.1.body_part', 'back')
            ->assertJsonPath('meta.pagination.total', 4);

        $this->getJson('/api/platform-admin/exercises?body_part=quads&per_page=25')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Quad Leg Press');
    }

    public function test_platform_exercise_web_pagination_preserves_filters(): void
    {
        $admin = $this->platformAdmin();

        foreach (range(1, 26) as $index) {
            $this->exercise("Filtered Press {$index}", 'chest');
        }
        $this->exercise('Unmatched Row', 'back');

        $response = $this->actingAs($admin)->get(route('web.admin.exercises.index', [
            'search' => 'Filtered',
            'body_part' => 'chest',
        ]));

        $response->assertOk();
        $paginator = $response->viewData('exercises');

        $this->assertSame(26, $paginator->total());
        $this->assertSame(25, $paginator->perPage());
        $this->assertStringContainsString('search=Filtered', $paginator->nextPageUrl());
        $this->assertStringContainsString('body_part=chest', $paginator->nextPageUrl());
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

    private function exercise(string $name, string $muscleGroup): Exercise
    {
        return Exercise::query()->create([
            'name' => $name,
            'muscle_group' => $muscleGroup,
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }
}
