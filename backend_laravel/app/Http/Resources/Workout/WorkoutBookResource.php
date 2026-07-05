<?php

namespace App\Http\Resources\Workout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutBookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $templates = $this->whenLoaded('templates');
        $dayCount = $this->relationLoaded('templates')
            ? $this->templates->sum(fn ($template) => $template->days->count())
            : null;
        $exerciseCount = $this->relationLoaded('templates')
            ? $this->templates->sum(fn ($template) => $template->days->sum(fn ($day) => $day->exercises->count()))
            : null;
        $focusAreas = $this->relationLoaded('templates')
            ? $this->templates
                ->flatMap(fn ($template) => $template->days->pluck('focus'))
                ->filter()
                ->unique()
                ->values()
                ->take(6)
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'audience' => $this->audience,
            'goal' => $this->goal,
            'difficulty' => $this->difficulty,
            'program_type' => $this->program_type,
            'equipment_profile' => $this->equipment_profile,
            'days_per_week' => $this->days_per_week,
            'duration_weeks' => $this->duration_weeks,
            'estimated_session_minutes' => $this->estimated_session_minutes,
            'description' => $this->description,
            'coach_notes' => $this->coach_notes,
            'is_featured' => $this->is_featured,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'plans_count' => $this->whenCounted('templates'),
            'total_workout_days' => $dayCount,
            'total_exercises' => $exerciseCount,
            'focus_areas' => $focusAreas,
            'plans' => WorkoutTemplateResource::collection($this->whenLoaded('templates')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
