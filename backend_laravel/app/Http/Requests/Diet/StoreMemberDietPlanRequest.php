<?php

namespace App\Http\Requests\Diet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberDietPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'daily_calorie_target' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'protein_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'carbs_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'fats_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'dietary_preferences' => ['nullable', 'string', 'max:4000'],
            'allergies_and_restrictions' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:6000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'meals' => ['required', 'array', 'min:1', 'max:12'],
            'meals.*.name' => ['required', 'string', 'max:255'],
            'meals.*.meal_type' => ['nullable', 'string', 'max:80'],
            'meals.*.scheduled_time' => ['nullable', 'date_format:H:i'],
            'meals.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.fats_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
