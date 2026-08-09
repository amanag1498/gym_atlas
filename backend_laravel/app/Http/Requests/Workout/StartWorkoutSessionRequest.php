<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'workout_plan_day_id' => [
                'nullable',
                'integer',
                Rule::exists('workout_plan_days', 'id'),
            ],
            'session_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'allow_duplicate_active_session' => ['nullable', 'boolean'],
        ];
    }
}
