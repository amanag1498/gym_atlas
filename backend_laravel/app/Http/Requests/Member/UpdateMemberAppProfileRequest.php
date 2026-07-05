<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberAppProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'fitness_goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fitness_goal_ids' => ['sometimes', 'array', 'min:1'],
            'fitness_goal_ids.*' => ['integer', 'exists:fitness_goals,id'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:40'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:400'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'injury_notes' => ['sometimes', 'nullable', 'string'],
            'medical_notes' => ['sometimes', 'nullable', 'string'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'member_onboarding_step' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'member_onboarding_completed' => ['sometimes', 'boolean'],
        ];
    }
}
