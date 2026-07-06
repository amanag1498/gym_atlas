<?php

namespace Database\Seeders;

use App\Models\FitnessGoal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FitnessGoalSeeder extends Seeder
{
    public function run(): void
    {
        $goals = [
            ['name' => 'Lose Fat', 'icon' => 'local_fire_department', 'description' => 'Reduce body fat and improve conditioning.', 'sort_order' => 1],
            ['name' => 'Build Muscle', 'icon' => 'fitness_center', 'description' => 'Focus on lean muscle growth and body composition.', 'sort_order' => 2],
            ['name' => 'Get Stronger', 'icon' => 'bolt', 'description' => 'Increase strength, lifts, and overall performance.', 'sort_order' => 3],
            ['name' => 'Improve Endurance', 'icon' => 'directions_run', 'description' => 'Boost stamina, cardio capacity, and energy.', 'sort_order' => 4],
            ['name' => 'General Fitness', 'icon' => 'favorite', 'description' => 'Stay active, healthy, and consistent every week.', 'sort_order' => 5],
            ['name' => 'Mobility & Recovery', 'icon' => 'self_improvement', 'description' => 'Improve movement quality, flexibility, and recovery.', 'sort_order' => 6],
        ];

        foreach ($goals as $goal) {
            FitnessGoal::query()->updateOrCreate(
                ['slug' => Str::slug($goal['name'])],
                [
                    'name' => $goal['name'],
                    'icon' => $goal['icon'],
                    'description' => $goal['description'],
                    'sort_order' => $goal['sort_order'],
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }
    }
}
