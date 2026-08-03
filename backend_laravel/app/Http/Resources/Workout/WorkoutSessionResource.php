<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'trainer_id' => $this->trainer_id,
            'workout_plan_id' => $this->workout_plan_id,
            'session_date' => $this->session_date?->toDateString(),
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notes' => $this->notes,
            'total_volume' => (float) $this->total_volume,
            'exercises' => $this->whenLoaded('exercises', fn () => $this->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'exercise_id' => $exercise->exercise_id,
                'exercise' => ExerciseResource::make($exercise->exercise),
                'sort_order' => $exercise->sort_order,
                'planned_sets' => $exercise->planned_sets,
                'planned_reps' => $exercise->planned_reps,
                'target_weight' => (float) ($exercise->target_weight ?? 0),
                'rest_timer_seconds' => $exercise->rest_timer_seconds,
                'notes' => $exercise->notes,
                'sets' => $exercise->sets->map(fn ($set) => [
                    'id' => $set->id,
                    'set_number' => $set->set_number,
                    'reps' => $set->reps,
                    'weight' => (float) $set->weight,
                    'rest_seconds' => $set->rest_seconds,
                    'notes' => $set->notes,
                    'is_completed' => $set->is_completed,
                ])->values(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
