<?php

namespace Database\Seeders;

use App\Models\FoodCatalogItem;
use Illuminate\Database\Seeder;

class FoodCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->foods() as [$name, $category, $quantity, $grams, $calories, $protein, $carbs, $fats, $fiber, $tags, $allergens]) {
            FoodCatalogItem::query()->firstOrCreate(
                ['name' => $name],
                [
                    'created_by_user_id' => null,
                    'category' => $category,
                    'default_quantity' => $quantity,
                    'serving_size_g' => $grams,
                    'calories' => $calories,
                    'protein_g' => $protein,
                    'carbs_g' => $carbs,
                    'fats_g' => $fats,
                    'fiber_g' => $fiber,
                    'dietary_tags' => $tags,
                    'allergens' => $allergens,
                    'notes' => 'Reference serving. Values are approximate and vary by variety, preparation, and brand; verify packaged-food labels when applicable.',
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<int, array{string, string, string, float, int, float, float, float, float, array<int, string>, array<int, string>}> */
    private function foods(): array
    {
        $veg = ['vegetarian'];
        $vegan = ['vegetarian', 'vegan'];

        return [
            ['Rolled oats, dry', 'Grains', '40 g', 40, 152, 5.1, 27.1, 2.8, 4.0, $vegan, ['gluten']],
            ['Brown rice, cooked', 'Grains', '1 cup', 195, 216, 5.0, 44.8, 1.8, 3.5, $vegan, []],
            ['White rice, cooked', 'Grains', '1 cup', 158, 205, 4.3, 44.5, 0.4, 0.6, $vegan, []],
            ['Whole wheat roti', 'Grains', '1 medium', 40, 120, 4.0, 22.0, 2.0, 3.0, $vegan, ['gluten']],
            ['Whole wheat bread', 'Grains', '2 slices', 56, 140, 7.0, 24.0, 2.0, 4.0, $vegan, ['gluten']],
            ['Quinoa, cooked', 'Grains', '1 cup', 185, 222, 8.1, 39.4, 3.6, 5.2, $vegan, []],
            ['Poha, cooked with vegetables', 'Prepared meal', '1 cup', 180, 250, 5.0, 45.0, 6.0, 4.0, $vegan, ['peanuts']],
            ['Idli', 'Prepared meal', '2 medium', 100, 146, 4.5, 30.0, 0.8, 2.0, $vegan, []],
            ['Plain dosa', 'Prepared meal', '1 medium', 100, 168, 4.0, 29.0, 4.0, 1.5, $vegan, []],
            ['Upma with vegetables', 'Prepared meal', '1 cup', 200, 260, 7.0, 42.0, 8.0, 5.0, $vegan, ['gluten']],
            ['Moong dal, cooked', 'Legumes', '1 cup', 200, 212, 14.0, 38.0, 1.0, 15.0, $vegan, []],
            ['Masoor dal, cooked', 'Legumes', '1 cup', 198, 230, 17.9, 39.9, 0.8, 15.6, $vegan, []],
            ['Rajma, cooked', 'Legumes', '1 cup', 177, 225, 15.3, 40.4, 0.9, 13.1, $vegan, []],
            ['Chickpeas, cooked', 'Legumes', '1 cup', 164, 269, 14.5, 45.0, 4.2, 12.5, $vegan, []],
            ['Roasted chickpeas', 'Snacks', '30 g', 30, 120, 6.0, 18.0, 2.0, 5.0, $vegan, []],
            ['Firm tofu', 'Protein', '100 g', 100, 144, 17.3, 2.8, 8.7, 2.3, $vegan, ['soy']],
            ['Tempeh', 'Protein', '100 g', 100, 195, 19.9, 7.6, 11.4, 3.8, $vegan, ['soy']],
            ['Paneer', 'Protein', '100 g', 100, 265, 18.3, 3.6, 20.8, 0.0, $veg, ['milk']],
            ['Low-fat cottage cheese', 'Protein', '100 g', 100, 82, 11.1, 3.4, 2.3, 0.0, $veg, ['milk']],
            ['Egg, boiled', 'Protein', '2 large', 100, 155, 12.6, 1.1, 10.6, 0.0, [], ['egg']],
            ['Chicken breast, cooked', 'Protein', '100 g', 100, 165, 31.0, 0.0, 3.6, 0.0, [], []],
            ['Chicken thigh, cooked skinless', 'Protein', '100 g', 100, 209, 26.0, 0.0, 10.9, 0.0, [], []],
            ['Fish, cooked lean', 'Protein', '100 g', 100, 128, 26.0, 0.0, 2.7, 0.0, [], ['fish']],
            ['Salmon, cooked', 'Protein', '100 g', 100, 206, 22.1, 0.0, 12.4, 0.0, [], ['fish']],
            ['Tuna, canned in water', 'Protein', '100 g', 100, 116, 25.5, 0.0, 0.8, 0.0, [], ['fish']],
            ['Greek yogurt, plain low-fat', 'Dairy', '170 g', 170, 130, 17.0, 9.0, 3.5, 0.0, $veg, ['milk']],
            ['Curd, plain', 'Dairy', '1 cup', 200, 122, 7.0, 9.4, 6.5, 0.0, $veg, ['milk']],
            ['Milk, low-fat', 'Dairy', '250 ml', 250, 105, 8.5, 12.0, 2.5, 0.0, $veg, ['milk']],
            ['Unsweetened soy milk', 'Dairy alternatives', '250 ml', 250, 83, 8.0, 4.0, 4.0, 1.0, $vegan, ['soy']],
            ['Banana', 'Fruit', '1 medium', 118, 105, 1.3, 27.0, 0.4, 3.1, $vegan, []],
            ['Apple', 'Fruit', '1 medium', 182, 95, 0.5, 25.1, 0.3, 4.4, $vegan, []],
            ['Orange', 'Fruit', '1 medium', 131, 62, 1.2, 15.4, 0.2, 3.1, $vegan, []],
            ['Papaya', 'Fruit', '1 cup', 145, 62, 0.7, 15.7, 0.4, 2.5, $vegan, []],
            ['Guava', 'Fruit', '1 cup', 165, 112, 4.2, 23.6, 1.6, 8.9, $vegan, []],
            ['Mango', 'Fruit', '1 cup', 165, 99, 1.4, 24.7, 0.6, 2.6, $vegan, []],
            ['Mixed berries', 'Fruit', '1 cup', 140, 70, 1.0, 17.0, 0.5, 6.0, $vegan, []],
            ['Spinach, cooked', 'Vegetables', '1 cup', 180, 41, 5.3, 6.8, 0.5, 4.3, $vegan, []],
            ['Broccoli, cooked', 'Vegetables', '1 cup', 156, 55, 3.7, 11.2, 0.6, 5.1, $vegan, []],
            ['Mixed vegetables, cooked', 'Vegetables', '1 cup', 160, 100, 4.0, 20.0, 1.5, 6.0, $vegan, []],
            ['Cucumber and tomato salad', 'Vegetables', '1 bowl', 200, 50, 2.0, 10.0, 0.5, 3.0, $vegan, []],
            ['Sweet potato, baked', 'Vegetables', '1 medium', 130, 112, 2.0, 26.0, 0.1, 3.9, $vegan, []],
            ['Potato, boiled', 'Vegetables', '1 medium', 167, 144, 3.0, 33.0, 0.2, 3.0, $vegan, []],
            ['Almonds', 'Nuts and seeds', '28 g', 28, 164, 6.0, 6.1, 14.2, 3.5, $vegan, ['tree nuts']],
            ['Peanuts, roasted', 'Nuts and seeds', '28 g', 28, 166, 6.9, 6.0, 14.1, 2.4, $vegan, ['peanuts']],
            ['Peanut butter, unsweetened', 'Nuts and seeds', '2 tbsp', 32, 188, 8.0, 6.0, 16.0, 1.9, $vegan, ['peanuts']],
            ['Chia seeds', 'Nuts and seeds', '2 tbsp', 28, 138, 4.7, 11.9, 8.7, 9.8, $vegan, []],
            ['Flax seeds, ground', 'Nuts and seeds', '2 tbsp', 14, 75, 2.6, 4.0, 5.9, 3.8, $vegan, []],
            ['Pumpkin seeds', 'Nuts and seeds', '28 g', 28, 158, 8.6, 3.0, 13.9, 1.7, $vegan, []],
            ['Coconut water, unsweetened', 'Beverages', '250 ml', 250, 46, 1.7, 8.9, 0.5, 2.6, $vegan, []],
            ['Hummus', 'Spreads', '4 tbsp', 60, 142, 4.8, 12.0, 8.5, 3.6, $vegan, ['sesame']],
            ['Avocado', 'Fruit', 'Half medium', 100, 160, 2.0, 8.5, 14.7, 6.7, $vegan, []],
        ];
    }
}
