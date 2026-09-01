<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\FoodCatalogItem;
use App\Models\TrainerSpecialization;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\CommonFacilitySeeder;
use Database\Seeders\FoodCatalogSeeder;
use Database\Seeders\TrainerSpecializationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_catalog_seeders_are_broad_and_idempotent(): void
    {
        $seeders = [
            CitySeeder::class,
            CommonFacilitySeeder::class,
            TrainerSpecializationSeeder::class,
            FoodCatalogSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $counts = [
            'cities' => City::query()->count(),
            'facilities' => Facility::query()->count(),
            'specializations' => TrainerSpecialization::query()->count(),
            'foods' => FoodCatalogItem::query()->count(),
        ];

        $this->assertGreaterThanOrEqual(100, $counts['cities']);
        $this->assertGreaterThanOrEqual(35, $counts['facilities']);
        $this->assertGreaterThanOrEqual(25, $counts['specializations']);
        $this->assertGreaterThanOrEqual(120, $counts['foods']);

        $this->assertDatabaseHas('cities', ['name' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India']);
        $this->assertDatabaseHas('cities', ['name' => 'Port Blair', 'state' => 'Andaman and Nicobar Islands', 'country' => 'India']);
        $this->assertDatabaseHas('cities', ['name' => 'Leh', 'state' => 'Ladakh', 'country' => 'India']);
        $this->assertDatabaseHas('facilities', ['slug' => 'crossfit', 'name' => 'Cross-training Zone']);
        $this->assertDatabaseHas('trainer_specializations', ['slug' => 'corrective-exercise']);
        $this->assertStringContainsString(
            'FDC ID 2707430',
            FoodCatalogItem::query()->where('name', 'Sambar')->firstOrFail()->notes,
        );
        $this->assertStringContainsString(
            'recipe-derived estimate',
            FoodCatalogItem::query()->where('name', 'Rajma chawal')->firstOrFail()->notes,
        );

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $this->assertSame($counts['cities'], City::query()->count());
        $this->assertSame($counts['facilities'], Facility::query()->count());
        $this->assertSame($counts['specializations'], TrainerSpecialization::query()->count());
        $this->assertSame($counts['foods'], FoodCatalogItem::query()->count());
    }

    public function test_food_seeder_updates_matching_seeded_rows_with_corrected_source_data(): void
    {
        FoodCatalogItem::query()->create([
            'name' => 'Sambar',
            'default_quantity' => 'old serving',
            'calories' => 999,
            'is_active' => false,
        ]);

        $this->seed(FoodCatalogSeeder::class);

        $sambar = FoodCatalogItem::query()->where('name', 'Sambar')->sole();

        $this->assertSame('100 g', $sambar->default_quantity);
        $this->assertSame(86, $sambar->calories);
        $this->assertTrue($sambar->is_active);
        $this->assertStringContainsString('FDC ID 2707430', $sambar->notes);
    }

    public function test_food_seeder_does_not_overwrite_an_admin_created_food_with_the_same_name(): void
    {
        $admin = User::factory()->create();

        FoodCatalogItem::query()->create([
            'created_by_user_id' => $admin->id,
            'name' => 'Sambar',
            'calories' => 222,
            'is_active' => true,
        ]);

        $this->seed(FoodCatalogSeeder::class);

        $this->assertSame(1, FoodCatalogItem::query()->where('name', 'Sambar')->count());
        $this->assertSame(222, FoodCatalogItem::query()->where('name', 'Sambar')->sole()->calories);
    }
}
