<?php

namespace App\Http\Requests\Workout\Concerns;

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
            $prefix.'.*.exercises.*.reps' => ['nullable', 'string', 'max:100'],
            $prefix.'.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            $prefix.'.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            $prefix.'.*.exercises.*.notes' => ['nullable', 'string'],
        ];
    }
}
