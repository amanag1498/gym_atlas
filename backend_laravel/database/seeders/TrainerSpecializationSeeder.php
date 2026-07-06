<?php

namespace Database\Seeders;

use App\Models\TrainerSpecialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrainerSpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            ['name' => 'Strength', 'icon' => 'fitness_center', 'description' => 'Strength training, progressive overload, and technique coaching.', 'sort_order' => 1],
            ['name' => 'Fat loss', 'icon' => 'local_fire_department', 'description' => 'Sustainable fat loss with training consistency and conditioning.', 'sort_order' => 2],
            ['name' => 'Body recomposition', 'icon' => 'monitor_weight', 'description' => 'Build muscle while improving body composition.', 'sort_order' => 3],
            ['name' => 'Mobility', 'icon' => 'self_improvement', 'description' => 'Movement quality, flexibility, mobility, and recovery work.', 'sort_order' => 4],
            ['name' => 'Sports conditioning', 'icon' => 'directions_run', 'description' => 'Performance, stamina, agility, and athletic conditioning.', 'sort_order' => 5],
        ];

        foreach ($specializations as $specialization) {
            TrainerSpecialization::query()->updateOrCreate(
                ['slug' => Str::slug($specialization['name'])],
                [
                    'name' => $specialization['name'],
                    'icon' => $specialization['icon'],
                    'description' => $specialization['description'],
                    'sort_order' => $specialization['sort_order'],
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }
    }
}
