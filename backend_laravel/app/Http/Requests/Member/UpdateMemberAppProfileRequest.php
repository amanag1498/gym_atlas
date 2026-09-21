<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberAppProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('experience_level')) {
            $this->merge([
                'experience_level' => str($this->input('experience_level'))
                    ->trim()
                    ->lower()
                    ->toString(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9() -]{7,30}$/'],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'photo' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'fitness_goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fitness_goal_ids' => ['sometimes', 'array', 'min:1'],
            'fitness_goal_ids.*' => ['integer', 'exists:fitness_goals,id'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'non_binary', 'prefer_not_to_say'])],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:400'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'experience_level' => ['sometimes', 'nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'injury_notes' => ['sometimes', 'nullable', 'string'],
            'medical_notes' => ['sometimes', 'nullable', 'string'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'member_onboarding_step' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'member_onboarding_completed' => ['sometimes', 'boolean'],
        ];
    }
}
