<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exerciseCount = $this->relationLoaded('days')
            ? $this->days->sum(fn ($day) => $day->exercises->count())
            : null;
        $focusAreas = $this->relationLoaded('days')
            ? $this->days->pluck('focus')->filter()->unique()->values()
            : collect();

        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'workout_book_id' => $this->workout_book_id,
            'name' => $this->name,
            'goal' => $this->goal,
            'difficulty' => $this->difficulty,
            'program_type' => $this->program_type,
            'equipment_profile' => $this->equipment_profile,
            'duration_weeks' => $this->duration_weeks,
            'estimated_session_minutes' => $this->estimated_session_minutes,
            'weekly_schedule' => $this->weekly_schedule ?? [],
            'notes' => $this->notes,
            'status' => $this->status,
            'is_public_catalog' => $this->is_public_catalog,
            'total_workout_days' => $this->relationLoaded('days') ? $this->days->count() : null,
            'total_exercises' => $exerciseCount,
            'focus_areas' => $focusAreas,
            'created_by_user_id' => $this->created_by_user_id,
            'days' => $this->whenLoaded('days', fn () => $this->days->map(fn ($day) => [
                'id' => $day->id,
                'day_number' => $day->day_number,
                'label' => $day->label,
                'focus' => $day->focus,
                'notes' => $day->notes,
                'exercises' => $day->exercises->map(fn ($exercise) => [
                    'id' => $exercise->id,
                    'exercise_id' => $exercise->exercise_id,
                    'exercise' => ExerciseResource::make($exercise->exercise),
                    'sort_order' => $exercise->sort_order,
                    'sets' => $exercise->sets,
                    'tracking_mode' => $exercise->tracking_mode ?? 'reps',
                    'reps' => $exercise->reps,
                    'planned_duration_seconds' => $exercise->planned_duration_seconds,
                    'planned_distance_meters' => $exercise->planned_distance_meters !== null ? (float) $exercise->planned_distance_meters : null,
                    'planned_speed_kph' => $exercise->planned_speed_kph !== null ? (float) $exercise->planned_speed_kph : null,
                    'planned_pace_seconds_per_km' => $exercise->planned_pace_seconds_per_km,
                    'target_weight' => (float) ($exercise->target_weight ?? 0),
                    'target_resistance' => $exercise->target_resistance !== null ? (float) $exercise->target_resistance : null,
                    'target_machine_level' => $exercise->target_machine_level !== null ? (float) $exercise->target_machine_level : null,
                    'is_per_side' => (bool) $exercise->is_per_side,
                    'is_bodyweight' => (bool) $exercise->is_bodyweight,
                    'rest_seconds' => $exercise->rest_seconds,
                    'notes' => $exercise->notes,
                ])->values(),
            ])->values()),
            'workout_book' => WorkoutBookResource::make($this->whenLoaded('workoutBook')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
