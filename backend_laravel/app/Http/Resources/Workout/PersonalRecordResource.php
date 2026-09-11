<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'exercise_id' => $this->exercise_id,
            'exercise' => ExerciseResource::make($this->whenLoaded('exercise')),
            'best_weight' => (float) $this->best_weight,
            'best_reps' => $this->best_reps,
            'best_volume' => (float) $this->best_volume,
            'best_estimated_one_rep_max' => $this->best_estimated_one_rep_max !== null ? (float) $this->best_estimated_one_rep_max : null,
            'estimated_one_rep_max_weight' => $this->estimated_one_rep_max_weight !== null ? (float) $this->estimated_one_rep_max_weight : null,
            'estimated_one_rep_max_reps' => $this->estimated_one_rep_max_reps,
            'estimated_one_rep_max_formula' => $this->estimated_one_rep_max_formula,
            'estimated_one_rep_max_achieved_at' => $this->estimated_one_rep_max_achieved_at?->toIso8601String(),
            'achieved_at' => $this->achieved_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
