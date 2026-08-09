<?php

namespace Database\Seeders;

use App\Models\DietPlanTemplate;
use App\Models\FoodCatalogItem;
use Illuminate\Database\Seeder;
use RuntimeException;

class ExpandedDietPlanTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $foods = FoodCatalogItem::query()->get()->keyBy('name');

        foreach ($this->templates($foods->all()) as $template) {
            DietPlanTemplate::query()->firstOrCreate(
                ['name' => $template['name']],
                $template + ['created_by_user_id' => null, 'status' => 'active'],
            );
        }
    }

    /** @param array<string, FoodCatalogItem> $foods */
    private function templates(array $foods): array
    {
        $item = function (string $name, ?string $quantity = null) use ($foods): array {
            $food = $foods[$name] ?? throw new RuntimeException("Missing seeded food: {$name}");

            return [
                'food_catalog_item_id' => $food->id,
                'name' => $food->name,
                'quantity' => $quantity ?? $food->default_quantity,
                'calories' => $food->calories,
                'protein_g' => $food->protein_g,
                'carbs_g' => $food->carbs_g,
                'fats_g' => $food->fats_g,
                'fiber_g' => $food->fiber_g,
            ];
        };
        $meal = static fn (string $name, string $type, string $time, array $items): array => ['name' => $name, 'meal_type' => $type, 'scheduled_time' => $time, 'items' => $items];

        return [
            [
                'name' => 'Indian Vegetarian Balanced Day', 'goal' => 'General wellness and meal consistency', 'daily_calorie_target' => 2000,
                'protein_target_g' => 105, 'carbs_target_g' => 255, 'fats_target_g' => 60,
                'dietary_preferences' => 'Vegetarian pattern using grains, pulses, dairy, vegetables, fruit, nuts, and seeds.',
                'notes' => 'Reference template only. Adjust energy, portions, allergies, and clinical needs before assignment.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Rolled oats, dry'), $item('Milk, low-fat'), $item('Banana')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Brown rice, cooked'), $item('Moong dal, cooked'), $item('Mixed vegetables, cooked')]),
                    $meal('Snack', 'snack', '17:00', [$item('Greek yogurt, plain low-fat'), $item('Guava')]),
                    $meal('Dinner', 'dinner', '20:00', [$item('Whole wheat roti', '2 medium'), $item('Paneer'), $item('Cucumber and tomato salad')]),
                ],
            ],
            [
                'name' => 'Indian High-Protein Training Day', 'goal' => 'Strength training and muscle recovery support', 'daily_calorie_target' => 2500,
                'protein_target_g' => 165, 'carbs_target_g' => 300, 'fats_target_g' => 72,
                'dietary_preferences' => 'Mixed diet with protein distributed across meals.',
                'notes' => 'Reference template; scale portions to the member and allow vegetarian substitutions.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '07:30', [$item('Egg, boiled'), $item('Whole wheat bread'), $item('Papaya')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('White rice, cooked'), $item('Chicken breast, cooked', '150 g'), $item('Mixed vegetables, cooked'), $item('Curd, plain')]),
                    $meal('Pre-workout', 'snack', '17:00', [$item('Banana'), $item('Peanut butter, unsweetened')]),
                    $meal('Dinner', 'dinner', '20:30', [$item('Whole wheat roti', '2 medium'), $item('Fish, cooked lean', '150 g'), $item('Spinach, cooked')]),
                ],
            ],
            [
                'name' => 'Plant-Based Protein Day', 'goal' => 'Balanced vegan nutrition', 'daily_calorie_target' => 2200,
                'protein_target_g' => 120, 'carbs_target_g' => 285, 'fats_target_g' => 68,
                'dietary_preferences' => 'Vegan; uses soy, pulses, grains, nuts, seeds, fruit, and vegetables.',
                'notes' => 'Review vitamin B12, vitamin D, iodine, calcium, and other individual needs with a qualified professional.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Rolled oats, dry', '60 g'), $item('Unsweetened soy milk'), $item('Chia seeds'), $item('Mixed berries')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Quinoa, cooked'), $item('Chickpeas, cooked'), $item('Cucumber and tomato salad')]),
                    $meal('Snack', 'snack', '17:00', [$item('Roasted chickpeas'), $item('Orange')]),
                    $meal('Dinner', 'dinner', '20:00', [$item('Brown rice, cooked'), $item('Firm tofu', '150 g'), $item('Broccoli, cooked')]),
                ],
            ],
            [
                'name' => 'Vegetarian Fat-Loss Foundation', 'goal' => 'Sustainable calorie control with filling meals', 'daily_calorie_target' => 1700,
                'protein_target_g' => 105, 'carbs_target_g' => 190, 'fats_target_g' => 50,
                'dietary_preferences' => 'Vegetarian and fibre-forward with minimally processed staples.',
                'notes' => 'Not a medical diet. Confirm an appropriate calorie target and avoid aggressive restriction.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Idli'), $item('Curd, plain', 'Half cup'), $item('Orange')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Whole wheat roti', '2 medium'), $item('Rajma, cooked'), $item('Cucumber and tomato salad')]),
                    $meal('Snack', 'snack', '16:30', [$item('Greek yogurt, plain low-fat'), $item('Apple')]),
                    $meal('Dinner', 'dinner', '19:30', [$item('Firm tofu', '150 g'), $item('Mixed vegetables, cooked'), $item('Brown rice, cooked', 'Half cup')]),
                ],
            ],
            [
                'name' => 'Affordable Indian Staples Day', 'goal' => 'Budget-conscious balanced eating', 'daily_calorie_target' => 2100,
                'protein_target_g' => 100, 'carbs_target_g' => 290, 'fats_target_g' => 58,
                'dietary_preferences' => 'Vegetarian plan centered on commonly available staple foods.',
                'notes' => 'Costs and availability vary by region; substitute equivalent seasonal produce and pulses.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Poha, cooked with vegetables'), $item('Peanuts, roasted')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('White rice, cooked'), $item('Masoor dal, cooked'), $item('Mixed vegetables, cooked')]),
                    $meal('Snack', 'snack', '17:00', [$item('Roasted chickpeas'), $item('Banana')]),
                    $meal('Dinner', 'dinner', '20:00', [$item('Whole wheat roti', '2 medium'), $item('Moong dal, cooked'), $item('Spinach, cooked')]),
                ],
            ],
            [
                'name' => 'Dairy-Free Mixed Training Day', 'goal' => 'Training support without dairy ingredients', 'daily_calorie_target' => 2300,
                'protein_target_g' => 150, 'carbs_target_g' => 270, 'fats_target_g' => 70,
                'dietary_preferences' => 'Dairy-free mixed diet. Check packaged products for hidden milk ingredients.',
                'allergies_and_restrictions' => 'Excludes milk and dairy foods; still requires individual allergen review.',
                'notes' => 'Reference plan only; seafood, egg, soy, and peanut allergens remain present.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '07:30', [$item('Rolled oats, dry'), $item('Unsweetened soy milk'), $item('Banana'), $item('Peanut butter, unsweetened')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Brown rice, cooked'), $item('Chicken breast, cooked', '150 g'), $item('Broccoli, cooked')]),
                    $meal('Snack', 'snack', '17:00', [$item('Egg, boiled'), $item('Apple')]),
                    $meal('Dinner', 'dinner', '20:30', [$item('Sweet potato, baked'), $item('Salmon, cooked', '150 g'), $item('Cucumber and tomato salad')]),
                ],
            ],
            [
                'name' => 'High-Fibre Everyday Day', 'goal' => 'Increase food variety and fibre intake', 'daily_calorie_target' => 2000,
                'protein_target_g' => 110, 'carbs_target_g' => 260, 'fats_target_g' => 60,
                'dietary_preferences' => 'Vegetarian pattern rich in legumes, whole grains, fruit, vegetables, nuts, and seeds.',
                'notes' => 'Increase fibre gradually and maintain adequate fluids when moving from a low-fibre diet.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Rolled oats, dry'), $item('Chia seeds'), $item('Guava')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Brown rice, cooked'), $item('Rajma, cooked'), $item('Spinach, cooked')]),
                    $meal('Snack', 'snack', '17:00', [$item('Apple'), $item('Almonds')]),
                    $meal('Dinner', 'dinner', '20:00', [$item('Whole wheat roti', '2 medium'), $item('Chickpeas, cooked'), $item('Broccoli, cooked')]),
                ],
            ],
            [
                'name' => 'Minimal-Cooking Workday Plan', 'goal' => 'Convenient meal consistency on busy days', 'daily_calorie_target' => 2050,
                'protein_target_g' => 125, 'carbs_target_g' => 235, 'fats_target_g' => 68,
                'dietary_preferences' => 'Flexible plan using ready-to-assemble foods and simple batch preparation.',
                'notes' => 'Use safe storage temperatures and check labels for sodium, allergens, and added sugars.',
                'meals' => [
                    $meal('Breakfast', 'breakfast', '08:00', [$item('Greek yogurt, plain low-fat'), $item('Rolled oats, dry'), $item('Mixed berries')]),
                    $meal('Lunch', 'lunch', '13:00', [$item('Whole wheat bread'), $item('Tuna, canned in water'), $item('Cucumber and tomato salad'), $item('Avocado')]),
                    $meal('Snack', 'snack', '17:00', [$item('Roasted chickpeas'), $item('Orange')]),
                    $meal('Dinner', 'dinner', '20:00', [$item('Quinoa, cooked'), $item('Firm tofu'), $item('Mixed vegetables, cooked')]),
                ],
            ],
        ];
    }
}
