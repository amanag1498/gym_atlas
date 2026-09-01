<?php

namespace Database\Seeders;

use App\Models\FoodCatalogItem;
use Illuminate\Database\Seeder;

class FoodCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->foods() as $food) {
            [$name, $category, $quantity, $grams, $calories, $protein, $carbs, $fats, $fiber, $tags, $allergens] = $food;

            $item = FoodCatalogItem::query()
                ->where('name', $name)
                ->whereNull('created_by_user_id')
                ->first();

            if ($item === null && FoodCatalogItem::query()->where('name', $name)->exists()) {
                continue;
            }

            ($item ?? new FoodCatalogItem(['name' => $name]))->fill([
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
                'notes' => $food[11] ?? $this->estimateNote(),
                'is_active' => true,
            ])->save();
        }
    }

    /** @return array<int, array{string, string, string, float, int, float, float, float, float, array<int, string>, array<int, string>, 11?: string}> */
    private function foods(): array
    {
        $veg = ['vegetarian'];
        $vegan = ['vegetarian', 'vegan'];

        return array_merge([
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
        ], $this->usdaIndianFoods(), $this->indianRecipeEstimates());
    }

    private function estimateNote(): string
    {
        return 'Gym Atlas reference serving; recipe-derived estimate. Values vary by ingredients, oil, preparation, and brand. Verify packaged-food labels where applicable.';
    }

    private function usdaNote(int $fdcId): string
    {
        return "Per 100 g. Source: USDA FoodData Central, FNDDS 2021-2023, FDC ID {$fdcId}; CC0/public domain. Values remain recipe-dependent.";
    }

    /** @return array<int, array{string, string, string, float, int, float, float, float, float, array<int, string>, array<int, string>, string}> */
    private function usdaIndianFoods(): array
    {
        $veg = ['vegetarian'];
        $vegan = ['vegetarian', 'vegan'];

        return [
            ['Phirni', 'Indian desserts', '100 g', 100, 144, 3.58, 25.13, 3.11, 0.3, $veg, ['milk'], $this->usdaNote(2705686)],
            ['Barfi', 'Indian desserts', '100 g', 100, 284, 5.42, 40.43, 11.36, 0.4, $veg, ['milk'], $this->usdaNote(2705700)],
            ['Paratha, plain', 'Indian breads', '100 g', 100, 326, 6.36, 45.35, 13.20, 9.6, $veg, ['gluten'], $this->usdaNote(2707715)],
            ['Paneer', 'Protein', '100 g', 100, 299, 15.86, 22.46, 15.52, 0.0, $veg, ['milk'], $this->usdaNote(2705740)],
            ['Chicken biryani', 'Indian meals', '100 g', 100, 104, 7.15, 13.55, 2.38, 1.1, [], [], $this->usdaNote(2706538)],
            ['Chapatti or roti', 'Indian breads', '100 g', 100, 299, 7.85, 46.13, 9.20, 9.7, $vegan, ['gluten'], $this->usdaNote(2707713)],
            ['Naan', 'Indian breads', '100 g', 100, 311, 11.09, 50.23, 7.28, 5.2, $veg, ['gluten', 'milk'], $this->usdaNote(2707613)],
            ['Plain dosa', 'Indian breakfast', '100 g', 100, 210, 5.70, 37.04, 4.05, 1.8, $vegan, [], $this->usdaNote(2708347)],
            ['Ladoo', 'Indian desserts', '100 g', 100, 411, 6.92, 46.74, 22.25, 4.0, $veg, [], $this->usdaNote(2710351)],
            ['Upma', 'Indian breakfast', '100 g', 100, 87, 1.98, 13.81, 2.64, 1.3, $vegan, ['gluten'], $this->usdaNote(2709128)],
            ['Puri', 'Indian breads', '100 g', 100, 409, 6.84, 39.23, 24.94, 3.5, $vegan, ['gluten'], $this->usdaNote(2707714)],
            ['Masala dosa', 'Indian breakfast', '100 g', 100, 184, 5.46, 30.80, 4.27, 2.2, $vegan, [], $this->usdaNote(2709129)],
            ['Papad, roasted', 'Indian sides', '100 g', 100, 371, 25.56, 59.87, 3.25, 18.6, $vegan, [], $this->usdaNote(2707429)],
            ['Sambar', 'Indian curries and dals', '100 g', 100, 86, 4.33, 11.77, 2.71, 4.0, $vegan, [], $this->usdaNote(2707430)],
            ['Chana saag', 'Indian curries and dals', '100 g', 100, 89, 4.10, 8.79, 4.33, 3.0, $vegan, [], $this->usdaNote(2709632)],
            ['Idli', 'Indian breakfast', '100 g', 100, 128, 6.36, 24.98, 0.35, 5.8, $vegan, [], $this->usdaNote(2708346)],
            ['Vegetable biryani', 'Indian meals', '100 g', 100, 109, 1.93, 17.91, 3.20, 1.2, $veg, ['milk'], $this->usdaNote(2708985)],
            ['Meat biryani', 'Indian meals', '100 g', 100, 145, 8.50, 12.19, 6.79, 1.0, [], [], $this->usdaNote(2706490)],
            ['Palak paneer', 'Indian curries and dals', '100 g', 100, 101, 5.42, 4.28, 7.02, 0.9, $veg, ['milk'], $this->usdaNote(2709631)],
            ['Tea with milk', 'Indian beverages', '100 ml', 100, 51, 1.58, 8.95, 1.05, 0.1, $veg, ['milk'], $this->usdaNote(2710506)],
        ];
    }

    /** @return array<int, array{string, string, string, float, int, float, float, float, float, array<int, string>, array<int, string>}> */
    private function indianRecipeEstimates(): array
    {
        $veg = ['vegetarian'];
        $vegan = ['vegetarian', 'vegan'];

        return [
            ['Basmati rice, cooked', 'Indian grains and millets', '1 cup', 160, 205, 4.2, 44.5, 0.5, 0.6, $vegan, []],
            ['Jeera rice', 'Indian grains and millets', '1 cup', 180, 245, 4.5, 46.0, 5.0, 1.0, $veg, ['milk']],
            ['Ragi porridge, unsweetened', 'Indian grains and millets', '1 bowl', 250, 190, 5.0, 38.0, 2.0, 4.0, $vegan, []],
            ['Jowar roti', 'Indian breads', '1 medium', 60, 145, 4.0, 30.0, 1.5, 4.0, $vegan, []],
            ['Bajra roti', 'Indian breads', '1 medium', 60, 160, 4.5, 29.0, 3.0, 4.0, $vegan, []],
            ['Ragi roti', 'Indian breads', '1 medium', 60, 150, 4.0, 30.0, 2.0, 4.5, $vegan, []],
            ['Makki roti', 'Indian breads', '1 medium', 60, 165, 3.5, 32.0, 3.0, 3.0, $vegan, []],
            ['Akki roti', 'Indian breads', '1 medium', 80, 190, 3.0, 36.0, 4.0, 2.0, $vegan, []],
            ['Thepla', 'Indian breads', '1 medium', 60, 160, 4.0, 25.0, 5.0, 3.0, $veg, ['gluten', 'milk']],
            ['Appam', 'Indian breakfast', '1 medium', 70, 120, 2.0, 24.0, 2.0, 1.0, $vegan, []],
            ['Puttu', 'Indian breakfast', '1 cup', 160, 280, 5.0, 55.0, 5.0, 4.0, $vegan, []],
            ['Pongal', 'Indian breakfast', '1 cup', 220, 280, 8.0, 45.0, 8.0, 5.0, $veg, ['milk']],
            ['Sabudana khichdi', 'Indian breakfast', '1 cup', 200, 320, 5.0, 55.0, 10.0, 4.0, $vegan, ['peanuts']],
            ['Vegetable poha', 'Indian breakfast', '1 cup', 180, 250, 5.0, 45.0, 6.0, 4.0, $vegan, ['peanuts']],
            ['Uttapam with vegetables', 'Indian breakfast', '1 medium', 150, 230, 6.0, 40.0, 5.0, 3.0, $vegan, []],
            ['Besan chilla', 'Indian breakfast', '2 medium', 140, 260, 12.0, 32.0, 9.0, 7.0, $vegan, []],
            ['Moong dal chilla', 'Indian breakfast', '2 medium', 160, 240, 14.0, 34.0, 6.0, 8.0, $vegan, []],
            ['Dhokla', 'Indian snacks', '4 pieces', 160, 250, 10.0, 40.0, 6.0, 6.0, $vegan, []],
            ['Khandvi', 'Indian snacks', '8 pieces', 150, 230, 9.0, 28.0, 9.0, 5.0, $veg, ['milk']],
            ['Roasted makhana', 'Indian snacks', '30 g', 30, 110, 3.0, 22.0, 1.5, 2.5, $veg, ['milk']],
            ['Bhel puri', 'Indian snacks', '1 bowl', 180, 280, 8.0, 48.0, 7.0, 7.0, $vegan, ['peanuts']],
            ['Sprouted moong chaat', 'Indian snacks', '1 bowl', 180, 170, 11.0, 29.0, 1.5, 8.0, $vegan, []],
            ['Vegetable khichdi', 'Indian meals', '1 bowl', 250, 300, 11.0, 52.0, 6.0, 8.0, $veg, ['milk']],
            ['Dal khichdi', 'Indian meals', '1 bowl', 250, 320, 12.0, 54.0, 7.0, 7.0, $veg, ['milk']],
            ['Curd rice', 'Indian meals', '1 bowl', 250, 300, 8.0, 50.0, 7.0, 2.0, $veg, ['milk']],
            ['Lemon rice', 'Indian meals', '1 cup', 200, 300, 6.0, 50.0, 9.0, 3.0, $vegan, ['peanuts']],
            ['Rajma chawal', 'Indian meals', '1 plate', 350, 520, 20.0, 92.0, 8.0, 16.0, $vegan, []],
            ['Chole chawal', 'Indian meals', '1 plate', 350, 560, 19.0, 95.0, 11.0, 16.0, $vegan, []],
            ['Dal tadka', 'Indian curries and dals', '1 cup', 220, 260, 14.0, 36.0, 7.0, 12.0, $veg, ['milk']],
            ['Dal makhani', 'Indian curries and dals', '1 cup', 220, 330, 14.0, 38.0, 14.0, 11.0, $veg, ['milk']],
            ['Chana dal', 'Indian curries and dals', '1 cup', 220, 270, 15.0, 42.0, 5.0, 12.0, $vegan, []],
            ['Toor dal', 'Indian curries and dals', '1 cup', 220, 250, 14.0, 40.0, 5.0, 11.0, $vegan, []],
            ['Urad dal', 'Indian curries and dals', '1 cup', 220, 260, 15.0, 41.0, 5.0, 12.0, $vegan, []],
            ['Chole masala', 'Indian curries and dals', '1 cup', 220, 300, 14.0, 45.0, 8.0, 13.0, $vegan, []],
            ['Rajma masala', 'Indian curries and dals', '1 cup', 220, 290, 15.0, 46.0, 6.0, 13.0, $vegan, []],
            ['Kadhi pakora', 'Indian curries and dals', '1 cup', 220, 280, 10.0, 30.0, 13.0, 4.0, $veg, ['milk']],
            ['Rasam', 'Indian curries and dals', '1 cup', 220, 70, 3.0, 11.0, 2.0, 2.0, $vegan, []],
            ['Mixed vegetable sabzi', 'Indian vegetables', '1 cup', 180, 160, 5.0, 24.0, 6.0, 7.0, $vegan, []],
            ['Bhindi masala', 'Indian vegetables', '1 cup', 180, 170, 5.0, 22.0, 8.0, 7.0, $vegan, []],
            ['Aloo gobi', 'Indian vegetables', '1 cup', 200, 190, 5.0, 30.0, 7.0, 6.0, $vegan, []],
            ['Baingan bharta', 'Indian vegetables', '1 cup', 200, 180, 5.0, 22.0, 9.0, 8.0, $vegan, []],
            ['Sarson ka saag', 'Indian vegetables', '1 cup', 200, 170, 7.0, 18.0, 9.0, 7.0, $veg, ['milk']],
            ['Paneer bhurji', 'Indian protein dishes', '1 cup', 200, 390, 24.0, 14.0, 27.0, 3.0, $veg, ['milk']],
            ['Paneer tikka', 'Indian protein dishes', '150 g', 150, 330, 24.0, 12.0, 21.0, 3.0, $veg, ['milk']],
            ['Tofu bhurji', 'Indian protein dishes', '1 cup', 200, 250, 22.0, 12.0, 14.0, 5.0, $vegan, ['soy']],
            ['Egg bhurji', 'Indian protein dishes', '2 eggs', 150, 240, 15.0, 8.0, 16.0, 2.0, [], ['egg']],
            ['Tandoori chicken', 'Indian protein dishes', '150 g', 150, 280, 40.0, 6.0, 10.0, 1.0, [], ['milk']],
            ['Chicken tikka', 'Indian protein dishes', '150 g', 150, 270, 39.0, 7.0, 9.0, 1.0, [], ['milk']],
            ['Chicken curry', 'Indian protein dishes', '1 cup', 220, 320, 30.0, 12.0, 17.0, 3.0, [], []],
            ['Fish curry', 'Indian protein dishes', '1 cup', 220, 280, 28.0, 10.0, 14.0, 3.0, [], ['fish']],
            ['Low-fat chaas', 'Indian beverages', '250 ml', 250, 70, 4.0, 8.0, 2.0, 0.0, $veg, ['milk']],
            ['Plain lassi, unsweetened', 'Indian beverages', '250 ml', 250, 150, 8.0, 12.0, 8.0, 0.0, $veg, ['milk']],
            ['Nimbu pani, unsweetened', 'Indian beverages', '250 ml', 250, 15, 0.2, 4.0, 0.0, 0.2, $vegan, []],
            ['Jaljeera, unsweetened', 'Indian beverages', '250 ml', 250, 25, 0.5, 5.0, 0.2, 0.8, $vegan, []],
            ['Aam panna, lightly sweetened', 'Indian beverages', '250 ml', 250, 95, 0.5, 24.0, 0.2, 1.0, $vegan, []],
            ['Kheer', 'Indian desserts', '1 small bowl', 150, 230, 6.0, 35.0, 8.0, 0.5, $veg, ['milk']],
            ['Shrikhand', 'Indian desserts', '100 g', 100, 240, 7.0, 34.0, 9.0, 0.0, $veg, ['milk']],
            ['Halwa, semolina', 'Indian desserts', '100 g', 100, 300, 4.0, 45.0, 12.0, 1.5, $veg, ['gluten', 'milk']],
        ];
    }
}
