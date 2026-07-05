<?php

namespace App\Http\Requests\Workout;

use App\Http\Requests\Workout\Concerns\HasWorkoutDayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutPlanRequest extends FormRequest
{
    use HasWorkoutDayRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'workout_template_id' => ['nullable', 'integer', 'exists:workout_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'duration_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'weekly_schedule' => ['nullable', 'array'],
            'weekly_schedule.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], $this->workoutDayRules());
    }
}
