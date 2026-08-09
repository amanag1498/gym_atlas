<?php

namespace App\Services\Diet;

use App\Models\DietPlan;
use App\Models\FoodCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DietPlanService
{
    public function create(User $actor, array $payload): array
    {
        return DB::transaction(function () use ($actor, $payload): array {
            $plans = [];
            foreach ($payload['member_ids'] as $memberId) {
                $plans[] = $this->persist(new DietPlan, $actor, $payload + ['member_id' => $memberId]);
            }

            return $plans;
        });
    }

    public function update(DietPlan $plan, User $actor, array $payload): DietPlan
    {
        return DB::transaction(fn () => $this->persist($plan, $actor, $payload + ['member_id' => $plan->member_id, 'gym_id' => $plan->gym_id, 'branch_id' => $plan->branch_id]));
    }

    private function persist(DietPlan $plan, User $actor, array $payload): DietPlan
    {
        $existingMeals = $plan->exists
            ? $plan->meals()->with('items')->get()->values()
            : collect();

        $plan->fill(collect($payload)->except(['member_ids', 'meals'])->all());
        $plan->trainer_id ??= $actor->active_role === 'trainer' ? $actor->id : null;
        $plan->created_by_user_id ??= $actor->id;
        $plan->assigned_at ??= now();
        $plan->save();

        $usedMealIds = [];
        foreach ($payload['meals'] as $mealIndex => $mealData) {
            $requestedMealId = isset($mealData['id'])
                ? (int) $mealData['id']
                : null;
            $meal = $requestedMealId
                ? $existingMeals->firstWhere('id', $requestedMealId)
                : $existingMeals->get($mealIndex);
            if ($meal && in_array($meal->id, $usedMealIds, true)) {
                $meal = null;
            }
            $meal ??= $plan->meals()->make();
            $existingItems = $meal->exists
                ? $meal->items->values()
                : collect();

            $meal->fill(
                collect($mealData)->except(['id', 'items'])->all()
                + ['sort_order' => $mealIndex]
            );
            $plan->meals()->save($meal);
            $usedMealIds[] = $meal->id;

            $usedItemIds = [];
            foreach (($mealData['items'] ?? []) as $itemIndex => $itemData) {
                $requestedItemId = isset($itemData['id'])
                    ? (int) $itemData['id']
                    : null;
                $item = $requestedItemId
                    ? $existingItems->firstWhere('id', $requestedItemId)
                    : $existingItems->get($itemIndex);
                if ($item && in_array($item->id, $usedItemIds, true)) {
                    $item = null;
                }
                $item ??= $meal->items()->make();
                $catalogItemId = isset($itemData['food_catalog_item_id'])
                    ? (int) $itemData['food_catalog_item_id']
                    : null;
                if ($catalogItemId !== null
                    && (int) $item->food_catalog_item_id !== $catalogItemId
                    && ! FoodCatalogItem::query()->active()->whereKey($catalogItemId)->exists()) {
                    throw ValidationException::withMessages([
                        "meals.{$mealIndex}.items.{$itemIndex}.food_catalog_item_id" => [
                            'The selected catalog food is no longer active. Choose another food or add it as a custom item.',
                        ],
                    ]);
                }
                $item->fill(
                    collect($itemData)->except('id')->all()
                    + ['sort_order' => $itemIndex]
                );
                $meal->items()->save($item);
                $usedItemIds[] = $item->id;
            }

            $existingItems
                ->reject(fn ($item) => in_array($item->id, $usedItemIds, true))
                ->each
                ->delete();
        }

        $existingMeals
            ->reject(fn ($meal) => in_array($meal->id, $usedMealIds, true))
            ->each
            ->delete();

        return $plan->fresh(['member', 'trainer', 'creator', 'meals.items']);
    }
}
