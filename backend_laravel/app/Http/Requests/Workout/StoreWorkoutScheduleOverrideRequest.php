<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutScheduleOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workout_plan_id' => ['required', 'integer', 'exists:workout_plans,id'],
            'workout_plan_day_id' => ['required', 'integer', 'exists:workout_plan_days,id'],
            'original_date' => ['required', 'date'],
            'replacement_date' => ['nullable', 'date'],
            'override_type' => ['required', Rule::in(['reschedule', 'rest'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }
}
