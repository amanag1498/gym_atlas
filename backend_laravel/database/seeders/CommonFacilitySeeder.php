<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommonFacilitySeeder extends Seeder
{
    public function run(): void
    {
        $facilities = [
            ['name' => 'AC', 'icon' => 'snowflake'],
            ['name' => 'Cardio', 'icon' => 'activity'],
            ['name' => 'Steam', 'icon' => 'flame'],
            ['name' => 'Parking', 'icon' => 'car'],
            ['name' => 'Women-only', 'icon' => 'user-heart'],
            ['name' => 'Personal Training', 'icon' => 'user-star'],
            ['name' => 'CrossFit', 'icon' => 'barbell'],
            ['name' => 'Zumba', 'icon' => 'music'],
            ['name' => 'Locker', 'icon' => 'lock'],
            ['name' => 'Shower', 'icon' => 'droplet'],
            ['name' => 'Weight Training', 'icon' => 'barbell'],
            ['name' => 'Functional Training', 'icon' => 'stretching'],
        ];

        foreach ($facilities as $facility) {
            Facility::query()->updateOrCreate(
                ['slug' => Str::slug($facility['name'])],
                [
                    'name' => $facility['name'],
                    'icon' => $facility['icon'],
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }
    }
}
