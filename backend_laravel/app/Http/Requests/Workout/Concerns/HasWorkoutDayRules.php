<?php

namespace App\Http\Requests\Workout\Concerns;

use Illuminate\Validation\Rule;

trait HasWorkoutDayRules
{
    /**
     * @return array<string, mixed>
     */
    protected function workoutDayRules(string $prefix = 'days'): array
    {
        return [
            $prefix => ['required', 'array', 'min:1'],
            $prefix.'.*.day_number' => ['required', 'integer', 'min:1', 'max:7'],
            $prefix.'.*.label' => ['nullable', 'string', 'max:255'],
            $prefix.'.*.focus' => ['nullable', 'string', 'max:255'],
            $prefix.'.*.notes' => ['nullable', 'string'],
            $prefix.'.*.exercises' => ['required', 'array', 'min:1'],
            $prefix.'.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            $prefix.'.*.exercises.*.sort_order' => ['nullable', 'integer', 'min:1'],
            $prefix.'.*.exercises.*.sets' => ['required', 'integer', 'min:1'],
            $prefix.'.*.exercises.*.tracking_mode' => ['nullable', Rule::in(['reps', 'timed', 'cardio', 'distance'])],
            $prefix.'.*.exercises.*.reps' => ['nullable', 'string', 'max:100'],
            $prefix.'.*.exercises.*.planned_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            $prefix.'.*.exercises.*.planned_distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            $prefix.'.*.exercises.*.planned_speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            $prefix.'.*.exercises.*.planned_pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            $prefix.'.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            $prefix.'.*.exercises.*.target_resistance' => ['nullable', 'numeric', 'min:0'],
            $prefix.'.*.exercises.*.target_machine_level' => ['nullable', 'numeric', 'min:0'],
            $prefix.'.*.exercises.*.is_per_side' => ['nullable', 'boolean'],
            $prefix.'.*.exercises.*.is_bodyweight' => ['nullable', 'boolean'],
            $prefix.'.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            $prefix.'.*.exercises.*.group_key' => ['nullable', 'string', 'max:40'],
            $prefix.'.*.exercises.*.group_type' => ['nullable', Rule::in(['superset', 'circuit'])],
            $prefix.'.*.exercises.*.group_order' => ['nullable', 'integer', 'min:1', 'max:50'],
            $prefix.'.*.exercises.*.group_rounds' => ['nullable', 'integer', 'min:1', 'max:20'],
            $prefix.'.*.exercises.*.transition_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            $prefix.'.*.exercises.*.rest_after' => ['nullable', Rule::in(['exercise', 'group'])],
            $prefix.'.*.exercises.*.progression_policy' => ['nullable', Rule::in(['off', 'linear_load', 'double_progression'])],
            $prefix.'.*.exercises.*.progression_config' => ['nullable', 'array'],
            $prefix.'.*.exercises.*.progression_config.load_increment_kg' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            $prefix.'.*.exercises.*.progression_config.min_reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            $prefix.'.*.exercises.*.progression_config.max_reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            $prefix.'.*.exercises.*.notes' => ['nullable', 'string'],
        ];
    }
}
