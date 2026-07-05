<?php

namespace App\Http\Requests\Workout;

use App\Http\Requests\Workout\Concerns\HasWorkoutDayRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutBookRequest extends FormRequest
{
    use HasWorkoutDayRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'program_type' => ['nullable', 'string', 'max:100'],
            'equipment_profile' => ['nullable', 'string', 'max:100'],
            'days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'estimated_session_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
            'description' => ['nullable', 'string'],
            'coach_notes' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.name' => ['required', 'string', 'max:255'],
            'plans.*.goal' => ['nullable', 'string', 'max:255'],
            'plans.*.difficulty' => ['nullable', 'string', 'max:100'],
            'plans.*.program_type' => ['nullable', 'string', 'max:100'],
            'plans.*.equipment_profile' => ['nullable', 'string', 'max:100'],
            'plans.*.duration_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'plans.*.estimated_session_minutes' => ['nullable', 'integer', 'min:10', 'max:240'],
            'plans.*.weekly_schedule' => ['nullable', 'array'],
            'plans.*.weekly_schedule.*' => ['string', 'max:50'],
            'plans.*.notes' => ['nullable', 'string'],
            'plans.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'plans.*.days' => ['required', 'array', 'min:1'],
            'plans.*.days.*.day_number' => ['required', 'integer', 'min:1', 'max:7'],
            'plans.*.days.*.label' => ['nullable', 'string', 'max:255'],
            'plans.*.days.*.focus' => ['nullable', 'string', 'max:255'],
            'plans.*.days.*.notes' => ['nullable', 'string'],
            'plans.*.days.*.exercises' => ['required', 'array', 'min:1'],
            'plans.*.days.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'plans.*.days.*.exercises.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'plans.*.days.*.exercises.*.sets' => ['required', 'integer', 'min:1'],
            'plans.*.days.*.exercises.*.reps' => ['nullable', 'string', 'max:100'],
            'plans.*.days.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            'plans.*.days.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'plans.*.days.*.exercises.*.notes' => ['nullable', 'string'],
        ];
    }
}
