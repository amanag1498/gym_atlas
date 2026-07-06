<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PlatformAdminSeeder::class,
            CitySeeder::class,
            CommonFacilitySeeder::class,
            FitnessGoalSeeder::class,
            TrainerSpecializationSeeder::class,
            WorkoutBookSeeder::class,
            PlatformSubscriptionSeeder::class,
            PlatformBannerSeeder::class,
        ]);
    }
}
