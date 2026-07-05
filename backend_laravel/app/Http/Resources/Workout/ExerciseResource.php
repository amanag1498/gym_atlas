<?php

namespace App\Http\Resources\Workout;

use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bodyPart = ExerciseBookCatalog::bodyPartForMuscleGroup($this->muscle_group);

        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'body_part' => $bodyPart,
            'body_part_label' => ExerciseBookCatalog::bodyPartLabel($bodyPart),
            'muscle_group' => $this->muscle_group,
            'secondary_muscles' => $this->secondary_muscles ?? [],
            'equipment' => $this->equipment,
            'difficulty' => $this->difficulty,
            'instructions' => $this->instructions,
            'image_url' => $this->image_url,
            'video_url' => $this->video_url,
            'is_global' => $this->is_global,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
