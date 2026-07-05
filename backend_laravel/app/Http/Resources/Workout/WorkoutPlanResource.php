<?php

namespace App\Http\Resources\Workout;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanResource extends JsonResource
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
            'member_id' => $this->member_id,
            'trainer_id' => $this->trainer_id,
            'created_by_user_id' => $this->created_by_user_id,
            'source_workout_book_id' => $this->source_workout_book_id,
            'plan_origin' => $this->plan_origin,
            'is_member_editable' => $this->is_member_editable,
            'workout_template_id' => $this->workout_template_id,
            'name' => $this->name,
            'goal' => $this->goal,
            'difficulty' => $this->difficulty,
            'duration_weeks' => $this->duration_weeks,
            'estimated_session_minutes' => $this->estimated_session_minutes,
            'equipment_profile' => $this->equipment_profile,
            'weekly_schedule' => $this->weekly_schedule ?? [],
            'notes' => $this->notes,
            'status' => $this->status,
            'total_workout_days' => $this->relationLoaded('days') ? $this->days->count() : null,
            'total_exercises' => $exerciseCount,
            'focus_areas' => $focusAreas,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'member' => UserResource::make($this->whenLoaded('member')),
            'trainer' => UserResource::make($this->whenLoaded('trainer')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'source_workout_book' => WorkoutBookResource::make($this->whenLoaded('sourceWorkoutBook')),
            'template' => WorkoutTemplateResource::make($this->whenLoaded('template')),
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
                    'reps' => $exercise->reps,
                    'target_weight' => (float) ($exercise->target_weight ?? 0),
                    'rest_seconds' => $exercise->rest_seconds,
                    'notes' => $exercise->notes,
                ])->values(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
