<?php

namespace Tests\Feature;

use App\Models\DietPlanTemplate;
use App\Models\Exercise;
use App\Models\FoodCatalogItem;
use App\Models\WorkoutBook;
use App\Models\WorkoutTemplate;
use Database\Seeders\AdvancedWorkoutPlanSeeder;
use Database\Seeders\ComprehensiveExerciseCatalogSeeder;
use Database\Seeders\DietPlanTemplateSeeder;
use Database\Seeders\ExpandedDietPlanTemplateSeeder;
use Database\Seeders\FoodCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformAdminSeeder;
use Database\Seeders\WorkoutBookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeders_create_complete_connected_catalogs_and_are_idempotent(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformAdminSeeder::class);

        $seeders = [
            WorkoutBookSeeder::class,
            ComprehensiveExerciseCatalogSeeder::class,
            AdvancedWorkoutPlanSeeder::class,
            FoodCatalogSeeder::class,
            DietPlanTemplateSeeder::class,
            ExpandedDietPlanTemplateSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $counts = [
            'exercises' => Exercise::query()->where('is_global', true)->count(),
            'books' => WorkoutBook::query()->count(),
            'templates' => WorkoutTemplate::query()->where('is_public_catalog', true)->count(),
            'foods' => FoodCatalogItem::query()->count(),
            'diets' => DietPlanTemplate::query()->globalCatalog()->count(),
        ];

        $this->assertGreaterThanOrEqual(80, $counts['exercises']);
        $this->assertGreaterThanOrEqual(11, $counts['books']);
        $this->assertSame($counts['books'], $counts['templates']);
        $this->assertGreaterThanOrEqual(50, $counts['foods']);
        $this->assertGreaterThanOrEqual(11, $counts['diets']);
        $this->assertSame(0, Exercise::query()->whereNull('instructions')->count());

        $expandedDiet = DietPlanTemplate::query()->where('name', 'Indian Vegetarian Balanced Day')->firstOrFail();
        $catalogItems = collect($expandedDiet->meals)
            ->flatMap(fn (array $meal): array => $meal['items'] ?? []);
        $this->assertNotEmpty($catalogItems);
        $this->assertTrue($catalogItems->every(fn (array $item): bool => isset($item['food_catalog_item_id'])));

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $this->assertSame($counts['exercises'], Exercise::query()->where('is_global', true)->count());
        $this->assertSame($counts['books'], WorkoutBook::query()->count());
        $this->assertSame($counts['templates'], WorkoutTemplate::query()->where('is_public_catalog', true)->count());
        $this->assertSame($counts['foods'], FoodCatalogItem::query()->count());
        $this->assertSame($counts['diets'], DietPlanTemplate::query()->globalCatalog()->count());
    }
}
