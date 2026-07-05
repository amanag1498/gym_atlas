<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdoptWorkoutBookPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'estimated_session_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
            'equipment_profile' => ['nullable', 'string', 'max:100'],
            'weekly_schedule' => ['nullable', 'array'],
            'weekly_schedule.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
