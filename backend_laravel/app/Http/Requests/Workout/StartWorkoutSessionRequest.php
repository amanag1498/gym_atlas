<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workout_plan_id' => ['nullable', 'integer', 'exists:workout_plans,id'],
            'session_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'allow_duplicate_active_session' => ['nullable', 'boolean'],
        ];
    }
}
