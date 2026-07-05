<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class AddWorkoutExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'planned_sets' => ['nullable', 'integer', 'min:1'],
            'planned_reps' => ['nullable', 'string', 'max:100'],
            'target_weight' => ['nullable', 'numeric', 'min:0'],
            'rest_timer_seconds' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'sets' => ['nullable', 'array'],
            'sets.*.set_number' => ['required_with:sets', 'integer', 'min:1'],
            'sets.*.reps' => ['required_with:sets', 'integer', 'min:0'],
            'sets.*.weight' => ['nullable', 'numeric', 'min:0'],
            'sets.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'sets.*.notes' => ['nullable', 'string'],
            'sets.*.is_completed' => ['nullable', 'boolean'],
        ];
    }
}
