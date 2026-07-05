<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignWorkoutTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'weekly_schedule' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
