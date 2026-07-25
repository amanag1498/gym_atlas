<?php

namespace Database\Seeders;

use App\Models\DietPlanTemplate;
use Illuminate\Database\Seeder;

class DietPlanTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Balanced Everyday Nutrition', 'goal' => 'General wellness', 'daily_calorie_target' => 2000, 'protein_target_g' => 120, 'carbs_target_g' => 220, 'fats_target_g' => 65, 'dietary_preferences' => 'Flexible balanced diet. Prefer whole foods and adequate hydration.', 'notes' => 'Adjust portions to individual needs and medical guidance.', 'meals' => [['name' => 'Breakfast', 'meal_type' => 'breakfast', 'scheduled_time' => '08:00', 'items' => [['name' => 'Oats with milk', 'quantity' => '1 bowl', 'calories' => 380], ['name' => 'Banana', 'quantity' => '1 medium', 'calories' => 105]]], ['name' => 'Lunch', 'meal_type' => 'lunch', 'scheduled_time' => '13:00', 'items' => [['name' => 'Rice, dal and vegetables', 'quantity' => '1 plate', 'calories' => 620]]], ['name' => 'Dinner', 'meal_type' => 'dinner', 'scheduled_time' => '20:00', 'items' => [['name' => 'Roti with paneer and salad', 'quantity' => '1 plate', 'calories' => 520]]]]],
            ['name' => 'High Protein Strength', 'goal' => 'Muscle gain and strength support', 'daily_calorie_target' => 2600, 'protein_target_g' => 170, 'carbs_target_g' => 300, 'fats_target_g' => 75, 'dietary_preferences' => 'High protein, spread across the day.', 'notes' => 'Use a qualified professional for allergy or medical adjustments.', 'meals' => [['name' => 'Protein breakfast', 'meal_type' => 'breakfast', 'scheduled_time' => '07:30', 'items' => [['name' => 'Eggs or paneer bhurji', 'quantity' => '1 serving', 'calories' => 350], ['name' => 'Whole wheat toast', 'quantity' => '2 slices', 'calories' => 180]]], ['name' => 'Training lunch', 'meal_type' => 'lunch', 'scheduled_time' => '13:30', 'items' => [['name' => 'Chicken or tofu rice bowl', 'quantity' => '1 bowl', 'calories' => 720]]], ['name' => 'Recovery dinner', 'meal_type' => 'dinner', 'scheduled_time' => '20:30', 'items' => [['name' => 'Dal, roti and curd', 'quantity' => '1 plate', 'calories' => 610]]]]],
            ['name' => 'Vegetarian Fat Loss', 'goal' => 'Sustainable fat loss', 'daily_calorie_target' => 1700, 'protein_target_g' => 110, 'carbs_target_g' => 175, 'fats_target_g' => 50, 'dietary_preferences' => 'Vegetarian, high-fibre, protein with each meal.', 'notes' => 'Do not use as a medical diet. Personalise calories before assignment.', 'meals' => [['name' => 'Fibre breakfast', 'meal_type' => 'breakfast', 'scheduled_time' => '08:00', 'items' => [['name' => 'Vegetable poha with curd', 'quantity' => '1 bowl', 'calories' => 340]]], ['name' => 'Protein lunch', 'meal_type' => 'lunch', 'scheduled_time' => '13:00', 'items' => [['name' => 'Rajma salad bowl', 'quantity' => '1 bowl', 'calories' => 520]]], ['name' => 'Light dinner', 'meal_type' => 'dinner', 'scheduled_time' => '19:30', 'items' => [['name' => 'Tofu stir fry with roti', 'quantity' => '1 plate', 'calories' => 450]]]]],
        ] as $template) {
            DietPlanTemplate::query()->updateOrCreate(['name' => $template['name']], $template + ['status' => 'active']);
        }
    }
}
