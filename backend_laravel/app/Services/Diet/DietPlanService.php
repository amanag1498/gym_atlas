<?php

namespace App\Services\Diet;

use App\Models\DietPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        $plan->fill(collect($payload)->except(['member_ids', 'meals'])->all());
        $plan->trainer_id ??= $actor->active_role === 'trainer' ? $actor->id : null;
        $plan->created_by_user_id ??= $actor->id;
        $plan->assigned_at ??= now();
        $plan->save();
        $plan->meals()->delete();
        foreach ($payload['meals'] as $mealIndex => $mealData) {
            $meal = $plan->meals()->create(collect($mealData)->except('items')->all() + ['sort_order' => $mealIndex]);
            foreach (($mealData['items'] ?? []) as $itemIndex => $itemData) {
                $meal->items()->create($itemData + ['sort_order' => $itemIndex]);
            }
        }

        return $plan->fresh(['member', 'trainer', 'creator', 'meals.items']);
    }
}
