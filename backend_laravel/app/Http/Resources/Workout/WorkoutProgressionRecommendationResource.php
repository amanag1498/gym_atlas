<?php

namespace App\Http\Resources\Workout;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutProgressionRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'trainer_id' => $this->trainer_id,
            'workout_plan_id' => $this->workout_plan_id,
            'workout_plan_exercise_id' => $this->workout_plan_exercise_id,
            'exercise_id' => $this->exercise_id,
            'source_workout_session_id' => $this->source_workout_session_id,
            'policy' => $this->policy,
            'algorithm_version' => $this->algorithm_version,
            'action' => $this->action,
            'status' => $this->status,
            'current_prescription' => $this->current_prescription,
            'recommended_prescription' => $this->recommended_prescription,
            'decision_inputs' => $this->decision_inputs,
            'explanation' => $this->explanation,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'member' => UserResource::make($this->whenLoaded('member')),
            'exercise' => ExerciseResource::make($this->whenLoaded('exercise')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
