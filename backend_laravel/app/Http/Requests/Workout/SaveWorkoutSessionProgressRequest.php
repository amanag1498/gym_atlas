<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkoutSessionProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercises' => ['required', 'array'],
            'exercises.*.id' => ['required', 'integer', 'exists:workout_session_exercises,id'],
            'exercises.*.notes' => ['nullable', 'string'],
            'exercises.*.sets' => ['present', 'array'],
            'exercises.*.sets.*.set_number' => ['required', 'integer', 'min:1'],
            'exercises.*.sets.*.reps' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'exercises.*.sets.*.distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'exercises.*.sets.*.speed_kph' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'exercises.*.sets.*.pace_seconds_per_km' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'exercises.*.sets.*.weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.effort_scale' => ['nullable', Rule::in(['rir', 'rpe'])],
            'exercises.*.sets.*.effort_value' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'exercises.*.sets.*.side' => ['nullable', Rule::in(['left', 'right', 'both', 'alternating'])],
            'exercises.*.sets.*.notes' => ['nullable', 'string'],
            'exercises.*.sets.*.is_completed' => ['nullable', 'boolean'],
            'runtime_state' => ['nullable', 'array'],
            'runtime_state.rest_exercise_index' => ['nullable', 'integer', 'min:0'],
            'runtime_state.rest_ends_at' => ['nullable', 'date'],
            'runtime_state.rest_total_seconds' => ['nullable', 'integer', 'min:0'],
            'runtime_state.work_exercise_index' => ['nullable', 'integer', 'min:0'],
            'runtime_state.work_set_index' => ['nullable', 'integer', 'min:0'],
            'runtime_state.work_started_at' => ['nullable', 'date'],
        ];
    }
}
