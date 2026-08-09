<?php

namespace App\Services\Diet;

use App\Models\DietPlanTemplate;
use App\Models\FoodCatalogItem;
use Illuminate\Validation\ValidationException;

class DietPlanTemplateService
{
    /**
     * Produces the reusable, scope-free portion of a diet-plan payload.
     * Callers must supply their own gym, branch and member scope.
     *
     * @return array<string, mixed>
     */
    public function planPayload(DietPlanTemplate $template): array
    {
        if ($template->status !== 'active') {
            throw ValidationException::withMessages([
                'diet_template_id' => ['Only active global diet templates can be used.'],
            ]);
        }

        $meals = collect($template->meals ?? [])->values();
        $catalogIds = $meals
            ->flatMap(fn (array $meal) => collect($meal['items'] ?? [])->pluck('food_catalog_item_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $activeCatalogIds = FoodCatalogItem::query()
            ->active()
            ->whereKey($catalogIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $meals = $meals->map(function (array $meal) use ($activeCatalogIds): array {
            $meal['items'] = collect($meal['items'] ?? [])->map(function (array $item) use ($activeCatalogIds): array {
                if (isset($item['food_catalog_item_id'])
                    && ! in_array((int) $item['food_catalog_item_id'], $activeCatalogIds, true)) {
                    unset($item['food_catalog_item_id']);
                }

                return $item;
            })->values()->all();

            return $meal;
        })->values()->all();

        return [
            'name' => $template->name,
            'goal' => $template->goal,
            'daily_calorie_target' => $template->daily_calorie_target,
            'protein_target_g' => $template->protein_target_g,
            'carbs_target_g' => $template->carbs_target_g,
            'fats_target_g' => $template->fats_target_g,
            'dietary_preferences' => $template->dietary_preferences,
            'allergies_and_restrictions' => $template->allergies_and_restrictions,
            'notes' => $template->notes,
            'meals' => $meals,
        ];
    }
}
