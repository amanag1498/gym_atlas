<?php

namespace App\Http\Resources\Diet;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DietPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'trainer_id' => $this->trainer_id,
            'independent_trainer_member_relationship_id' => $this->independent_trainer_member_relationship_id,
            'coaching_scope' => $this->independent_trainer_member_relationship_id !== null
                ? 'independent'
                : ($this->gym_id !== null ? 'gym' : 'personal'),
            'created_by_user_id' => $this->created_by_user_id,
            'is_member_owned' => (int) $this->created_by_user_id === (int) $request->user()?->id
                && $this->trainer_id === null,
            'name' => $this->name,
            'goal' => $this->goal,
            'daily_calorie_target' => $this->daily_calorie_target,
            'protein_target_g' => $this->decimalOrNull($this->protein_target_g),
            'carbs_target_g' => $this->decimalOrNull($this->carbs_target_g),
            'fats_target_g' => $this->decimalOrNull($this->fats_target_g),
            'dietary_preferences' => $this->dietary_preferences,
            'allergies_and_restrictions' => $this->allergies_and_restrictions,
            'notes' => $this->notes,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'member' => UserResource::make($this->whenLoaded('member')),
            'trainer' => UserResource::make($this->whenLoaded('trainer')),
            'meals' => $this->whenLoaded(
                'meals',
                fn () => $this->meals
                    ->map(fn ($meal) => [
                        'id' => $meal->id,
                        'name' => $meal->name,
                        'meal_type' => $meal->meal_type,
                        'scheduled_time' => $meal->scheduled_time,
                        'calories' => $meal->calories,
                        'protein_g' => $this->decimalOrNull($meal->protein_g),
                        'carbs_g' => $this->decimalOrNull($meal->carbs_g),
                        'fats_g' => $this->decimalOrNull($meal->fats_g),
                        'notes' => $meal->notes,
                        'completed_for' => $meal->relationLoaded('logs')
                            ? $meal->logs->first()?->completed_at?->toIso8601String()
                            : null,
                        'items' => $meal->items
                            ->map(fn ($item) => [
                                'id' => $item->id,
                                'name' => $item->name,
                                'quantity' => $item->quantity,
                                'calories' => $item->calories,
                                'protein_g' => $this->decimalOrNull($item->protein_g),
                                'carbs_g' => $this->decimalOrNull($item->carbs_g),
                                'fats_g' => $this->decimalOrNull($item->fats_g),
                                'notes' => $item->notes,
                            ])
                            ->values(),
                    ])
                    ->values(),
            ),
        ];
    }

    private function decimalOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
