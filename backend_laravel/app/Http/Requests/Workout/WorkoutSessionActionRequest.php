<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkoutSessionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'complete_set',
                'update_set',
                'delete_set',
                'next_exercise',
                'previous_exercise',
                'start_rest',
                'skip_rest',
            ])],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'expected_revision' => ['nullable', 'integer', 'min:0'],
            'workout_session_exercise_id' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), ['complete_set', 'update_set', 'delete_set', 'start_rest'], true)),
                'nullable',
                'integer',
                'exists:workout_session_exercises,id',
            ],
            'set_id' => ['nullable', 'integer', 'exists:workout_sets,id'],
            'set_number' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), ['complete_set', 'update_set', 'delete_set'], true) && ! $this->filled('set_id')),
                'nullable',
                'integer',
                'min:1',
            ],
            'reps' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), ['complete_set', 'update_set'], true)),
                'nullable',
                'integer',
                'min:0',
            ],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
