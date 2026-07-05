<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.id' => ['nullable', 'integer', 'exists:workout_session_exercises,id'],
            'exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'exercises.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'exercises.*.planned_sets' => ['nullable', 'integer', 'min:1'],
            'exercises.*.planned_reps' => ['nullable', 'string', 'max:100'],
            'exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.rest_timer_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.notes' => ['nullable', 'string'],
            'exercises.*.sets' => ['required', 'array', 'min:1'],
            'exercises.*.sets.*.set_number' => ['required', 'integer', 'min:1'],
            'exercises.*.sets.*.reps' => ['required', 'integer', 'min:0'],
            'exercises.*.sets.*.weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.notes' => ['nullable', 'string'],
            'exercises.*.sets.*.is_completed' => ['nullable', 'boolean'],
        ];
    }
}
