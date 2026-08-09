<?php

namespace App\Http\Resources\Diet;

use App\Enums\RoleName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DietPlanTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwned = (int) $this->created_by_user_id === (int) $request->user()?->id;
        $source = $isOwned
            && $request->user()?->active_role !== RoleName::PlatformAdmin->value
                ? 'trainer'
                : 'atlas';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal' => $this->goal,
            'daily_calorie_target' => $this->daily_calorie_target,
            'protein_target_g' => $this->decimalOrNull($this->protein_target_g),
            'carbs_target_g' => $this->decimalOrNull($this->carbs_target_g),
            'fats_target_g' => $this->decimalOrNull($this->fats_target_g),
            'dietary_preferences' => $this->dietary_preferences,
            'allergies_and_restrictions' => $this->allergies_and_restrictions,
            'notes' => $this->notes,
            'meals' => collect($this->meals ?? [])->values(),
            'status' => $this->status,
            'is_owned' => $isOwned,
            'source' => $source,
        ];
    }

    private function decimalOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
