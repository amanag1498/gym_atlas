<?php

namespace App\Http\Requests\Workout;

use App\Http\Requests\Workout\Concerns\HasWorkoutDayRules;
use App\Http\Requests\Workout\Concerns\ValidatesSelectableExercises;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutPlanRequest extends FormRequest
{
    use HasWorkoutDayRules, ValidatesSelectableExercises;

    protected function selectableExerciseAudience(): string
    {
        return 'trainer';
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'independent_trainer_member_relationship_id' => [
                'nullable',
                'integer',
                'exists:independent_trainer_member_relationships,id',
            ],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'workout_template_id' => ['nullable', 'integer', 'exists:workout_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'duration_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'weekly_schedule' => ['nullable', 'array'],
            'weekly_schedule.*' => ['string', 'max:50'],
            'progression_policy' => ['nullable', Rule::in(['off', 'linear_load', 'double_progression'])],
            'progression_config' => ['nullable', 'array'],
            'progression_config.load_increment_kg' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'progression_config.min_reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            'progression_config.max_reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], $this->workoutDayRules());
    }
}
