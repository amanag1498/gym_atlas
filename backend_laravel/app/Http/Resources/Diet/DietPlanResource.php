<?php

namespace App\Http\Resources\Diet;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DietPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'gym_id' => $this->gym_id, 'branch_id' => $this->branch_id, 'member_id' => $this->member_id, 'trainer_id' => $this->trainer_id, 'name' => $this->name, 'goal' => $this->goal, 'daily_calorie_target' => $this->daily_calorie_target, 'protein_target_g' => (float) $this->protein_target_g, 'carbs_target_g' => (float) $this->carbs_target_g, 'fats_target_g' => (float) $this->fats_target_g, 'dietary_preferences' => $this->dietary_preferences, 'allergies_and_restrictions' => $this->allergies_and_restrictions, 'notes' => $this->notes, 'status' => $this->status, 'assigned_at' => $this->assigned_at?->toIso8601String(), 'starts_on' => $this->starts_on?->toDateString(), 'ends_on' => $this->ends_on?->toDateString(), 'member' => UserResource::make($this->whenLoaded('member')), 'trainer' => UserResource::make($this->whenLoaded('trainer')), 'meals' => $this->whenLoaded('meals', fn () => $this->meals->map(fn ($meal) => ['id' => $meal->id, 'name' => $meal->name, 'meal_type' => $meal->meal_type, 'scheduled_time' => $meal->scheduled_time, 'calories' => $meal->calories, 'protein_g' => (float) $meal->protein_g, 'carbs_g' => (float) $meal->carbs_g, 'fats_g' => (float) $meal->fats_g, 'notes' => $meal->notes, 'items' => $meal->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'quantity' => $item->quantity, 'calories' => $item->calories, 'protein_g' => (float) $item->protein_g, 'carbs_g' => (float) $item->carbs_g, 'fats_g' => (float) $item->fats_g, 'notes' => $item->notes])->values()])->values())];
    }
}
